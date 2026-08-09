FieldTrack

A web-based attendance and field visit tracking system built with PHP, MySQL, HTML, CSS, JavaScript, Leaflet.js, and OpenStreetMap.

FieldTrack allows field officers to record IN/OUT attendance with their current GPS location, submit completed weekly attendance for approval, and follow a multi-level review workflow involving an Admin Officer and Admin Manager. A System Administrator manages users, roles, permissions, assignments, and audit information.

Features

Field Officer

Secure role-based login

Mark IN and OUT attendance

Automatically capture the officer's current GPS location

No manual location selection is required

No photo upload is required

Enforce the attendance sequence:

IN → OUT → IN → OUT

Prevent duplicate consecutive IN or OUT actions

View recent attendance records

View latitude, longitude, date, and time

View today's recorded route on a map

View weekly attendance status

Submit completed weeks for approval

View rejection reasons

Correct and resubmit rejected weeks

View final approval status

Admin Officer

View weekly submissions assigned to the Admin Officer

View attendance records linked to a weekly submission

Review submitted or resubmitted weeks

Approve a weekly submission for Manager review

Reject a submission with a mandatory reason

View approval history

Track pending and previously reviewed submissions

Admin Manager

View submissions forwarded by Admin Officers

Perform final review

Final approve a weekly submission

Final reject a submission with a reason

View approval history and submission details

System Administrator

Manage users

Manage roles

Manage permissions

Manage Field Officer → Admin Officer → Admin Manager assignments

View attendance records

View administrative dashboards

View audit logs

Monitor system activity

User Roles

Role

Main Responsibility

field_officer

Marks attendance and submits weekly attendance

admin_officer

Performs first-level weekly review

admin_manager

Performs final weekly review

system_admin

Manages the system, users, roles, permissions, and assignments

Attendance Workflow

Field Officer
     |
     v
Click Mark IN
     |
     v
Browser requests current GPS location
     |
     v
Latitude + Longitude captured automatically
     |
     v
IN attendance saved
     |
     v
Click Mark OUT
     |
     v
Current GPS location captured again
     |
     v
OUT attendance saved

Attendance Rules

The first attendance action must be IN.

An IN cannot be followed by another IN.

An OUT cannot be followed by another OUT.

Attendance location is captured automatically when the button is clicked.

Latitude must be between -90 and 90.

Longitude must be between -180 and 180.

Location permission must be allowed in the browser.

Submitted/locked weeks cannot be edited unless returned for correction.

Weekly Approval Workflow

Field Officer
     |
     v
Submit completed week
     |
     v
SUBMITTED
     |
     v
Admin Officer Review
   /             \
Reject           Approve
  |                |
  v                v
Admin Officer   Pending Manager
Rejected        Review
  |                |
  v                v
Field Officer   Admin Manager
Corrects       Final Review
  |             /        \
  v         Reject       Approve
Resubmit        |           |
  |             v           v
  +------> Correction   FINAL APPROVED

Weekly Status Values

FieldTrack uses statuses such as:

draft

submitted

admin_officer_approved

admin_officer_rejected

pending_manager_review

manager_rejected

returned_for_correction

resubmitted

final_approved

Technology Stack

Frontend

HTML5

CSS3

JavaScript

Backend

PHP 8+

MySQL / MariaDB

Maps and Location

Browser Geolocation API

Leaflet.js

OpenStreetMap

Development Environment

XAMPP

Apache

phpMyAdmin

Version Control

Git

GitHub

Project Structure

FieldTrack/
│
├── index.php
├── login.php
├── login_process.php
├── login_failed.php
├── logout.php
├── login_style.css
│
├── session_config.php
├── auth.php
├── permissions.php
├── db.php
│
├── user_panel.php
├── user_panel.css
├── mark_attendance.php
├── attendance_details.php
│
├── submit_week.php
├── submit_weekly.php
├── resubmit_week.php
├── weekly_helpers.php
│
├── admin_officer_panel.php
├── admin_manager_panel.php
├── admin_weekly_submissions.php
├── weekly_submission_details.php
├── process_weekly_review.php
├── review_helpers.php
├── review_panel.css
│
├── admin_panel.php
├── admin_style.css
├── manage_users.php
├── manage_roles.php
├── manage_assignments.php
├── audit_logs.php
│
├── submission_details.php
├── process_review.php
│
├── records_example.php
├── dev_test_status.php
│
├── database.sql
├── verify_database.sql
│
└── README.md

Some compatibility files may exist to support links used by older project versions.

Database

The application expects a MySQL database named:

fieldtrack_db

Main Tables

users

Stores user accounts.

Typical fields include:

id

name

username

password

is_active

created_at

updated_at

roles

Stores system roles.

user_roles

Links users with roles.

permissions

Stores available system permissions.

Examples:

attendance.mark_in

attendance.mark_out

attendance.view_own

weekly.submit

weekly.view_own

weekly.review_assigned

weekly.approve_level1

weekly.reject_level1

weekly.approve_final

weekly.reject_final

users.manage

roles.manage

assignments.manage

audit.view

role_permissions

Links permissions to roles.

attendance_events

Stores attendance records.

Typical fields:

id

user_id

action_type

latitude

longitude

is_locked

created_at

updated_at

The current FieldTrack workflow does not require attendance photos.

officer_assignments

Defines the approval hierarchy.

Links:

Field Officer

Admin Officer

Admin Manager

weekly_submissions

Stores weekly submissions and their current approval status.

weekly_submission_records

Links attendance events to weekly submissions.

approval_history

Stores the approval/rejection history.

Typical information includes:

Submission ID

Reviewer ID

Reviewer role

Decision

Previous status

New status

Rejection reason

Comment

IP address

Timestamp

audit_logs

Stores important system activity for traceability.

Installation

Requirements

Install:

XAMPP

PHP 8.2 or later

MySQL / MariaDB

A modern browser such as Chrome, Edge, or Firefox

1. Copy the Project

Place the project inside:

C:\xampp\htdocs\FieldTrack\

The folder should be named exactly:

FieldTrack

2. Start XAMPP

Start:

Apache

MySQL

3. Open phpMyAdmin

Open:

http://localhost/phpmyadmin/

4. Prepare the Database

Create/import the database as required.

The expected database name is:

fieldtrack_db

5. Check Database Connection

Typical local XAMPP configuration:

$host = "localhost";
$username = "root";
$password = "";
$database = "fieldtrack_db";

6. Run the Application

Open:

http://localhost/FieldTrack/

Local Demo Accounts

These accounts may be used for local coursework/demo testing if they exist in the database.

Role

Username

Password

System Administrator

admin

admin123

Field Officer

officer

officer123

Admin Officer

kamal

123

Admin Manager

test

test123

Demo credentials should not be displayed on the public login screen and should not be used in a production environment.

How to Test

Test 1 — Login

Open:

http://localhost/FieldTrack/

Login using each account and verify that the user is redirected to the correct dashboard.

Test 2 — Field Officer Attendance

Login as the Field Officer.

Click Mark IN.

Allow browser location access.

Confirm the system captures the current GPS position automatically.

Confirm the IN record appears in the attendance history.

Click Mark OUT.

Confirm the location is captured again.

Confirm the OUT record appears.

Check today's route map.

Expected sequence:

IN → OUT → IN → OUT

Test 3 — Weekly Submission

Use a completed previous week containing attendance records.

Login as Field Officer.

Open the Weekly Attendance section.

Click Submit Week.

Confirm the status changes to Submitted.

Test 4 — Admin Officer Reject

Login as Admin Officer.

Open a submitted week.

Enter a rejection reason.

Click Admin Officer Reject.

Confirm the application returns to the Admin Officer dashboard.

Confirm the submission is no longer pending review.

Login as the Field Officer.

Confirm the rejection reason is displayed.

Test 5 — Field Officer Resubmit

Login as Field Officer.

Open the rejected week.

Review the rejection reason.

Correct the attendance if required.

Click Resubmit Week.

Confirm the status becomes Resubmitted.

Test 6 — Admin Officer Approve

Login as Admin Officer.

Open the resubmitted week.

Click Admin Officer Approve.

Confirm the submission moves to Pending Manager Review.

Test 7 — Admin Manager Final Review

Login as Admin Manager.

Open the pending submission.

Choose Final Approve or Final Reject.

Confirm the correct final status is shown.

A successful final approval should result in:

final_approved

Test 8 — System Administrator

Login as System Administrator and test:

User management

Role management

Permission management

Officer assignments

Attendance monitoring

Audit logs

Dashboard information

Validation and Business Rules

Login

Username is required.

Password is required.

User must exist.

User must be active.

User must have a valid role.

Attendance

Only Field Officers can mark attendance.

Action must be IN or OUT.

Current location is required.

Latitude and longitude are validated.

First action must be IN.

IN cannot follow IN.

OUT cannot follow OUT.

Weekly Submission

Only Field Officers can submit their attendance.

The week must already be completed.

Attendance records must exist.

The officer must have an approval assignment.

A new weekly submission cannot be duplicated.

Submitted attendance records can be locked.

Review

Only the assigned Admin Officer can perform the first review.

Admin Officer rejection requires a reason.

Only the assigned Admin Manager can perform the final review.

Final rejection requires a reason.

A reviewer should not approve their own submission.

Approval and rejection actions are recorded in history.

Browser Location Permission

FieldTrack uses the browser's Geolocation API.

When Mark IN or Mark OUT is clicked, the browser may ask:

Allow localhost to know your location?

Choose:

Allow

If location permission is denied, attendance cannot be recorded because latitude and longitude are required.

Chrome Location Settings

If location access was previously blocked:

Open FieldTrack.

Click the icon beside the address bar.

Open Site settings.

Find Location.

Change it to Allow.

Refresh the page.

Troubleshooting

404 Not Found

Confirm the project exists at:

C:\xampp\htdocs\FieldTrack\

and open it using:

http://localhost/FieldTrack/

Also confirm that the PHP filename used by the form or redirect actually exists.

Database Connection Error

Check:

MySQL is running.

Database name is fieldtrack_db.

db.php contains the correct connection settings.

XAMPP MySQL is using the expected port.

Unknown Column Error

Example:

Unknown column 'column_name' in 'field list'

This means the PHP code and database schema are from different versions.

Check the table structure in phpMyAdmin and make sure it matches the current PHP code.

Location Is Not Captured

Check:

Browser location permission is allowed.

Location services are enabled on the device.

The browser supports Geolocation.

The request has not timed out.

Refresh the page and try again.

Field Officer Cannot Submit a Week

Check:

The week has ended.

Attendance records exist.

The officer is assigned to an Admin Officer and Admin Manager.

The week has not already been submitted as a new submission.

Admin Officer Cannot See a Submission

Check:

The submission is assigned to that Admin Officer.

The user has the admin_officer role.

The correct review permissions exist.

The status is submitted or resubmitted.

Admin Manager Cannot See a Submission

Check:

The Admin Officer has already approved it.

The manager assignment is correct.

The submission is in a Manager-review status.

Approve / Reject Gives 404

Confirm that these files exist:

process_weekly_review.php
weekly_submission_details.php
admin_officer_panel.php
admin_manager_panel.php

Use project-relative or /FieldTrack/... paths consistently.

Security Notes

This project is designed primarily for local coursework and demonstration.

For a real production deployment, improve security by:

Storing passwords with password_hash()

Verifying passwords with password_verify()

Using HTTPS

Adding CSRF protection to forms

Using secure session-cookie settings

Restricting development/demo pages

Removing demo accounts

Adding login rate limiting

Validating all server-side input

Applying least-privilege database permissions

Protecting sensitive audit information

Future Improvements

Possible improvements include:

Mobile-friendly Progressive Web App support

Push notifications for approvals/rejections

Email notifications

Attendance reports and exports

Monthly attendance summaries

Dashboard charts

Geo-fencing

Configurable working hours

Leave/holiday integration

Offline attendance synchronization

Stronger authentication

Password reset workflow

Enhanced audit reporting

Project Summary

FieldTrack provides a complete attendance workflow:

Login
  ↓
Automatic GPS Attendance
  ↓
IN / OUT Tracking
  ↓
Weekly Submission
  ↓
Admin Officer Review
  ↓
Admin Manager Final Review
  ↓
Final Approval

The system combines attendance tracking, GPS location recording, role-based access control, multi-level approval, and audit history in one web application.

License

This project is intended for educationa