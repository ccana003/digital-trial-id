# Google Wallet short-link Azure Function

This is a **separate Azure Function App**, not the standalone ResearchPass API and not the Apple Wallet service. No Azure resources are provisioned by this repository.

## Routes
- `POST /api/shortlinks` with `Authorization: Bearer <registration-secret>` and JSON `{"wallet_url":"https://pay.google.com/gp/v/save/..."}` returns HTTP 201 `{"short_url":"https://<host>/api/g/<token>"}`.
- `GET /api/g/<token>` redirects (302) to the stored Google Wallet destination. Public access is intentional; treat each QR as a bearer credential.

## Required Function App settings
- `AzureWebJobsStorage`: Azure Functions runtime storage setting (managed identity configuration supported by the Functions host, or connection string).
- `FUNCTIONS_WORKER_RUNTIME`: `python`.
- `SHORTLINK_STORAGE_CONNECTION`: connection string for a **dedicated Azure Storage account** (Table Storage); store as a Key Vault reference where available.
- `SHORTLINK_REGISTRATION_SECRET`: strong randomly generated secret, stored as a secret setting / Key Vault reference. Must match the REDCap external module system setting `google_shortlink_api_key`.
- `SHORTLINK_PUBLIC_BASE`: public HTTPS base URL ending in `/api`, e.g. `https://<function-app>.azurewebsites.net/api`.
- `SHORTLINK_TABLE`: optional, default `GoogleWalletLinks`.
- `SHORTLINK_LIFETIME_DAYS`: optional, default 180, range 1–365.

Create the Table Storage table **before** deploying; the function does not auto-create it. Do not enable anonymous writes to storage. Set HTTPS only. Configure platform access controls / throttling for registration. The POST endpoint uses its own bearer authentication; the GET endpoint must remain publicly accessible.

## REDCap settings
Set `google_shortlink_register_endpoint` to `https://<function-app>.azurewebsites.net/api/shortlinks` and `google_shortlink_api_key` to the matching secret. In the project, map `field_google_wallet_short` to `google_wallet_short`. Use `@QRCODE="google_wallet_short"` on the `qr_google` text field (no validation).

## Deployment and verification
1. Create a separate Azure Function App (Python 3.11 or compatible supported runtime) and a dedicated storage account/table in the ResearchPass resource group; **do not reuse** `researchpass-api-dev` or `digitaltrialsid-apple`.
2. Configure settings above, enable HTTPS only, and deploy this directory using Azure Functions Core Tools or GitHub Actions with federated credentials.
3. From a trusted terminal, POST a **test** Google Wallet URL to `/api/shortlinks` with the bearer secret. Confirm HTTP 201 and a short URL.
4. Open the short URL on an Android phone and verify the redirect; check 401 on unauthenticated POST, 404 for invalid tokens, and 410 for expired/revoked tokens.
5. Configure REDCap **Test**, issue a new test record, and confirm `google_wallet_short` populates. Save again if the QR Code module runs before ResearchPass; verify `qr_google` generates and scans.
6. Verify the existing Google button and Apple Wallet button/QR are unchanged.

## Security and limitations
- The destination JWT may contain participant information. It is stored in the Azure Table entity; use dedicated storage, encryption at rest, least-privilege access, restricted diagnostics, and a retention/deletion policy.
- Tokens are 192-bit random, stored as SHA-256 hashes; links are valid for the configured lifetime. Revocation is supported by setting `revoked=true` on the table entity or deleting it.
- The public resolver is a bearer-link endpoint. Anyone with a valid QR URL can open the pass; avoid exposing QR images outside the intended participant flow.
- This first version has no automated expiration cleanup, no dedicated management/revocation API, and no built-in distributed rate limiting. Configure Azure ingress controls and operational cleanup before production.
- Registration is not idempotent: retries create distinct links. QR generation is on save and depends on REDCap external module execution order.
- Test against your institution's network and PHP/REDCap security settings before merging PR #18.
