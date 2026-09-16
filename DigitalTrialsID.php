<?php

namespace UniversityofMiami\DigitalTrialsID;

use REDCap;

require_once __DIR__ . '/pass_utils.php';

class DigitalTrialsID extends \ExternalModules\AbstractExternalModule
{
    /** @var array<string, bool> Guards the saveData call from re-entering this hook. */
    private static array $recordsBeingUpdated = [];

    private function getInstallUuid(): string
    {
        $uuid = (string) ($this->getProjectSetting('gw_install_uuid') ?? '');

        if ($uuid === '') {
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
            $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
            $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
            $this->setProjectSetting('gw_install_uuid', $uuid);
        }

        return $uuid;
    }

    private function isValidWalletUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !empty($parts['host']);
    }

    /**
     * Generate wallet links when the configured, non-repeating instrument is saved.
     *
     * Repeating instruments are deliberately unsupported. This keeps source values and
     * generated links in an unambiguous event-level location in longitudinal projects.
     */
    public function redcap_save_record(
        $project_id,
        $record,
        $instrument,
        $event_id,
        $group_id = null,
        $survey_hash = null,
        $response_id = null,
        $repeat_instance = 1
    ): void {
        $guardKey = implode(':', [(string) $project_id, (string) $record, (string) $event_id]);

        if (isset(self::$recordsBeingUpdated[$guardKey])) {
            return;
        }

        try {
            $triggerInstrument = (string) ($this->getProjectSetting('trigger_instrument') ?? '');
            if ($triggerInstrument === '' || $instrument !== $triggerInstrument) {
                return;
            }

            $isRepeating = method_exists(REDCap::class, 'isRepeatingFormOrEvent')
                && REDCap::isRepeatingFormOrEvent($event_id, $instrument);
            if ($isRepeating || (int) $repeat_instance > 1) {
                $this->log('Wallet issuance skipped because repeating instruments are not supported.');
                return;
            }

            $fieldSettings = [
                'participant' => 'field_participant_name',
                'start_date' => 'field_start_date',
                'end_date' => 'field_end_date',
                'study_title' => 'field_study_title',
                'pi_name' => 'field_pi_name',
                'contact_email' => 'field_contact_email',
                'google_link' => 'field_google_wallet_link',
                'apple_link' => 'field_apple_wallet_link',
            ];
            $fieldNames = [];
            foreach ($fieldSettings as $name => $settingKey) {
                $fieldNames[$name] = trim((string) ($this->getProjectSetting($settingKey) ?? ''));
            }

            $requiredMappings = ['participant', 'start_date', 'study_title', 'pi_name', 'contact_email', 'google_link', 'apple_link'];
            foreach ($requiredMappings as $mapping) {
                if ($fieldNames[$mapping] === '') {
                    $this->log('Wallet issuance skipped because a required project field mapping is missing.');
                    return;
                }
            }

            $requestedFields = array_values(array_unique(array_filter($fieldNames)));
            $data = REDCap::getData([
                'project_id' => $project_id,
                'return_format' => 'array',
                'records' => [$record],
                'events' => [$event_id],
                'fields' => $requestedFields,
            ]);
            $eventData = $data[$record][$event_id] ?? [];

            $values = [];
            foreach ($fieldNames as $name => $fieldName) {
                $values[$name] = $fieldName === '' ? '' : trim((string) ($eventData[$fieldName] ?? ''));
            }

            foreach (['participant', 'start_date', 'study_title', 'pi_name', 'contact_email'] as $requiredValue) {
                if ($values[$requiredValue] === '') {
                    $this->log('Wallet issuance skipped because a required record value is empty.');
                    return;
                }
            }

            $needsGoogle = !$this->isValidWalletUrl($values['google_link']);
            $needsApple = !$this->isValidWalletUrl($values['apple_link']);
            if (!$needsGoogle && !$needsApple) {
                return;
            }

            $studyDescription = trim((string) ($this->getProjectSetting('field_study_description') ?? ''));
            $contactPhone = trim((string) ($this->getProjectSetting('study_contact_phone') ?? ''));
            $linksToSave = [];

            if ($needsGoogle) {
                $googleLink = $this->generateGoogleLink(
                    (int) $project_id,
                    (string) $record,
                    $values,
                    $studyDescription,
                    $contactPhone
                );
                if ($googleLink !== null) {
                    $linksToSave[$fieldNames['google_link']] = $googleLink;
                }
            }

            if ($needsApple) {
                $appleLink = $this->generateAppleLink(
                    (int) $project_id,
                    (string) $record,
                    (int) $event_id,
                    $values,
                    $studyDescription,
                    $contactPhone
                );
                if ($appleLink !== null) {
                    $linksToSave[$fieldNames['apple_link']] = $appleLink;
                }
            }

            if ($linksToSave === []) {
                return;
            }

            self::$recordsBeingUpdated[$guardKey] = true;
            try {
                $result = REDCap::saveData($project_id, 'array', [
                    $record => [
                        $event_id => $linksToSave,
                    ],
                ]);
            } finally {
                unset(self::$recordsBeingUpdated[$guardKey]);
            }

            if (!empty($result['errors'])) {
                $this->log('One or more generated Wallet links could not be saved to REDCap.');
            }
        } catch (\Throwable $exception) {
            // The module must never interrupt REDCap's record-save operation.
            $this->log('Unexpected error while processing Wallet issuance.');
        }
    }

    private function generateGoogleLink(
        int $projectId,
        string $record,
        array $values,
        string $studyDescription,
        string $contactPhone
    ): ?string {
        try {
            $googleJson = (string) ($this->getSystemSetting('google_service_account_json') ?? '');
            $issuerId = trim((string) ($this->getSystemSetting('google_issuer_id') ?? ''));
            $classSuffix = trim((string) ($this->getSystemSetting('google_class_id') ?? ''));
            $credentials = json_decode($googleJson, true);

            if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key']) || $issuerId === '' || $classSuffix === '') {
                $this->log('Google Wallet generation skipped because its system configuration is incomplete or invalid.');
                return null;
            }

            $environment = (string) ($this->getSystemSetting('environment') ?? 'prod');
            $installShort = strtoupper(substr(preg_replace('/[^a-f0-9]/i', '', $this->getInstallUuid()), 0, 6));
            $issueId = strtoupper(dechex(time()) . bin2hex(random_bytes(6)));
            $objectKey = implode('-', [
                'dtid', $environment, 'p' . $projectId, 'i' . $installShort, 'r' . $record, 'u' . $issueId,
            ]);

            $link = generate_wallet_link(
                $objectKey,
                $values['participant'],
                $values['study_title'],
                $values['pi_name'],
                $values['contact_email'],
                $contactPhone,
                $values['start_date'],
                $values['end_date'],
                $credentials,
                $issuerId,
                $issuerId . '.' . $classSuffix,
                $studyDescription,
                $this->getSystemSetting('logo_url'),
                $this->getSystemSetting('hero_url'),
                $this->getSystemSetting('background_color')
            );

            if (!is_string($link) || !$this->isValidWalletUrl($link)) {
                $this->log('Google Wallet link generation failed.');
                return null;
            }

            return $link;
        } catch (\Throwable $exception) {
            $this->log('Google Wallet link generation failed.');
            return null;
        }
    }

    private function generateAppleLink(
        int $projectId,
        string $record,
        int $eventId,
        array $values,
        string $studyDescription,
        string $contactPhone
    ): ?string {
        try {
            $secret = trim((string) ($this->getSystemSetting('apple_token_secret') ?? ''));
            $endpoint = trim((string) ($this->getSystemSetting('apple_wallet_endpoint') ?? ''));
            if ($secret === '' || !$this->isValidWalletUrl($endpoint)) {
                $this->log('Apple Wallet generation skipped because its system configuration is incomplete or invalid.');
                return null;
            }

            $claims = [
                'record_id' => $record,
                'project_id' => $projectId,
                'event_id' => $eventId,
                'participant_name' => $values['participant'],
                'study_title' => $values['study_title'],
                'pi_name' => $values['pi_name'],
                'contact_email' => $values['contact_email'],
                'study_description' => $studyDescription,
                'start_date' => $values['start_date'],
                'end_date' => $values['end_date'],
                'study_contact_phone' => $contactPhone,
            ];
            $token = dtid_make_apple_jwt_token($claims, $secret, 3600);
            $link = rtrim($endpoint, '/') . '?token=' . urlencode($token);

            return $this->isValidWalletUrl($link) ? $link : null;
        } catch (\Throwable $exception) {
            $this->log('Apple Wallet link generation failed.');
            return null;
        }
    }
}
