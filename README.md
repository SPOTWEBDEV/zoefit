# ZoeFeeds — Installation Guide

## Requirements
- PHP 8.1+
- MySQL 8.0+
- Apache/Nginx with mod_rewrite
- Composer (optional)

---

## 1. Setup Database

```sql
CREATE DATABASE zoefeeds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the schema:
```bash
mysql -u root -p zoefeeds < database/schema.sql
```

---

## 2. Configure App

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'zoefeeds');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

Edit `config/config.php`:
```php
define('APP_URL', 'https://yourdomain.com/zoefeeds');
```

---

## 3. File Permissions

```bash
chmod 755 uploads/
chmod 644 uploads/.htaccess
chmod 600 config/database.php
chmod 600 config/config.php
```

---

## 4. Default Login Credentials

### Super Admin
- URL: `/admin/super-login.php`
- Email: `superadmin@zoefeeds.com`
- Password: `password` *(change immediately!)*

> The schema inserts a default super admin with bcrypt hash of "password". Change it on first login.

---

## 5. First Steps After Login

1. Login as **Super Admin** → Create Admin account
2. Login as **Admin** → Generate codes (Admin → Codes → Generate)
3. Admin → Create a Vendor account
4. Admin → Assign codes to vendor
5. Vendor logs in → Credits codes to users
6. Users register → Redeem codes → Enter draws

---

## 6. Directory Structure

```
/admin          Admin & Super Admin pages
/user           User-facing pages
/vendor         Vendor portal pages
/ajax           All AJAX/API endpoints
/assets
  /css          app.css (Poppins + custom styles)
  /js           app.js (all JS logic)
/components     Reusable PHP components (sidebars, header, footer)
/config         Database & app configuration
/database       SQL schema
/uploads        User-uploaded files (banners, images)
```

---

## 7. Security Notes

- All passwords are bcrypt hashed (cost 12)
- All forms use CSRF protection
- All DB queries use PDO prepared statements
- XSS protection via `htmlspecialchars()` on all output
- Transfer PIN is separately hashed
- Rate limiting on login (5 attempts, 15 min lockout)
- Uploads restricted to images only via .htaccess
- Config and database directories blocked from web access

---

## 8. Features Summary

| Feature | Location |
|---------|----------|
| Landing page with draws + slideshow | `/index.php` |
| User registration & login | `/user/register.php`, `/user/login.php` |
| User dashboard (OPay style) | `/user/dashboard.php` |
| Code redemption | `/user/redeem.php` |
| Code transfer with PIN | `/user/transfer.php` |
| Draw entry (multi-code) | `/user/draw-detail.php` |
| Live draw watch page | `/user/live-draw.php` |
| Vendor dashboard | `/vendor/dashboard.php` |
| Vendor code distribution | `/vendor/credit-user.php` |
| Admin dashboard | `/admin/dashboard.php` |
| Admin code generation (bulk) | `/admin/codes.php` |
| Admin draw management | `/admin/draws.php` |
| Live draw admin control | `/admin/live-draw-admin.php` |
| Super Admin panel | `/admin/super-dashboard.php` |
| Audit logs | `/admin/audit-logs.php` |

---

## 9. Poppins Font

All pages use **Poppins** from Google Fonts (loaded via CDN).
Font weights included: 300, 400, 500, 600, 700, 800.
