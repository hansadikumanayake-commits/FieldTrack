# FieldTrack

<p align="center">
  <strong>A mobile-responsive attendance and field visit tracking system for field officers and administrators.</strong>
</p>

<p align="center">

![Project Status](https://img.shields.io/badge/Status-In%20Development-orange)
![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php\&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?logo=mysql\&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?logo=javascript\&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-Markup-E34F26?logo=html5\&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-Styling-1572B6?logo=css3\&logoColor=white)
![Leaflet](https://img.shields.io/badge/Leaflet.js-Interactive%20Maps-199900?logo=leaflet\&logoColor=white)
![OpenStreetMap](https://img.shields.io/badge/OpenStreetMap-Map%20Data-7EBC6F?logo=openstreetmap\&logoColor=white)

</p>

<p align="center">

![XAMPP](https://img.shields.io/badge/XAMPP-Local%20Server-FB7A24?logo=xampp\&logoColor=white)
![phpMyAdmin](https://img.shields.io/badge/phpMyAdmin-Database%20Management-6C78AF?logo=phpmyadmin\&logoColor=white)
![Git](https://img.shields.io/badge/Git-Version%20Control-F05032?logo=git\&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-Repository-181717?logo=github\&logoColor=white)
![VS Code](https://img.shields.io/badge/Visual%20Studio%20Code-Editor-007ACC?logo=visualstudiocode\&logoColor=white)

</p>

---

## Table of Contents

* [About FieldTrack](#about-fieldtrack)
* [Project Background](#project-background)
* [Project Objectives](#project-objectives)
* [Target Users](#target-users)
* [User Roles](#user-roles)
* [Main Features](#main-features)
* [Attendance Workflow](#attendance-workflow)
* [Administrator Dashboard](#administrator-dashboard)
* [Location and Map Functionality](#location-and-map-functionality)
* [Photo Evidence](#photo-evidence)
* [Attendance Filtering](#attendance-filtering)
* [Security Features](#security-features)
* [Technologies Used](#technologies-used)
* [System Requirements](#system-requirements)
* [Installation Overview](#installation-overview)
* [Database Overview](#database-overview)
* [Project Components](#project-components)
* [Project Status](#project-status)
* [Testing Considerations](#testing-considerations)
* [Future Improvements](#future-improvements)
* [Contributions](#contributions)
* [Repository](#repository)
* [Disclaimer](#disclaimer)

---

## About FieldTrack

**FieldTrack** is a web-based attendance and field visit tracking application designed for employees who perform their duties outside a traditional office environment.

The system allows field officers and regional officers to record their attendance using **IN** and **OUT** actions. Each attendance record can include the officer's location, date, time and photographic evidence.

Administrators can monitor officer activities through a central dashboard, review attendance records, view uploaded photos, apply filters and inspect recorded locations using interactive maps.

FieldTrack is designed to improve the accuracy, transparency and accountability of field-based attendance management.

---

## Project Background

Traditional attendance systems are normally designed for employees working from a fixed office location. However, field officers, regional officers and mobile employees may begin and complete their work from different locations.

Manual attendance methods can create several problems, including:

* Inaccurate attendance times
* Missing attendance records
* Difficulty confirming an officer's location
* Limited evidence of field visits
* Delays in preparing attendance reports
* Difficulty monitoring multiple field officers
* Limited accountability for completed visits
* Dependence on paper-based attendance records

FieldTrack addresses these issues by combining attendance recording, location capture, photo evidence and administrator monitoring in one application.

---

## Project Objectives

The main objectives of FieldTrack are to:

* Provide a digital attendance system for field officers.
* Allow officers to record IN and OUT attendance remotely.
* Capture location information for each attendance action.
* Record attendance dates and times automatically.
* Allow officers to submit photographic evidence.
* Provide administrators with a centralized monitoring dashboard.
* Present attendance locations using interactive maps.
* Organize IN and OUT records into understandable field visits.
* Improve transparency and accountability.
* Reduce the use of manual attendance records.
* Protect attendance information through security controls.
* Provide a responsive interface for mobile phones, tablets and computers.

---

## User Roles

FieldTrack includes two main user roles as user and administrator .

### Administrator

The administrator is responsible for monitoring the overall attendance system.

Administrators can:

* Access the administrator dashboard.
* View registered field officers.
* Review all attendance records.
* Monitor recent attendance activity.
* View daily IN and OUT totals.
* Search and filter attendance information.
* View individual attendance details.
* Inspect uploaded attendance photos.
* View attendance locations on maps.
* Review system audit logs.
* Monitor officer attendance patterns.
* Identify incomplete or unusual attendance records.

### Field Officer

The field officer uses the application to record personal attendance and field visits.

Field officers can:

* Log in to the user panel.
* Record an IN attendance event.
* Record an OUT attendance event.
* Capture the current device location.
* Select a location using the map.
* Capture or upload a photo.
* Preview the selected photo before submission.
* View personal attendance history.
* Review previously recorded locations.
* Complete attendance actions using a mobile-responsive interface.

A field officer can only view and submit attendance under their own authenticated account.

---

## Main Features

### Secure Login and Logout

FieldTrack provides separate access for administrators and field officers through a common login system.

After successful authentication, users are directed to the correct panel based on their assigned role.

The logout process securely ends the user's active session.

### IN and OUT Attendance

Field officers record attendance using two actions:

* **IN** indicates the beginning of a field visit or work session.
* **OUT** indicates the completion of that field visit or work session.

The application controls the order of attendance actions to prevent invalid attendance sequences.

### Automatic Date and Time Recording

The system automatically records the date and time when an attendance action is submitted.

This improves the reliability of attendance information and reduces manual entry errors.

### Location Capture

FieldTrack records the geographical location associated with each attendance event.

Location information includes:

* Latitude
* Longitude
* Attendance date
* Attendance time
* Officer information
* IN or OUT action

The officer can use the device's current location or select an appropriate location through the interactive map.

### Photo Evidence

Field officers can capture or upload a photograph when recording attendance.

Photo evidence can help confirm:

* The officer's presence at a location
* The working environment
* The field visit
* The assigned site
* The completion of a particular activity

### Personal Attendance History

Field officers can review their previous attendance records.

Attendance history can include:

* IN and OUT actions
* Attendance dates
* Attendance times
* Recorded locations
* Uploaded photo information

### Administrator Monitoring

Administrators can review attendance information from a central dashboard.

The dashboard provides a clearer understanding of officer activity and allows administrators to identify records that require further review.

---

## Attendance Workflow

The standard FieldTrack attendance process is as follows:

1. The field officer opens the FieldTrack login page.
2. The officer enters valid login credentials.
3. The system opens the field officer panel.
4. The officer captures or selects the current location.
5. The officer captures or uploads a photo.
6. The officer previews the selected photo.
7. The officer records an IN attendance event.
8. The officer performs the field visit or assigned work.
9. The officer records the corresponding OUT attendance event.
10. The attendance information becomes available to the administrator.
11. The administrator reviews the visit through the dashboard, records, photos and map.

Each completed IN and OUT combination represents one field visit or attendance session.

---

## Administrator Dashboard

The administrator dashboard provides a central overview of FieldTrack activity.

The dashboard can display:

* Total registered field officers
* Total IN records for the current day
* Total OUT records for the current day
* Total attendance records
* Recent attendance activities
* Officer information
* Attendance action
* Attendance date and time
* Photo availability
* Attendance location

The dashboard allows the administrator to access more detailed information about individual attendance records.

---

## Location and Map Functionality

FieldTrack uses interactive maps to display attendance locations.

The map functionality allows users and administrators to understand where attendance actions were recorded.

### Field Officer Map

The field officer map allows an officer to:

* View the selected location.
* Use the current device location.
* Select a location manually.
* Confirm the location before submitting attendance.

### Administrator Map

The administrator map can display:

* Officer attendance locations
* IN markers
* OUT markers
* Attendance dates
* Attendance times
* Officer names
* Visit information
* Links to detailed attendance records

Related IN and OUT records can be displayed as a visit pair, allowing the administrator to understand the beginning and completion of a field visit.

---

## Photo Evidence

Photo evidence is included to improve the reliability and transparency of attendance records.

Supported image formats include:

* JPG
* JPEG
* JFIF
* PNG
* WEBP

The application checks uploaded photos before storing them.

Photo handling includes:

* File type validation
* File size validation
* Photo preview
* Safe filename generation
* Controlled storage
* Display through administrator and attendance detail pages

Administrators can review photos together with the officer's attendance information and recorded location.

---

## Attendance Filtering

The administrator can filter attendance records to locate relevant information more efficiently.

Available filtering options include:

### Officer Filter

Displays attendance records belonging to a selected officer.

### Date Range Filter

Available date selections can include:

* All records
* Today
* Yesterday
* Last seven days
* Last thirty days
* This month
* Custom date range

### Custom Date Filter

Allows the administrator to select a specific starting date and ending date.

### Time Filter

Allows attendance records to be filtered using a starting time and ending time.

### Attendance Action Filter

Records can be filtered by:

* IN
* OUT
* All actions

### Photo Filter

Records can be filtered by:

* Records with a photo
* Records without a photo
* All records

These filters help administrators review specific periods, officers, attendance actions and evidence availability.

---

## Security Features

FieldTrack includes several security controls to protect user accounts, attendance data and administrative functions.

### Password Protection

User passwords are protected using password hashing rather than being stored as readable plain text.

Secure password verification is used during login.

### Session Authentication

The application uses authenticated sessions to identify logged-in users.

Session information is used to determine:

* The authenticated user
* The user's account ID
* The user's name
* The user's assigned role

The session identifier is regenerated after a successful login to strengthen session security.

### Role-Based Access Control

Access to system pages is controlled according to the user's role.

This means:

* Administrators can access administrator functions.
* Field officers can access officer functions.
* Field officers cannot access administrator pages.
* Unauthenticated visitors cannot access protected pages.

Access is checked by the server rather than depending only on whether a link or button is visible.

### Prepared Database Queries

Prepared database statements are used for important database operations.

This helps protect the application against SQL injection attacks.

### Server-Side Input Validation

FieldTrack validates submitted information on the server.

Validated information includes:

* User identifiers
* Attendance actions
* Latitude
* Longitude
* Date selections
* Time selections
* Filter values
* Uploaded photo types
* Uploaded photo sizes

Server-side validation is important because browser-side validation can be modified or bypassed.

### Secure Attendance Ownership

The identity of the officer submitting attendance is taken from the authenticated session.

The system does not depend on a user-selected officer ID when creating an attendance record.

This reduces the possibility of one officer submitting attendance for another officer.

### Attendance Sequence Validation

The application checks the officer's previous attendance action before accepting a new attendance record.

This helps prevent:

* Consecutive IN records
* OUT records without a previous IN
* Incorrect attendance visit sequences

### Secure Photo Upload

Uploaded photos are checked using their actual file type.

The application also uses generated filenames instead of trusting the original uploaded filename.

This helps reduce risks associated with:

* Unsupported file uploads
* Dangerous filenames
* Duplicate filenames
* Existing file replacement
* Files incorrectly presented as images

### Output Protection

Information displayed from the database is safely processed before appearing on a web page.

This helps reduce the risk of cross-site scripting attacks.

### Secure Logout

The logout process clears the user's active session and redirects the user back to the login page.

### Audit Logging

Important system activities are recorded in audit logs.

Audit events may include:

* Successful login
* Failed login
* Logout
* Attendance IN
* Attendance OUT
* Attendance record access
* Other important administrator or user activities

Audit logs help improve accountability and make it easier to review important system activity.

### Database Integrity

Database rules help maintain accurate and consistent data.

These rules include:

* Unique usernames
* Restricted user roles
* Restricted attendance action types
* Relationships between users and attendance records
* Relationships between users and audit records

---

## Technologies Used

| Technology         | Purpose                                                    |
| ------------------ | ---------------------------------------------------------- |
| PHP                | Backend application logic and server-side processing       |
| MySQL              | Storage of users, attendance records and audit information |
| MySQLi             | Communication between PHP and the MySQL database           |
| HTML5              | Structure of application pages                             |
| CSS3               | Styling and responsive interface design                    |
| JavaScript         | Browser interaction, photo previews and location handling  |
| Leaflet.js         | Interactive map creation                                   |
| OpenStreetMap      | Geographical map tiles and map information                 |
| PHP Sessions       | User authentication and role management                    |
| Apache             | Local web server                                           |
| XAMPP              | Local PHP and MySQL development environment                |
| phpMyAdmin         | Database creation and management                           |
| Git                | Version control                                            |
| GitHub             | Repository hosting and project collaboration               |
| Visual Studio Code | Application development and source editing                 |

---

## System Requirements

The following software is required to run FieldTrack locally:

* Windows, Linux or macOS
* XAMPP or another compatible PHP server environment
* Apache
* PHP 8 or later
* MySQL or MariaDB
* phpMyAdmin or another MySQL administration tool
* A modern web browser
* Internet access for loading map resources
* Git for cloning and version control
* A source-code editor such as Visual Studio Code

### Recommended Browser Capabilities

The browser should support:

* JavaScript
* Geolocation
* File upload
* Camera access, where available
* Modern responsive layouts

The user may need to give permission for the browser to access the device location or camera.

---

## Installation Overview

To run FieldTrack in a local XAMPP environment:

1. Install XAMPP.
2. Start the Apache and MySQL services.
3. Download or clone the FieldTrack repository.
4. Place the FieldTrack folder inside the XAMPP `htdocs` directory.
5. Open phpMyAdmin.
6. Create the FieldTrack database.
7. Import the provided database file.
8. Check that the database connection settings match the local environment.
9. Confirm that the photo upload directory exists.
10. Open FieldTrack through the localhost address.
11. Log in using an administrator or field officer account.

---

## Database Overview

FieldTrack uses a MySQL database named `fieldtrack_db`.

The main database tables are:

### Users

Stores information about system users, including:

* User ID
* Name
* Username
* Protected password
* User role

### Attendance Events

Stores officer attendance information, including:

* Attendance record ID
* Officer ID
* IN or OUT action
* Latitude
* Longitude
* Photo path
* Date and time

### Audit Logs

Stores important system activity, including:

* Audit record ID
* User information
* Action performed
* Additional details
* IP address
* Date and time

The tables are connected so attendance and audit records can be associated with the correct system user.

---

## Project Components

The project is organized into several functional areas.

### Authentication Components

Responsible for:

* Login
* Credential verification
* Session creation
* Role checking
* Login failure handling
* Logout

### Administrator Components

Responsible for:

* Dashboard statistics
* Officer monitoring
* Attendance filtering
* Attendance record display
* Map display
* Photo review
* Audit log review

### Field Officer Components

Responsible for:

* Attendance recording
* Location selection
* Current location capture
* Photo upload
* Photo preview
* Attendance history
* IN and OUT control

### Database Components

Responsible for:

* Database connection
* User storage
* Attendance storage
* Audit log storage
* Data relationships
* Data integrity

### Interface Components

Responsible for:

* Login page design
* Administrator dashboard design
* Field officer panel design
* Responsive layouts
* Tables
* Forms
* Buttons
* Maps
* Photo presentation

---

## Project Status

FieldTrack is currently under development.

The following major functions have been implemented:

* User login
* User logout
* Administrator role
* Field officer role
* Session authentication
* Role-based access control
* Administrator dashboard
* Field officer panel
* IN attendance
* OUT attendance
* Attendance sequence validation
* Automatic date and time recording
* Location capture
* Map location selection
* Interactive attendance maps
* Photo capture and upload
* Photo preview
* Attendance history
* Attendance details
* Administrator attendance filters
* Password protection
* Prepared database statements
* Input validation
* Output protection
* Audit logging
* Responsive interface design

Further testing and improvements may be completed as the project continues.

---

## Testing Considerations

FieldTrack should be tested in several areas before production use.

### Functional Testing

Verify that:

* Valid users can log in.
* Invalid credentials are rejected.
* Users are redirected to the correct panel.
* Officers can record IN.
* Officers can record OUT.
* Invalid attendance sequences are blocked.
* Locations are saved correctly.
* Photos are uploaded correctly.
* Filters return the expected records.
* Attendance details are displayed correctly.
* Logout ends the session.

### Security Testing

Verify that:

* Unauthenticated users cannot access protected pages.
* Field officers cannot access administrator pages.
* Invalid database input is rejected.
* Unsupported photo types are rejected.
* Oversized photos are rejected.
* User information is displayed safely.
* Sessions are destroyed after logout.
* Attendance cannot be recorded for another user.

### Responsive Testing

The system should be tested on:

* Desktop computers
* Laptops
* Tablets
* Android phones
* iPhones
* Different screen sizes
* Different modern browsers

### Location Testing

Location functionality should be tested for:

* Current location access
* Permission denial
* Manual map selection
* Invalid coordinates
* Low-accuracy GPS situations
* Different geographical locations

---

## Future Improvements

The following features may be added in future versions:

* CSRF protection for forms
* Login attempt limiting
* Temporary account lockout
* Password reset
* Password change functionality
* Two-factor authentication
* Secure HTTPS deployment
* User account management
* Administrator user creation
* Officer profile management
* Geofencing
* Route tracking
* Visit purpose
* Visit notes
* Daily attendance reports
* Monthly attendance reports
* PDF report generation
* Excel report export
* Email notifications
* SMS notifications
* Attendance reminders
* Map marker clustering
* Advanced attendance analytics
* Dashboard charts
* Automatic photo compression
* Cloud deployment
* Database backup tools
* Offline attendance recording
* Progressive Web App support
* Mobile application development
* Session timeout controls
* Additional administrator permission levels

---

## Contributions

### Hansadi Kumanayake

Main contributions include:

* Login functionality
* Login processing
* Logout functionality
* Session authentication
* Role-based access control
* Administrator dashboard
* Dashboard statistics
* Attendance record management
* Attendance filters
* Attendance detail display
* Database integration
* Database query development
* Prepared database statements
* Input validation
* Security improvements
* Audit log functionality
* Administrator map functionality
* Backend development
* Project documentation
* Git and GitHub project management

## Team Contributions

* Developed the field officer user interface
* Added attendance marking and location capture
* Implemented photo upload and preview
* Improved responsive design and usability
* Supported testing and overall system improvements

---

## Repository

**GitHub Repository:**
https://github.com/hansadikumanayake-commits/FieldTrack

---

## Disclaimer

FieldTrack is currently developed as an educational software project.

Before using the application in a real organization, the system should undergo:

* Full functional testing
* Security testing
* User acceptance testing
* Performance testing
* Mobile-device testing
* Browser compatibility testing
* Database backup testing
* HTTPS configuration
* Production server configuration
* Privacy and data-protection review
* Organizational policy review

The system should not be considered production-ready until these reviews and deployment controls have been completed.

---

<p align="center">
  <strong>FieldTrack — Smart attendance and field visit tracking with location and photo evidence.</strong>
</p>
