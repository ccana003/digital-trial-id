<?php
namespace UniversityofMiami\\DigitalTrialsID;

/**
 * Register a signed Google Wallet URL with an institution-controlled resolver.
 * The resolver must authenticate this request, store the destination securely,
 * and return a short HTTPS URL for a cryptographically random token.
 */
function dtid_register_google_shortlink(string $endpoint, string $apiKey, string $walletUrl): string
{
    $parts = parse_url($endpoint);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
        throw new \\RuntimeException('Invalid registration endpoint.');
    }
    if (!preg_match('~^https://pay\\.google\\.com/gp/v/save/[A-Za-z0-9_.-]+$~', $walletUrl)) {
        throw new \\RuntimeException('Invalid Google Wallet destination.');
    }
    if (!function_exists('curl_init')) {
        throw new \\RuntimeException('PHP cURL is required.');
    }
    $body = json_encode(['wallet_url' => $walletUrl], JSON_THROW_ON_ERROR);
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAXREDIRS => 0
    ]);
    try {
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($response === false || $status !== 201 || strlen($response) > 4096) {
            throw new \\RuntimeException('Registration request failed.');
        }
    } finally {
        curl_close($curl);
    }
    $data = json_decode($response, true, 8, JSON_THROW_ON_ERROR);
    $short = $data['short_url'] ?? null;
    if (!is_string($short) || strlen($short) > 512) {
        throw new \\RuntimeException('Invalid short-link response.');
    }
    $shortParts = parse_url($short);
    if (!$shortParts || ($shortParts['scheme'] ?? '') !== 'https' || empty($shortParts['host'])
        || isset($shortParts['user']) || isset($shortParts['pass']) || isset($shortParts['fragment'])) {
        throw new \\RuntimeException('Invalid short-link URL.');
    }
    return $short;
}
