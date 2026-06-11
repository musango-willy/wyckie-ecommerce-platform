# Wyckie Ecommerce Platform Architecture

A high-performance, modular monolithic ecommerce backend and control dashboard built from scratch using PHP 8.2+ and a custom Composer infrastructure. This platform seamlessly integrates standard cart mechanics, multi-item Stripe payment checkouts, background webhooks, automated media optimization pipelines, and financial ledger data exporting capabilities.

## 🚀 Key Framework Features

- **Custom Namespace Autoloading:** Managed natively via PSR-4 mapping rules right inside the `composer.json` layer.
- **Relational Shopping Cart Table System:** Direct MySQL row-mapping engine powered by secure PHP Data Object (PDO) prepared statements.
- **Dynamic Stripe Invoice Checkouts:** Leverages the official Stripe SDK to construct multi-line transaction schemas for single-click payment redirections.
- **Secure Background Webhook Listener:** Automatically captures, validates signatures (`whsec`), and processes cloud payment receipts to alter transaction states.
- **Media Optimization Pipeline:** Automated 300x300px cropping, padding, and layout aspect ratios handled instantly by the Intervention Image V3 GD engine.
- **Excel Spreadsheet Distribution:** Dynamic generation and streaming of active billing data logs utilizing PhpSpreadsheet engines.
- **Gateway Isolation Layer:** Implements secure PHP Session cookies and `.env` isolation protocols to prevent token exposures.

## 📁 System Directory Tree

```text
ecommerce/
├── vendor/                # Composer third-party package matrices (Ignored)
├── uploads/               # Processed and compressed image assets (Ignored)
├── Database.php           # Secure MySQL PDO query mapping controller class
├── PaymentGateway.php     # Stripe SDK wrapper for single & dynamic cart checkouts
├── ImageProcessor.php     # Intervention Image compression & thumbnail handler class
├── ReportGenerator.php    # PhpSpreadsheet workbook compiler script
├── webhook.php            # Security signature verifier endpoint for Stripe events
├── index.php              # Secure Administrative Control Dashboard UI View
├── .env.example           # Shared distribution template file for environment setups
├── .gitignore             # Git instructions to block secure keys from repository leaks
├── composer.json          # Root-level configuration declarations & PSR-4 bindings
└── README.md              # Global infrastructure documentation sheet
```

## 🛠️ Installation & Workspace Setup

Follow these operational deployment steps to install this package locally:

### 1. Clone the Code and Install Packages
Clone this repository directly into your XAMPP server root directory (`C:\xampp\htdocs\ecommerce`), then open your terminal inside that folder and run:
```bash
composer install
```

### 2. Configure Your System Variables
Copy the distribution config layout or create a new file named `.env` right next to `composer.json` and paste your credentials:
```text
STRIPE_SECRET_KEY="sk_test_your_secret_key_here"
STRIPE_WEBHOOK_SECRET="whsec_your_cli_signing_secret_here"
DB_HOST="127.0.0.1"
DB_NAME="ecommerce_db"
DB_USER="root"
DB_PASS=""
ADMIN_USER="admin"
ADMIN_PASS="SecretWyckie2026"
```

### 3. Initialize Your Local MySQL Tables
Launch **phpMyAdmin** (`http://localhost/phpmyadmin/`), create a database named `ecommerce_db`, and execute this SQL migration query sequence to build your relational data tables:

```sql
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `image_path` VARCHAR(255) DEFAULT 'uploads/thumb_product.jpg',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `carts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cart_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `stripe_session_id` VARCHAR(255) NOT NULL UNIQUE,
  `customer_email` VARCHAR(255) DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `payment_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `products` (`id`, `name`, `description`, `price`) VALUES 
(1, 'Premium Wireless Headphones', 'High-fidelity audio with noise cancellation.', 35.00),
(2, 'Ergonomic Office Chair', 'Breathable mesh lumbar support with adjustment levers.', 120.00);
```

### 4. Deploy the Local Webhook Bridge Tunnel
Open a separate PowerShell window, enter your Stripe executable file location, and initialize your traffic forwarder to link Stripe directly to your server:
```powershell
.\stripe.exe login
.\stripe.exe listen --forward-to localhost/ecommerce/webhook.php
```

### 5. Open Your Browser Control Dashboard
Ensure the Apache and MySQL modules are green inside your XAMPP framework app, open your browser, and navigate to:
```text
http://localhost/ecommerce/index.php
```
*Log in using your secure `.env` user profiles (e.g., `admin` / `SecretWyckie2026`) to activate the command desk!*

## 📜 Framework Licensing
Distributed under the permissive open-source **MIT License** terms. See standard definitions for package allocation parameters.
