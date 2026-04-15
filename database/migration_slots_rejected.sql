-- Idopont elutasitas jelzo oszlop
ALTER TABLE vv_orders
    ADD COLUMN slots_rejected_at DATETIME NULL AFTER selected_slot_id;
