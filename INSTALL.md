# IT Management System - Installation Guide

## Requirements
- **Web Server**: Apache or Nginx
- **PHP**: Version 8.0 or higher
- **MySQL**: Version 5.7 or higher
- **Extensions**: `pdo_mysql`, `gd`, `mbstring`

## Installation Steps

1.  **Clone/Copy Files**
    - Copy all project files to your web server's root directory (e.g., `htdocs` or `www`).

2.  **Database Setup**
    - Create a new MySQL database named `it_management_system`.
    - There is no `schema.sql` in this repository. Apply the schema by logging in as an administrator and visiting
      `http://localhost/IT%20Management%20System/public/update_db.php`.
      This page is idempotent - it checks for each column, index and table before creating it, so it is safe to run more than once.
    - *Note:* `update_db.php` is a migration page, not a from-scratch installer. It adds missing columns, indexes and
      auxiliary tables to an existing `users` / `assets` schema, so those base tables must already exist.
    - *No default administrator account is shipped.* Create the initial admin account directly in the database, choosing
      a password of at least 10 characters containing uppercase, lowercase, a number and a special character. This policy
      is enforced by `is_password_strong()` in `includes/functions.php`.

3.  **Configuration**
    - Create a `.env` file in the project root. It is not committed (see `.gitignore`); `config/env.php` reads it and
      `config/database.php` falls back to its built-in defaults for any key you omit.
    - Set at least `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`. Also set `APP_KEY` (used by `encrypt_data()` /
      `decrypt_data()`) and `CRON_SECRET` (guards the endpoints under `public/cron/`) to long random values.
    - Run `composer install` to fetch the PHP dependencies into `vendor/`. `vendor/` is not committed, and mail sending
      fails without it.

4.  **Permissions**
    - Ensure the `uploads/` directory is writable by the web server.
    - `chmod -R 755 uploads/` (Linux/Mac)

5.  **Access the System**
    - Navigate to `http://localhost/IT Management System/public/` in your browser.

## Mail Setup (SMTP)
- Password reset and email verification both go out through `send_email()` (PHPMailer over SMTP). If the keys below are
  missing, no mail is sent at all.
- Set these in `.env`:
    - `MAIL_HOST` - SMTP host, e.g. `smtp.gmail.com`
    - `MAIL_PORT` - `587` for STARTTLS (default) or `465` for SSL
    - `MAIL_ENCRYPTION` - `tls` (default) or `ssl`
    - `MAIL_USER` / `MAIL_PASS` - SMTP account credentials
    - `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` - sender shown on outgoing mail
- **Gmail**: use a 16-character App Password, not the Google account password. Enable 2-Step Verification first, then
  generate one at `https://myaccount.google.com/apppasswords`.
- `send_email()` returns `false` and writes the reason to the PHP error log rather than pretending the mail was sent;
  each delivery attempt is also appended to `logs/email.log`.

## Security Notes
- **No Default Credentials**: No administrator account ships with this system. Create the first one with a password of
  at least 10 characters containing uppercase, lowercase, a number and a special character, and never commit it.
- **Production Deployment**:
    - Disable error reporting in `php.ini` (`display_errors = Off`).
    - Use HTTPS (SSL) for secure data transmission.
    - Restrict access to the `config/` directory via `.htaccess`.

## Features
- **Ticketing**: Submit, track, and resolve IT support tickets.
- **Inventory**: Manage IT assets, track status, and generate QR codes.
- **Staff Management**: Manage IT staff profiles and roles.
- **Reporting**: View analytics and export ticket data.
