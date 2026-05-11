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
|-- k8s/                Docker Desktop Kubernetes manifests
|-- Dockerfile          PHP Apache image for Kubernetes/demo deployment
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

## Kubernetes Local Demo

This project can run on Docker Desktop Kubernetes as one PHP web pod plus one MySQL pod.

### 1. Build The Web Image

```powershell
docker build -t dvmd-web:local .
```

The image uses `php:8.2-apache`, enables `mysqli`, copies the current project into Apache, and redirects `/` to `/backend/loginpage.php`.

For Docker Desktop Kubernetes, the local image tag `dvmd-web:local` can be used directly. For a cloud cluster, push the image to a registry first and update `k8s/app.yaml`.

### 2. Apply Kubernetes Manifests

Make sure Docker Desktop Kubernetes is enabled, then run:

```powershell
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/mysql.yaml
kubectl apply -f k8s/app.yaml
```

Check the deployment:

```powershell
kubectl get pods -n dvmd
kubectl get svc -n dvmd
```

Open:

```text
http://localhost:30081/backend/loginpage.php
```

If Docker Desktop kind does not expose the NodePort directly, start a port-forward in another PowerShell window:

```powershell
kubectl port-forward -n dvmd svc/dvmd-web 30081:80
```

Then open the same URL:

```text
http://localhost:30081/backend/loginpage.php
```

For the cloud CI/CD flow, the deployment image is pulled from GHCR:

```text
ghcr.io/shiebin-bit/dvmd-emergency-module:<commit-sha>
```

For local Docker Desktop testing, override the image back to the local build if needed:

```powershell
kubectl -n dvmd set image deployment/dvmd-web web=dvmd-web:local
kubectl -n dvmd rollout status deployment/dvmd-web
```

### Kubernetes Database Settings

Inside Kubernetes, the PHP app does not use `127.0.0.1:3307`. It connects through the MySQL service:

```text
DVMD_DB_HOST=dvmd-mysql
DVMD_DB_PORT=3306
DVMD_DB_USER=root
DVMD_DB_PASSWORD=
DVMD_DB_NAME=dvmd_db
```

The SQL seed is stored in `k8s/configmap.yaml` from `database/dvmd_db.sql` and is mounted into the MySQL container at `/docker-entrypoint-initdb.d/01-dvmd.sql`.

If you change the SQL seed after MySQL has already started, reset the Kubernetes database PVC:

```powershell
kubectl delete -f k8s/mysql.yaml
kubectl delete pvc dvmd-mysql-pvc -n dvmd
kubectl apply -f k8s/mysql.yaml
```

Only delete the PVC when you are okay losing the local Kubernetes MySQL data.

### Optional Secrets

`k8s/secret.yaml.example` contains placeholders for SMTP and New Relic values. Do not commit real secrets.

To use it locally:

```powershell
Copy-Item k8s/secret.yaml.example k8s/secret.yaml
notepad k8s/secret.yaml
kubectl apply -f k8s/secret.yaml
```

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
- If Kubernetes login page cannot connect to MySQL, confirm the `dvmd-mysql` pod is `Running` and `k8s/configmap.yaml` has `DVMD_DB_HOST=dvmd-mysql`.
- If `http://localhost:30081` does not load, run `kubectl port-forward -n dvmd svc/dvmd-web 30081:80` and keep that terminal open during the demo.
- If the dashboard shows old data after changing `database/dvmd_db.sql`, reset the Docker volume with `docker compose down -v`.
- If forgot/reset password email fails, check the `DVMD_SMTP_*` environment variables.

## CI/CD Pipeline

GitHub Actions is split into two workflows:

```text
.github/workflows/ci.yml  automatic CI
.github/workflows/cd.yml  manual CD
```

CI runs on push and pull request:

```text
Composer install
PHP lint
PHPUnit smoke tests
Docker build
Snyk dependency/container/IaC scans when SNYK_TOKEN is configured
Push Docker image to GHCR when not running on a pull request
```

The Docker image is published to:

```text
ghcr.io/shiebin-bit/dvmd-emergency-module
```

CD is manual. In GitHub Actions, open the `CD` workflow and click `Run workflow`. The deploy job:

```text
Runs Terraform
Creates/updates one Google Compute Engine VM
Installs k3s on the VM
Copies k8s manifests to the VM
Applies the manifests with k3s kubectl
Updates dvmd-web to the GHCR image for the current commit
```

When manually running CD, `image_tag` is optional. Leave it empty to deploy the current workflow commit SHA, or enter a GHCR tag such as `latest`.

### GitHub Variables

Add these under GitHub `Settings > Secrets and variables > Actions > Variables`:

```text
GCP_PROJECT_ID
TF_STATE_BUCKET
```

| Variable | Example | Used by | Purpose |
| --- | --- | --- | --- |
| `GCP_PROJECT_ID` | `my-gcp-project-id` | CD | Google Cloud project where Terraform creates the k3s VM. |
| `TF_STATE_BUCKET` | `my-dvmd-terraform-state` | CD | GCS bucket used by Terraform remote state. Create it once before the first CD run. |

### GitHub Secrets

Add these under GitHub `Settings > Secrets and variables > Actions > Secrets`:

```text
SNYK_TOKEN
GCP_SA_KEY
GCE_SSH_PRIVATE_KEY
GCE_SSH_PUBLIC_KEY
```

| Secret | Required | Used by | Purpose |
| --- | --- | --- | --- |
| `SNYK_TOKEN` | Recommended | CI | Runs Snyk dependency, container, and IaC scans. If missing, CI skips Snyk. |
| `GCP_SA_KEY` | Yes for CD | CD | Google service account JSON for Terraform. |
| `GCE_SSH_PRIVATE_KEY` | Yes for CD | CD | Private SSH key used by GitHub Actions to connect to the k3s VM. |
| `GCE_SSH_PUBLIC_KEY` | Yes for CD | CD/Terraform | Public SSH key injected into the GCE VM metadata. |
| `GHCR_READ_TOKEN` | Only if GHCR image is private | CD/k3s | GitHub PAT with `read:packages` so k3s can pull a private GHCR image. |

For `GCP_SA_KEY`, create a Google Cloud service account with enough permission to manage Compute Engine, firewall rules, and the Terraform state bucket, then paste the full JSON key as the secret value.

For the SSH keys, generate a pair and put the public key in `GCE_SSH_PUBLIC_KEY`, private key in `GCE_SSH_PRIVATE_KEY`.

Optional secret if the GHCR package is private:

```text
GHCR_READ_TOKEN
```

For the simplest demo, set the GHCR package visibility to public so the k3s VM can pull the image without `GHCR_READ_TOKEN`.

## Terraform

Terraform files are in `terraform/`. The configuration creates:

```text
1 Google Compute Engine VM
Firewall rule for SSH 22
Firewall rule for app NodePort 30081
k3s installed through startup script
```

Default location:

```text
Region: asia-southeast1
Zone: asia-southeast1-a
Machine: e2-medium
```

After a successful manual CD run, open:

```text
http://<GCP_VM_EXTERNAL_IP>:30081/backend/loginpage.php
```

## Notes

- The original dashboard layout, CSS, and PHP behavior are intentionally preserved.
- This is a conservative folder split, not a full API/frontend rewrite.
- The old cPanel database settings were removed from the active connection path; local development points to Docker MySQL by default.
