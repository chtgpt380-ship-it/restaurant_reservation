-- =====================================================================
-- Caba Cloud Analytics — Restaurant Management System
-- Database schema + seed data
-- Import with: mysql -u root -p restaurant_system < schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS restaurant_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant_system;

-- ── AUTH ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    role        ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    last_login  DATETIME NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account
-- Username: admin   Password: admin123
INSERT INTO users (username, password, full_name, role, is_active)
VALUES ('admin', '$2b$10$SwC2aSHFCqlVOhwnnGmswuNQq9RUQA3Q38vyZ97aaPMZ06IEhkD8e', 'System Administrator', 'admin', 1)
ON DUPLICATE KEY UPDATE username = username;

-- ── OLTP: MENU / TABLES / ORDERS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS menu_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS menu_items (
    item_id        INT AUTO_INCREMENT PRIMARY KEY,
    category_id    INT NOT NULL,
    name           VARCHAR(100) NOT NULL,
    price          DECIMAL(10,2) NOT NULL,
    inventory_qty  INT NOT NULL DEFAULT 0,
    is_available   TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES menu_categories(category_id)
);

CREATE TABLE IF NOT EXISTS dining_tables (
    table_id   INT AUTO_INCREMENT PRIMARY KEY,
    table_num  VARCHAR(10) NOT NULL,
    capacity   INT NOT NULL DEFAULT 4
);

CREATE TABLE IF NOT EXISTS orders (
    order_id     INT AUTO_INCREMENT PRIMARY KEY,
    table_id     INT NOT NULL,
    server_name  VARCHAR(100) NOT NULL,
    order_time   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status       ENUM('preparing','served','paid','cancelled') NOT NULL DEFAULT 'preparing',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (table_id) REFERENCES dining_tables(table_id)
);

CREATE TABLE IF NOT EXISTS order_details (
    detail_id   INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    item_id     INT NOT NULL,
    quantity    INT NOT NULL,
    unit_price  DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (item_id)  REFERENCES menu_items(item_id)
);

-- ── INGREDIENTS / RESTOCK ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS ingredients (
    ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    unit_cost     DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_qty     DECIMAL(10,2) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS ingredient_restock (
    restock_id  INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    status      ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending',
    total_cost  DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
);

CREATE TABLE IF NOT EXISTS restock_items (
    restock_item_id INT AUTO_INCREMENT PRIMARY KEY,
    restock_id      INT NOT NULL,
    ingredient_id   INT NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL,
    unit_cost       DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (restock_id) REFERENCES ingredient_restock(restock_id),
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id)
);

CREATE TABLE IF NOT EXISTS stock_movements (
    movement_id   INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    movement_type ENUM('restock','usage','adjustment') NOT NULL,
    quantity      DECIMAL(10,2) NOT NULL,
    reference_no  VARCHAR(50),
    notes         VARCHAR(255),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id)
);

-- ── OLAP: STAR SCHEMA FOR ANALYTICS ─────────────────────────────────
CREATE TABLE IF NOT EXISTS dim_time (
    time_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_datetime DATETIME NOT NULL UNIQUE,
    day_name      VARCHAR(15) NOT NULL,
    hour_num      TINYINT NOT NULL,
    meal_period   VARCHAR(20) NOT NULL,
    month_name    VARCHAR(15) NOT NULL,
    year_num      INT NOT NULL
);

CREATE TABLE IF NOT EXISTS dim_menu_item (
    dim_item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id     INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    category    VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS dim_service (
    dim_service_id INT AUTO_INCREMENT PRIMARY KEY,
    server_name    VARCHAR(100) NOT NULL,
    table_num      VARCHAR(10) NOT NULL
);

CREATE TABLE IF NOT EXISTS fact_restaurant_sales (
    fact_id         INT AUTO_INCREMENT PRIMARY KEY,
    time_id         INT NOT NULL,
    dim_item_id     INT NOT NULL,
    dim_service_id  INT NOT NULL,
    order_id        INT NOT NULL,
    quantity_sold   INT NOT NULL,
    unit_price      DECIMAL(10,2) NOT NULL,
    gross_amount    DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (time_id)        REFERENCES dim_time(time_id),
    FOREIGN KEY (dim_item_id)    REFERENCES dim_menu_item(dim_item_id),
    FOREIGN KEY (dim_service_id) REFERENCES dim_service(dim_service_id)
);

-- ── SAMPLE SEED DATA ─────────────────────────────────────────────────
INSERT INTO menu_categories (name) VALUES
('Appetizers'), ('Main Course'), ('Desserts'), ('Beverages');

INSERT INTO menu_items (category_id, name, price, inventory_qty, is_available) VALUES
(1, 'Spring Rolls', 120.00, 50, 1),
(1, 'Garlic Bread', 95.00, 60, 1),
(2, 'Sisig', 220.00, 40, 1),
(2, 'Adobo Rice Bowl', 180.00, 55, 1),
(3, 'Leche Flan', 90.00, 30, 1),
(3, 'Halo-Halo', 130.00, 25, 1),
(4, 'Iced Tea', 60.00, 100, 1),
(4, 'Calamansi Juice', 70.00, 100, 1);

INSERT INTO dining_tables (table_num, capacity) VALUES
('T1', 2), ('T2', 4), ('T3', 4), ('T4', 6), ('T5', 2);

INSERT INTO suppliers (name) VALUES ('Metro Fresh Supply'), ('Coastal Seafood Co.');

INSERT INTO ingredients (name, unit_cost, stock_qty) VALUES
('Rice (kg)', 55.00, 100),
('Chicken (kg)', 180.00, 50),
('Pork Belly (kg)', 250.00, 40),
('Calamansi (kg)', 90.00, 20);
