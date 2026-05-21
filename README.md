# JEWEL ONE 1 MATALE — POS System
## Complete Billing & Point of Sale System

---

## 🚀 QUICK SETUP GUIDE

### Requirements
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Apache / Nginx web server
- Web browser (Chrome, Firefox, Edge)

---

## 📁 INSTALLATION STEPS

### Step 1 — Copy Files
Copy the entire `jewel_one/` folder to your web server root:
- **XAMPP:** `C:/xampp/htdocs/jewel_one/`
- **WAMP:**  `C:/wamp64/www/jewel_one/`
- **Linux:** `/var/www/html/jewel_one/`

### Step 2 — Create Database
1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Click **New** → Create database: `jewel_one_pos`
3. Click **Import** → Upload `database.sql`
4. Click **Go** to run

### Step 3 — Configure Database
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');     // Your MySQL host
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password
define('DB_NAME', 'jewel_one_pos'); // Database name
define('APP_URL', 'http://localhost/jewel_one'); // Your URL
```

### Step 4 — Set Permissions (Linux)
```bash
chmod 755 uploads/ backup/
chmod 644 includes/config.php
```

### Step 5 — Access the System
Open your browser: `http://localhost/jewel_one`

---

## 🔐 DEFAULT LOGIN CREDENTIALS

| Role    | Username | Password |
|---------|----------|----------|
| Admin   | admin    | admin123 |
| Cashier | cashier1 | admin123 |

**⚠️ Change passwords immediately after first login!**

---

## 📋 SYSTEM FEATURES

### Billing / POS
- Create bills with unlimited items
- Per-item discount (% or fixed amount)
- Real-time total calculation
- Customer info (name, phone)
- Payment methods: Cash, Card, Bank Transfer
- Auto bill number generation
- Keyboard shortcuts (Alt+A, Alt+S, Alt+P)

### Thermal Receipt Printing
- 58mm and 80mm thermal printer support
- Auto-print after saving
- Clean black & white receipt layout
- CSS print media queries

### Dashboard
- Today / Weekly / Monthly / Yearly sales
- Sales trend chart (last 7 days)
- Best selling products
- Top cashiers performance
- Recent transactions

### Reports (Admin only)
- Date range filter
- Daily breakdown table
- Revenue bar chart
- Payment method pie chart
- Top products report
- Cashier performance report
- Print report button

### Bill History
- Search by bill number / customer / phone
- Filter by cashier and date range
- Reprint receipts
- Duplicate bills
- Delete bills (Admin only)
- Pagination

### User Management (Admin)
- Create admin / cashier accounts
- Enable / Disable users
- Reset passwords
- View last login and bill counts

### Settings (Admin)
- Shop name, address, phone, email
- Logo upload
- Currency symbol
- Tax enable/disable + percentage
- Receipt footer text
- Thermal printer size
- Theme mode (dark/light)

### Database Backup (Admin)
- One-click SQL dump
- Download backup file
- View all stored backups
- Database size information

---

## 🎨 UI THEME

**Dark luxury theme** using:
- Colors: Black, Gold (#C9A84C), White
- Fonts: Cormorant Garamond (display), Josefin Sans (body)
- Sidebar navigation with collapsible menu
- Toast notifications
- Modal popups
- Loading spinners
- Chart.js for analytics

---

## 🛡️ SECURITY FEATURES

- Password hashing (bcrypt cost 12)
- Session management with timeout
- CSRF token protection (forms)
- XSS protection (htmlspecialchars)
- SQL injection prevention (PDO prepared statements)
- Role-based access control (Admin/Cashier)
- Remember-me cookie (HttpOnly, SameSite)

---

## ⌨️ KEYBOARD SHORTCUTS

| Shortcut | Action         |
|----------|----------------|
| Alt + A  | Add item row   |
| Alt + S  | Save bill      |
| Alt + P  | Print receipt  |
| Alt + C  | Clear bill     |

---

## 📁 FILE STRUCTURE

```
jewel_one/
├── index.php              → Redirect to login/dashboard
├── login.php              → Login page
├── logout.php             → Logout handler
├── dashboard.php          → Analytics dashboard
├── billing.php            → POS / new bill
├── bill_history.php       → View all bills
├── reports.php            → Reports & analytics
├── print_receipt.php      → Thermal receipt page
├── database.sql           → Database schema + seed data
│
├── includes/
│   ├── config.php         → DB config + helpers
│   ├── auth.php           → Auth functions + CSRF
│   ├── header.php         → Page header + sidebar
│   └── footer.php         → Page footer + JS
│
├── ajax/
│   ├── save_bill.php      → Save bill AJAX handler
│   ├── delete_bill.php    → Delete bill AJAX
│   └── save_setting.php   → Theme setting AJAX
│
├── modules/
│   ├── users.php          → User management
│   ├── settings.php       → System settings
│   ├── backup.php         → Database backup
│   └── download_backup.php→ Backup file download
│
├── css/
│   ├── style.css          → Main luxury dark theme
│   └── extra.css          → Sidebar collapsed styles
│
├── js/
│   └── app.js             → Billing logic + utilities
│
├── uploads/logo/          → Uploaded shop logos
└── backup/                → Database backup files
```

---

## 🖨️ THERMAL PRINTER SETUP

1. Install your thermal printer driver
2. Set paper size to 58mm or 80mm in printer settings
3. In POS Settings → choose matching thermal size
4. Open a receipt and click "Print Receipt"
5. Select your thermal printer from the print dialog
6. Disable headers/footers in browser print settings

---

## ❓ TROUBLESHOOTING

**"Database connection failed"**
→ Check `config.php` credentials, ensure MySQL is running

**"Call to undefined function"**
→ PHP version issue — requires PHP 7.4+

**Session keeps expiring**
→ Increase `SESSION_TIMEOUT` in `config.php`

**Logo not uploading**
→ Check `uploads/logo/` folder permissions (chmod 755)

**Receipt not printing correctly**
→ Match thermal size setting to actual printer width

---

*JEWEL ONE 1 MATALE POS System v1.0 — Built for real jewelry business usage*
