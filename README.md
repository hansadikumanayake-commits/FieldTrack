📍 FieldTrack



FieldTrack is a web-based field attendance and visit-tracking system developed using PHP, MySQL, HTML, CSS, JavaScript, Leaflet.js, and OpenStreetMap.

The system allows field officers to record IN / OUT attendance with date, time, geographical location, and optional photo evidence. It also provides a multi-level weekly attendance approval workflow where an Admin Officer reviews submitted attendance first and an Admin Manager performs the final review.

The project is designed to run locally using XAMPP.

📑 Table of Contents

Project Overview

Main Objectives

Technologies Used

System Roles

Role Hierarchy

Main Features

Attendance Workflow

Weekly Submission Workflow

Weekly Submission Statuses

Installation

Database Setup

Demo Accounts

How to Test the System

Project File Structure

Database Tables

Validation and Business Rules

Security and Access Control

Maps and Location

Photo Evidence

Approval and Audit Trail

Compatibility Files

Demo and Debug Utilities

Troubleshooting

Limitations

Future Improvements

Project Demonstration Flow

Author / Contribution

📌 Project Overview

FieldTrack was created to improve the process of monitoring field officers and recording field attendance.

Instead of depending only on manually written attendance records, the system stores attendance electronically together with:

Officer identity

IN / OUT action

Date and time

Latitude

Longitude

Optional photo evidence

Weekly submission status

Approval / rejection history

The system also uses role-based access so that each user sees only the functions required for their role.

🎯 Main Objectives

The main objectives of FieldTrack are to:

Digitize field attendance recording.

Record the exact time of each IN and OUT action.

Capture the geographical location of attendance.

Support optional photographic evidence.

Prevent invalid IN / OUT sequences.

Allow field officers to review their own attendance history.

Allow weekly attendance to be submitted for approval.

Support two-level weekly attendance approval.

Allow rejected submissions to be corrected and resubmitted.

Give administrators an overall attendance monitoring dashboard.

Provide map-based visualization of attendance locations.

Maintain approval history and audit records.

Apply role-based access control throughout the system.

🛠 Technologies Used

Frontend

HTML5

CSS3

JavaScript

Backend

PHP 8.2+

Database

MySQL / MariaDB

Mapping

Leaflet.js

OpenStreetMap

Nominatim place search

Browser Geolocation API

Development Environment

XAMPP

Apache

phpMyAdmin

Visual Studio Code

Version Control

Git

GitHub

👥 System Roles

FieldTrack contains four main roles.

1. Field Officer

The Field Officer performs daily field attendance activities.

Main functions:

Login to the Field Officer dashboard.

Select attendance location.

Use current GPS location.

Search for a location.

Select a location directly from the map.

Mark IN attendance.

Mark OUT attendance.

Upload optional photo evidence.

Preview a selected photo.

View personal attendance history.

View today's attendance route on a map.

View weekly attendance status.

Submit a completed past week.

View rejection reasons.

Resubmit rejected weekly attendance.

2. Admin Officer

The Admin Officer performs the first level of weekly attendance review.

Main functions:

View Field Officers assigned to the Admin Officer.

View weekly attendance submissions.

Open a submitted week's details.

Inspect all attendance records linked to the submission.

Approve the submission at Level 1.

Reject the submission with a mandatory reason.

View previously reviewed submissions.

3. Admin Manager

The Admin Manager performs the final level of weekly attendance review.

Main functions:

View submissions that have passed Admin Officer review.

Open weekly submission details.

Inspect attendance records and review history.

Final approve a submission.

Final reject a submission with a reason.

View audit logs.

4. System Administrator

The System Administrator manages the overall system configuration and attendance monitoring functions.

Main functions:

View the overall attendance dashboard.

View field officers and attendance records.

Filter attendance information.

View all filtered attendance locations on a map.

Open individual attendance details.

Manage users.

Manage roles and permissions.

Manage officer reporting assignments.

View audit logs.

Use demo / testing utilities.

🏢 Role Hierarchy

The intended hierarchy is:

System Administrator

↓

Admin Manager

↓

Admin Officer

↓

Field Officer

For the supplied local demo data, the reporting relationship is:

Field Officer (officer) → Kamal Perera (kamal) → Admin Manager (test)

✨ Main Features

Authentication

Username and password login.

Only active accounts can log in.

Role-based dashboard redirection.

Session-based authentication.

Session timeout support.

Logout functionality.

Attendance Recording

Mark IN attendance.

Mark OUT attendance.

Automatic server-side date and time recording.

Latitude and longitude storage.

Optional photo upload.

Personal attendance history.

Individual attendance details.

IN / OUT Sequence Validation

FieldTrack enforces the sequence:

IN → OUT → IN → OUT

The system prevents:

Starting with OUT.

Repeated IN without OUT.

Repeated OUT without IN.

Location Selection

The Field Officer can select a location using:

Current browser GPS location.

Place search.

Manual selection by clicking the map.

Photo Evidence

Supported image extensions:

JPG

JPEG

PNG

WEBP

JFIF

Maximum upload size:

5 MB

System Administrator Attendance Dashboard

The administrator can:

View attendance records.

Filter by officer.

Filter by attendance action.

Filter by date.

Filter by time.

View locations on a shared Leaflet map.

View photo evidence.

Open detailed attendance records.

Weekly Attendance Approval

The system supports:

Weekly submission.

Admin Officer review.

Admin Manager final review.

Rejection reasons.

Resubmission.

Locked attendance records while under approval.

Unlocking records when a submission is rejected.

Approval history.

Audit logs.

🔄 Attendance Workflow

The normal attendance workflow is:

Field Officer Login

↓

Select Location

↓

Upload Photo (Optional)

↓

Mark IN

↓

Perform Field Activity

↓

Select / Confirm Location

↓

Mark OUT

↓

Attendance Saved in Database

The next available action is automatically controlled by the previous attendance action.

✅ Weekly Submission Workflow

The complete weekly workflow is:

Step 1 — Field Officer

The Field Officer records attendance during the week.

Step 2 — Submit Week

After the selected week has finished, the Field Officer submits it.

Status becomes:

Submitted

The attendance records linked to the submission are locked.

Step 3 — Admin Officer Review

The assigned Admin Officer opens the submission.

The Admin Officer can:

Approve

Reject

If Approved

Status becomes:

Pending Manager Review

The submission is forwarded to the assigned Admin Manager.

If Rejected

Status becomes:

Admin Officer Rejected

The rejection reason is saved and the related attendance records are unlocked for correction.

Step 4 — Resubmission

The Field Officer sees the rejection reason and can resubmit the rejected week.

Status becomes:

Resubmitted

The submission returns to the Admin Officer.

Step 5 — Admin Manager Review

After Admin Officer approval, the Admin Manager performs the final review.

The Admin Manager can:

Final Approve

Final Reject

Final Approve

Status becomes:

Final Approved

Final Reject

Status becomes:

Manager Rejected

The rejection reason is stored and the Field Officer can correct and resubmit the attendance again.

🏷 Weekly Submission Statuses

The system uses the following weekly submission statuses:

Status

Meaning

draft

Week has not yet entered approval workflow

submitted

Field Officer submitted the week

admin_officer_approved

Approved at Admin Officer level

admin_officer_rejected

Rejected by Admin Officer

pending_manager_review

Waiting for Admin Manager review

manager_rejected

Rejected by Admin Manager

returned_for_correction

Returned to officer for correction

resubmitted

Rejected submission submitted again

final_approved

Final approval completed

💻 Installation

Requirements

Install:

XAMPP

PHP 8.2 or later

MySQL / MariaDB

A modern browser such as Chrome, Edge, or Firefox

Step 1 — Copy the Project

Place the project folder inside:

C:\xampp\htdocs\FieldTrack\

The folder name should be exactly:

FieldTrack

This is important because the configured application base path is:

/FieldTrack

Step 2 — Start XAMPP

Open XAMPP Control Panel and start:

Apache

MySQL

Step 3 — Open phpMyAdmin

Open:

http://localhost/phpmyadmin/

Step 4 — Prepare the Database

Use one of the database methods described below.

Step 5 — Open FieldTrack

Open:

http://localhost/FieldTrack/

🗄 Database Setup

The application expects the database name:

fieldtrack_db

The database connection is configured in:

db.php

Default local XAMPP settings are:

Host: localhost

Username: root

Password: empty

Database: fieldtrack_db

Option A — Existing Database

If your current fieldtrack_db already contains the required RBAC and weekly workflow tables, keep it and first test the PHP files with the existing database.

You can run:

verify_database.sql

to inspect the important system data.

Option B — Clean Database Reset

If the database contains mixed tables from older versions, back it up first and then import:

database.sql

Warning: the clean database script recreates the FieldTrack database and therefore removes existing FieldTrack data.

🔑 Demo Accounts

The clean database contains four local demo accounts.

Role

Name

Username

Password

System Administrator

System Administrator

admin

admin123

Field Officer

Field Officer

officer

officer123

Admin Officer

Kamal Perera

kamal

123

Admin Manager

Admin Manager

test

test123

These credentials are intended for local coursework / demonstration use.

🧪 How to Test the System

Test 1 — Login

Open:

http://localhost/FieldTrack/

Login using each test account and confirm that each role is redirected to the correct dashboard.

Test 2 — Field Officer Attendance

Login as:

officer / officer123

Then:

Select a location.

Upload a photo if required.

Click Mark IN.

Confirm the IN record appears in attendance history.

Click Mark OUT.

Confirm the OUT record appears.

Check the map.

Test 3 — Weekly Submission

Using a completed past week:

Login as Field Officer.

Open Weekly Attendance Submission.

Click Submit Week.

Confirm the status becomes Submitted.

Test 4 — Admin Officer Reject

Login as:

kamal / 123

Then:

Open the submitted week.

Enter a rejection reason.

Click Admin Officer Reject.

Confirm the application returns to the Admin Officer dashboard.

Confirm the submission status is Rejected by Admin Officer.

Test 5 — Field Officer Resubmit

Login again as:

officer / officer123

Then:

View the rejected week.

Read the rejection reason.

Make any required correction.

Click Resubmit Week.

Test 6 — Admin Officer Approve

Login as:

kamal / 123

Then:

Open the resubmitted week.

Click Admin Officer Approve.

Confirm it moves to Pending Manager Review.

Test 7 — Admin Manager Final Review

Login as:

test / test123

Then:

Open the pending submission.

Click Final Approve or Final Reject.

Confirm the final status.

Test 8 — System Administrator

Login as:

admin / admin123

Test:

Attendance dashboard

Filters

Attendance map

Attendance details

Manage Users

Manage Roles

Manage Assignments

Audit Logs

Demo status tool

Demo attendance record generator

📁 Project File Structure

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
├── uploads/
│   └── .gitkeep
│
└── README.md

🧱 Database Tables

The main database includes the following tables.

users

Stores user accounts.

Important data includes:

User ID

Name

Username

Password

Active / inactive status

Created and updated dates

roles

Stores the system roles:

field_officer

admin_officer

admin_manager

system_admin

user_roles

Links users to roles.

permissions

Stores individual application permissions.

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

Connects roles with their allowed permissions.

attendance_events

Stores attendance records such as:

Field Officer ID

IN / OUT action

Latitude

Longitude

Photo path

Record lock status

Created date and time

officer_assignments

Stores the reporting relationship between:

Field Officer

Admin Officer

Admin Manager

weekly_submissions

Stores weekly attendance submissions and their current approval status.

weekly_submission_records

Connects attendance records to a weekly submission.

approval_history

Stores the history of:

Submission

Reviewer

Reviewer role

Decision

Previous status

New status

Rejection reason

Comment

IP address

Date and time

audit_logs

Stores important system activities for traceability.

✔ Validation and Business Rules

FieldTrack includes validation at the PHP / server level.

Login Validation

Username is required.

Password is required.

User must exist.

User must be active.

User must have a valid role.

Attendance Validation

Only Field Officers can mark attendance.

Attendance action must be IN or OUT.

Latitude is required.

Longitude is required.

Latitude must be between -90 and 90.

Longitude must be between -180 and 180.

First attendance action must be IN.

IN cannot follow IN.

OUT cannot follow OUT.

Photo Validation

Maximum size is 5 MB.

Only supported image extensions are accepted.

Weekly Submission Validation

Only Field Officers can submit weekly attendance.

The week start must be valid.

The week must already be completed.

The week must contain attendance records.

The Field Officer must have an Admin Officer / Admin Manager assignment.

A week cannot be submitted twice as a new submission.

Submitted attendance records are locked.

Review Validation

Only administrative review roles can access review functions.

Admin Officer can only review submissions assigned to that Admin Officer.

Admin Manager can only review submissions assigned to that Admin Manager.

A reviewer cannot approve their own Field Officer submission.

Rejection requires a reason.

Review actions are allowed only for valid workflow statuses.

🔐 Security and Access Control

FieldTrack uses several basic security controls.

Role-Based Access Control

Permissions are stored in the database and linked through:

users → user_roles → roles → role_permissions → permissions

Each protected PHP page checks the current user's role before allowing access.

Session Protection

The application uses PHP sessions with:

Strict session mode

Cookie-only sessions

HTTP-only cookies

SameSite Lax

Session regeneration after login

30-minute inactivity timeout

Database Queries

Important database operations use prepared statements to reduce SQL injection risk.

Output Escaping

User-controlled values displayed in HTML are escaped before output.

Audit Logging

Important actions such as login, attendance recording, submission, and review are stored in audit records where implemented.

Password Note

The login code supports both hashed and plain-text stored passwords. The supplied local demo database currently uses plain-text passwords to remain compatible with the coursework/demo setup.

For a production deployment, passwords should be stored only using secure password hashing such as password_hash() and verified using password_verify().

🗺 Maps and Location

FieldTrack uses Leaflet.js with OpenStreetMap.

Field Officers can select a location in three ways:

Current Location

The browser Geolocation API requests the device's current position.

Search Location

The officer can enter a place name and search for it using OpenStreetMap Nominatim.

Click on Map

The officer can manually click the map to select a location.

The chosen latitude and longitude are stored with the attendance record.

The administrator dashboard can also display attendance locations using a shared map.

📷 Photo Evidence

Field Officers may attach photo evidence when recording attendance.

The application:

Allows supported image formats.

Checks maximum file size.

Creates a generated file name.

Stores the physical image inside the uploads/ folder.

Stores only the file path in the database.

Allows administrators to open the uploaded photo.

🧾 Approval and Audit Trail

FieldTrack keeps an approval history for weekly submissions.

The approval history can contain:

Who reviewed the submission

Reviewer's role

Approved / rejected decision

Previous status

New status

Rejection reason

Optional comment

IP address

Timestamp

This helps maintain traceability throughout the multi-level approval workflow.

🔁 Compatibility Files

The clean integrated build contains compatibility files so older links do not create a 404 error.

Main weekly review processor

process_weekly_review.php

Main weekly details page

weekly_submission_details.php

Older compatibility names

process_review.php

submission_details.php

Weekly submission processor

submit_week.php

Older compatibility submission name

submit_weekly.php

These wrapper files redirect older project references to the current implementation.

🧰 Demo and Debug Utilities

records_example.php

Used by the System Administrator to create sample attendance records for demonstration purposes.

This is useful when a completed past week is needed for testing the weekly submission workflow.

dev_test_status.php

Provides development / demonstration information about the current FieldTrack setup.

These files are intended for local testing and demonstration rather than production deployment.

🛠 Troubleshooting

Apache 404 Not Found

Make sure the project is located exactly at:

C:\xampp\htdocs\FieldTrack\

and access it through:

http://localhost/FieldTrack/

The configured application base path is /FieldTrack.

Database Connection Failed

Check that:

MySQL is running in XAMPP.

The database is named fieldtrack_db.

db.php contains the correct database details.

The MySQL port is correct.

Login Does Not Work

Check:

The user exists in users.

is_active = 1.

The user has a record in user_roles.

The role exists in roles.

Field Officer Cannot Submit a Week

Check that:

The week has already ended.

Attendance records exist for that week.

The officer has a record in officer_assignments.

A previous submission for the same week does not already exist.

Admin Officer Cannot See a Submission

Check:

The weekly submission contains the correct admin_officer_id.

The logged-in account has the admin_officer role.

The Admin Officer has the required weekly review permissions.

Admin Manager Cannot See a Submission

Check:

The Admin Officer has already approved it.

The status is pending_manager_review or the compatible Admin Officer approved status.

The correct admin_manager_id is assigned.

Approve / Reject Returns 404

Use the clean project files and confirm these files exist:

process_weekly_review.php

weekly_submission_details.php

process_review.php

submission_details.php

Also confirm the folder is named exactly FieldTrack.

Uploaded Photo Does Not Save

Check that:

The uploads/ folder exists.

Apache/PHP has permission to write to the folder.

The file is smaller than 5 MB.

The file extension is supported.

⚠ Limitations

This version is primarily designed for local coursework and demonstration use.

Current limitations include:

Local XAMPP deployment rather than production hosting.

Demo accounts use simple credentials.

The supplied demo database uses plain-text passwords.

No email or SMS notifications.

No mobile application; the system is browser based.

OpenStreetMap/Nominatim location search requires internet connectivity.

The application does not provide enterprise-grade monitoring or deployment configuration.

🚀 Future Improvements

Possible improvements include:

Store all passwords using password_hash().

Add password reset functionality.

Add CSRF protection to all modifying forms.

Add HTTPS for production deployment.

Add email or SMS notifications for approval and rejection.

Add configurable working days and public holidays.

Add stronger weekly completeness rules.

Add downloadable weekly reports.

Add PDF / Excel report export.

Add advanced analytics and charts.

Add attendance geofencing.

Add approved work-site boundaries.

Add mobile-first / PWA support.

Add device identification.

Add manager comments and notification history.

Add automatic archival of old attendance records.

Add database backup and restore utilities.

🎥 Project Demonstration Flow

A recommended demonstration sequence is:

1. Field Officer

Login using:

officer / officer123

Demonstrate:

Location selection

Photo selection

Mark IN

Mark OUT

Attendance history

Weekly submission

2. Admin Officer

Login using:

kamal / 123

Demonstrate:

Assigned submissions

Open weekly submission

Attendance record inspection

Rejection with reason

Approval

3. Field Officer Again

Demonstrate:

Rejected status

Rejection reason

Resubmission

4. Admin Manager

Login using:

test / test123

Demonstrate:

Pending final reviews

Final approval / rejection

Audit logs

5. System Administrator

Login using:

admin / admin123

Demonstrate:

Attendance dashboard

Filters

Shared attendance map

Attendance details

User management

Role management

Officer assignments

Audit logs

👩‍💻 Author / Contribution

Hansadi Kumanayake

Main contributions to the FieldTrack project include:

Developed login and logout functionality.

Implemented session-based authentication.

Implemented role-based access control.

Worked with user, role, and permission database structures.

Developed the System Administrator attendance dashboard.

Added attendance filtering.

Added officer, date, time, and action filtering logic.

Integrated Leaflet.js and OpenStreetMap.

Displayed attendance locations on maps.

Developed attendance record detail views.

Worked on the Field Officer attendance workflow.

Implemented IN / OUT validation.

Integrated attendance photo upload support.

Worked on weekly attendance submission.

Implemented multi-level approval and rejection workflow.

Added rejection reasons and resubmission support.

Added administrative role dashboards.

Worked on audit and approval history functionality.

Integrated PHP with the MySQL database.

Tested the system using XAMPP and phpMyAdmin.

Improved the interface and overall system workflow for demonstration.

📄 Project Type

Academic / Coursework / Demonstration Project

📌 Final Workflow Summary

Field Officer

IN / OUT → Weekly Submission

↓

Admin Officer

Approve / Reject

↓

Admin Manager

Final Approve / Final Reject

↓

Final Weekly Attendance Status

⭐ FieldTrack

FieldTrack provides a centralized way to record field attendance, capture location information, monitor attendance records, and perform controlled multi-level weekly attendance approval using PHP and MySQL.