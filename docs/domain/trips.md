# Trips Domain Model

## 1. Purpose

The Trips module manages modeling trips, camps, competitions, photo sessions, workshops, and external school events. It connects participants, payments, expenses, media, capacity, and profitability.

## 2. Business Rules

- Every trip must have a name and date range.
- End date cannot be earlier than start date.
- Capacity controls confirmed participants.
- Students can be invited, confirmed, cancelled, or marked as attended.
- Trip price can generate expected payments for participants.
- Trip expenses should be linked to the trip.
- Completed trips remain visible for reporting.
- Cancelled trips should stop new participant registrations.
- Trip photos should be connected to the trip gallery.

## 3. Fields

Trip fields:

- Trip ID.
- Name.
- Location.
- Start date.
- End date.
- Capacity.
- Price.
- Internal cost estimate.
- Status.
- Description.
- Cover image.
- Created date.
- Updated date.

Participant fields:

- Participant ID.
- Trip.
- Student.
- Status.
- Payment status.
- Registration date.
- Notes.

## 4. Validation Rules

- Trip name is required.
- Start date is required.
- End date must be valid and cannot be earlier than start date.
- Capacity cannot be negative.
- Price cannot be negative.
- Internal cost cannot be negative.
- Participant student must exist.
- A student cannot be added to the same trip twice.
- Confirmed participant count cannot exceed capacity unless admin override is used.
- Cancelled trips cannot accept new confirmed participants.

## 5. Relationships With Other Modules

- Students: trips have student participants.
- Payments: trip participation may generate expected payments.
- Expenses: trip costs are linked to the trip.
- Cloudinary: trip photos and cover images are stored as media.
- Reports: trip profitability and participation are reported.
- Settings: trip statuses and default payment categories are configured there.
- Users: staff manage trip records and participant lists.

## 6. Automatic Calculations

- Confirmed participant count.
- Remaining capacity.
- Expected trip revenue.
- Received trip payments.
- Outstanding trip balance.
- Total trip expenses.
- Estimated profit.
- Actual profit.
- Attendance count after trip completion.

## 7. Possible Future Extensions

- Trip itinerary.
- Transportation planning.
- Room assignments.
- Consent forms.
- Packing lists.
- Parent notifications.
- Public registration links.
- Trip document downloads.
- Staff assignments.
- Multi-day schedule.

## 8. Edge Cases

- Student cancels after payment.
- Student is added after capacity is full.
- Trip is cancelled after payments were recorded.
- Trip dates are changed after expected payments were created.
- Trip has expenses but no participants.
- Participant has partial payment.
- Cover image is deleted from Cloudinary.
- Trip is completed but attendance was not marked.

## 9. User Actions

- View trip list.
- Create trip.
- Edit trip details.
- Add participant.
- Change participant status.
- View payment status.
- Upload trip photos.
- Select cover image.
- View trip expenses.
- Export participant list.

## 10. Admin Actions

- Configure trip statuses.
- Override capacity.
- Cancel trip.
- Generate trip expected payments.
- Review trip profitability.
- Lock completed trip.
- Manage trip media.
- Restore archived trip.
