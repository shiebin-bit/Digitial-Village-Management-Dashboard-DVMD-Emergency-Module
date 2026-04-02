# DVMD Emergency Management System

DVMD is a PHP and MySQL emergency management platform for Malaysian local administration. It provides role-based dashboards for `Ketua Kampung`, `Penghulu`, and `Pejabat Daerah`, plus a small set of JSON APIs used by the village-side client to submit incident and SOS reports.

## Overview

This repository contains two main parts:

- A browser-based management dashboard for local authority users
- A PHP API layer under `dvmd/api/` for villager/mobile-side reporting flows

The system is organised around Malaysia's administrative hierarchy:

- `Ketua Kampung` manages village-level data
- `Penghulu` manages subdistrict-level data
- `Pejabat Daerah` manages district-level data

## Main Features

- Role-based login and dashboard routing
- Account registration flow managed by authenticated staff users
- Incident submission, tracking, and status updates
- SOS submission flow for urgent alerts
- Village announcements
- Weather widget powered by Open-Meteo
- Password reset with OTP email delivery through PHPMailer
- CSV/report export endpoints

## User Roles

| Role value | Role | Main entry page |
| --- | --- | --- |
| `0` | Ketua Kampung | `ketuakampungdashboard.php` |
| `1` | Penghulu | `penghuludashboard.php` |
| `2` | Pejabat Daerah | `pejabatdaerahdashboard.php` |

Registration permissions in the current code:

- `Penghulu` can create `Ketua Kampung` accounts
- `Pejabat Daerah` can create `Ketua Kampung`, `Penghulu`, and `Pejabat Daerah` accounts

## Project Structure

```text
.
|-- css/                     Frontend styles
|-- database/
|   `-- dvmd_db.sql          Database schema and sample data
|-- dvmd/
|   |-- api/                 JSON endpoints for villager/mobile workflows
|   `-- assets/              Uploaded image assets
|-- images/                  Static images and icons
|-- includes/
|   |-- auth_user.php        Session/role validation
|   |-- dbconnect.php        MySQL connection
|   `-- footer.php           Shared footer include
|-- js/
|   `-- weather.js           Weather widget client logic
|-- management/
|   |-- incident/            Incident update/delete handlers
|   `-- sos/                 SOS update/delete handlers
|-- vendor/                  Bundled PHPMailer dependency
|-- loginpage.php            Main web login entry
|-- registerpage.php         Role-based account creation
|-- forgotpasswordpage.php   OTP request page
|-- verifyOTPpage.php        OTP verification page
|-- resetpasswordpage.php    Password reset page
|-- weather_api.php          Weather proxy endpoint
|-- get_reports.php          Combined report feed for dashboards
`-- export_incidents.php     CSV export endpoint
```

## Tech Stack

- PHP 8.x style application code
- MySQL or MariaDB
- Apache or another PHP-capable web server
- PHPMailer for OTP email delivery
- Open-Meteo for live weather data
- HTML, CSS, and JavaScript for the dashboard UI

## Requirements

- PHP 8.0 or above
- MySQL or MariaDB
- Apache/Nginx with PHP enabled
- Internet access for:
  - Open-Meteo weather requests
  - SMTP email delivery if password reset is used

## Installation

1. Copy the project into your web root.
   Example for XAMPP:
   `E:\xampp\htdocs\Digitial-Village-Management-Dashboard-DVMD-Emergency-Module-main`
2. Create a database in MySQL or MariaDB.
3. Import `database/dvmd_db.sql`.
4. Update the database connection in `includes/dbconnect.php`.
5. Review SMTP settings in `forgotpasswordpage.php` and `verifyOTPpage.php`.
6. Ensure PHP can write uploaded images into `dvmd/assets/`.
7. Open `loginpage.php` in the browser to access the web dashboard.

## Configuration Checklist

Before using this project outside the original environment, update these items:

- Database host, username, password, and database name in `includes/dbconnect.php`
- SMTP server settings and sender credentials in:
  - `forgotpasswordpage.php`
  - `verifyOTPpage.php`
- File/folder permissions for uploaded incident and SOS images
- CORS policy in `dvmd/api/` if the API will not be public

## Web Entry Points

| Path | Purpose |
| --- | --- |
| `loginpage.php` | Staff login page |
| `registerpage.php` | Account creation for permitted roles |
| `forgotpasswordpage.php` | Start password reset flow |
| `verifyOTPpage.php` | Verify OTP code |
| `resetpasswordpage.php` | Set a new password |
| `ketuakampungdashboard.php` | Village-level dashboard |
| `penghuludashboard.php` | Subdistrict-level dashboard |
| `pejabatdaerahdashboard.php` | District-level dashboard |

## API Endpoints

These endpoints are currently present under `dvmd/api/`.

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `dvmd/api/login_villager.php` | `POST` | Villager/mobile login |
| `dvmd/api/report_incident.php` | `POST` | Submit a new incident report |
| `dvmd/api/send_sos.php` | `POST` | Submit an SOS alert |
| `dvmd/api/get_my_reports.php` | `POST` | Fetch a villager's submitted reports |
| `dvmd/api/get_announcements.php` | `POST` | Fetch the latest village announcements |
| `dvmd/api/get_ketua_phone.php` | `GET` | Fetch Ketua Kampung contact number by `village_id` |

Other supporting endpoints outside `dvmd/api/`:

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `weather_api.php` | `GET` | Returns current weather for a selected area |
| `get_reports.php` | Session-based | Returns combined incident/SOS feed for logged-in dashboard users |
| `export_incidents.php` | Session-based | Streams incident data as CSV |

## Database Notes

The SQL dump currently defines these main tables:

- `tbl_users`
- `tbl_villagers`
- `tbl_districts`
- `tbl_subdistricts`
- `tbl_villages`
- `tbl_incidents`
- `tbl_sos`
- `tbl_announcements`
- `tbl_audit_log`

The database models district, subdistrict, village, staff users, villagers, incidents, SOS alerts, and announcements.

## Known Gaps To Review Before Deployment

This repository should be treated as a working academic/project codebase, not a ready-to-deploy production package. Review these items first:

- `database/dvmd_db.sql` does not fully match every PHP script. Some API files reference fields such as `latitude`, `longitude`, `type`, `urgency_level`, `image`, `failed_attempts`, and `lock_until` that must exist in the deployed database schema.
- `export_incidents.php` queries `tbl_incident`, while the SQL dump defines `tbl_incidents`.
- Secrets are hard-coded in source files. Move database and SMTP credentials into environment-based configuration before production use.
- `dvmd/api/` currently allows `Access-Control-Allow-Origin: *`, which is too open for most production deployments.
- The repository includes vendored PHPMailer code under `vendor/`, but there is no root-level Composer workflow documented in this project.

## Suggested First Improvements

If you are continuing development, these are the highest-value cleanup tasks:

1. Centralise database and SMTP configuration in one non-committed config file.
2. Reconcile the SQL dump with the current incident, SOS, and villager API code.
3. Add a root `composer.json` if dependency management is expected.
4. Add a proper `.env` example and setup guide.
5. Document API request parameters and sample responses if a mobile app depends on this repository.

## License

No license file is currently included in this repository. Add one if the project will be shared publicly.
