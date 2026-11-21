# Storage Architecture

## Purpose

`StorageService` is the central place for all logical storage paths in Limodelia CRM. No module should manually concatenate folders for Cloudinary or future storage providers.

The service is provider agnostic. It returns logical folder paths only. Cloudinary-specific upload, delete, signing and API behavior remain inside `CloudinaryService`.

## Responsibilities

`StorageService`:

- reads the root folder through `SettingsService`;
- generates logical folders for trips, students, marketing, reports, documents, temporary files and backups;
- keeps naming conventions consistent across modules;
- prevents duplicated folder rules in business services.

`CloudinaryService`:

- reads Cloudinary credentials through `SettingsService`;
- uploads files to a folder supplied by application services;
- deletes Cloudinary assets by public ID;
- tests Cloudinary API connectivity;
- does not decide module folder structure.

Business services:

- request the needed folder from `StorageService`;
- pass that folder to the storage provider service;
- do not manually concatenate storage paths.

## Folder Tree

```text
{Root}
  trips/
    {tripId}/
      gallery/
      cover/
  students/
    {studentId}/
      avatar/
      documents/
  marketing/
    instagram/
    other/
  reports/
  documents/
    contracts/
    invoices/
    exports/
  temp/
  backups/
```

`{Root}` is configured in Settings under Cloudinary `Root Folder`. Changing this value immediately changes generated upload destinations for services that use `StorageService`.

## Public Methods

- `tripFolder($tripId)`
- `tripGalleryFolder($tripId)`
- `tripCoverFolder($tripId)`
- `studentFolder($studentId)`
- `studentAvatarFolder($studentId)`
- `studentDocumentsFolder($studentId)`
- `marketingInstagramFolder()`
- `marketingOtherFolder()`
- `reportsFolder()`
- `contractsFolder()`
- `invoicesFolder()`
- `exportsFolder()`
- `temporaryFolder()`
- `backupFolder()`

The service also exposes local student avatar helper paths for the current legacy local avatar workflow. These keep local path construction centralized until student avatars are migrated to the configured storage provider.

## Naming Conventions

- Use lowercase module folders.
- Use database IDs for entity folders.
- Use stable folder names such as `gallery`, `cover`, `avatar` and `documents`.
- Avoid dates in folder paths unless the module explicitly needs archival partitioning.
- Store provider-specific identifiers, such as Cloudinary public IDs, in the database metadata tables.

## Why Paths Are Centralized

Centralized paths prevent subtle inconsistencies such as:

- `trips/1/gallery` in one module and `trip/1/photos` in another;
- changing root folder in Settings but only some modules respecting it;
- Cloudinary-specific path decisions leaking into business services;
- future migrations requiring edits across unrelated modules.

## Future Providers

The same logical paths can be passed to future provider-specific services:

- AWS S3
- Google Cloud Storage
- Local Storage
- Azure Blob Storage

Provider services should accept paths from `StorageService` and translate them only when the provider requires it. Business modules should not know which provider is active.
