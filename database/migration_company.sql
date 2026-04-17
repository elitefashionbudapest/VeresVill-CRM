-- Céges megrendelő mező hozzáadása
ALTER TABLE vv_orders
    ADD COLUMN is_company TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_address;
