# FieldTrack

FieldTrack is a mobile responsive web application developed for field officers or regional officers to record their IN and OUT visits with location, date, time, and photo proof.

The system has two user roles: user and admin.

Users can mark IN and OUT attendance, capture their current location automatically, and upload or capture a photo of visited places.

Admins can view all user records, photos, and map locations of each IN and OUT entry.

---

## Project Features

### User Features

- User login
- Mobile responsive user panel
- IN and OUT buttons
- Automatic date and time capture
- Automatic latitude and longitude capture
- Photo capture using mobile camera
- Photo upload from device
- User can view their own IN and OUT records
- IN button is disabled after clicking IN
- OUT button is enabled only after clicking IN

### Admin Features

- Admin login
- Admin dashboard
- View all users' IN and OUT records
- View user name, action type, date, time, latitude, and longitude
- View uploaded/captured photos
- View all IN and OUT locations on a map
- Map automatically zooms to the recorded location area

---

## Technologies Used

- HTML
- CSS
- JavaScript
- PHP
- MySQL
- XAMPP
- OpenStreetMap
- Leaflet.js

---

## Database Overview

The system uses two main tables.

### users table

Stores user and admin login details.

### attendance_events table

Stores every IN and OUT record.

Each record contains:

- User ID
- Action type: IN or OUT
- Latitude
- Longitude
- Photo path
- Created date and time


## Folder Structure

FieldTrack/
│
├── README.md
├── db.php
├── login.php
├── login_process.php
├── user_panel.php
├── mark_attendance.php
├── admin_panel.php
├── logout.php
├── style.css
├── database.sql
├── .gitignore
│
└── uploads/
    └── .gitkeep