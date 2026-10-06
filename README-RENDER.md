# Odynasties — Render deployment

## What changed
- Removed dependence on XAMPP paths such as `/odynasties/...`.
- Added a Docker PHP 8.3 + Apache runtime for Render.
- Database credentials now come from environment variables.
- Removed schema-changing `ALTER TABLE`/`CREATE TABLE` calls from normal page requests.
- Added `/health.php` for Render health checks.
- Added `render.yaml` for easier service creation.
- Added upload-directory protection.
- Profile uploads still need persistent storage in production.

## Database
This app uses MySQL/MariaDB. Create the `odynasties` database with your MySQL provider, then import `database/odynasties.sql` into that database. The SQL file intentionally does not run `CREATE DATABASE` or `USE`, because hosted MySQL users often do not have permission to create databases. Then set:
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

Render does not provide a built-in managed MySQL database, so use a MySQL-compatible external provider and copy its host, port, database name, username and password into Render environment variables.

## Render
1. Push this folder to a GitHub repository.
2. In Render, create a Web Service from the repository.
3. Choose Docker runtime (or use the included `render.yaml`).
4. Set the DB environment variables.
5. Deploy.
6. Confirm `/health.php` returns `OK`.
7. Set a strong temporary `ADMIN_SETUP_TOKEN`, open `/setup_admin.php`, create the first administrator, then immediately remove `ADMIN_SETUP_TOKEN` from Render.

## Uploads
Render web-service files are ephemeral unless you attach a persistent disk. If members can upload profile pictures, attach a persistent disk mounted at:
`/var/www/html/uploads`
on a paid Render service, or move uploads to object storage later.

## Security before launch
- Change/remove the sample administrator creation credentials.
- Use a strong production DB password.
- Do not commit `.env` files or real secrets.
- Enable HTTPS (Render terminates TLS for public web services).
