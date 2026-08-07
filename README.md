# FieldTrack - Clean Integrated Build

This package is a self-consistent FieldTrack build for XAMPP.

## Important fix

The approval workflow now uses one real processor:

- `process_weekly_review.php`

Both old and new filenames are supported:

- `process_review.php` -> compatibility wrapper
- `submission_details.php` -> compatibility wrapper
- `weekly_submission_details.php` -> real details page
- `submit_week.php` -> real submit processor
- `submit_weekly.php` -> compatibility wrapper

All important form actions and redirects use the configured `/FieldTrack` application path. This removes the mixed-filename / wrong-relative-path 404 problem that occurred after Approve or Reject.

## Expected XAMPP folder

Copy the project to:

`C:\xampp\htdocs\FieldTrack\`

Then open:

`http://localhost/FieldTrack/`

## Database

The PHP files match the normalized RBAC database schema in `database.sql`.

The four local demo accounts are:

- System Administrator: `admin` / `admin123`
- Field Officer: `officer` / `officer123`
- Admin Officer: `kamal` / `123`
- Admin Manager: `test` / `test123`

Passwords are intentionally plain text in this local coursework/demo database because that is how the existing project was configured. For a production application, use secure password hashing.

### If your existing database already has the RBAC tables

Do not delete it. First replace the PHP/CSS files and test.

You can run `verify_database.sql` in phpMyAdmin to inspect users, assignments, submissions, and approval history.

### If the database is badly mixed from older versions

Back up your current database first. Then import `database.sql` for a clean reset. `database.sql` drops and recreates `fieldtrack_db`, so it will remove existing FieldTrack data.

## Correct weekly workflow

1. Field Officer records IN / OUT attendance.
2. Field Officer submits a completed past week.
3. Admin Officer opens the submission.
4. Admin Officer:
   - Approve -> `pending_manager_review`
   - Reject -> `admin_officer_rejected`
5. If rejected, Field Officer sees the rejection reason and can resubmit that same week.
6. If approved by the Admin Officer, the Admin Manager can:
   - Final Approve -> `final_approved`
   - Final Reject -> `manager_rejected`
7. Rejected attendance records are unlocked; approved/submitted records remain locked.

## Files most important for Approve / Reject

- `admin_officer_panel.php`
- `admin_manager_panel.php`
- `weekly_submission_details.php`
- `process_weekly_review.php`
- `permissions.php`
- `weekly_helpers.php`
- `review_helpers.php`
- `auth.php`
- `db.php`

## Test sequence

1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/FieldTrack/`.
3. Login as `officer`.
4. Submit a completed week.
5. Logout.
6. Login as `kamal`.
7. Open the submitted week.
8. Enter a rejection reason and click **Admin Officer Reject**.
9. You should return to `admin_officer_panel.php` with a success message, not a 404 page.
10. Login as `officer`; the rejected week and reason should appear in Weekly Attendance Submission.
11. Resubmit it.
12. Login as `kamal` and approve it.
13. Login as `test` and perform Final Approve or Final Reject.

## Demo helper

`records_example.php` can create 10 sample attendance records for a past Monday-Friday week. It is available only to the System Administrator.

`dev_test_status.php` is a System Administrator demo/debug helper. Normal workflow testing should use the real approval buttons.
