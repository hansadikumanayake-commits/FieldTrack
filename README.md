FieldTrack

# FieldTrack

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6%2B-F7DF1E?logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-Frontend-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-Styling-1572B6?logo=css3&logoColor=white)
![Leaflet](https://img.shields.io/badge/Leaflet.js-Maps-199900?logo=leaflet&logoColor=white)
![OpenStreetMap](https://img.shields.io/badge/OpenStreetMap-Map_Data-7EBC6F?logo=openstreetmap&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-Web_Server-D22128?logo=apache&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Local_Development-FB7A24?logo=xampp&logoColor=white)
![Git](https://img.shields.io/badge/Git-Version_Control-F05032?logo=git&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-Repository-181717?logo=github&logoColor=white)

Overview

FieldTrack is a web-based attendance and field visit tracking system developed using PHP and MySQL.

The system allows Field Officers to mark IN and OUT attendance while automatically capturing their current GPS location. Completed weekly attendance can then be submitted through a multi-level approval workflow involving an Admin Officer and Admin Manager.

The system also includes a System Administrator role for managing users, roles, permissions, officer assignments, attendance information, and audit records.

Main Features

Field Officer

Role-based login

Mark IN and OUT

Automatically capture current GPS location

No manual location selection required

No photo upload required

IN/OUT sequence validation

View attendance history

View latitude, longitude, date, and time

View today's route on a map

Submit completed weekly attendance

View rejection reasons

Correct and resubmit rejected weeks

View final approval status

Admin Officer

View assigned weekly submissions

Review attendance records

Approve submissions for Manager review

Reject submissions with a reason

View approval history

Monitor pending submissions

Admin Manager

View submissions approved by Admin Officers

Perform final review

Final approve weekly attendance

Final reject with a reason

View review history

System Administrator

Manage users

Manage roles

Manage permissions

Manage officer assignments

View attendance information

View audit logs

Monitor system activity

User Roles

Role

Responsibility

field_officer

Marks attendance and submits weekly attendance

admin_officer

Performs first-level approval or rejection

admin_manager

Performs final approval or rejection

system_admin

Manages users, permissions, assignments and system information

Attendance Workflow

Field Officer
     |
     v
Click Mark IN
     |
     v
Current GPS location captured automatically
     |
     v
IN attendance saved
     |
     v
Click Mark OUT
     |
     v
Current GPS location captured automatically
     |
     v
OUT attendance saved

Attendance Sequence

IN → OUT → IN → OUT

The system prevents:

OUT as the first attendance action

IN immediately after IN

OUT immediately after OUT

Weekly Approval Workflow

Field Officer
     |
     v
Submit Weekly Attendance
     |
     v
SUBMITTED
     |
     v
Admin Officer
   /       \
Reject    Approve
  |          |
  v          v
Officer    Pending Manager Review
Corrects        |
  |             v
  v        Admin Manager
Resubmit      /      \
           Reject   Approve
             |        |
             v        v
          Correct   FINAL APPROVED

Weekly Submission Statuses

draft
submitted
admin_officer_approved
admin_officer_rejected
pending_manager_review
manager_rejected
returned_for_correction
resubmitted
final_approved

Technologies Used

Technology

Purpose

PHP 8.2+

Server-side application logic

MySQL / MariaDB

Relational database

HTML5

Page structure

CSS3

User interface styling

JavaScript

Client-side interaction and GPS handling

Browser Geolocation API

Automatic current-location capture

Leaflet.js

Interactive maps

OpenStreetMap

Map tiles and geographic data

Apache

Local web server

XAMPP

Local development environment

Git

Version control

GitHub

Source-code repository

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

Database

The application uses:

fieldtrack_db

Main Tables

Table

Purpose

users

Stores user accounts

roles

Stores system roles

user_roles

Assigns roles to users

permissions

Stores system permissions

role_permissions

Assigns permissions to roles

attendance_events

Stores IN/OUT attendance and GPS coordinates

officer_assignments

Stores officer approval hierarchy

weekly_submissions

Stores weekly attendance submissions

weekly_submission_records

Links attendance events with submissions

approval_history

Stores approval/rejection history

audit_logs

Stores important system actions

Installation

Requirements

XAMPP

PHP 8.2+

MySQL / MariaDB

Chrome, Edge, or Firefox

Step 1 — Place the Project

Copy the project to:

C:\xampp\htdocs\FieldTrack\

Step 2 — Start XAMPP

Start:

Apache
MySQL

Step 3 — Open phpMyAdmin

http://localhost/phpmyadmin/

Step 4 — Prepare the Database

Create or import:

fieldtrack_db

Step 5 — Open FieldTrack

http://localhost/FieldTrack/

Local Demo Accounts

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

Demo credentials are intended only for local development and coursework demonstration. They should not be displayed on the production login page.

Testing the System

Field Officer

Login
→ Click Mark IN
→ Allow location access
→ Current GPS location is captured
→ IN record is saved
→ Click Mark OUT
→ Current GPS location is captured again
→ OUT record is saved

Weekly Submission

Field Officer
→ Submit completed week
→ Status: Submitted

Admin Officer Review

Admin Officer
→ Open Submission
→ Approve
      OR
→ Reject with reason

Rejected Submission

Field Officer
→ View rejection reason
→ Correct attendance if needed
→ Resubmit Week

Admin Manager Review

Admin Manager
→ Open pending submission
→ Final Approve
      OR
→ Final Reject

Successful approval ends with:

FINAL APPROVED

Location Tracking

FieldTrack uses the browser Geolocation API.

When the Field Officer clicks Mark IN or Mark OUT, the system automatically requests the current device location.

The recorded attendance contains:

Latitude
Longitude
Date
Time
IN / OUT action

No manual map selection is required before marking attendance.

Validation

FieldTrack validates:

Logged-in user

User role

Active account status

IN/OUT action type

Latitude and longitude

IN/OUT order

Weekly submission dates

Officer assignments

Submission status

Reviewer permissions

Rejection reason

Final approval permissions

Troubleshooting

404 Not Found

Confirm the project folder is:

C:\xampp\htdocs\FieldTrack\

and open:

http://localhost/FieldTrack/

Database Connection Error

Check:

MySQL is running
Database = fieldtrack_db
Username = root
Correct MySQL port

Location Permission Denied

In Chrome:

Site Settings
→ Location
→ Allow
→ Refresh FieldTrack

Unknown Column Error

An error such as:

Unknown column 'example_column' in 'field list'

usually means the PHP files and database schema are from different versions.

Update the database structure so it matches the current application.

Security Notes

This version is primarily intended for coursework and local demonstration.

For production use:

Hash passwords using password_hash()

Verify passwords using password_verify()

Enable HTTPS

Add CSRF protection

Add login rate limiting

Secure session cookies

Remove demo accounts

Restrict development utilities

Validate all server-side inputs

Apply database least privilege

Protect audit records

Future Improvements

Email notifications

Approval notifications

Push notifications

Attendance report export

Monthly summaries

Dashboard charts

Geo-fencing

Working-hour validation

Leave management

Offline attendance support

Password reset

Two-factor authentication

Enhanced audit reports

Progressive Web App support

System Summary

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

FieldTrack combines GPS attendance tracking, role-based access control, weekly submission management, multi-level approval, and audit history in a single web application.

License

This project is intended for educational and demonstration purposes.