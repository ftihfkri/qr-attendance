# Koperasi Attendance (Laravel 9 + MySQL)

QR-based attendance: shareholders/staff scan a QR, fill a short form
(name, koperasi ID, phone, email) and check in. Admins/staff log in to a
dashboard with the QR, a live attendee list, CSV export, manual check-in,
and an admin-set geofence. Admins also manage users (roles: admin / staff).

Default login created on first run: **admin / Admin1234** (change it).

---

## Run locally (XAMPP MySQL)

```bash
composer install
cp .env.example .env        # then edit DB_* (see below) and run:
php artisan key:generate
# create the database once (XAMPP root / no password):
php -r "(new PDO('mysql:host=127.0.0.1','root',''))->exec('CREATE DATABASE IF NOT EXISTS koperasi_attendance');"
php artisan migrate --seed  # creates tables + the admin user
php artisan serve           # http://127.0.0.1:8000
```

Local `.env` DB block:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=koperasi_attendance
DB_USERNAME=root
DB_PASSWORD=
```

Open `http://127.0.0.1:8000/login` → `admin` / `Admin1234`.
Public check-in page (what the QR points to): `http://127.0.0.1:8000/checkin`.

---

## Deploy on Railway (Docker — no build config needed)

The repo ships a `Dockerfile` + `railway.toml`, so Railway builds it directly
(PHP 8.2 + nginx + supervisor). On boot it runs migrations and seeds the admin.

1. **New Railway project** → Deploy from this repo (use the `laravel` branch),
   or `railway up` from this folder.
2. **Add a MySQL database** (＋ New → Database → MySQL) in the same project/region.
3. In the **app service → Variables**, set:
   ```
   APP_KEY=            # paste output of: php artisan key:generate --show
   APP_ENV=production
   APP_DEBUG=false
   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   # optional first-admin override (defaults to admin / Admin1234):
   BOOTSTRAP_ADMIN_USER=admin
   BOOTSTRAP_ADMIN_PASS=change-me
   ```
   Do **not** set `PORT` (Railway provides it; nginx uses it).
4. **Deploy.** Logs should show migrations running and `supervisord` starting.
   Visit `https://<your-app>.up.railway.app/login` → log in with the admin user.

> Keep the MySQL service in the **same region** as the app, or use the MySQL
> service's public host for `DB_HOST`, so the database is reachable.

---

## Roles
- **admin**: full access incl. **Users** management (create/change/delete, assign roles).
- **staff**: same dashboard (QR, list, export, venue, manual check-in) but **no Users** page.
- **Public register** page creates **staff** accounts.
