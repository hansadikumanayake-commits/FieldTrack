## FIELDTRACK

### Project Overview

FieldTrack is a mobile-responsive attendance and field visit tracking web application designed for field officers, regional officers and system administrators.

The system allows field officers to record their work attendance using IN and OUT actions. Each attendance record includes the officer's location, date, time and photo evidence.

Administrators can monitor officer activity, view attendance records, filter records, inspect uploaded photos and view recorded locations on interactive maps.

FieldTrack was developed using PHP, MySQL, JavaScript, HTML, CSS, Leaflet.js and OpenStreetMap.


### Project Purpose

Field officers frequently work outside the main office. Traditional attendance systems may not accurately record where and when an officer begins or completes a field visit.

FieldTrack solves this problem by allowing officers to:

Record their attendance remotely.
Capture their current geographical location.
Upload or capture photo evidence.
Record accurate server-generated dates and times.
Complete structured IN and OUT visit records.

Administrators can use the system to monitor attendance records and review field activities from a central dashboard.

### Main Objectives

The main objectives of FieldTrack are to:

Provide a simple digital attendance system for field officers.
Record IN and OUT attendance events.
Capture the geographical location of each attendance event.
Store photo evidence with attendance records.
Allow administrators to monitor field officer activities.
Display recorded locations using interactive maps.
Improve attendance transparency and accountability.
Protect user accounts and attendance data through security controls.
Provide a responsive interface for mobile phones, tablets and computers.

### User Roles

FieldTrack contains two main user roles.

## Administrator

The administrator can:

Log in to the administration panel.
View dashboard statistics.
View all registered field officers.
View all IN and OUT attendance records.
Search and filter attendance records.
Filter records by officer.
Filter records by date range.
Filter records by time range.
Filter records by IN or OUT action.
Filter records by photo availability.
View individual attendance details.
View attendance locations on an interactive map.
View uploaded officer photos.
View audit logs.
Monitor user login, logout and attendance activities.

## Field Officer

A field officer can:

Log in to the user panel.
Record an IN attendance event.
Record an OUT attendance event.
Use the device's current location.
Select a location using an interactive map.
Capture or upload photo evidence.
Preview a photo before submission.
View their own attendance history.
View their recorded locations.
Record another IN only after completing the previous OUT.

### Main Features

# Field Officer Features
Secure officer login
Record IN and OUT attendance
Automatic date and time recording
Capture the current device location
Select a location using an interactive map
Capture or upload photo evidence
Preview photos before submission
View personal attendance history
View previously recorded locations
Prevent consecutive IN records without an OUT
Prevent OUT records without an active IN

# Administrator Features

Secure administrator login
Administrator dashboard
View all registered field officers
View all attendance records
View recent attendance activity
View daily IN and OUT totals
Filter attendance records by officer
Filter by date and time
Filter by IN or OUT action
Filter by photo availability
View attendance details
View uploaded attendance photos
View officer locations on an interactive map
Review system audit logs

## Attendance Process
The field officer logs in.
The officer selects or captures the current location.
The officer captures or uploads a photo.
The officer records an IN attendance event.
The officer completes the field visit or work session.
The officer records the corresponding OUT event.
The administrator can review the completed visit from the dashboard.

Each IN and OUT pair represents one completed field visit.

Attendance Process
The field officer logs in.
The officer selects or captures the current location.
The officer captures or uploads a photo.
The officer records an IN attendance event.
The officer completes the field visit or work session.
The officer records the corresponding OUT event.
The administrator can review the completed visit from the dashboard.

Each IN and OUT pair represents one completed field visit.

## Security Features

FieldTrack includes several security controls to protect user accounts and attendance information.

Password hashing
Secure password verification
PHP session authentication
Session ID regeneration after login
Role-based access control
Prepared SQL statements
Server-side input validation
Attendance action validation
Latitude and longitude validation
Secure photo type validation
Photo size restrictions
Random photo filename generation
HTML output escaping
Secure logout
Audit logging
Database foreign-key constraints
Unique usernames
Restricted user-role values
Restricted IN and OUT action values

These security controls help reduce risks such as unauthorized access, SQL injection, cross-site scripting, unsafe file uploads and attendance manipulation.

## Technologies Used

Technologies                Used
Technology	                Purpose
PHP	                        Backend development and server-side processing
MySQL	                    User and attendance data storage
MySQLi	                    Database connection and prepared statements
HTML5	                    Web page structure
CSS3	                    Interface design and responsive layout
JavaScript	                Browser interaction and location handling
Leaflet.js	                Interactive map functionality
OpenStreetMap	            Map tiles and geographical data
PHP Sessions	            Authentication and role management
XAMPP	                    Local development server
phpMyAdmin	                Database administration
Git	                        Version control
GitHub	                    Repository hosting and collaboration
Visual Studio Code          Source-code editing

## Supported Photo Formats

FieldTrack supports the following attendance photo formats:

JPG
JPEG
JFIF
PNG
WEBP

Uploaded files are validated before they are stored in the system.

## System Requirements

To run FieldTrack locally, the following software is required:

XAMPP or another PHP development server
Apache
PHP 8 or later
MySQL or MariaDB
phpMyAdmin
A modern web browser
Git
A code editor such as Visual Studio Code

## Contributions
-Hansadi Kumanayake

Main contributions include:

Login and logout system
Session authentication
Role-based access control
Administrator dashboard
Attendance record management
Attendance filters
Attendance details page
Database integration
SQL query development
Security validation
Prepared statements
Audit log functionality
Administrator map functionality
Backend development
Project documentation

## Team Contributions

Collaborative work includes:
    Field officer interface
    Mobile-responsive design
    Attendance form design
    Location capture
    Photo capture and preview
    User attendance history
    Testing and interface improvements