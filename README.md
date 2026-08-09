<div align="center">

# 📍 FieldTrack

### Smart Attendance & Field Visit Tracking System

A web-based attendance management system that allows field officers to record **IN/OUT attendance with automatic GPS location**, submit weekly attendance, and complete a **multi-level approval workflow**.

<br>

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6%2B-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-Frontend-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-Styling-1572B6?style=for-the-badge&logo=css3&logoColor=white)

![Leaflet](https://img.shields.io/badge/Leaflet.js-Maps-199900?style=for-the-badge&logo=leaflet&logoColor=white)
![OpenStreetMap](https://img.shields.io/badge/OpenStreetMap-Map_Data-7EBC6F?style=for-the-badge&logo=openstreetmap&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-Web_Server-D22128?style=for-the-badge&logo=apache&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Local_Development-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)

![Git](https://img.shields.io/badge/Git-Version_Control-F05032?style=for-the-badge&logo=git&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-Repository-181717?style=for-the-badge&logo=github&logoColor=white)

</div>

---

# 📖 Table of Contents

- [About FieldTrack](#-about-fieldtrack)
- [Objectives](#-objectives)
- [Key Features](#-key-features)
- [User Roles](#-user-roles)
- [Attendance Workflow](#-attendance-workflow)
- [Weekly Approval Workflow](#-weekly-approval-workflow)
- [Submission Statuses](#-submission-statuses)
- [Technology Stack](#-technology-stack)
- [System Architecture](#-system-architecture)
- [Project Structure](#-project-structure)
- [Database Structure](#-database-structure)
- [Installation](#-installation)
- [Running the System](#-running-the-system)
- [Demo Accounts](#-demo-accounts)
- [How to Test the System](#-how-to-test-the-system)
- [Automatic GPS Location](#-automatic-gps-location)
- [Validation Rules](#-validation-rules)
- [Role-Based Access Control](#-role-based-access-control)
- [Audit and Approval History](#-audit-and-approval-history)
- [Troubleshooting](#-troubleshooting)
- [Security Notes](#-security-notes)
- [Future Improvements](#-future-improvements)
- [Main Contributions](#-main-contributions)
- [Project Summary](#-project-summary)
- [Author](#-author)
- [License](#-license)

---

# 🧭 About FieldTrack

**FieldTrack** is a web-based attendance and field visit tracking system developed to simplify the process of monitoring field officers and reviewing their weekly attendance.

Instead of manually entering a location, the application automatically captures the officer's **current GPS coordinates** when the officer clicks **Mark IN** or **Mark OUT**.

The system also provides a structured approval process where weekly attendance passes through:

```text
Field Officer
      ↓
Admin Officer
      ↓
Admin Manager
      ↓
Final Approval
```

A separate **System Administrator** manages users, roles, permissions, assignments, audit logs, and other administrative functions.

---

# 🎯 Objectives

The main objectives of FieldTrack are to:

- Digitize field officer attendance.
- Reduce manual attendance processing.
- Capture the officer's real-time location during attendance marking.
- Maintain a proper IN/OUT attendance sequence.
- Organize attendance into weekly submissions.
- Provide first-level and final-level approval.
- Allow rejection reasons and resubmission.
- Maintain approval history.
- Maintain audit logs.
- Provide role-based access to different system functions.
- Improve accountability and traceability.

---

# ✨ Key Features

## 👤 Field Officer

The Field Officer can:

- Login securely using a username and password.
- Mark **IN** attendance.
- Mark **OUT** attendance.
- Automatically capture the current GPS location.
- Record latitude and longitude automatically.
- Record attendance date and time automatically.
- View the current attendance status.
- View the next allowed attendance action.
- View recent attendance history.
- View attendance coordinates.
- View today's field movement route on a map.
- View weekly attendance information.
- Submit completed weekly attendance.
- View the current submission status.
- View rejection reasons.
- Correct rejected attendance when permitted.
- Resubmit rejected weekly attendance.
- Track final approval status.

### Attendance Sequence

```text
IN → OUT → IN → OUT
```

The system prevents:

```text
IN → IN     ❌
OUT → OUT   ❌
OUT first   ❌
```

---

## 🧑‍💼 Admin Officer

The Admin Officer performs the **first-level review**.

The Admin Officer can:

- View assigned Field Officers.
- View weekly attendance submissions.
- View pending submissions.
- Open submission details.
- Review all attendance records belonging to the submitted week.
- View IN and OUT records.
- View attendance locations.
- Approve a weekly submission.
- Reject a weekly submission.
- Enter a mandatory rejection reason.
- View resubmitted attendance.
- View approval history.
- Forward approved submissions to the Admin Manager.

---

## 👨‍💼 Admin Manager

The Admin Manager performs the **final review**.

The Admin Manager can:

- View submissions approved by Admin Officers.
- View submission details.
- Review attendance records.
- View previous approval decisions.
- Perform final approval.
- Perform final rejection.
- Enter a rejection reason.
- View approval history.
- Confirm the final status of the weekly submission.

---

## ⚙️ System Administrator

The System Administrator manages the application.

The System Administrator can:

- Manage users.
- Activate users.
- Deactivate users.
- Manage roles.
- Manage permissions.
- Manage role-permission assignments.
- Manage officer assignments.
- Assign a Field Officer to an Admin Officer.
- Assign a Field Officer to an Admin Manager.
- View attendance records.
- View administrative information.
- View audit logs.
- Monitor important system activity.

---

# 👥 User Roles

FieldTrack contains four main roles.

| Role | Description |
|---|---|
| `field_officer` | Marks IN/OUT attendance and submits weekly attendance |
| `admin_officer` | Performs first-level weekly attendance review |
| `admin_manager` | Performs final weekly attendance review |
| `system_admin` | Manages users, roles, permissions, assignments and audit information |

---

# 📍 Attendance Workflow

The attendance workflow is designed to be simple for the Field Officer.

```text
Field Officer
      │
      ▼
Click "Mark IN"
      │
      ▼
Browser requests location permission
      │
      ▼
Current GPS location captured
      │
      ▼
Latitude + Longitude recorded
      │
      ▼
IN attendance saved
      │
      ▼
Officer performs field work
      │
      ▼
Click "Mark OUT"
      │
      ▼
Current location captured again
      │
      ▼
OUT attendance saved
```

The Field Officer does **not** need to manually select a location.

The Field Officer does **not** need to upload a photo.

---

# 🔄 Weekly Approval Workflow

After a completed week, the Field Officer can submit the attendance records for approval.

```text
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
                        /           \
                       /             \
                  Reject             Approve
                    │                   │
                    ▼                   ▼
          Admin Officer Rejected   Pending Manager Review
                    │                   │
                    ▼                   ▼
          Field Officer Corrects    ADMIN MANAGER
                    │                /         \
                    ▼               /           \
                Resubmit        Reject         Approve
                    │             │               │
                    │             ▼               ▼
                    └──────► Correction      FINAL APPROVED
```

---

# 📌 Submission Statuses

FieldTrack uses different statuses during the weekly approval workflow.

| Status | Meaning |
|---|---|
| `draft` | Attendance week has not been submitted |
| `submitted` | Submitted to the Admin Officer |
| `admin_officer_approved` | Approved by the Admin Officer |
| `admin_officer_rejected` | Rejected by the Admin Officer |
| `pending_manager_review` | Waiting for Admin Manager review |
| `manager_rejected` | Rejected by the Admin Manager |
| `returned_for_correction` | Returned to the Field Officer for correction |
| `resubmitted` | Rejected attendance has been submitted again |
| `final_approved` | Final approval completed |

---

# 🛠 Technology Stack

## Frontend

![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)

Used for:

- Page structure
- Responsive user interfaces
- Buttons and forms
- Browser interaction
- GPS location capture
- Map functionality

---

## Backend

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)

PHP is used for:

- Authentication
- Sessions
- Role validation
- Attendance processing
- Weekly submissions
- Approval workflow
- Database operations
- Audit logging
- User management

---

## Database

![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=flat-square&logo=mysql&logoColor=white)

MySQL / MariaDB stores:

- Users
- Roles
- Permissions
- Attendance
- Weekly submissions
- Approval history
- Officer assignments
- Audit logs

---

## Maps

![Leaflet](https://img.shields.io/badge/Leaflet.js-Maps-199900?style=flat-square&logo=leaflet&logoColor=white)
![OpenStreetMap](https://img.shields.io/badge/OpenStreetMap-Data-7EBC6F?style=flat-square&logo=openstreetmap&logoColor=white)

Used to display attendance locations and Field Officer routes.

---

## Development Environment

![XAMPP](https://img.shields.io/badge/XAMPP-FB7A24?style=flat-square&logo=xampp&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-D22128?style=flat-square&logo=apache&logoColor=white)

XAMPP provides:

- Apache
- PHP
- MySQL / MariaDB
- phpMyAdmin

---

## Version Control

![Git](https://img.shields.io/badge/Git-F05032?style=flat-square&logo=git&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-181717?style=flat-square&logo=github&logoColor=white)

Used for project version control and repository management.

---

# 🏗 System Architecture

FieldTrack follows a simple three-layer web architecture.

```text
┌─────────────────────────────────┐
│          USER BROWSER           │
│                                 │
│ HTML + CSS + JavaScript         │
│ Browser Geolocation API         │
│ Leaflet.js                      │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│         APACHE + PHP            │
│                                 │
│ Login & Authentication          │
│ Role Validation                 │
│ Attendance Processing           │
│ Weekly Submission Workflow      │
│ Approval / Rejection Logic      │
│ User Management                 │
│ Audit Logging                   │
└────────────────┬────────────────┘
                 │
                 ▼
┌─────────────────────────────────┐
│        MYSQL / MARIADB          │
│                                 │
│ Users                           │
│ Roles                           │
│ Permissions                     │
│ Attendance Events               │
│ Weekly Submissions              │
│ Approval History                │
│ Officer Assignments             │
│ Audit Logs                      │
└─────────────────────────────────┘
```

---

# 📁 Project Structure

```text
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
```

---

# 🗄 Database Structure

The system uses the database:

```text
fieldtrack_db
```

The main database tables are described below.

---

## `users`

Stores user account information.

Important fields include:

```text
id
name
username
password
is_active
created_at
updated_at
```

---

## `roles`

Stores the available FieldTrack roles.

```text
field_officer
admin_officer
admin_manager
system_admin
```

---

## `user_roles`

Links users with their assigned roles.

Example:

```text
User
 ↓
Role
```

---

## `permissions`

Stores individual system permissions.

Examples:

```text
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
```

---

## `role_permissions`

Connects roles to their permissions.

Example:

```text
Field Officer
    ↓
attendance.mark_in
attendance.mark_out
weekly.submit
```

---

## `attendance_events`

Stores Field Officer attendance.

Important information includes:

```text
id
user_id
action_type
latitude
longitude
is_locked
created_at
updated_at
```

Example:

```text
Action Type : IN
Latitude    : 6.927079
Longitude   : 79.861244
Date/Time   : Automatically recorded
```

The current FieldTrack version does **not require attendance photos**.

---

## `officer_assignments`

Stores the reporting and approval hierarchy.

```text
Field Officer
      ↓
Admin Officer
      ↓
Admin Manager
```

---

## `weekly_submissions`

Stores weekly attendance submissions.

Important information includes:

```text
Field Officer
Admin Officer
Admin Manager
Week Start
Week End
Current Status
Latest Rejection Reason
Submitted Date
Admin Review Date
Manager Review Date
```

---

## `weekly_submission_records`

Links attendance events to the corresponding weekly submission.

```text
Weekly Submission
       ↓
Attendance Event
```

---

## `approval_history`

Stores the full approval and rejection history.

Information can include:

```text
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
```

---

## `audit_logs`

Stores important system activities.

Audit information can include:

```text
User
Action
Target Type
Target ID
Details
IP Address
Date and Time
```

---

# 💻 Installation

## Requirements

Before running FieldTrack, install:

![XAMPP](https://img.shields.io/badge/XAMPP-Required-FB7A24?style=flat-square&logo=xampp&logoColor=white)

You need:

- XAMPP
- PHP 8.2 or later
- MySQL / MariaDB
- Apache
- phpMyAdmin
- Chrome, Edge, or Firefox

---

## Step 1 — Install XAMPP

Install XAMPP on your computer.

The default installation location is normally:

```text
C:\xampp\
```

---

## Step 2 — Copy FieldTrack

Place the FieldTrack project inside:

```text
C:\xampp\htdocs\
```

The final project location should be:

```text
C:\xampp\htdocs\FieldTrack\
```

Make sure the folder is named exactly:

```text
FieldTrack
```

---

## Step 3 — Start XAMPP

Open:

```text
XAMPP Control Panel
```

Start:

```text
Apache
MySQL
```

Both should display a running status.

---

## Step 4 — Open phpMyAdmin

Open your browser and visit:

```text
http://localhost/phpmyadmin/
```

---

## Step 5 — Prepare the Database

The required database is:

```text
fieldtrack_db
```

Import the supplied database SQL file if a clean database is required.

Example:

```text
database.sql
```

If using an existing FieldTrack database, confirm that the database tables match the current PHP code.

---

## Step 6 — Database Connection

The local XAMPP connection is typically:

```php
$host = "localhost";
$username = "root";
$password = "";
$database = "fieldtrack_db";
```

These settings are normally stored in:

```text
db.php
```

---

# ▶ Running the System

Once Apache and MySQL are running, open:

```text
http://localhost/FieldTrack/
```

The login page should appear.

---

# 🔑 Demo Accounts

The following accounts can be used for local demonstration and testing.

| Role | Username | Password |
|---|---|---|
| System Administrator | `admin` | `admin123` |
| Field Officer | `officer` | `officer123` |
| Admin Officer | `kamal` | `123` |
| Admin Manager | `test` | `test123` |

> **Note:** These accounts are only intended for local development and coursework demonstrations. Demo credentials should not be displayed on a production login page.

---

# 🧪 How to Test the System

## Test 1 — Field Officer Login

Login using:

```text
Username: officer
Password: officer123
```

Expected result:

```text
Field Officer Dashboard
```

---

## Test 2 — Mark IN

On the Field Officer dashboard:

1. Click **Mark IN**.
2. The browser requests location permission.
3. Click **Allow**.
4. FieldTrack obtains the current latitude and longitude.
5. The attendance record is saved.

Expected information:

```text
Action: IN
Latitude: Captured automatically
Longitude: Captured automatically
Date: Automatic
Time: Automatic
```

---

## Test 3 — Prevent Duplicate IN

After marking IN, try clicking IN again.

Expected result:

```text
IN → IN
```

should not be permitted.

The next valid action should be:

```text
OUT
```

---

## Test 4 — Mark OUT

Click:

```text
Mark OUT
```

The current GPS location is captured again and the OUT record is stored.

The completed sequence becomes:

```text
IN → OUT
```

---

## Test 5 — View Attendance History

The Field Officer should be able to view:

```text
Action
Date / Time
Latitude
Longitude
Lock Status
```

---

## Test 6 — View Today's Route

If multiple attendance locations exist for the day, the system displays them on the interactive Leaflet map.

Example:

```text
IN Location
    ↓
Field Activity
    ↓
OUT Location
```

---

## Test 7 — Submit Weekly Attendance

Use a completed previous week.

```text
Field Officer
      ↓
Weekly Attendance
      ↓
Submit Week
      ↓
Submitted
```

Expected status:

```text
submitted
```

---

## Test 8 — Admin Officer Review

Login using:

```text
Username: kamal
Password: 123
```

Open the weekly submission.

The Admin Officer can:

```text
Approve
```

or:

```text
Reject
```

---

## Test 9 — Admin Officer Rejection

Enter a rejection reason.

Example:

```text
Attendance record requires correction.
```

Click:

```text
Admin Officer Reject
```

Expected status:

```text
admin_officer_rejected
```

---

## Test 10 — Field Officer Resubmission

Login again as:

```text
officer / officer123
```

The rejected week should display:

```text
Rejection Reason
```

The officer can then:

```text
Correct
   ↓
Resubmit Week
```

Expected status:

```text
resubmitted
```

---

## Test 11 — Admin Officer Approval

Login again as:

```text
kamal / 123
```

Open the resubmitted week.

Click:

```text
Admin Officer Approve
```

Expected status:

```text
pending_manager_review
```

---

## Test 12 — Admin Manager Final Approval

Login as:

```text
Username: test
Password: test123
```

Open the pending submission.

Click:

```text
Final Approve
```

Expected result:

```text
FINAL APPROVED
```

Database status:

```text
final_approved
```

---

## Test 13 — Admin Manager Rejection

The Admin Manager may alternatively:

```text
Final Reject
```

A rejection reason should be provided.

The Field Officer can then be informed that additional correction is required.

---

## Test 14 — System Administrator

Login using:

```text
Username: admin
Password: admin123
```

Test:

- Manage Users
- Manage Roles
- Manage Permissions
- Manage Assignments
- View Attendance
- View Audit Logs
- View System Information

---

# 🌍 Automatic GPS Location

FieldTrack uses the browser:

```text
Geolocation API
```

When the Field Officer clicks:

```text
Mark IN
```

or:

```text
Mark OUT
```

the application automatically requests the current location.

The user does not need to:

```text
❌ Search for a location
❌ Select a location from a dropdown
❌ Click a location on a map
❌ Upload a photo
```

Instead:

```text
Click IN / OUT
      ↓
Browser gets location
      ↓
Latitude + Longitude
      ↓
Attendance saved
```

---

## Chrome Location Permission

The browser may display:

```text
Allow localhost to know your location?
```

Select:

```text
Allow
```

If location was previously blocked:

```text
Address Bar
    ↓
Site Settings
    ↓
Location
    ↓
Allow
    ↓
Refresh FieldTrack
```

---

# ✅ Validation Rules

## Login Validation

The system checks:

- Username is provided.
- Password is provided.
- User exists.
- User account is active.
- User has a valid role.
- User is redirected to the correct dashboard.

---

## Attendance Validation

The system checks:

- User is a Field Officer.
- Attendance action is IN or OUT.
- Latitude exists.
- Longitude exists.
- Latitude is valid.
- Longitude is valid.
- First attendance action is IN.
- IN cannot follow IN.
- OUT cannot follow OUT.

Valid latitude range:

```text
-90 to 90
```

Valid longitude range:

```text
-180 to 180
```

---

## Weekly Submission Validation

The system checks:

- User is a Field Officer.
- Attendance week exists.
- Week has already completed.
- Attendance records exist.
- Officer has an Admin Officer assignment.
- Officer has an Admin Manager assignment.
- Duplicate submission is prevented.
- Submitted attendance is locked where required.

---

## Review Validation

The system checks:

- Reviewer is authorized.
- Submission belongs to the reviewer.
- Submission has the correct current status.
- Admin Officer can perform first-level review.
- Admin Manager can perform final review.
- Rejection contains a reason.
- Approval/rejection history is recorded.

---

# 🔐 Role-Based Access Control

FieldTrack uses **Role-Based Access Control (RBAC)**.

Each role receives only the permissions required for that role.

Example:

```text
FIELD OFFICER
├── Mark IN
├── Mark OUT
├── View own attendance
├── Submit week
└── View own submissions
```

```text
ADMIN OFFICER
├── View assigned submissions
├── Review weekly attendance
├── Approve Level 1
└── Reject Level 1
```

```text
ADMIN MANAGER
├── View assigned submissions
├── Perform final review
├── Final approve
└── Final reject
```

```text
SYSTEM ADMIN
├── Manage users
├── Manage roles
├── Manage assignments
└── View audit information
```

---

# 🧾 Audit and Approval History

FieldTrack maintains historical information about important workflow actions.

Approval history can record:

```text
Reviewer
Reviewer Role
Decision
Previous Status
New Status
Reason
Comment
IP Address
Date / Time
```

Example:

```text
Submitted
    ↓
Rejected by Admin Officer
    ↓
Resubmitted
    ↓
Approved by Admin Officer
    ↓
Approved by Admin Manager
```

This provides better traceability of the attendance approval process.

---

# 🛠 Troubleshooting

## 1. 404 Not Found

If you see:

```text
Not Found
The requested URL was not found on this server.
```

confirm the project is located at:

```text
C:\xampp\htdocs\FieldTrack\
```

Open:

```text
http://localhost/FieldTrack/
```

Also verify that the required PHP file exists.

---

## 2. MySQL Connection Error

Check:

```text
XAMPP
```

and make sure:

```text
Apache = Running
MySQL  = Running
```

Also check:

```text
Database: fieldtrack_db
Username: root
Password: blank
```

---

## 3. Unknown Column Error

Example:

```text
Unknown column 'example_column' in 'field list'
```

This normally means:

```text
PHP code version
        ≠
Database structure version
```

Check the table structure in phpMyAdmin and ensure the expected columns exist.

---

## 4. Location Permission Denied

If the system says location permission was denied:

```text
Chrome
   ↓
Site Settings
   ↓
Location
   ↓
Allow
```

Then refresh the page.

---

## 5. Current Location Unavailable

Check:

- Device location services are enabled.
- Browser has permission.
- Browser supports Geolocation.
- Internet/location services are available.
- Try again after refreshing.

---

## 6. Field Officer Cannot Submit a Week

Check:

- The week is completed.
- Attendance records exist.
- Correct Admin Officer is assigned.
- Correct Admin Manager is assigned.
- Week has not already been submitted.

---

## 7. Admin Officer Cannot See a Submission

Check:

- Correct Admin Officer is assigned.
- User role is `admin_officer`.
- Submission status is `submitted` or `resubmitted`.
- Required permissions are assigned.

---

## 8. Admin Manager Cannot See a Submission

Check:

- Admin Officer has approved it.
- Correct Admin Manager is assigned.
- Submission is in Manager review status.

Expected status:

```text
pending_manager_review
```

---

## 9. Approve / Reject Produces 404

Confirm these files exist:

```text
process_weekly_review.php
weekly_submission_details.php
admin_officer_panel.php
admin_manager_panel.php
```

Use consistent application paths.

Example:

```text
/FieldTrack/process_weekly_review.php
```

---

# 🔒 Security Notes

FieldTrack is primarily designed for **educational and local demonstration purposes**.

For production deployment, the following security improvements are recommended:

```text
✔ Password hashing
✔ HTTPS
✔ CSRF protection
✔ Session protection
✔ Login rate limiting
✔ Secure cookies
✔ Input validation
✔ Restricted database permissions
✔ Removal of demo accounts
✔ Removal of development/debug utilities
✔ Environment variables for credentials
✔ Stronger password policies
✔ Multi-factor authentication
```

For production passwords, PHP should use:

```php
password_hash()
```

and:

```php
password_verify()
```

instead of storing passwords as plain text.

---

# 🚀 Future Improvements

FieldTrack can be extended with:

- Email notifications
- Approval notifications
- Push notifications
- Monthly attendance reports
- CSV export
- Excel export
- PDF reports
- Dashboard charts
- Attendance analytics
- Geo-fencing
- Approved working locations
- Working-hour validation
- Late attendance detection
- Leave management
- Holiday calendar
- Offline attendance
- Mobile application
- Progressive Web App support
- Password reset
- Two-factor authentication
- Improved audit reporting
- Department-based reporting
- Additional Field Officer hierarchy
- Supervisor notifications

---

# 💡 Main Contributions

Major development areas of the project include:

- Login functionality
- Role-based access control
- Field Officer dashboard
- IN/OUT attendance functionality
- Automatic GPS location capture
- Attendance history
- Route mapping
- Admin dashboard functionality
- Weekly attendance submission
- Admin Officer approval/rejection
- Rejection reason handling
- Field Officer resubmission
- Admin Manager final approval
- Multi-level approval workflow
- User management
- Role management
- Permission management
- Officer assignment management
- Approval history
- Audit logging
- Database integration
- Responsive user interface
- System testing and troubleshooting

---

# 🎯 Project Summary

FieldTrack provides a complete attendance management workflow.

```text
                      LOGIN
                        │
                        ▼
              FIELD OFFICER DASHBOARD
                        │
                        ▼
               AUTOMATIC GPS LOCATION
                        │
                        ▼
                  IN / OUT RECORD
                        │
                        ▼
              WEEKLY ATTENDANCE
                        │
                        ▼
                  SUBMIT WEEK
                        │
                        ▼
                 ADMIN OFFICER
                  /          \
                 /            \
              Reject         Approve
                │              │
                ▼              ▼
             Correct      ADMIN MANAGER
                │          /          \
                ▼         /            \
            Resubmit   Reject         Approve
                                     │
                                     ▼
                              FINAL APPROVED
```

FieldTrack combines:

![GPS](https://img.shields.io/badge/GPS-Location_Tracking-2EA44F?style=flat-square)
![RBAC](https://img.shields.io/badge/RBAC-Role_Based_Access-4F46E5?style=flat-square)
![Workflow](https://img.shields.io/badge/Workflow-Multi_Level_Approval-F59E0B?style=flat-square)
![Audit](https://img.shields.io/badge/Audit-Activity_Tracking-DC2626?style=flat-square)
![Responsive](https://img.shields.io/badge/UI-Responsive-06B6D4?style=flat-square)

The project demonstrates the practical use of:

```text
PHP
MySQL
JavaScript
HTML
CSS
Geolocation
Leaflet.js
OpenStreetMap
RBAC
Multi-level Approval
Audit Logging
```

---

# 👩‍💻 Author

### Hansadi Kumanayake

**BSc (Hons) Software Engineering Undergraduate**

University of Westminster  
Informatics Institute of Technology (IIT), Sri Lanka

---

<div align="center">

## 📍 FieldTrack

### Smart Attendance • GPS Tracking • Weekly Approval • Role-Based Access

![PHP](https://img.shields.io/badge/Built_with-PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/Powered_by-MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Leaflet](https://img.shields.io/badge/Maps-Leaflet.js-199900?style=flat-square&logo=leaflet&logoColor=white)

</div>

---
