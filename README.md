# DigitalTrialsID

**Digital research participant ID cards for REDCap**

DigitalTrialsID is a REDCap External Module that allows research studies to issue digital participant ID cards that participants can save to **Apple Wallet** or **Google Wallet**.

The module generates wallet links when a configured REDCap instrument is saved. Study teams select the participant fields and wallet-link destination fields through the project-level module settings, so specific REDCap variable names are not hardcoded.

---

## For REDCap Project Teams

After a REDCap administrator installs and configures the module, a study team completes the project-level setup described below.

## 1. Prepare the REDCap Fields

The project needs fields for the following information:

| Purpose | Recommended field type | Required |
|---|---|---:|
| Participant name | Text | Yes |
| Study start date | Text with date validation | Yes |
| Study end date | Text with date validation | No |
| Google Wallet link | Plain text | Yes |
| Apple Wallet link | Plain text | Yes |

The field variable names are chosen by the study team. Examples include:

```text
participant_name
study_start_date
study_end_date
google_wallet_link
apple_wallet_link
```

These names are only examples. DigitalTrialsID uses the fields selected in the project-level module settings.

The end-date field is optional. Studies that do not know a participant’s end date at enrollment may leave the field unmapped or leave its value blank.

### Wallet-Link Fields

The Google and Apple Wallet destination fields should:

- be separate REDCap text fields;
- not be manually edited by participants; and
- not use URL validation, because signed Wallet URLs can be long.

If the fields are located on a participant-facing survey, consider adding:

```text
@HIDDEN-SURVEY
```

This prevents the generated URLs from appearing as editable survey fields.

---

## 2. Configure the Project Settings

Open the External Module settings for the REDCap project.

### Study Information

Enter the information that should appear on the digital participant ID:

- **Study Title**
- **Principal Investigator Name**
- **Study Contact Email**
- **Study Description**
- **Contact Phone Number** — optional

The study description should be brief and understandable to participants.

The contact phone number may be useful when a participant or healthcare provider needs to contact the study team.

### REDCap Field Mappings

Select the project fields that contain or store:

- participant name;
- study start date;
- study end date — optional;
- Google Wallet link; and
- Apple Wallet link.

The Google and Apple Wallet links must be assigned to different destination fields.

### Trigger Instrument

Select the REDCap instrument that should issue the digital participant ID.

DigitalTrialsID runs whenever the selected instrument is saved.

---

## 3. How Issuance Works

When the configured trigger instrument is saved, DigitalTrialsID:

1. Reads the participant name and study dates from the selected REDCap fields.
2. Reads the study information from the project settings.
3. Generates a signed Google Wallet link.
4. Generates a short-lived request URL for the Apple Wallet service.
5. Saves both URLs to the configured REDCap destination fields.

The participant should have a name and study start date before the trigger instrument is saved.

A study end date is not required.

---

## 4. Present the Wallet Options

The study controls where and how the generated Wallet links are presented to participants.

One option is to place Wallet buttons in the survey completion text.

In REDCap:

1. Open the survey settings for the trigger instrument.
2. Locate **Survey Completion Text**.
3. Switch the editor to **Source Code** view.
4. Paste the HTML below.
5. Replace the example REDCap variable names with the actual destination fields selected in the module settings.

```html
<div style="max-width: 720px; margin: 20px auto; text-align: center; font-family: Arial, Helvetica, sans-serif;">
  <h2 style="font-size: 24px; line-height: 1.3; margin-bottom: 12px;">
    Add Your ResearchPass
  </h2>

  <p style="font-size: 16px; line-height: 1.5; margin-bottom: 24px;">
    Select the appropriate option below to add your digital study ID
    to Apple Wallet or Google Wallet.
  </p>

  <p style="margin: 12px 0 30px;">
    <a
      href="[apple_wallet_link]"
      style="display: inline-block;"
      aria-label="Add your ResearchPass to Apple Wallet">
      <img
        src="https://support.apple.com/library/content/dam/edam/applecare/images/en_US/iOS/add-to-apple-wallet-logo.png"
        alt="Add to Apple Wallet"
        style="height: 50px; width: auto; border: 0;">
    </a>
  </p>

  <p style="margin: 12px 0;">
    <a
      href="[google_wallet_link]"
      style="display: inline-block;"
      aria-label="Add your ResearchPass to Google Wallet">
      <img
        src="https://codelabs.developers.google.com/static/add-to-wallet-android/images/add-wallet-btn-large.png"
        alt="Add to Google Wallet"
        style="height: 50px; width: auto; border: 0;">
    </a>
  </p>
</div>
```

For example, if the project uses different variable names, change:

```text
[apple_wallet_link]
[google_wallet_link]
```

to the actual REDCap fields selected in the module settings.

REDCap replaces these piping references with the generated Wallet URLs when it renders the page.

---

## 5. Test Before Participant Use

Test the complete workflow before issuing cards to participants.

### Test With an End Date

1. Create or select a test record.
2. Enter a participant name.
3. Enter a study start date.
4. Enter a study end date.
5. Save the configured trigger instrument.
6. Confirm that both configured Wallet-link fields are populated.
7. Open the Google Wallet link.
8. Open the Apple Wallet link.
9. Confirm that both cards contain the expected information.

### Test Without an End Date

Repeat the test with the end date blank.

Confirm that:

- both Wallet links are generated;
- the Google card does not display an empty end-date line; and
- the Apple pass opens successfully without an end date.

Final Apple Wallet testing should be performed on a compatible Apple device.

---

## Information Displayed on the Digital ID

Depending on the project and system configuration, a digital ID may contain:

- participant name;
- study title;
- principal investigator;
- study contact email;
- study contact phone number;
- study start date;
- study end date, when available;
- study description; and
- institutional or study branding.

Apple Wallet and Google Wallet may display this information differently because each platform controls its own card layout.

---

## Google Wallet

DigitalTrialsID creates a signed Google Save-to-Wallet object using the institution’s configured Google service account.

The generated URL is saved to the REDCap field selected as the **Google Wallet Link Field**.

When the participant follows the URL, Google processes the signed object and allows the participant to add the card to Google Wallet.

---

## Apple Wallet

Apple Wallet passes must be cryptographically signed using Apple-issued credentials.

DigitalTrialsID does not store the Apple signing certificate or private key. Instead, it generates a signed, short-lived request to a separately configured Apple Wallet pass-generation service.

The Apple service:

1. validates the request;
2. creates the participant’s pass;
3. signs the pass using the institution’s Apple credentials; and
4. returns the completed `.pkpass` file.

The generated service URL is saved to the REDCap field selected as the **Apple Wallet Link Field**.

---

# Administrator Setup

The following sections are intended for REDCap system administrators responsible for installing DigitalTrialsID and configuring the institutional Wallet integrations.

Project users generally do not need to perform these steps.

---

## System Requirements

### REDCap

- REDCap 13.1.0 or later
- REDCap External Modules enabled
- External Module Framework version 12
- Permission to install and configure External Modules

### Server

- PHP 8.0 or later
- OpenSSL support
- Ability to make outbound HTTPS requests to the configured Wallet services

### Google Wallet

The institution needs:

- Google Wallet API access;
- a Google Wallet Issuer account;
- a Google service account authorized for the issuer; and
- an existing Google Wallet Generic Pass class.

### Apple Wallet

The institution needs access to an HTTPS pass-generation service configured with:

- an Apple Developer account;
- an Apple Pass Type Identifier;
- an Apple Pass Type signing certificate;
- the corresponding private key;
- the applicable Apple Worldwide Developer Relations certificate; and
- the same shared token secret configured in DigitalTrialsID.

---

## Installation

DigitalTrialsID is distributed with its PHP dependencies in the `vendor` directory.

A REDCap administrator does not need Composer installed on the REDCap server.

The module package includes:

```text
DigitalTrialsID.php
pass_utils.php
config.json
composer.json
composer.lock
README.md
LICENSE.txt
vendor/
```

After installation:

1. Enable DigitalTrialsID in the REDCap Control Center.
2. Configure the system-level Wallet settings.
3. Enable the module in a test project.
4. Configure its project-level settings.
5. Test Google and Apple Wallet issuance.
6. Enable the module for additional projects as appropriate.

---

## System Configuration

Only authorized REDCap administrators should manage the system-level settings.

### Environment

Select:

- **Production**
- **Test**

The selected environment is incorporated into generated Google Wallet object identifiers to help separate test and production issuance.

### Google Service Account Key

Paste the complete Google service account JSON authorized to issue Wallet objects.

This credential is sensitive and should be accessible only to authorized REDCap administrators.

Do not commit service account credentials to GitHub or another source-code repository.

### Google Issuer ID

Enter the institution’s Google Wallet Issuer ID.

Example:

```text
1234567890123456789
```

### Google Wallet Class ID Suffix

Enter only the Generic Pass class suffix.

Example:

```text
research_pass_class
```

DigitalTrialsID combines the issuer ID and class suffix automatically.

### Apple Token Secret

Enter the shared secret used to sign requests sent to the Apple Wallet pass-generation service.

The same secret must be configured in the Apple service.

This is not an Apple certificate or Apple private key.

### Apple Wallet Endpoint URL

Enter the HTTPS endpoint for the Apple Wallet pass-generation service.

Example:

```text
https://wallet-service.example.org/api/apple-pass
```

The service may be hosted on Azure or another institutionally approved platform.

### Logo URL

Optionally enter a direct HTTPS URL for the logo displayed by Google Wallet.

Example:

```text
https://assets.example.org/research/logo.png
```

### Hero Image URL

Optionally enter a direct HTTPS URL for the hero image displayed by Google Wallet.

Example:

```text
https://assets.example.org/research/hero.png
```

### Card Background Color

Optionally enter a hexadecimal background color for the Google Wallet card.

Example:

```text
#336699
```

---

## Apple Wallet Service Requirements

The Apple Wallet endpoint is separate from this REDCap module.

The service must:

1. receive the JWT generated by DigitalTrialsID;
2. validate its signature;
3. validate the `apple-wallet` audience;
4. verify the token expiration;
5. read the participant and study information;
6. construct the Apple Wallet pass;
7. sign the pass using the institution’s Apple credentials; and
8. return the completed `.pkpass`.

The Apple token is signed using HS256 and expires after approximately one hour.

Institutions may host this service using Azure Functions or another approved serverless platform, container, or web server.

The service’s signing certificates, private keys, and token secrets must never be committed to a public source repository.

---

## Data Flow and Privacy

DigitalTrialsID transmits information from REDCap to external Wallet services.

Administrators and study teams should evaluate the information displayed on each card before enabling participant use.

### Google Wallet

Participant and study information is incorporated into a signed Google Wallet object.

This may include:

- participant name;
- study title;
- principal investigator;
- study contact information;
- study dates; and
- study description.

When the participant follows the generated URL, the information needed to create the card is provided to Google Wallet.

### Apple Wallet

Participant and study information is incorporated into a signed, short-lived JWT and sent to the configured Apple Wallet service.

The token may include:

- participant name;
- study title;
- principal investigator;
- study contact information;
- study dates;
- study description; and
- REDCap project, event, and record identifiers used by the pass-generation workflow.

Institutions are responsible for determining whether their configuration is appropriate under applicable:

- institutional privacy and security policies;
- IRB requirements;
- participant consent or disclosure requirements;
- data-use requirements; and
- applicable laws and regulations.

Only information appropriate for display on a participant’s digital study ID should be configured for Wallet issuance.

---

## Security Considerations

### Google Credentials

Google service account credentials are stored in the system-level module settings.

Access to these settings should be restricted to authorized REDCap administrators.

### Apple Credentials

Apple signing certificates and private keys remain outside REDCap.

DigitalTrialsID stores only the shared token secret required to sign requests to the Apple Wallet service.

### Generated Wallet Links

Generated Wallet links should be treated as sensitive REDCap record data and should not be exposed unnecessarily.

### Logging

DigitalTrialsID does not create its own log file.

Meaningful configuration or generation failures may be written to the REDCap External Module log. Participant names, credentials, signed tokens, and complete Wallet URLs are not intentionally written to that log.

---

## Administrator Testing Checklist

Before making DigitalTrialsID available for participant use, confirm that:

- the module enables without errors;
- the system settings can be saved;
- the project settings and field mappings can be configured;
- the configured trigger instrument generates both Wallet links;
- the links are saved to the selected destination fields;
- the Google Wallet card opens correctly;
- the Apple service returns a valid `.pkpass`;
- the Apple pass opens correctly on an Apple device;
- a card can be generated without an end date;
- optional phone and image settings do not prevent generation;
- generated links are written to the correct REDCap record; and
- a Wallet-generation failure does not prevent the original REDCap record from saving.

Testing should be repeated after significant changes to DigitalTrialsID, REDCap, PHP, Wallet APIs, or the Apple pass-generation service.

---

## Troubleshooting

If Wallet links are not generated, check:

1. Is the correct trigger instrument configured?
2. Are the participant-name and start-date field mappings configured?
3. Are the Google and Apple destination-field mappings configured?
4. Are the two Wallet links mapped to different destination fields?
5. Does the record contain a participant name and start date?
6. Is the Google service account JSON valid?
7. Are the Google issuer ID and class suffix correct?
8. Is the Apple Wallet endpoint reachable over HTTPS?
9. Does the Apple token secret match the secret used by the Apple service?
10. Does the Apple service have valid signing credentials?
11. Does the REDCap External Module log contain a configuration or generation error?

An end date is optional and should not prevent Wallet generation.

---

## Module Files

```text
DigitalTrialsID.php
pass_utils.php
config.json
composer.json
composer.lock
README.md
LICENSE.txt
vendor/
```

### `DigitalTrialsID.php`

Contains the REDCap save-record hook, reads the configured settings and field mappings, generates unique issuance identifiers, and saves Wallet links to the selected REDCap fields.

### `pass_utils.php`

Creates the signed Google Save-to-Wallet JWT and the signed Apple service request token.

### `config.json`

Defines the module metadata, compatibility requirements, system settings, project settings, and REDCap field mappings.

### `vendor/`

Contains the PHP dependency required to create signed JWTs.

---

## Development

Normal REDCap installation does not require Composer because the `vendor` directory is included with the module.

Composer is required only when a developer intentionally modifies or rebuilds the PHP dependency set.

The primary dependency is:

```text
firebase/php-jwt
```

Install the locked dependencies with:

```bash
composer install --no-dev
```

Intentionally update dependencies with:

```bash
composer update --no-dev
```

After rebuilding dependencies:

1. Run `composer validate --strict`.
2. Run `composer audit`.
3. Test both Wallet integrations.
4. Commit the updated `composer.lock` and `vendor` contents.
5. Include the complete `vendor` directory in the distributed release.

---

## Author

**Carlos A. Canales**  
University of Miami

DigitalTrialsID was developed at the University of Miami.

The ResearchPass implementation was developed through the University of Miami Clinical and Translational Science Institute (CTSI).

Development was supported by grant **UM1TR004556** from the National Center for Advancing Translational Sciences.

---

## License

DigitalTrialsID is released under the **MIT License**.

See `LICENSE.txt` for details.
