# Backup and Restore Architecture

## Purpose

The Backup and Restore module gives the local CRM administrator a safe way to create restore points before larger development or production-preparation work.

The module is intended for local XAMPP use during alpha development. It is admin-only ready, but final access control must be enforced after authentication and role authorization are completed.

## Storage Location

Backups are stored locally in:

```text
storage/backups/
```

Backup file naming:

```text
limodelia_backup_YYYY-MM-DD_HH-mm-ss.zip
```

The backup folder contains:

- `.htaccess` with deny rules.
- `index.html` to avoid directory listing fallback.
- generated `.zip` backup files.

Backup files are not linked for direct browser download from the UI.

## Backup Contents

Each backup ZIP contains:

- `database.sql` - MySQL dump of the current database.
- `metadata.json` - app name, app version, timestamp, database name, PHP version and upload presence.
- `uploads/` - local uploaded files, if the local uploads folder exists.

Cloudinary files are not downloaded into local backups. Cloudinary metadata stored in MySQL is included through `database.sql`.

## MySQL Dump Strategy

`BackupService` uses the best available local strategy:

1. `mysqldump.exe` when available in the XAMPP MySQL folder.
2. PHP-based SQL export fallback when `mysqldump` is unavailable.

The PHP fallback exports table definitions and rows using PDO quoting and disables foreign key checks around the export.

## ZIP Strategy

The service uses:

1. PHP `ZipArchive` if the extension is available.
2. Windows PowerShell `Compress-Archive` fallback when `ZipArchive` is unavailable.

Current local XAMPP PHP does not include `ZipArchive`, so the PowerShell fallback is used.

## Restore Process

Restore is performed from `pages/backups.php`.

The user must:

1. Select a backup from history.
2. Type the exact confirmation text:

```text
RESTORE
```

3. Submit the restore form.

Restore flow:

1. Validate selected backup file.
2. Validate confirmation text.
3. Extract the ZIP into a temporary storage folder.
4. Restore `database.sql` using `mysql.exe` when available.
5. Fallback to PHP SQL execution if `mysql.exe` is unavailable.
6. Restore local `uploads/` if present in the archive.
7. Remove temporary restore files.

## Risks

Restore overwrites the current database state. Any changes after the selected backup can be lost.

Local uploads may be overwritten by files from the archive.

Cloudinary remote assets are not restored from backup. The database can restore references to Cloudinary assets, but deleted remote Cloudinary files cannot be recreated by this module.

## Local XAMPP Limitations

- Backups are local to the developer/admin machine.
- PowerShell archive support is required when `ZipArchive` is missing.
- Large uploads can make backup creation slower.
- `mysqldump.exe` and `mysql.exe` paths are detected from common XAMPP locations.
- The module assumes local database credentials from `config/db.php`.

## Security

- Backup files are stored under `storage/backups/`.
- `.htaccess` denies direct Apache access when Apache overrides are enabled.
- The UI does not expose direct file download links.
- Restore requires explicit confirmation text.
- Production must add role checks so only owner/admin users can use this page.

## Future Production Recommendations

- Move backups outside the public web root.
- Encrypt backup archives.
- Add backup retention policy.
- Add scheduled backups.
- Push backups to external storage.
- Include Cloudinary export/sync strategy.
- Log every backup and restore action in `system_logs`.
- Require re-authentication before restore.
