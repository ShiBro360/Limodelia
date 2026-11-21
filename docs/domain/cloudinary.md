# Cloudinary Domain Model

## 1. Purpose

The Cloudinary module defines how Limodelia CRM stores, organizes, displays, and deletes uploaded media. It supports student photos, trip galleries, cover images, portfolio images, receipts, and future media workflows.

Cloudinary should be treated as the long-term media storage provider, while local uploads are used only for temporary staging or development fallback.

## 2. Business Rules

- Media must be validated before upload.
- Only authorized users can upload files.
- Every Cloudinary asset must be linked to an internal file record.
- Student photos should be connected to the related student.
- Trip photos should be connected to the related trip.
- Cover images are selected from existing uploaded assets.
- Deleting a media relationship should not always delete the Cloudinary asset.
- Permanent Cloudinary deletion requires admin permission or a controlled cleanup process.
- Asset metadata must be stored locally for reliable reporting and display.
- Cloudinary credentials must never be committed to source control.

## 3. Fields

Local file metadata:

- File ID.
- Original filename.
- Storage provider.
- Storage path.
- Public URL.
- MIME type.
- File size.
- Width.
- Height.
- Uploaded by.
- Created date.

Cloudinary asset metadata:

- Cloudinary asset ID.
- File ID.
- Public ID.
- Secure URL.
- Resource type.
- Format.
- Version.
- Folder.
- Tags.
- Created date.

Media relationship fields:

- Related module.
- Related record ID.
- File ID.
- Caption.
- Sort order.
- Featured flag.
- Cover flag.
- Photo type.

## 4. Validation Rules

- File is required for upload.
- File size must be below configured limit.
- MIME type must be allowed.
- Image dimensions must meet configured limits when required.
- User must have upload permission.
- Related student or trip must exist when assigning media.
- Public ID must be unique.
- Cover image must belong to the same module context.
- Deleted local relationship must not leave broken UI references.

## 5. Relationships With Other Modules

- Students: profile photos, portfolio images, documents, and gallery photos.
- Trips: cover images, trip albums, and event galleries.
- Expenses: receipt images or documents.
- Users: uploaded-by relationship and permissions.
- Settings: upload limits, folder rules, and credentials.
- Reports: media counts may be used for operational reporting.

## 6. Automatic Calculations

- Thumbnail URL from Cloudinary transformation rules.
- Cover image URL for student or trip.
- Gallery item count.
- Total media storage count by module.
- File size totals for monitoring.
- Image aspect ratio from width and height.

## 7. Possible Future Extensions

- Video uploads.
- Private authenticated media.
- Automatic face-safe thumbnails.
- AI tagging.
- Portfolio selection workflow.
- Bulk upload.
- Drag-and-drop sorting.
- Watermarked previews.
- Expiring public links.
- Parent-facing galleries.

## 8. Edge Cases

- Upload succeeds in Cloudinary but local database save fails.
- Local record exists but Cloudinary asset was deleted externally.
- User deletes a photo used as cover image.
- Same image is assigned to multiple students or albums.
- File upload exceeds limit.
- Unsupported file type is uploaded.
- Cloudinary credentials are invalid.
- Network failure interrupts upload.
- Image is deleted while a report or gallery page references it.

## 9. User Actions

- Upload image.
- View gallery.
- Assign image to student.
- Assign image to trip.
- Add caption.
- Select cover image.
- Mark portfolio image.
- Remove image from album.
- Reorder gallery.

## 10. Admin Actions

- Configure Cloudinary credentials.
- Configure upload limits.
- Configure allowed file types.
- Permanently delete Cloudinary asset.
- Restore media relationship when possible.
- Audit media uploads.
- Clean temporary upload files.
- Review orphaned Cloudinary assets.

## Upload Workflow

1. User selects a file in the CRM.
2. Browser sends file to the server.
3. Server validates file size, MIME type, extension, and image metadata.
4. Server stores file temporarily in `uploads/temp`.
5. Server uploads the validated file to Cloudinary.
6. Cloudinary returns public ID, secure URL, dimensions, format, and resource type.
7. Server creates local file metadata.
8. Server creates Cloudinary asset metadata.
9. Server links the file to student, trip, album, receipt, or another module.
10. Temporary local file is deleted after successful processing.

If any step fails after Cloudinary upload, the system should either delete the Cloudinary asset or mark it for cleanup.

## Folder Structure

Recommended Cloudinary folder pattern:

```text
limodelia/
  students/
    {student_id}/
      profile/
      portfolio/
      gallery/
      documents/
  trips/
    {trip_id}/
      cover/
      gallery/
      documents/
  expenses/
    receipts/
      {year}/
      {month}/
  temp/
  system/
```

Rules:

- Use stable IDs instead of names in folder paths.
- Do not expose sensitive personal details in folder names.
- Keep temporary assets separate from permanent assets.
- Use tags to support filtering by school year, module, and media type.

## Image Naming

Recommended naming pattern:

```text
{module}_{record_id}_{type}_{timestamp}_{short_random}
```

Examples:

- `student_45_profile_20260625_a8f3`
- `trip_12_gallery_20260625_b91c`
- `expense_receipt_20260625_7d21`

Rules:

- Avoid original filenames in public IDs.
- Normalize names to lowercase ASCII.
- Use short random suffixes to prevent collisions.
- Keep original filename only in local metadata.

## Deleting Images

Deletion types:

- Remove relationship: image is removed from a student, trip, or album but remains stored.
- Archive asset: image is hidden from normal views but retained.
- Permanent delete: asset is deleted from Cloudinary and local metadata is marked as deleted.

Rules:

- Normal users should remove relationships, not permanently delete assets.
- Admins can permanently delete assets when no required historical reference exists.
- If an image is used in multiple places, deleting one relationship must not remove it from all contexts.
- If a cover image is deleted, the module should require a new cover or fall back to no cover.
- Deletion should be logged.

## Cover Image

Cover image rules:

- A student can have one primary profile image.
- A trip can have one cover image.
- An album can have one featured cover image.
- Cover image must be connected to the same record context.
- When a cover image is removed, the system should clear the cover reference or select the next featured image according to configured behavior.

## Gallery

Gallery rules:

- Gallery images can belong to albums.
- Images can be sorted.
- Images can have captions.
- Images can be marked as featured.
- Student galleries can include profile, portfolio, event, and document categories.
- Trip galleries can include cover, event, backstage, document, and marketing categories.
- Gallery views should use optimized thumbnails, not original full-size images.

## Security Considerations

- Validate files server-side before upload.
- Restrict allowed MIME types.
- Limit file size.
- Strip or ignore unsafe filenames.
- Do not store Cloudinary secrets in public files.
- Do not expose private credentials in error messages.
- Use signed uploads only if direct browser uploads are introduced.
- Avoid storing sensitive student data in public asset names or folders.
- Consider authenticated delivery for private documents.
- Log uploads and deletions.
- Periodically check for orphaned assets.
