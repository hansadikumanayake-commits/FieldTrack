# FieldTrack

FieldTrack is a mobile-responsive attendance and visit-tracking web application designed for field officers and regional officers.

The system allows officers to record their **IN** and **OUT** visits together with their current location, date, time, and photo evidence. Administrators can monitor officer activity, view attendance records, inspect uploaded photos, and check recorded locations using interactive maps.

FieldTrack is developed using PHP, MySQL, JavaScript, HTML, CSS, Leaflet.js, and OpenStreetMap.

---

## Project Purpose

Field officers often work outside the main office and may visit several locations during the day. FieldTrack provides a simple way to record these visits and allows administrators to monitor attendance and movement information from one dashboard.

The system helps to:

* Record field officers' IN and OUT times
* Capture the location of each attendance event
* Store photo evidence of visited locations
* Monitor field activities through an admin dashboard
* Display recorded locations using interactive maps
* Maintain organized attendance and visit records

---

## User Roles

FieldTrack has two main user roles:

### Field Officer

Field officers can log in, mark IN and OUT attendance, upload or capture photos, and view their own attendance history.

### Administrator

Administrators can access the admin dashboard, view all officers' attendance records, inspect uploaded photos, and view recorded locations on maps.

---

## Main Features

### User Features

* Secure user login
* Mobile-responsive user dashboard
* IN attendance button
* OUT attendance button
* Automatic date and time capture
* Automatic latitude and longitude capture
* Current location detection using browser geolocation
* Photo capture using a mobile device camera
* Photo upload from a computer or mobile device
* Support for common image formats
* View personal IN and OUT attendance history
* Display previously uploaded attendance photos
* Prevent repeated IN actions before completing an OUT action
* Enable the OUT button only after an IN record has been created

---

### Admin Features

* Secure administrator login
* Responsive admin dashboard
* View the total number of field officers
* View today's IN records
* View today's OUT records
* View all attendance events
* View officer name and username
* View IN and OUT action types
* View attendance date and time
* View latitude and longitude
* View uploaded or captured photos
* View officer locations on Leaflet maps
* Display IN and OUT locations for each officer
* Group related IN and OUT records as visits
* Use different colours to identify separate visit pairs
* Automatically adjust map zoom to show recorded locations

---

## Attendance Process

The FieldTrack attendance process works as follows:

1. The field officer logs in to the system.
2. The officer opens the user dashboard.
3. The officer clicks the **IN** button.
4. The system captures:

   * Officer ID
   * IN action type
   * Current latitude
   * Current longitude
   * Current date
   * Current time
   * Uploaded or captured photo
5. The IN button becomes unavailable until the officer records an OUT event.
6. When leaving the location, the officer clicks the **OUT** button.
7. The system captures the OUT location, date, time, and photo.
8. The administrator can view the IN and OUT records from the admin dashboard.

---

## Map Integration

FieldTrack uses Leaflet.js with OpenStreetMap.

The map is used to:

* Display recorded IN locations
* Display recorded OUT locations
* Show multiple attendance locations
* Group related IN and OUT events
* Connect visit locations visually
* Automatically zoom to recorded location areas

OpenStreetMap is used instead of Google Maps, so the project does not require a Google Maps API key.

---

## Photo Upload Support

Field officers can either:

* Capture a photo directly using a mobile camera
* Upload an existing image from their device

The uploaded image is stored inside the `uploads` folder.

The database stores only the path of the uploaded image, not the complete image file.

Supported image formats can include:

* JPG
* JPEG
* PNG
* WEBP
* JFIF

---

## Technologies Used

### Frontend

* HTML5
* CSS3
* JavaScript
* Leaflet.js

### Backend

* PHP
* MySQL

### Development Environment

* XAMPP
* Apache
* phpMyAdmin

### Map Services

* OpenStreetMap
* Leaflet.js

### Version Control

* Git
* GitHub

---

## Database Overview

FieldTrack uses the `fieldtrack_db` MySQL database.

The main tables are:

### `users` Table

The `users` table stores administrator and field officer account details.

Main columns:

* `id`
* `name`
* `username`
* `password`
* `role`

The `role` column identifies whether the account belongs to an administrator or a field officer.

Possible role values:

* `admin`
* `user`

---

### `attendance_events` Table

The `attendance_events` table stores every IN and OUT attendance event.

Main columns:

* `id`
* `user_id`
* `action_type`
* `latitude`
* `longitude`
* `photo_path`
* `created_at`

The `user_id` column connects each attendance event to a user in the `users` table.

The `action_type` column stores either:

* `IN`
* `OUT`

---

## Database Relationship

The project uses a one-to-many relationship between the `users` table and the `attendance_events` table.

```text
One user can have many attendance events.

users.id
    |
    └── attendance_events.user_id
```

Each attendance event belongs to one field officer.

---

## Project Folder Structure

```text
FieldTrack/
│
├── README.md
├── database.sql
├── db.php
├── .gitignore
│
├── login.php
├── login_process.php
├── login_failed.php
├── logout.php
│
├── user_panel.php
├── user_style.css
├── mark_attendance.php
│
├── admin_panel.php
├── admin_style.css
│
├── style.css
│
└── uploads/
    └── .gitkeep
```

---

## File Descriptions

### `db.php`

Creates the connection between the PHP application and the MySQL database.

### `login.php`

Displays the login form for users and administrators.

### `login_process.php`

Checks the entered username and password, creates the login session, and redirects users according to their role.

### `login_failed.php`

Displays a message when invalid login details are entered.

### `user_panel.php`

Displays the field officer dashboard and personal attendance records.

### `mark_attendance.php`

Processes IN and OUT attendance submissions, location information, date, time, and photo uploads.

### `admin_panel.php`

Displays dashboard statistics, officer attendance records, photos, visit pairs, and map locations.

### `user_style.css`

Contains styling for the field officer dashboard.

### `admin_style.css`

Contains styling for the administrator dashboard.

### `style.css`

Contains general styling used by shared pages such as the login page.

### `database.sql`

Contains the SQL commands required to create the database and tables.

### `uploads/`

Stores photos uploaded or captured during IN and OUT attendance submissions.

---

## Installation Guide

### 1. Install XAMPP

Download and install XAMPP.

Start:

* Apache
* MySQL

---

### 2. Copy the Project Folder

Copy the `FieldTrack` folder into:

```text
C:\xampp\htdocs\
```

The complete path should be:

```text
C:\xampp\htdocs\FieldTrack
```

---

### 3. Create the Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create a database named:

```text
fieldtrack_db
```

Import the `database.sql` file into the database.

---

### 4. Check the Database Connection

Open `db.php` and confirm the database settings.

Example:

```php
<?php

$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "fieldtrack_db"
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
```

---

### 5. Run the Application

Open the following URL in a browser:

```text
http://localhost/FieldTrack/login.php
```

---

## Location Permission

FieldTrack requires browser location access.

When the browser asks for permission, the user must select:

```text
Allow location access
```

Location capture may not work when:

* Browser location permission is blocked
* Device location services are turned off
* The website is not running through localhost or HTTPS
* The device cannot detect its current location

---

## Photo Upload Requirements

The `uploads` folder must exist inside the project folder.

Example:

```text
FieldTrack/uploads/
```

The folder must also have permission to store uploaded files.

The `.gitkeep` file is used to keep the empty uploads folder inside the Git repository.

Uploaded attendance photos should normally be excluded from Git using `.gitignore`.

Example:

```gitignore
uploads/*
!uploads/.gitkeep
```

---

## Security Notes

The system should use the following security practices:

* Validate all form inputs
* Use prepared SQL statements
* Validate uploaded image types
* Limit uploaded image file sizes
* Rename uploaded images before saving
* Protect admin pages using sessions
* Protect user pages using sessions
* Prevent users from accessing admin pages
* Escape displayed values using `htmlspecialchars()`
* Store passwords using `password_hash()`
* Verify passwords using `password_verify()`

Plain-text passwords should not be used in a production system.

---

## Planned Admin Dashboard Improvements

The following improvements are planned for the FieldTrack admin dashboard:

* Filter records by officer
* Filter records by date range
* Filter records by time range
* Filter IN and OUT records
* Filter records with or without photos
* Search attendance records
* Sort records by date or officer name
* Display all filtered records on one shared map
* Show attendance details when hovering over map markers
* Show a photo preview inside map popups
* Open a separate detailed attendance page
* Display completed and incomplete visit pairs
* Add pagination for large attendance tables
* Improve dashboard performance and responsiveness

---

## Future Improvements

Possible future improvements include:

* Password encryption
* Forgot-password feature
* Email notifications
* Export attendance records to PDF
* Export attendance records to Excel
* Generate attendance reports
* Reverse geocoding for readable location names
* Officer profile management
* Admin user-management section
* Real-time dashboard updates
* Distance calculation between IN and OUT locations
* Visit duration calculation
* Late-attendance detection
* Progressive Web App support
* Cloud deployment

---

## Project Status

FieldTrack is currently under development.

The main attendance, location capture, photo upload, user dashboard, admin dashboard, and map-viewing functions are being developed and improved.

---

## Project Name
FieldTrack

## Author
Hansadi Kumanayake