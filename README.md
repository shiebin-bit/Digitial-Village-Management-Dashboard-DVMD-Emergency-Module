# DVMD Emergency Management System

Digital Village Management Dashboard emergency module with PHP dashboards, MySQL data storage, and the original static CSS/JS/image assets.

This branch keeps the original UI and PHP flow, but arranges the files into clearer `frontend/` and `backend/` folders for local development. It does not convert the dashboard into React/Vue and it does not move the login/dashboard pages to static HTML yet.

## Project Structure

```text
.
|-- backend/
|   |-- includes/       Database connection, auth guard, shared footer
|   |-- management/     Incident and SOS update/delete handlers
|   |-- dvmd/           Existing mobile/villager APIs and uploaded images
|   |-- vendor/         PHPMailer dependencies
|   |-- loginpage.php
|   |-- registerpage.php
|   |-- ketuakampungdashboard.php
|   |-- penghuludashboard.php
|   `-- pejabatdaerahdashboard.php
|-- frontend/
|   |-- css/            Original CSS files
|   |-- js/             Original JavaScript files
|   `-- images/         Original static images
|-- database/
|   `-- dvmd_db.sql     Local database dump
|-- docker-compose.yml  Local MySQL service
|-- start-local.bat     Starts the PHP built-in server
`-- .env.example        Example local environment variables
```

## Local Setup

### 1. Start MySQL With Docker

```powershell
docker compose up -d mysql
```

Local database settings:

```text
Host: 127.0.0.1
Port: 3307
Database: dvmd_db
User: root
Password: empty
```

The SQL dump in `database/dvmd_db.sql` is imported automatically when the Docker MySQL volume is created for the first time.

If the database was already created before the latest dump update, reset the local Docker database with:

```powershell
docker compose down -v
docker compose up -d mysql
```

Only run the reset command when you are okay deleting the local Docker MySQL data.

### 2. Start PHP

Use the included script:

```powershell
.\start-local.bat
```

Or run PHP manually:

```powershell
E:\xampp\php\php.exe -S 127.0.0.1:8001 -t .
```

### 3. Open The App

Use this URL:

```text
http://127.0.0.1:8001/backend/loginpage.php
```

Do not open `frontend/login.html`. In this branch, `frontend/` contains CSS, JavaScript, and images only. The working pages are still PHP pages inside `backend/`.

## Test Accounts

All dashboard test users use this password:

```text
Password@123
```

| Role | Email |
| --- | --- |
| Ketua Kampung | `eric@gmail.com` |
| Ketua Kampung | `wei@gmail.com` |
| Ketua Kampung | `zhou@gmail.com` |
| Penghulu | `lim@gmail.com` |
| Penghulu | `foo@gmail.com` |
| Pejabat Daerah | `yeekientanpro@gmail.com` |
| Pejabat Daerah | `wee@gmail.com` |

Role redirects after login:

| Role ID | Dashboard |
| --- | --- |
| `0` | `backend/ketuakampungdashboard.php` |
| `1` | `backend/penghuludashboard.php` |
| `2` | `backend/pejabatdaerahdashboard.php` |

## Configuration

Database connection defaults are stored in `backend/includes/dbconnect.php` and are set for local Docker MySQL.

They can be overridden with environment variables:

```text
DVMD_DB_HOST=127.0.0.1
DVMD_DB_PORT=3307
DVMD_DB_USER=root
DVMD_DB_PASSWORD=
DVMD_DB_NAME=dvmd_db
```

Forgot/reset password email uses PHPMailer. SMTP credentials should be set with environment variables instead of hardcoding them:

```text
DVMD_SMTP_USER=
DVMD_SMTP_PASSWORD=
DVMD_SMTP_FROM_EMAIL=
```

Use `.env.example` as a reference. The PHP built-in server does not automatically load `.env` files, so set environment variables in your terminal or system environment if you need to override the defaults.

## Local URLs

Main pages:

```text
http://127.0.0.1:8001/backend/loginpage.php
http://127.0.0.1:8001/backend/registerpage.php
http://127.0.0.1:8001/backend/forgotpasswordpage.php
```

Static assets:

```text
http://127.0.0.1:8001/frontend/css/style.css
http://127.0.0.1:8001/frontend/js/reports.js
http://127.0.0.1:8001/frontend/images/background.png
```

Existing data endpoints:

```text
http://127.0.0.1:8001/backend/get_reports.php
http://127.0.0.1:8001/backend/weather_api.php
http://127.0.0.1:8001/backend/export_incidents.php
```

## Troubleshooting

- If login works but reports/weather do not show, confirm PHP is running from the repository root with `-t .`.
- If database connection fails, confirm Docker MySQL is running and port `3307` is not used by another MySQL service.
- If the dashboard shows old data after changing `database/dvmd_db.sql`, reset the Docker volume with `docker compose down -v`.
- If forgot/reset password email fails, check the `DVMD_SMTP_*` environment variables.

## Notes

- The original dashboard layout, CSS, and PHP behavior are intentionally preserved.
- This is a conservative folder split, not a full API/frontend rewrite.
- The old cPanel database settings were removed from the active connection path; local development points to Docker MySQL by default.
