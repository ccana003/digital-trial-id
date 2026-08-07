<?php

/**
 * PASS UTILS
 * - Google Wallet: object-only Save to Wallet JWT generation
 * - Apple Wallet: HS256 JWT token generation
 * - Logging hardened to avoid redeclare fatals and capture shutdown fatals
 */

use Firebase\JWT\JWT;

/* ============================================================
 * BASIC LOGGING
 * ============================================================ */
if (!function_exists('dtid_log')) {
    function dtid_log(string $msg, $projectId = null): void
    {
        $projectPrefix = $projectId !== null && $projectId !== ''
            ? 'PROJECT ' . (string)$projectId . ' | '
            : '';

        @file_put_contents(
            __DIR__ . DIRECTORY_SEPARATOR . 'debug.log',
            '[' . date('Y-m-d H:i:s') . "] {$projectPrefix}{$msg}\n",
            FILE_APPEND | LOCK_EX
        );
    }
}

/* Capture silent IIS fatals */
static $DTID_SHUTDOWN_LOGGER_REGISTERED = false;

if (!$DTID_SHUTDOWN_LOGGER_REGISTERED) {
    $DTID_SHUTDOWN_LOGGER_REGISTERED = true;

    register_shutdown_function(function () {
        $err = error_get_last();

        if (
            $err &&
            in_array(
                $err['type'],
                [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR],
                true
            )
        ) {
            dtid_log('FATAL: unexpected PHP shutdown error');
        }
    });
}

/**
 * Load Composer autoload only when needed so REDCap/EM can
 * include this file safely in different contexts.
 */
function ensure_vendor_autoload(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $autoload =
        __DIR__
        . DIRECTORY_SEPARATOR
        . 'vendor'
        . DIRECTORY_SEPARATOR
        . 'autoload.php';

    if (!file_exists($autoload)) {
        throw new Exception(
            'Missing vendor/autoload.php in module folder.'
        );
    }

    require_once $autoload;
    $loaded = true;

    if (!class_exists('\\Firebase\\JWT\\JWT')) {
        throw new Exception('JWT library not available after autoload.');
    }
}

/* ============================================================
 * GOOGLE WALLET
 * ============================================================ */
function generate_wallet_link(
    $objectKey,
    $participant,
    $study,
    $pi,
    $contact,
    $study_contact_phone = null,
    $start_date = null,
    $end_date = null,
    $credentials = null,
    $issuerId = null,
    $classId = null,
    $study_description = null,
    $logo_url = null,
    $hero_url = null,
    $background_color = null,
    $project_id = null
) {
    try {
        ensure_vendor_autoload();

        if (
            !$credentials ||
            empty($credentials['client_email']) ||
            empty($credentials['private_key'])
        ) {
            dtid_log('GOOGLE ERROR: service account credentials unavailable', $project_id);
            return false;
        }

        if (!$issuerId || !$classId) {
            dtid_log('GOOGLE ERROR: issuer or class configuration unavailable', $project_id);
            return false;
        }

        // Google Wallet object IDs must be unique per issuer.
        // Required format:
        // {issuerId}.{identifier}
        $identifier = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            (string)$objectKey
        );

        if (strlen($identifier) > 120) {
            $identifier = substr($identifier, 0, 120);
        }

        $objectId = $issuerId . '.' . $identifier;

        $textModules = [
            [
                'id' => 'start_date',
                'header' => 'Helpful Information',
                'body' =>
                    "Start Date: $start_date\n"
                    . "End Date: $end_date\n"
                    . "PI: $pi\n"
                    . "Contact: $contact\n"
                    . "Phone: $study_contact_phone"
            ],
            [
                'id' => 'participant',
                'header' => 'Participant',
                'body' => $participant ?: 'Unavailable'
            ],
            [
                'id' => 'pi_name',
                'header' => 'Principal Investigator',
                'body' => $pi ?: 'Unavailable'
            ],
            [
                'id' => 'contact',
                'header' => 'Study Contact',
                'body' => $contact ?: 'Unavailable'
            ],
            [
                'id' => 'study_description',
                'header' => 'Study Description',
                'body' => $study_description ?: 'Unavailable'
            ],
            [
                'id' => 'author_description',
                'header' => 'Author',
                'body' =>
                    'This pass was developed by the University of Miami CTSI '
                    . 'with support from grant UM1TR004556 of the National Center '
                    . 'for Advancing Translational Sciences.'
            ]
        ];

        $links = [
            [
                'uri' => 'mailto:' . $contact,
                'description' => 'Contact PI',
                'id' => 'email_link'
            ]
        ];

        if (!empty($study_contact_phone)) {
            $textModules[] = [
                'id' => 'contact_phone',
                'header' => 'Contact Phone',
                'body' => $study_contact_phone
            ];
        }

        $obj = [
            'id' => $objectId,
            'classId' => $classId,
            'genericType' => 'GENERIC_TYPE_UNSPECIFIED',
            'hexBackgroundColor' => $background_color ?: '#ffb994',
            'cardTitle' => [
                'defaultValue' => [
                    'language' => 'en',
                    'value' => $study
                ]
            ],
            'subheader' => [
                'defaultValue' => [
                    'language' => 'en',
                    'value' => 'Research Participant'
                ]
            ],
            'header' => [
                'defaultValue' => [
                    'language' => 'en',
                    'value' => $participant
                ]
            ],
            'textModulesData' => $textModules,
            'linksModuleData' => [
                'uris' => $links
            ]
        ];

        if (!empty($logo_url)) {
            $obj['logo'] = [
                'sourceUri' => [
                    'uri' => $logo_url
                ],
                'contentDescription' => [
                    'defaultValue' => [
                        'language' => 'en',
                        'value' => 'Logo'
                    ]
                ]
            ];
        }

        if (!empty($hero_url)) {
            $obj['heroImage'] = [
                'sourceUri' => [
                    'uri' => $hero_url
                ],
                'contentDescription' => [
                    'defaultValue' => [
                        'language' => 'en',
                        'value' => 'Study image'
                    ]
                ]
            ];
        }

        $claims = [
            'iss' => $credentials['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => [
                'genericObjects' => [$obj]
            ]
        ];

        $jwt = JWT::encode(
            $claims,
            $credentials['private_key'],
            'RS256'
        );

        return "https://pay.google.com/gp/v/save/{$jwt}";

    } catch (\Throwable $e) {
        dtid_log('GOOGLE ERROR: wallet link generation failed', $project_id);
        return false;
    }
}

/* ============================================================
 * APPLE WALLET — JWT TOKEN GENERATION
 * ============================================================ */
function dtid_make_apple_jwt_token(
    array $claims,
    string $secret,
    int $ttlSeconds = 3600
): string {
    ensure_vendor_autoload();

    $now = time();
    $exp = $now + max(60, $ttlSeconds);

    $base = [
        'iss' => 'DigitalTrialsID',
        'aud' => 'apple-wallet',
        'iat' => $now,
        'exp' => $exp,
        'jti' => bin2hex(random_bytes(16))
    ];

    $payload = array_merge($base, $claims);

    return JWT::encode(
        $payload,
        $secret,
        'HS256'
    );
}
