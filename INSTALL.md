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
    - Import the `sql/schema.sql` file into your database.
    - *Default Admin Credentials:*
        - Username: `admin`
        - Password: `admin123`

3.  **Configuration**
    - Open `config/database.php`.
    - Update the database credentials (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) if necessary.

4.  **Permissions**
    - Ensure the `uploads/` directory is writable by the web server.
    - `chmod -R 755 uploads/` (Linux/Mac)

5.  **Access the System**
    - Navigate to `http://localhost/IT Management System/public/` in your browser.

## Security Notes
- **Change Default Password**: Immediately change the default admin password after logging in.
- **Production Deployment**:
    - Disable error reporting in `php.ini` (`display_errors = Off`).
    - Use HTTPS (SSL) for secure data transmission.
    - Restrict access to the `config/` directory via `.htaccess`.

## Features
- **Ticketing**: Submit, track, and resolve IT support tickets.
- **Inventory**: Manage IT assets, track status, and generate QR codes.
- **Staff Management**: Manage IT staff profiles and roles.
- **Reporting**: View analytics and export ticket data.
