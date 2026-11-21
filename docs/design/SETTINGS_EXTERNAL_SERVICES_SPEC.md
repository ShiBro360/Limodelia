# Settings External Services Specification

## Purpose

The External Services section in Settings allows the CRM administrator to configure third-party integrations used by the system. The first supported integration is Cloudinary for Trips photo storage.

## Architecture

The implementation follows MVC Lite:

- `pages/settings.php` renders the settings page and handles POST/Redirect/GET.
- `app/Repositories/SettingsRepository.php` reads and writes rows in the `settings` table.
- `app/Services/SettingsService.php` validates and normalizes settings.
- `app/Services/CloudinaryService.php` reads Cloudinary credentials through `SettingsService`.

No credentials are hardcoded in PHP.

## Cloudinary Fields

The Cloudinary configuration uses existing settings keys:

- `cloudinary.enabled`
- `cloudinary.cloud_name`
- `cloudinary.api_key`
- `cloudinary.api_secret`
- `cloudinary.root_folder`

The root folder defaults to `limodelia` when empty.

## Security Rules

`API Secret` is never rendered back into the page as plain text. After saving, the field is shown empty with the placeholder:

```text
••••••••••
```

If the administrator leaves the secret field empty, the existing secret remains unchanged. Entering a new value replaces it.

## Validation

When Cloudinary is enabled:

- Cloud Name is required.
- API Key is required.
- API Secret is required unless one is already saved.

When Cloudinary is disabled, values may remain stored for later use.

## Test Connection

The `Test Cloudinary` button saves the submitted settings first and then calls `CloudinaryService::testConnection()`.

The test uses the Cloudinary Admin `usage` endpoint with API key and API secret authentication. The page redirects back with one of these results:

- `Connected`
- `Connection failed`

The failure message is shortened before being displayed.

## Trips Integration

Trips upload continues to use `CloudinaryService`. Because the service now reads credentials through `SettingsService`, image uploads automatically use the latest saved Settings values without changing Trips UI.

## Future Improvements

- Add per-environment configuration warnings.
- Add last successful connection timestamp.
- Add Cloudinary folder preview.
- Add audit logging for settings changes without exposing secret values.
