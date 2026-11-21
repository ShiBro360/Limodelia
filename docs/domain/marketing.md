# Marketing Domain Model

## 1. Purpose

The Marketing module tracks leads, inquiry sources, contact progress, trial lessons, conversion to students, and campaign performance. It helps the school understand where new students come from and which channels produce revenue.

## 2. Business Rules

- Every lead must have a name and at least one contact method.
- Every lead should have a status.
- Lead source should be selected when known.
- A lead can be assigned to a staff member.
- Converted leads should link to a student profile.
- A lead should not be converted more than once.
- Lost leads should include a reason when possible.
- Contact history should be preserved.
- Marketing reports should distinguish lead count from converted student count.

## 3. Fields

Lead fields:

- Lead ID.
- Name.
- Phone.
- Email.
- Source.
- Status.
- Assigned user.
- Notes.
- Lost reason.
- Converted student.
- Created date.
- Updated date.

Source fields:

- Source ID.
- Name.
- Slug.
- Active state.

Campaign fields for future use:

- Campaign ID.
- Name.
- Source.
- Start date.
- End date.
- Budget.
- Status.

## 4. Validation Rules

- Lead name is required.
- Phone or email is required.
- Email must use valid email format.
- Source must exist when selected.
- Assigned user must exist when selected.
- Converted status requires converted student.
- Lost status should include lost reason.
- A converted lead cannot be converted again.
- Campaign date range must be valid.
- Campaign budget cannot be negative.

## 5. Relationships With Other Modules

- Students: converted leads become students.
- Users: leads can be assigned to staff.
- Reports: marketing data contributes to conversion and acquisition reports.
- Payments: converted student payments measure source quality and return.
- Settings: lead statuses and sources are configured there.

## 6. Automatic Calculations

- Leads by source.
- Leads by status.
- Conversion rate.
- Time from lead creation to conversion.
- Revenue from converted leads.
- Cost per lead for campaigns.
- Cost per converted student.
- Lost lead percentage.

## 7. Possible Future Extensions

- Campaign management.
- Contact timeline.
- Call reminders.
- Trial lesson scheduling.
- Automated follow-up tasks.
- Import leads from forms.
- UTM tracking.
- Source ROI reporting.
- Lead scoring.
- Duplicate lead detection.

## 8. Edge Cases

- Same person submits multiple inquiries.
- Lead has only social media contact.
- Lead is converted but student creation fails.
- Converted student later becomes inactive.
- Source is disabled after historical leads exist.
- Assigned user leaves the school.
- Lead asks to delete personal data.
- Campaign has spend but no leads.

## 9. User Actions

- View lead list.
- Filter leads by source, status, date, or assignee.
- Create lead.
- Edit lead.
- Add contact note.
- Assign lead.
- Change lead status.
- Convert lead to student.
- Mark lead as lost.

## 10. Admin Actions

- Configure lead sources.
- Configure lead statuses.
- Merge duplicate leads.
- Reassign leads in bulk.
- Export marketing data.
- Review conversion performance.
- Configure campaign tracking.
