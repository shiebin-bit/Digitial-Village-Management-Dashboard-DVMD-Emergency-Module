# DVMD Emergency Management System

This version keeps the original dashboard code and UI, but arranges the project into `frontend/` and `backend/` folders.

## Structure

```text
.
|-- frontend/
|   |-- css/          Original CSS files
|   |-- js/           Original JavaScript files
|   `-- images/       Original static images
|-- backend/
|   |-- includes/     DB connection, auth, footer
|   |-- management/   Incident/SOS update and delete handlers
|   |-- dvmd/         Mobile APIs and uploaded incident/SOS images
|   |-- vendor/       PHPMailer
|   |-- loginpage.php
|   |-- ketuakampungdashboard.php
|   |-- penghuludashboard.php
|   `-- pejabatdaerahdashboard.php
|-- database/
|   `-- dvmd_db.sql
|-- docker-compose.yml
`-- start-local.bat
```

## Run With Docker MySQL

Start MySQL:

```powershell
docker compose up -d mysql
```

The database runs on:

```text
host: 127.0.0.1
port: 3307
database: dvmd_db
user: root
password: empty
```

If you already have XAMPP MySQL using port `3307`, stop it first or change the Docker port in `docker-compose.yml`.

Start PHP:

```powershell
E:\xampp\php\php.exe -S 127.0.0.1:8001 -t .
```

Or double-click:

```text
start-local.bat
```

Open:

```text
http://127.0.0.1:8001/backend/loginpage.php
```

## Test Accounts

All dashboard users use:

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

## Notes

- The original dashboard layout, CSS, and PHP flow are preserved.
- Only paths were adjusted so PHP files load CSS/JS/images from `frontend/`.
- DB settings are in `backend/includes/dbconnect.php` and can be overridden with `.env.example` variables.
