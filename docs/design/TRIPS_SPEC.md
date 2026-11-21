# Trips Module Specification

## Purpose

The Trips module manages school trips, photo sessions outside the studio, participant lists, trip costs and trip photo galleries. The module is connected to the existing MVC Lite architecture and uses the same PDO connection as the rest of the CRM.

## Architecture

The module is split into:

- `pages/trips.php` renders the page, handles POST/Redirect/GET actions and keeps SQL out of the view.
- `app/Services/TripService.php` contains business logic for trips, participants, gallery actions and linked expenses.
- `app/Services/CloudinaryService.php` uploads and deletes images through the signed Cloudinary API.
- `app/Repositories/TripRepository.php` reads and writes trip records.
- `app/Repositories/TripPhotoRepository.php` reads and writes photo metadata.
- `app/Validators/TripValidator.php` validates trip form input.

## Page Layout

The page follows the existing Dashboard, Payments and Expenses visual language:

- module hero with title `Wyjazdy`, subtitle and top actions;
- reusable filter bar for search, month and status;
- main card with trips table;
- right side summary cards for Cloudinary and cost integration;
- modal windows for adding, editing and managing trip details.

## Trips Table

The list includes:

- Date
- Name
- Location
- Participants
- Total cost
- Photos
- Status
- Actions

The total cost column uses linked expenses when they exist and falls back to the planned internal cost.

## Add and Edit Flow

The modal supports:

- name;
- start date;
- end date;
- location;
- description;
- participants;
- total cost;
- notes;
- status.

Validation requires a name and valid start date. Cost cannot be negative.

## Participants

Participants are selected from active students and stored in the normalized `trip_participants` table.

The relationship is many-to-many:

- one trip can contain many students;
- one student can participate in many trips;
- the composite primary key is `trip_id, student_id`;
- participant status can be `planned`, `confirmed`, or `cancelled`.

`trips.description` must never contain hidden system data. It is reserved only for user-visible trip description and optional notes entered through the form.

## Photo Gallery

Each trip has a gallery in the trip details modal. Supported actions:

- upload image;
- set image as cover;
- delete image;
- move image up or down;
- preview image by opening the secure Cloudinary URL.

The UI uses a responsive gallery grid and a drag-and-drop upload area. The database stores only metadata and Cloudinary URLs.

## Cloudinary Flow

Upload flow:

1. User selects or drops an image.
2. `pages/trips.php` sends the uploaded file to `TripService`.
3. `TripService` calls `CloudinaryService`.
4. `CloudinaryService` creates a signed request using `cloudinary.api_key`, `cloudinary.api_secret`, `cloudinary.cloud_name` and `cloudinary.root_folder`.
5. Cloudinary stores the image in the folder returned by `StorageService`:

```text
StorageService::tripGalleryFolder($tripId)
StorageService::tripCoverFolder($tripId)
```

6. `TripPhotoRepository` stores:

- public ID;
- secure URL;
- thumbnail URL;
- original URL;
- width;
- height;
- bytes as `file_size`;
- format;
- file name;
- cover flag;
- sort order;
- creation timestamp.

Delete flow:

1. User deletes a photo.
2. The image is deleted from Cloudinary by public ID.
3. The database metadata row is removed.
4. If the photo was the cover, the trip cover reference is cleared.

Cloudinary must be configured in `settings` before real uploads can work.

## Expense Integration

The trip details modal includes a cost form. When a trip cost is added:

1. `TripService` locates the active expense category with slug `wyjazdy`.
2. It creates an expense through `ExpenseService`.
3. The expense is linked through `expenses.trip_id`.
4. Dashboard totals and recent expenses update through the existing live Dashboard queries.

No duplicate expense logic is implemented inside the Trips page.

## Dashboard Preparation

`DashboardRepository` and `DashboardService` expose prepared trip metrics:

- trips this month;
- trip expenses this month.

The current Dashboard UI was not redesigned.

## Security

- All database access uses prepared statements.
- No image binary data is stored in MySQL.
- Cloudinary upload and delete requests are signed server-side.
- POST/Redirect/GET is used for write actions.
- Trip deletion is implemented as cancellation, not physical deletion.

## Responsive Behavior

The table remains horizontally scrollable in narrow layouts. The trip details modal becomes a single-column layout on mobile. Gallery cards wrap automatically based on available width.

## Future Improvements

- Add AJAX upload progress.
- Add in-page lightbox instead of opening images in a new tab.
- Add bulk photo reorder by drag and drop.
- Add automatic trip profitability report.
- Add participant payment tracking per trip.
