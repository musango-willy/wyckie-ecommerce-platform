# Wyckie Engine: Administrative Commerce Platform

Wyckie Engine is a high-performance, lightweight monolithic e-commerce management dashboard. Engineered using **Native PHP (v8.x+)**, this platform was intentionally built as a lightweight, lightning-fast alternative to heavy frameworks like Laravel. It bypasses framework overhead to deliver near-instant layout rendering, modular request handling, and smooth transactional processing.

---

## 🚀 The Laravel Framework Inspiration

While this project is framework-free to maximize server efficiency, its core developer workflow is deeply inspired by Laravel’s best architectural practices:

* **Laravel-Inspired Environment Control (`.env`):** Utilizes Composer to pull in the `vlucas/phpdotenv` package. This mimics Laravel's security workflow by keeping database credentials and live Stripe API secret keys safely decoupled from raw code files.
* **Artisan-Style Seeder Simulation Tools:** Instead of forcing terminal commands like `php artisan db:seed`, this platform embeds a visual, one-click **Artisan-Style Seeder Trigger** directly into the administrator console. Clicking it builds and injects 50 fully structured retail products into MySQL instantly.

---

## 🔥 Core Architectural Features

### 1. Decoupled Persistence Layer (`Database.php`)
Data operations are isolated inside a custom database namespace (`Wyckie\EcommercePlatform`) using **PHP Data Objects (PDO)**. It implements strict prepared statements to eliminate SQL Injection risks and features a query routine that automatically flattens single-row queries to prevent multi-nested array extraction bugs.

### 2. Media Optimization Pipeline (`ImageProcessor.php`)
To maximize mobile performance and page speed metrics, the system features an image optimization engine. When publishing or editing inventory, raw media uploads are intercepted, cropped to a uniform square matrix (400×400 pixels), compressed, and re-encoded directly into the modern **WebP** asset format—dropping payload file sizes by up to 80%.

### 3. Split-Grid Admin Workspace & Relational Cart
* **Dual-Column Layout:** Built using modern CSS Grid and Flexbox variables for a clean, side-by-side workspace split.
* **Session-Linked Cart Sidebar:** Shoppers are tracked via secure cookie tokens. The cart runs relational join tables to display active quantities, line items, and running subtotal balances live on screen.
* **Dynamic Edit Modals:** Features an inline overlay form controller that passes structured row data on demand, allowing administrators to modify information or replace item images on the fly.

### 4. Native Stripe Secure Gateway Integration
Integrates directly with the official Stripe API client. Shopping summary items are bundled into verified `price_data` arrays, dollar values are safely converted to cents, and the browser is securely redirected to a hosted sandbox payment checkout screen. Upon payment, the active database cart is automatically flushed to prevent duplicate checkouts.

---

## 🛠️ Tech Stack & Requirements

* **Backend Engine:** Native PHP 8.x+ (with strict type hints)
* **Database Storage:** MySQL Server Engine (via PDO)
* **Package Management:** Composer (Stripe SDK, PHP-Dotenv)
* **Front-End Architecture:** Responsive Vanilla HTML5 / Inline CSS Variables
* **Version Control:** Git & GitHub Integration

---

## 💾 Local Environment Setup Installation Guide

Follow these steps to deploy and run the **Wyckie Engine** locally on your computer using XAMPP:

### 1. Clone the Workspace
Clone this repository directly into your local server web root directory (e.g., `C:\xampp\htdocs\`):
```bash
cd C:\xampp\htdocs
git clone https://github.com ecommerce
cd ecommerce
```

### 2. Pull Package Dependencies
Run Composer inside your terminal to download your environmental variables and payment gateway SDK packages:
```bash
composer install
```

### 3. Configure Your Environment (`.env`)
Create a new file named exactly `.env` in the root folder and configure your MySQL blocks alongside your Sandbox developer secret keys:
```env
DB_HOST="127.0.0.1"
DB_NAME="ecommerce_db"
DB_USER="root"
DB_PASS=""

STRIPE_SECRET_KEY="sk_test_your_secret_key_here"
STRIPE_WEBHOOK_SECRET="whsec_your_webhook_secret_here"

ADMIN_USER="admin"
ADMIN_PASS="YourSecurePassword2026"
```
*(Note: A custom `.gitignore` sheet is already bundled to guarantee your private `.env` text file never leaks onto public GitHub profile logs).*

### 4. Initialize Database Structure
Open your web browser and go to `http://localhost/phpmyadmin/`. Create a database named `ecommerce_db` and execute these queries inside the SQL terminal tab:
```sql
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL DEFAULT 'uploads/default.jpg'
);

CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stripe_session_id VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
);
```

### 5. Launch the Platform
Ensure your Apache and MySQL modules are toggled **ON** inside your XAMPP Control Panel application. Open your browser and navigate to:
```text
http://localhost/ecommerce/index.php
```
Click the green **⚡ Auto-Generate 50 Assorted Inventory Items** button to seed your inventory catalogue instantly!
