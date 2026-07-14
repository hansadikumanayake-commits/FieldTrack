# FieldTrack

FieldTrack is a mobile-responsive attendance and visit-tracking web application designed for field officers and regional officers.

The system allows field officers to record **IN** and **OUT** attendance together with their location, date, time, and photo evidence. Administrators can monitor officer activities, filter attendance records, inspect uploaded photos, view individual attendance details, and display recorded locations using interactive maps.

FieldTrack is developed using PHP, MySQL, JavaScript, HTML, CSS, Leaflet.js, and OpenStreetMap.

## Project Purpose

Field officers often work outside the main office and may visit several locations during the day. FieldTrack provides a simple way to record these visits while allowing administrators to monitor attendance and location information from one dashboard.

The system helps to:

- Record field officers' IN and OUT times
- Capture the location of every attendance event
- Store photo evidence of visited locations
- Monitor field activities through an admin dashboard
- Filter attendance records by different criteria
- Display recorded locations using interactive maps
- Group related IN and OUT records into visit pairs
- Maintain organized attendance and visit records

## User Roles

FieldTrack has two main user roles.

### Field Officer

Field officers can:

- Log in to the system
- Mark IN attendance
- Mark OUT attendance
- Capture their current location
- Upload or capture attendance photos
- View their own attendance history
- View their previously uploaded photos
- View their recorded attendance locations

### Administrator

Administrators can:

- Log in to the admin dashboard
- View all officers' attendance records
- Filter attendance records
- Inspect uploaded attendance photos
- View individual attendance details
- View all filtered officer locations on a shared map
- Monitor completed and incomplete IN/OUT visit pairs

---

## Main Features

### Field Officer Features

- Role-based user login
- Mobile-responsive user dashboard
- IN attendance button
- OUT attendance button
- Automatic date and time capture
- Automatic latitude and longitude capture
- Current location detection using browser geolocation
- Photo capture using a mobile device camera
- Photo upload from a computer or mobile device
- Support for common image formats
- Personal IN and OUT attendance history
- Display previously uploaded attendance photos
- Prevent repeated IN actions before completing an OUT action
- Enable the OUT button only after an IN record has been created
- Display recorded attendance locations on a map

---

### Administrator Features

- Role-based administrator login
- Responsive admin dashboard
- View matching officer count
- View filtered IN record count
- View filtered OUT record count
- View total filtered attendance record count
- View officer names and usernames
- View IN and OUT action types
- View attendance dates and times
- View latitude and longitude
- View uploaded or captured photos
- View individual attendance details
- View officer locations using Leaflet maps
- Display all filtered records on one shared map
- Group related IN and OUT records as visits
- Connect paired IN and OUT locations with lines
- Use different colours to identify separate visit pairs
- Show attendance information when hovering over map markers
- Show photo previews inside map popups
- Open a separate detailed attendance page
- Automatically adjust map zoom to show recorded locations

---

## Admin Attendance Filters

The administrator can filter attendance records using the following options:

- Officer
- Date range
- Today
- Yesterday
- Last 7 days
- Last 30 days
- Current month
- Custom date range
- From time
- To time
- IN records only
- OUT records only
- Records with photos
- Records without photos

The custom date fields are displayed only when **Custom Date Range** is selected.

The system validates:

- Missing custom dates
- Invalid date values
- From Date later than To Date
- Missing time values
- Invalid time values
- From Time later than To Time

Validation is performed using both JavaScript and PHP.

---

## Attendance Process

The FieldTrack attendance process works as follows:

1. The field officer logs in to the system.
2. The officer opens the user dashboard.
3. The officer selects or captures a photo.
4. The officer allows the browser to detect the current location.
5. The officer clicks the **IN** button.
6. The system records:
   - Officer ID
   - IN action type
   - Current latitude
   - Current longitude
   - Current date and time
   - Uploaded or captured photo
7. The IN button becomes unavailable until an OUT record is submitted.
8. When leaving the location, the officer clicks the **OUT** button.
9. The system records the OUT location, date, time, and photo.
10. The administrator can view the IN and OUT records from the admin dashboard.

---

## Visit Pairing

FieldTrack groups related IN and OUT attendance records into visit pairs.

A visit pair normally contains:

- One IN record
- One OUT record

The administrator map displays the IN and OUT markers using the same colour and connects them with a line.

The system can also display incomplete visits, such as:

- An IN record without an OUT record
- An OUT record without a matching IN record

---

## Attendance Details Page

Each attendance record includes a **View Details** link.

The attendance details page displays:

- Officer name
- Officer username
- Attendance action type
- Attendance date and time
- Latitude
- Longitude
- Uploaded photo
- Recorded location on a Leaflet map

The details page can be opened from:

- The recent attendance records table
- Attendance marker popups on the shared map

---

## Map Integration

FieldTrack uses Leaflet.js with OpenStreetMap.

The map is used to:

- Display recorded IN locations
- Display recorded OUT locations
- Display multiple attendance locations
- Group related IN and OUT events
- Connect visit locations visually
- Show attendance information on marker hover
- Show photos and details inside marker popups
- Automatically zoom to recorded location areas

OpenStreetMap is used instead of Google Maps, so the project does not require a Google Maps API key.

---

## Photo Upload Support

Field officers can either:

- Capture a photo directly using a mobile camera
- Upload an existing image from their device

Uploaded images are stored inside the `uploads` folder.

The database stores the path of the uploaded image instead of storing the full image file.

Supported image formats include:

- JPG
- JPEG
- PNG
- WEBP
- JFIF

---

## Technologies Used

### Frontend

- HTML5
- CSS3
- JavaScript
- Leaflet.js

### Backend

- PHP
- MySQL

### Development Environment

- XAMPP
- Apache
- phpMyAdmin

### Map Services

- OpenStreetMap
- Leaflet.js

### Version Control

- Git
- GitHub

---

### Project Name
-FieldTrack


### Author
-Hansadi Kumanayake