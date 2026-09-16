<?php

namespace UniversityofMiami\DigitalTrialsID;

use REDCap;

require_once __DIR__ . '/pass_utils.php';

// Prove the file loads
dtid_log('BOOT: DigitalTrialsID.php loaded');

class DigitalTrialsID extends \ExternalModules\AbstractExternalModule
{
    public function __construct()
    {
        parent::__construct();
        dtid_log('CTOR: DigitalTrialsID instantiated');
    }

    private function getInstallUuid(int $project_id): string
    {
        $uuid = $this->getProjectSetting('gw_install_uuid');

        if (!$uuid) {
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
            $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

            $uuid = vsprintf(
                '%s%s-%s-%s-%s-%s%s%s',
                str_split(bin2hex($bytes), 4)
            );

            $this->setProjectSetting('gw_install_uuid', $uuid);

            dtid_log("INIT: generated gw_install_uuid={$uuid}");
        }

        return $uuid;
    }

    public function redcap_save_record(
        $project_id,
        $record,
        $instrument,
        $event_id,
        $group_id = null,
        $survey_hash = null,
        $response_id = null,
        $repeat_instance = 1
    ) {
        dtid_log(
            "HOOK: redcap_save_record | instrument={$instrument}"
        );

        $triggerInstrument = trim(
            (string)($this->getProjectSetting('trigger_instrument') ?? '')
        );

        if ($triggerInstrument === '') {
            dtid_log(
                'SKIP: trigger_instrument project setting not configured'
            );
            return;
        }

        if ($instrument !== $triggerInstrument) {
            dtid_log(
                "SKIP: instrument mismatch ({$instrument} !== {$triggerInstrument})"
            );
            return;
        }

        // ----------------------------------------------------
        // Configured REDCap field mappings
        // ----------------------------------------------------
        $participantField = trim(
            (string)($this->getProjectSetting(
                'field_participant_name'
            ) ?? '')
        );

        $startDateField = trim(
            (string)($this->getProjectSetting(
                'field_study_start_date'
            ) ?? '')
        );

        $endDateField = trim(
            (string)($this->getProjectSetting(
                'field_study_end_date'
            ) ?? '')
        );

        $googleWalletLinkField = trim(
            (string)($this->getProjectSetting(
                'field_google_wallet_link'
            ) ?? '')
        );

        $appleWalletLinkField = trim(
            (string)($this->getProjectSetting(
                'field_apple_wallet_link'
            ) ?? '')
        );

        // Participant, start date, and both destination fields
        // are required. The end-date field is optional.
        if (
            $participantField === '' ||
            $startDateField === '' ||
            $googleWalletLinkField === '' ||
            $appleWalletLinkField === ''
        ) {
            dtid_log(
                'ERROR: One or more required project field mappings are missing'
            );
            return;
        }

        if ($googleWalletLinkField === $appleWalletLinkField) {
            dtid_log(
                'ERROR: Google Wallet and Apple Wallet cannot use the same destination field'
            );
            return;
        }

        // ----------------------------------------------------
        // Retrieve participant-specific record data
        // ----------------------------------------------------
        $requestedFields = [
            $participantField,
            $startDateField
        ];

        if ($endDateField !== '') {
            $requestedFields[] = $endDateField;
        }

        $requestedFields = array_values(
            array_unique($requestedFields)
        );

        $data = REDCap::getData([
            'project_id' => $project_id,
            'records'    => [$record],
            'events'     => [$event_id],
            'fields'     => $requestedFields
        ]);

        $fields = $data[$record][$event_id] ?? [];

        $participant = trim(
            (string)($fields[$participantField] ?? '')
        );

        $startDate = trim(
            (string)($fields[$startDateField] ?? '')
        );

        $endDate = '';

        if ($endDateField !== '') {
            $endDate = trim(
                (string)($fields[$endDateField] ?? '')
            );
        }

        dtid_log(
            'DATA: participant=' .
            ($participant !== '' ? '[SET]' : '[EMPTY]') .
            ' startDate=' .
            ($startDate !== '' ? '[SET]' : '[EMPTY]') .
            ' endDate=' .
            ($endDate !== '' ? '[SET]' : '[EMPTY]')
        );

        // ----------------------------------------------------
        // Project-level study information
        // ----------------------------------------------------
        $studyTitle = trim(
            (string)($this->getProjectSetting(
                'field_study_title'
            ) ?? '')
        );

        $piName = trim(
            (string)($this->getProjectSetting(
                'field_pi_name'
            ) ?? '')
        );

        $contactEmail = trim(
            (string)($this->getProjectSetting(
                'field_contact_email'
            ) ?? '')
        );

        $studyDescription = trim(
            (string)($this->getProjectSetting(
                'field_study_description'
            ) ?? '')
        );

        $contactPhone = trim(
            (string)($this->getProjectSetting(
                'study_contact_phone'
            ) ?? '')
        );

        // ----------------------------------------------------
        // System-level Google Wallet settings
        // ----------------------------------------------------
        $googleJson = $this->getSystemSetting(
            'google_service_account_json'
        );

        $issuerId = $this->getSystemSetting(
            'google_issuer_id'
        );

        $classSuffix = $this->getSystemSetting(
            'google_class_id'
        );

        $classId = $issuerId . '.' . $classSuffix;

        $logoUrl = $this->getSystemSetting(
            'logo_url'
        );

        $heroUrl = $this->getSystemSetting(
            'hero_url'
        );

        $backgroundColor = $this->getSystemSetting(
            'background_color'
        );

        if (!$googleJson || !$issuerId || !$classSuffix) {
            dtid_log(
                'ERROR: Missing Google Wallet system settings'
            );
            return;
        }

        $credentials = json_decode($googleJson, true);

        if (
            !$credentials ||
            empty($credentials['client_email']) ||
            empty($credentials['private_key'])
        ) {
            dtid_log(
                'ERROR: Invalid Google service account JSON'
            );
            return;
        }

        dtid_log(
            "CONFIG: issuerId={$issuerId} classId={$classId}"
        );

        $env = (string)(
            $this->getSystemSetting('environment') ?? 'prod'
        );

        $installUuid = $this->getInstallUuid($project_id);

        $installShort = strtoupper(
            substr(
                preg_replace(
                    '/[^a-f0-9]/i',
                    '',
                    $installUuid
                ),
                0,
                6
            )
        );

        // Unique per issuance — never reused.
        $issueId = strtoupper(
            dechex(time()) . bin2hex(random_bytes(6))
        );

        $googleObjectKey = implode('-', [
            'dtid',
            $env,
            'p' . $project_id,
            'i' . $installShort,
            'r' . $record,
            'u' . $issueId
        ]);

        dtid_log(
            'GOOGLE: object key generated successfully'
        );

        // ----------------------------------------------------
        // Generate Google Wallet link
        // ----------------------------------------------------
        $walletLink = generate_wallet_link(
            $googleObjectKey,
            $participant,
            $studyTitle,
            $piName,
            $contactEmail,
            $contactPhone,
            $startDate,
            $endDate,
            $credentials,
            $issuerId,
            $classId,
            $studyDescription,
            $logoUrl,
            $heroUrl,
            $backgroundColor
        );

        if (!is_string($walletLink)) {
            dtid_log(
                'ERROR: Wallet link is not a string'
            );
            return;
        }

        dtid_log(
            'GOOGLE: wallet link generated successfully'
        );

        // ----------------------------------------------------
        // Apple Wallet — dynamic JWT token and URL
        // ----------------------------------------------------
        $appleWalletUrl = '';

        try {
            $appleSecret = trim(
                (string)(
                    $this->getSystemSetting(
                        'apple_token_secret'
                    ) ?? ''
                )
            );

            if ($appleSecret === '') {
                throw new \Exception(
                    'Missing system setting: apple_token_secret'
                );
            }

            $appleClaims = [
                'record_id'          => (string)$record,
                'project_id'         => (int)$project_id,
                'event_id'           => (int)$event_id,
                'participant_name'   => (string)$participant,
                'study_title'        => (string)$studyTitle,
                'pi_name'            => (string)$piName,
                'contact_email'      => (string)$contactEmail,
                'study_description'  => (string)$studyDescription,
                'start_date'         => (string)$startDate,
                'end_date'           => (string)$endDate,
                'study_contact_phone' => (string)$contactPhone
            ];

            $appleJwt = dtid_make_apple_jwt_token(
                $appleClaims,
                $appleSecret,
                3600
            );

            $appleEndpoint = trim(
                (string)$this->getSystemSetting(
                    'apple_wallet_endpoint'
                )
            );

            if ($appleEndpoint === '') {
                throw new \Exception(
                    'Missing system setting: apple_wallet_endpoint'
                );
            }

            $appleWalletUrl =
                rtrim($appleEndpoint, '/') .
                '?token=' .
                urlencode($appleJwt);

            dtid_log(
                'APPLE: wallet URL generated successfully'
            );
        } catch (\Throwable $e) {
            // Apple failure must not interrupt the REDCap save.
            dtid_log(
                'APPLE ERROR (ignored for save): ' .
                $e->getMessage()
            );
        }

        // ----------------------------------------------------
        // Save links to the configured REDCap fields
        // ----------------------------------------------------
        dtid_log(
            'STEP: about to saveData()'
        );

        $payload = [
            $googleWalletLinkField => $walletLink,
            $appleWalletLinkField  => $appleWalletUrl
        ];

        $save = REDCap::saveData(
            $project_id,
            'array',
            [
                $record => [
                    $event_id => $payload
                ]
            ]
        );

        dtid_log(
            'SAVE: item_count=' .
            ($save['item_count'] ?? 'null') .
            ' error_count=' .
            count($save['errors'] ?? []) .
            ' warning_count=' .
            count($save['warnings'] ?? [])
        );

        dtid_log(
            'STEP: end of hook'
        );
    }
}
