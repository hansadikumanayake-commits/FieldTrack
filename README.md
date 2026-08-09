<div align="center">

📍 FieldTrack

Smart Attendance & Field Visit Tracking System

A role-based web application for recording IN/OUT attendance with automatic GPS location, submitting weekly attendance, and managing a multi-level approval workflow.

<br>

## 👩‍💻 Author

**Hansadi Kumanayake**

### Main Contributions
- Developed the login and role-based access system
- Implemented the Field Officer attendance workflow
- Added automatic GPS location capture for IN/OUT attendance
- Developed the administrator dashboard
- Implemented weekly attendance submission
- Implemented Admin Officer approval/rejection workflow
- Implemented Admin Manager final approval workflow
- Added user, role, permission, and officer assignment management
- Integrated Leaflet.js and OpenStreetMap
- Worked on database integration and backend functionality

</div>

📖 Table of Contents

About the Project

Key Features

User Roles

Attendance Workflow

Weekly Approval Workflow

Technology Stack

System Architecture

Project Structure

Database Overview

Installation

Demo Accounts

How to Test

Validation Rules

Location Tracking

Troubleshooting

Security Notes

Future Improvements

Project Summary

Author

🧭 About the Project

FieldTrack is a web-based attendance and field visit tracking system developed for managing the daily activities of field officers.

The system allows a Field Officer to:

mark IN and OUT attendance,

automatically capture the current GPS location,

view recent attendance records,

view the daily movement route on a map,

submit completed weekly attendance,

receive rejection reasons,

correct and resubmit attendance,

and track final approval status.

The approval process is handled in two levels:

Admin Officer Review

Admin Manager Final Review

A System Administrator manages users, roles, permissions, officer assignments, and audit information.

✨ Key Features

👤 Field Officer

Role-based login

Mark IN attendance

Mark OUT attendance

Automatic GPS location capture

No manual location selection required

No photo upload required

Enforced IN → OUT → IN → OUT sequence

View attendance date and time

View latitude and longitude

View recent attendance history

View today's route on an interactive map

Submit completed weekly attendance

View current weekly submission status

View rejection reasons

Correct and resubmit rejected weeks

View final approval status

🧑‍💼 Admin Officer

View assigned Field Officers

View pending weekly submissions

Open weekly submission details

Review linked attendance records

Approve a weekly submission

Reject a weekly submission

Enter a mandatory rejection reason

View resubmitted attendance

View approval history

Forward approved submissions to the Admin Manager

👨‍💼 Admin Manager

View submissions forwarded by Admin Officers

Review submission details

Perform final approval

Perform final rejection

Enter a rejection reason

View approval history

Confirm the final status of weekly attendance

⚙️ System Administrator

Manage user accounts

Activate/deactivate users

Manage roles

Manage permissions

Manage role-permission assignments

Manage Field Officer assignments

Link Field Officers to Admin Officers

Link Field Officers to Admin Managers

View attendance information

View audit logs

Monitor system activity

👥 User Roles

Role

Main Responsibility

field_officer

Marks IN/OUT attendance and submits weekly attendance

admin_officer

Performs first-level review and approval/rejection

admin_manager

Performs final approval/rejection

system_admin

Manages users, roles, permissions, assignments, and audit information

📍 Attendance Workflow

Field Officer
     │
     ▼
Click "Mark IN"
     │
     ▼
Browser requests current GPS location
     │
     ▼
Latitude + Longitude captured automatically
     │
     ▼
IN attendance saved
     │
     ▼
Field Officer completes field work
     │
     ▼
Click "Mark OUT"
     │
     ▼
Current GPS location captured again
     │
     ▼
OUT attendance saved

Attendance Sequence

IN → OUT → IN → OUT

The system prevents invalid actions such as:

IN → IN     ❌
OUT → OUT   ❌
OUT first   ❌

🔄 Weekly Approval Workflow

                    FIELD OFFICER
                          │
                          ▼
               Submit Weekly Attendance
                          │
                          ▼
                      SUBMITTED
                          │
                          ▼
                    ADMIN OFFICER
                     /         \
                    /           \
               Reject           Approve
                 │                 │
                 ▼                 ▼
       Admin Officer Rejected   Pending Manager Review
                 │                 │
                 ▼                 ▼
        Field Officer Corrects  ADMIN MANAGER
                 │               /       \
                 ▼              /         \
             Resubmit       Reject       Approve
                 │            │             │
                 └───────► Correction      ▼
                                       FINAL APPROVED

📌 Weekly Submission Statuses

Status

Meaning

draft

Week has not yet been submitted

submitted

Waiting for Admin Officer review

admin_officer_approved

Approved at first level

admin_officer_rejected

Rejected by Admin Officer

pending_manager_review

Waiting for Admin Manager

manager_rejected

Rejected by Admin Manager

returned_for_correction

Returned to Field Officer

resubmitted

Corrected and submitted again

final_approved

Fully approved

🛠 Technology Stack

Technology

Purpose

PHP 8.2+

Backend logic and server-side processing

MySQL / MariaDB

Relational database

HTML5

Web page structure

CSS3

Styling and responsive layout

JavaScript

Client-side interaction

Browser Geolocation API

Automatic current-location capture

Leaflet.js

Interactive map rendering

OpenStreetMap

Map tiles and location display

Apache

Local web server

XAMPP

Local development environment

Git

Version control

GitHub

Source code hosting

🏗 System Architecture

FieldTrack follows a simple web application architecture:

┌─────────────────────────────┐
│          Browser            │
│ HTML + CSS + JavaScript     │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│        Apache / PHP         │
│ Authentication             │
│ Attendance Logic           │
│ Weekly Approval Workflow   │
│ Role & Permission Checks   │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│       MySQL Database        │
│ Users                       │
│ Roles                       │
│ Attendance                  │
│ Weekly Submissions          │
│ Approval History            │
│ Audit Logs                  │
└─────────────────────────────┘

📁 Project Structure

FieldTrack/
│
├── index.php
│
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

🗄 Database Overview

FieldTrack uses:

fieldtrack_db

Main Tables

users

Stores user account information.

Typical fields:

id
name
username
password
is_active
created_at
updated_at

roles

Stores application roles.

field_officer
admin_officer
admin_manager
system_admin

user_roles

Links users with their assigned roles.

permissions

Stores individual system permissions.

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

Links roles with the permissions they are allowed to use.

attendance_events

Stores Field Officer attendance records.

Typical information:

id
user_id
action_type
latitude
longitude
is_locked
created_at
updated_at

The current FieldTrack attendance workflow does not require photo uploads.

officer_assignments

Defines the approval hierarchy:

Field Officer
      │
      ▼
Admin Officer
      │
      ▼
Admin Manager

weekly_submissions

Stores weekly attendance submissions and their current workflow status.

weekly_submission_records

Links attendance records with a weekly submission.

approval_history

Stores the complete review history.

Typical information:

submission_id
reviewer_id
reviewer_role
decision
previous_status
new_status
reason
comment
ip_address
created_at

audit_logs

Stores important system actions for accountability and traceability.

💻 Installation

Requirements

Before running FieldTrack, install:

XAMPP

PHP 8.2+

MySQL / MariaDB

Chrome, Edge, or Firefox

Git (optional)

Step 1 — Copy the Project

Place the FieldTrack folder inside:

C:\xampp\htdocs\

The final location should be:

C:\xampp\htdocs\FieldTrack\

Step 2 — Start XAMPP

Open XAMPP Control Panel and start:

Apache
MySQL

Both services should be running.

Step 3 — Open phpMyAdmin

Open:

http://localhost/phpmyadmin/

Step 4 — Prepare the Database

The expected database is:

fieldtrack_db

Import the appropriate FieldTrack SQL file if required.

Step 5 — Check db.php

Typical local XAMPP settings are:

$host = "localhost";
$username = "root";
$password = "";
$database = "fieldtrack_db";

Step 6 — Open FieldTrack

Open:

http://localhost/FieldTrack/

🔑 Demo Accounts

For local coursework/demo testing:

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

These credentials are intended only for local development and demonstration. They should not be displayed on the public login page or used in production.

🧪 How to Test

1. Test Field Officer Login

Login using:

officer / officer123

Expected result:

Field Officer Dashboard

2. Test Mark IN

Click Mark IN.

Allow browser location access.

Wait for the current GPS location to be captured.

Confirm the attendance record is saved.

Expected result:

Action: IN
Latitude: Captured
Longitude: Captured
Date/Time: Saved automatically

3. Test Mark OUT

After an IN record:

Click Mark OUT.

Allow the browser to capture the current location.

Confirm the OUT record is saved.

Expected sequence:

IN → OUT

4. Test Weekly Submission

Use a completed previous week.

Field Officer
     ↓
Weekly Attendance Submission
     ↓
Submit Week
     ↓
Submitted

5. Test Admin Officer Reject

Login:

kamal / 123

Then:

Open Submission
      ↓
Enter Rejection Reason
      ↓
Admin Officer Reject

Expected result:

Submission becomes rejected

Rejection reason is stored

Field Officer can see the reason

Field Officer can resubmit

6. Test Field Officer Resubmission

Login again as the Field Officer.

Rejected Week
      ↓
Read Rejection Reason
      ↓
Correct Attendance
      ↓
Resubmit Week
      ↓
Resubmitted

7. Test Admin Officer Approval

Login as:

kamal / 123

Open the resubmitted week and choose:

Admin Officer Approve

Expected result:

Pending Manager Review

8. Test Admin Manager Final Review

Login:

test / test123

Open the pending submission.

Choose:

Final Approve

or:

Final Reject

Successful final approval results in:

FINAL APPROVED

9. Test System Administrator

Login:

admin / admin123

Test:

Manage Users

Manage Roles

Manage Permissions

Manage Assignments

View Attendance

View Audit Logs

View System Information

✅ Validation Rules

Login Validation

Username is required

Password is required

User must exist

Account must be active

User must have a valid role

Attendance Validation

Only Field Officers can mark attendance

Action must be IN or OUT

Latitude is required

Longitude is required

Latitude must be between -90 and 90

Longitude must be between -180 and 180

First action must be IN

IN cannot follow IN

OUT cannot follow OUT

Weekly Submission Validation

Only Field Officers can submit attendance

Week must be completed

Attendance records must exist

Officer assignment must exist

Duplicate weekly submissions are prevented

Submitted attendance can be locked

Review Validation

Only authorized reviewers can review submissions

Admin Officer must be assigned to the Field Officer

Admin Manager must be assigned to the Field Officer

Rejection reason is required for rejection

Approval/rejection status must be valid

Review actions are recorded in approval history

🌍 Location Tracking

FieldTrack uses the browser Geolocation API.

When the user clicks:

Mark IN

or:

Mark OUT

the system requests the current device location automatically.

The user does not need to:

search for a location,

select a location manually,

click on a map,

or upload a photo.

The attendance record stores:

Action Type
Latitude
Longitude
Date
Time

Allowing Location in Chrome

If Chrome asks:

Allow localhost to know your location?

select:

Allow

If location was previously blocked:

Browser Address Bar
       ↓
Site Settings
       ↓
Location
       ↓
Allow
       ↓
Refresh FieldTrack

🛠 Troubleshooting

404 Not Found

Confirm the project exists at:

C:\xampp\htdocs\FieldTrack\

and open:

http://localhost/FieldTrack/

Also confirm the PHP file used in the link, form action, or redirect actually exists.

Database Connection Error

Check:

MySQL is running

Database is named fieldtrack_db

db.php contains correct settings

MySQL port is correct

Unknown Column Error

Example:

Unknown column 'example_column' in 'field list'

This usually means the database schema and PHP files are from different versions.

Check the table structure in phpMyAdmin and make sure it matches the PHP code.

Location Not Captured

Check:

Browser location permission is enabled

Device location services are enabled

Browser supports Geolocation

Location request did not time out

Refresh the page and try again

Cannot Submit Weekly Attendance

Check:

Week has ended

Attendance records exist

Officer assignment exists

Week has not already been submitted

Admin Officer Cannot See Submission

Check:

Correct Admin Officer is assigned

User role is admin_officer

Submission status is submitted or resubmitted

Required permissions are assigned

Admin Manager Cannot See Submission

Check:

Admin Officer has already approved it

Admin Manager assignment is correct

Submission is in Manager review status

Approve / Reject Gives 404

Confirm these files exist:

process_weekly_review.php
weekly_submission_details.php
admin_officer_panel.php
admin_manager_panel.php

Use consistent paths such as:

/FieldTrack/process_weekly_review.php

🔐 Security Notes

The current project is intended mainly for educational and local demonstration purposes.

For production deployment, the following improvements are recommended:

Use password_hash() for password storage

Use password_verify() for login

Enable HTTPS

Add CSRF protection

Add login rate limiting

Use secure session cookies

Remove demo accounts

Remove development/debug pages

Validate all inputs server-side

Use least-privilege database permissions

Protect audit information

Use environment variables for credentials

🚀 Future Improvements

Possible future enhancements:

Email notifications

Approval notifications

Push notifications

Monthly attendance reports

CSV/PDF export

Dashboard charts

Geo-fencing

Working-hour rules

Leave management

Holiday calendar

Offline attendance synchronization

Password reset

Two-factor authentication

Progressive Web App support

Advanced audit reporting

🎯 Project Summary

FieldTrack provides the following complete workflow:

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

The project combines:

📍 GPS attendance tracking

👥 Role-based access control

🗓 Weekly attendance submission

✅ Multi-level approval

🗺 Route visualization

🧾 Approval history

🔐 Permission management

📊 Administrative monitoring

👩‍💻 Author

Hansadi Kumanayake

Software Engineering Undergraduate Project

<div align="center">

⭐ FieldTrack

Smart Attendance • GPS Tracking • Weekly Approval • Role-Based Access

</div>