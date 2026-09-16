# DigitalTrialsID

**Digital research participant ID cards for REDCap**

DigitalTrialsID is a REDCap External Module that allows research studies to issue digital participant ID cards that participants can save to **Apple Wallet** or **Google Wallet**.

Once DigitalTrialsID has been installed and enabled for your REDCap project, most studies only need to complete a few steps to begin issuing cards.

---

## Getting Started

### 1. Add the Required Fields

Your REDCap project needs text or date fields containing the following values. Field names are configurable; the names below are examples only:

- `participant_name` — participant name displayed on the digital ID (**required**)
- `start_date` — participant's study start date (**required**)
- `end_date` — participant's study end date (**optional**)
- `study_title` — study title (**required**)
- `pi_name` — principal investigator name (**required**)
- `study_contact_email` — study contact email (**required**)
- `google_wallet_link` — destination for the generated Google Wallet link (**required**)
- `apple_wallet_link` — destination for the generated Apple Wallet link (**required**)

Configure each actual field under the module's project settings. DigitalTrialsID reads record values from the selected fields and writes generated Wallet links to the two selected destination fields. It never displays the configured variable names as pass content.

The Wallet link fields should not be manually edited by participants.

If these fields are located on a participant-facing survey, consider hiding them with an appropriate REDCap action tag such as:

```text
@HIDDEN-SURVEY
```

---

### 2. Configure DigitalTrialsID

Open the External Module settings for your REDCap project and configure the study information.

#### Study Title

Enter the REDCap variable name containing the study title. This setting is a text input, not a field dropdown.

#### Principal Investigator Name

Enter the REDCap variable name containing the principal investigator's name. This setting is a text input, not a field dropdown.

#### Study Contact Email

Enter the REDCap variable name containing the study contact email address. This setting is a text input, not a field dropdown.

#### Participant and Date Fields

Select the fields containing the participant name and study start date. Both values are required before links can be issued. The study end-date field and its value are optional; if omitted, Google Wallet does not show an empty **End Date** line, and Apple receives an empty end-date claim safely.

#### Wallet Link Fields

Select the text fields in which the Google Wallet and Apple Wallet links should be stored. Keep these fields on the same non-repeating event as the trigger instrument.

#### Study Description

Enter a short, participant-friendly description of the study purpose or objective.

#### Contact Phone Number

Optionally provide a study contact phone number.

This may be useful when a participant or healthcare provider needs to contact the study team.

#### Instrument That Issues Digital Study IDs

Select the REDCap instrument that should trigger creation of the participant's digital ID.

---

### 3. Choose When the Card Is Created

DigitalTrialsID generates the Wallet links when the selected trigger instrument is saved.

```text
Participant information is entered
              |
              v
Configured instrument is saved
              |
              v
      DigitalTrialsID runs
              |
        +-----+-----+
        |           |
        v           v
   Apple Wallet  Google Wallet
       link          link
        |           |
        +-----+-----+
              |
              v
     Saved to REDCap record
```

The participant must have a name and study start date before the digital ID can be generated. The end date is optional.

Repeating instruments are not supported. The trigger-instrument selector excludes repeating instruments, and saves for repeat instances after instance 1 are rejected as an additional safeguard. In longitudinal projects, the module reads and writes values in the event that fired the hook; configure the mapped fields and trigger instrument in that same event.

A valid existing link is not reissued on later saves. To intentionally regenerate a pass after changing mapped data, clear the applicable saved link and save the trigger instrument again.

---

### 4. Present the Wallet Options

After generation, the participant's REDCap record contains:

```text
[apple_wallet_link]
[google_wallet_link]
```

The study controls where and how these links are presented to the participant.

One option is to place the Wallet buttons in the survey completion text.

In REDCap:

1. Open the survey settings for the instrument.
2. Locate the **Survey Completion Text**.
3. Switch the editor to **Source Code** view.
4. Paste the following HTML.

```html
<h1 style="font-size: 16px; margin-bottom: 18px; text-align: center;">
  <span style="font-size: 18pt;">
    Please select the appropriate button below to add your
    <strong>ResearchPass</strong> to Apple Wallet or Google Wallet.
    <br><br>
  </span>
</h1>

<p style="margin: 8px 0px; text-align: center;">
  <a style="display: inline-block;" href="[apple_wallet_link]">
    <img
      style="height: 50px; width: auto; border: 0px;"
      src="https://support.apple.com/library/content/dam/edam/applecare/images/en_US/iOS/add-to-apple-wallet-logo.png"
      alt="Add to Apple Wallet">
  </a>
  <br><br><br>
</p>

<p style="margin: 8px 0px; text-align: center;">
  <a style="display: inline-block;" href="[google_wallet_link]">
    <img
      style="height: 50px; width: auto; border: 0px;"
      src="https://codelabs.developers.google.com/static/add-to-wallet-android/images/add-wallet-btn-large.png"
      alt="Add to Google Wallet">
  </a>
</p>
```

The study may modify the text, spacing, or placement as needed.

The important pieces are the REDCap piping references:

```text
[apple_wallet_link]
[google_wallet_link]
```

These are replaced with the participant's generated Wallet URLs when REDCap renders the page.

---

### 5. Test Before Use

Before issuing cards to participants:

1. Create or use a test record.
2. Enter a participant name.
3. Enter a study start date.
4. Optionally enter a study end date.
5. Populate the selected study title, PI name, and contact email fields.
6. Save the configured trigger instrument.
7. Confirm that the configured Apple and Google link fields are populated.
8. Open the Google Wallet link and confirm that the card displays correctly and omits the end-date line when no end date was entered.
9. Open the Apple Wallet link and confirm that a valid pass is generated.
10. Test each integration with the other integration deliberately misconfigured and confirm the successful link is still saved.
11. Save unrelated data and confirm existing valid passes are not reissued.
12. Test the configured event in a longitudinal project.
13. Confirm repeating instruments cannot be selected and repeat instances after instance 1 are skipped for an older retained configuration.
14. Perform final Apple Wallet testing on an Apple device before participant use.

Google and Apple generation are independent. If one integration is unavailable or misconfigured, a valid link from the other integration is still saved. A failed service never replaces an existing valid link with an empty value and never interrupts the REDCap record save.

If the configured Wallet options work as expected, the project is ready to issue digital participant IDs.

---

## How It Works

DigitalTrialsID sits between the REDCap project and the two mobile Wallet platforms.

```text
+------------------------+
|     REDCap Project     |
|                        |
| Participant + Study    |
| Information            |
+-----------+------------+
            |
            | Trigger instrument saved
            v
+------------------------+
|    DigitalTrialsID     |
+-----------+------------+
            |
      +-----+-----+
      |           |
      v           v
+-----------+  +-------------+
|  Google   |  |    Apple    |
|  Wallet   |  |   Wallet    |
+-----+-----+  +------+------+
      |               |
      +-------+-------+
              |
              v
      +---------------+
      |  Participant  |
      | Mobile Wallet |
      +---------------+
```

Google Wallet issuance is handled through Google's Wallet integration.

Apple Wallet requires an additional pass-generation service because Apple `.pkpass` files must be cryptographically signed using Apple-issued credentials.

The study does not need to manage this infrastructure. Once the institutional Wallet integrations have been configured by an administrator, the project primarily interacts with the DigitalTrialsID project settings.

---

## What Can Appear on the Digital ID?

Depending on the project and system configuration, a digital ID may contain:

- Participant name
- Study title
- Principal investigator
- Study contact email
- Study contact phone number
- Study start date
- Study end date
- Study description
- Institutional or study branding

The Apple and Google versions may display the information somewhat differently because each Wallet platform controls its own card layout.

---

## Google Wallet

DigitalTrialsID generates a signed Google Save-to-Wallet object for the participant.

```text
REDCap Record
     |
     v
DigitalTrialsID
     |
     | Signed Wallet object
     v
Google Wallet
     |
     v
Participant saves card
```

The generated link is stored in:

```text
google_wallet_link
```

When the participant follows the link, Google processes the signed Wallet object and allows the participant to save the card to Google Wallet.

---

## Apple Wallet

Apple Wallet passes must be cryptographically signed using Apple-issued credentials.

DigitalTrialsID therefore generates a short-lived signed request to a separate Apple Wallet pass-generation service.

```text
REDCap Record
     |
     v
DigitalTrialsID
     |
     | Short-lived signed request
     v
Apple Pass Service
     |
     | Builds and signs pass
     v
.pkpass
     |
     v
Apple Wallet
```

The generated link is stored in:

```text
apple_wallet_link
```

The Apple signing certificate and private key are **not stored in REDCap**.

Because the Apple Wallet endpoint is configurable, institutions may use their preferred infrastructure to host the pass-generation service.

---

# Administrator Setup

> The sections below are intended primarily for REDCap system administrators responsible for installing DigitalTrialsID and configuring the institutional Wallet integrations.

A project user generally does not need to perform the steps below.

---

## Installation

DigitalTrialsID is distributed with its required PHP dependencies already included in the `vendor` directory.

A REDCap administrator does **not** need Composer installed on the REDCap server to use the module.

Install DigitalTrialsID using the standard REDCap External Module installation process.

The module package should include:

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
3. Enable DigitalTrialsID in a test REDCap project.
4. Configure the project settings.
5. Test both Wallet integrations.
6. Enable the module for additional projects as appropriate.

---

## System Requirements

### REDCap

- REDCap 12.0.0 or later
- REDCap External Modules enabled
- Permission to install and configure External Modules

### Server

- PHP 8.0 or later
- OpenSSL support

### Google Wallet

The institution needs:

- Google Wallet API access
- A Google Wallet Issuer account
- A Google service account authorized for the issuer
- A Google Wallet Generic Pass class

### Apple Wallet

The institution needs:

- An Apple Developer account
- An Apple Pass Type Identifier
- An Apple Pass Type signing certificate and corresponding private key
- The applicable Apple Worldwide Developer Relations certificate
- An HTTPS service capable of generating and signing `.pkpass` files

The Apple service does **not** have to use a particular hosting provider.

---

## System Configuration

The following settings are configured at the REDCap system level. Credential settings are marked for super-user-only access where the installed External Module Framework supports that restriction.

### Environment

Select:

- **Production**
- **Test**

The environment is incorporated into generated Wallet object identifiers to help keep test and production issuance separate.

### Google Service Account Key

Paste the JSON credentials for the Google service account authorized to issue Wallet objects.

This credential should be treated as sensitive and accessible only to appropriate administrators.

### Google Issuer ID

Enter the institution's Google Wallet Issuer ID.

Generic example:

```text
1234567890123456789
```

### Google Wallet Class ID Suffix

Enter only the configured Generic Pass class suffix.

Generic example:

```text
research_pass_class
```

DigitalTrialsID combines the issuer ID and class suffix automatically.

### Apple Token Secret

Enter the shared secret used to sign Apple Wallet requests.

The same secret must be configured by the Apple Wallet pass-generation service.

This is **not** an Apple certificate or Apple private key.

### Apple Wallet Endpoint URL

Enter the HTTPS endpoint for the Apple Wallet pass-generation service.

Generic example:

```text
https://wallet-service.example.org/api/apple-pass
```

The service may be hosted on Azure or another institutionally approved platform.

### Logo URL

Optional HTTPS URL for the logo displayed by Google Wallet.

Generic example:

```text
https://assets.example.org/research/logo.png
```

### Hero Image URL

Optional HTTPS URL for the hero image displayed by Google Wallet.

Generic example:

```text
https://assets.example.org/research/hero.png
```

### Card Background Color

Optional hexadecimal background color for the Google Wallet card.

Generic example:

```text
#336699
```

---

## Apple Wallet Pass-Generation Service

Apple Wallet passes cannot simply be constructed as URLs. The completed `.pkpass` must be signed using Apple-issued credentials.

DigitalTrialsID keeps that signing process outside REDCap.

The configured service must:

1. Receive the signed request generated by DigitalTrialsID.
2. Validate the request.
3. Verify that the token has not expired.
4. Read the participant and study information.
5. Construct the Apple Wallet pass.
6. Sign the pass using the institution's Apple credentials.
7. Return the completed `.pkpass`.

This design keeps the Apple certificate and private key outside REDCap and allows institutions to choose their own hosting infrastructure.

---

## Azure Reference Implementation

A working reference implementation of the Apple Wallet service was developed using **Microsoft Azure Functions**.

**Azure is optional.** It is provided as an example of how the Apple Wallet service can be implemented.

The reference implementation uses:

- Microsoft Azure Functions
- Flex Consumption hosting
- Node.js 22 LTS
- Azure Functions programming model v4
- `jsonwebtoken`
- `passkit-generator`

### Reference Architecture

```text
+-------------+
|   REDCap    |
+------+------+
       |
       | Signed JWT
       v
+-------------------+
| DigitalTrialsID   |
+---------+---------+
          |
          | HTTPS
          v
+-------------------+
|  Azure Function   |
|                   |
| Validate request  |
| Build pass        |
| Sign pass         |
+---------+---------+
          |
          | .pkpass
          v
+-------------------+
|   Apple Wallet    |
+-------------------+
```

### Reference Folder Structure

```text
digitaltrials_azure/
|
+-- host.json
+-- local.settings.json
+-- package.json
+-- package-lock.json
+-- .funcignore
+-- .gitignore
|
+-- src/
    |
    +-- functions/
    |   +-- apple-pass.js
    |
    +-- assets/
        +-- apple/
            +-- icon.png
            +-- icon@2x.png
            +-- logo.png
            +-- logo@2x.png
```

### apple-pass.js

The HTTP-triggered Azure Function is responsible for:

1. Receiving the JWT generated by DigitalTrialsID.
2. Validating its signature, audience, and expiration.
3. Reading the participant and study information.
4. Loading the Apple signing credentials.
5. Building the Apple Wallet pass.
6. Signing the pass.
7. Returning the completed `.pkpass`.

### Apple Image Assets

The reference implementation packages:

```text
icon.png
icon@2x.png
logo.png
logo@2x.png
```

Institutions may replace these with their own appropriately sized Apple Wallet assets.

---

## Azure Dependencies

The reference implementation uses:

```json
{
  "dependencies": {
    "@azure/functions": "^4.0.0",
    "jsonwebtoken": "^9.0.3",
    "passkit-generator": "^3.5.7"
  }
}
```

Install the Node dependencies on the development workstation with:

```bash
npm install
```

---

## Azure Application Settings

The reference implementation expects:

```text
APPLE_TOKEN_SECRET
APPLE_WWDR_BASE64
APPLE_SIGNER_CERT_BASE64
APPLE_SIGNERKEY_BASE64
APPLE_PASS_TYPE_IDENTIFIER
APPLE_TEAM_IDENTIFIER
```

### APPLE_TOKEN_SECRET

Shared secret used to validate requests from DigitalTrialsID.

It must match the **Apple Token Secret** configured in REDCap.

### APPLE_WWDR_BASE64

Base64-encoded Apple Worldwide Developer Relations certificate.

### APPLE_SIGNER_CERT_BASE64

Base64-encoded Apple Pass Type signing certificate.

### APPLE_SIGNERKEY_BASE64

Base64-encoded private key corresponding to the Pass Type certificate.

### APPLE_PASS_TYPE_IDENTIFIER

Apple Pass Type Identifier associated with the signing certificate.

Generic example:

```text
pass.org.example.research
```

### APPLE_TEAM_IDENTIFIER

Apple Developer Team Identifier associated with the Pass Type ID.

Certificate values, private keys, and token secrets should **never be committed to a public source repository**.

---

## Basic Azure Deployment

The reference implementation can be hosted using an Azure Function App configured with:

- Flex Consumption hosting
- Node.js runtime
- Node.js 22 LTS

Azure Functions Core Tools are used from the development workstation.

Initialize a Node.js v4 Functions project:

```bash
func init . --worker-runtime node --model v4
```

Install dependencies:

```bash
npm install
```

Run locally:

```bash
func start
```

Deploy to an existing Azure Function App:

```bash
func azure functionapp publish YOUR-FUNCTION-APP-NAME
```

After deployment:

1. Obtain the HTTPS endpoint for the Apple pass function.
2. Add the required Azure application settings.
3. Enter the endpoint in DigitalTrialsID as the **Apple Wallet Endpoint URL**.
4. Configure the same Apple Token Secret in REDCap and Azure.
5. Test the workflow using a REDCap test project.

---

## Using a Platform Other Than Azure

Azure is only a reference implementation.

DigitalTrialsID itself is hosting-platform independent.

Another serverless platform, containerized application, web server, or institutionally approved service may be used if it can:

- expose an HTTPS endpoint;
- receive the signed request from DigitalTrialsID;
- validate the JWT signature;
- verify token expiration;
- securely protect Apple signing credentials;
- generate a valid Apple Wallet pass;
- cryptographically sign the pass; and
- return the resulting `.pkpass`.

The configurable **Apple Wallet Endpoint URL** allows DigitalTrialsID to use the institution's chosen implementation without modifying the REDCap module.

---

## Data Flow and Privacy

DigitalTrialsID integrates REDCap with external Wallet services.

Administrators and study teams should understand this data flow before enabling the module for participant use.

### Google Wallet

Participant and study information is incorporated into the signed Google Wallet object.

Depending on project configuration, this may include:

- Participant name
- Study title
- Principal investigator
- Study contact information
- Study dates
- Study description

When the participant uses the generated link, the information required to create the card is provided to Google Wallet.

### Apple Wallet

DigitalTrialsID places the information required to generate the Apple pass into a signed, short-lived JWT.

Depending on configuration, this may include:

- Participant name
- Study title
- Principal investigator
- Study contact information
- Study dates
- Study description
- REDCap identifiers required by the pass-generation workflow

When the participant requests the pass, this information is transmitted to the configured Apple Wallet pass-generation service.

Institutions are responsible for determining whether their implementation is appropriate under applicable institutional policies, study requirements, participant disclosures, and privacy requirements.

---

## Security Considerations

### Google Credentials

Google service account credentials are configured at the system level and should be accessible only to authorized REDCap administrators.

### Apple Credentials

Apple signing certificates and private keys remain outside REDCap.

DigitalTrialsID stores only the shared secret required to sign requests to the Apple Wallet service.

### Short-Lived Tokens

Apple Wallet generation requests use signed JWTs with expiration timestamps.

### HTTPS

The Apple Wallet pass-generation endpoint should use HTTPS.

### Generated Wallet Links

Generated Wallet links should be treated as sensitive record data and should not be exposed unnecessarily.

### Logging

Operational logging is intentionally limited and uses REDCap's External Module log. The module does not write a `debug.log` file in its directory.

Logs report configuration, generation, and save failures without participant names, credentials, private keys, JWTs, complete Wallet URLs, or other sensitive values.

---

## Administrator Testing Checklist

Before making DigitalTrialsID available for participant use, test the complete workflow.

Confirm that:

- the module enables without errors;
- all eight field mappings and the trigger instrument can be configured;
- the configured non-repeating trigger instrument generates Wallet links in classic and longitudinal projects;
- `google_wallet_link` is populated;
- `apple_wallet_link` is populated;
- the Google Wallet card opens correctly;
- the Apple service returns a valid `.pkpass`;
- the Apple pass opens correctly on an Apple device;
- a missing end date does not prevent generation or add a blank Google end-date line;
- missing optional contact information does not prevent generation;
- missing optional images do not prevent generation;
- generated links are written to the correct REDCap record and event;
- repeating instruments cannot be selected and repeat instances after instance 1 are skipped for an older retained configuration;
- saving unrelated data does not reissue existing valid links;
- each Wallet integration succeeds when the other is unavailable;
- a failed integration does not erase its existing valid link; and
- failure of an external Wallet service does not prevent the REDCap record from saving.

Testing should be repeated after significant changes to DigitalTrialsID, REDCap, PHP, Wallet APIs, or the Apple pass-generation service.

---

## Troubleshooting

If Wallet links are not generated, check these items first:

1. Is the correct, non-repeating trigger instrument configured?
2. Are the participant, start-date, study-title, PI-name, and contact-email mappings configured and populated in the current event?
3. Are the configured Google and Apple destination fields present in the current event?
4. Is the Google service account configuration valid?
5. Is the Google issuer and class configuration valid?
6. Is the Apple Wallet endpoint reachable?
7. Does the Apple Token Secret match between REDCap and the pass-generation service?
8. Does the Apple service have valid signing credentials?
9. Does the module log indicate which Wallet generation step failed?

---

## Development

Normal REDCap installation does **not** require Composer because `vendor/` is included with the module.

Composer is only required when a developer intentionally modifies or rebuilds the PHP dependency set.

The primary PHP dependency is:

```text
firebase/php-jwt
```

From a development workstation:

```bash
composer install
```

To intentionally update dependency versions:

```bash
composer update
```

After rebuilding dependencies:

1. Test the updated module.
2. Confirm both Wallet integrations still work.
3. Include the resulting `vendor/` directory in the version distributed to REDCap.

---

## Institutional Review

DigitalTrialsID provides the technical mechanism for issuing digital research participant ID cards.

Use of the module does not by itself determine whether particular information is appropriate for a specific research study or mobile Wallet.

Institutions and study teams are responsible for evaluating their implementation under applicable:

- Institutional privacy and security policies
- IRB requirements
- Participant consent or disclosure requirements
- Data-use requirements
- Applicable laws and regulations

Only information appropriate for display on a participant's digital study ID should be configured for Wallet issuance.

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
