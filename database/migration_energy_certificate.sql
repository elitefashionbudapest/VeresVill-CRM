-- Energetikai tanusitvany opcio
ALTER TABLE vv_orders
    ADD COLUMN energy_certificate TINYINT(1) NOT NULL DEFAULT 0 AFTER message,
    ADD COLUMN energy_certificate_amount INT UNSIGNED NULL AFTER quote_amount;
