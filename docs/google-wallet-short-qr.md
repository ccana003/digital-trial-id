# Google Wallet short-link QR enhancement (development)

Status: design and integration specification; **not yet implemented or deployable**.

## Existing behavior to preserve
- `pass_utils.php::generate_wallet_link()` signs a Google Wallet JWT and returns `https://pay.google.com/gp/v/save/{jwt}`.
- `DigitalTrialsID.php::redcap_save_record()` saves the Google and Apple Wallet URLs to project-configured fields.
- The separate REDCap QR Code external module generates a Base64 PNG on form save using `@QRCODE="source_field"`.
- The Apple Wallet link, QR image and pass service must remain unchanged.

## New workflow
1. Generate the Google Wallet URL as before.
2. Register its destination with a trusted HTTPS short-link resolver and obtain a short, unguessable URL.
3. Save the short URL to a new optional project-mapped text field, e.g. `google_wallet_short`.
4. On the same instrument, the QR Code module reads `google_wallet_short` and writes the image to `qr_google` using `@QRCODE="google_wallet_short"`.
5. Display the image in a descriptive field using `<img src="data:image/png;base64,[qr_google]" alt="Scan with your Android phone to add your ResearchPass to Google Wallet">`.

## Security requirements for the resolver
- Public HTTPS GET endpoint must accept only opaque cryptographically random tokens (at least 128 bits entropy).
- Resolve only pre-registered Google Wallet save destinations, never arbitrary user-supplied redirect URLs.
- Store token mappings in an access-controlled durable store; encrypt destination URLs at rest where supported.
- Avoid logging JWTs, destination URLs, participant data, and tokens.
- Provide expiry/revocation and a deliberate policy for participant re-access.
- Do not put record IDs, names, or JWTs in the short token.
- Rate-limit lookups and provide generic errors for unknown/expired tokens.
- Authenticate the module-to-resolver registration request; never expose that credential to survey respondents.
- Validate TLS, HTTP status, and response schema before saving a short URL.
- Resolver failure must not prevent REDCap record saves or affect existing wallet links.

## Integration caveat
Both ResearchPass and the QR Code module run on save. Their hook ordering is not guaranteed. Confirm that the short URL exists before QR generation; if not, use a supported post-generation trigger or separate save step. Do not assume `REDCap::saveData()` causes a second action-tag processing pass.

## Deployment prerequisite
Decide whether the short-link resolver will run as a security-reviewed public REDCap external-module endpoint or in the existing Azure infrastructure. No functional code should be deployed until the endpoint's public base URL, storage and credential model are established.

## Acceptance tests
- Existing Apple QR and wallet button unchanged.
- Existing Google button continues using full Google Wallet URL.
- New optional field mapping is backward-compatible with projects without it.
- Distinct participants get distinct opaque short URLs resolving to their correct Google passes.
- Invalid, expired and revoked tokens fail safely.
- QR Code module generates a readable PNG for the short URL.
- Form saves succeed if the resolver is unavailable.
- No JWTs or participant data in logs or short URL paths.
