-- Migration: emlékeztető email nyomkövetés
-- Futtatás: phpMyAdmin SQL fülön

ALTER TABLE vv_orders
    ADD COLUMN reminder_sent_at DATETIME NULL AFTER slots_rejected_at;

-- quote_sent_at kitöltése visszamenőleg azon megrendeléseknél ahol NULL,
-- de már ment az árajánlat (a státusznapló alapján)
UPDATE vv_orders o
SET o.quote_sent_at = (
    SELECT MIN(changed_at)
    FROM vv_order_status_log
    WHERE order_id = o.id AND new_status = 'ajanlat_kuldve'
)
WHERE o.quote_sent_at IS NULL
  AND o.status IN ('ajanlat_kuldve','elfogadva','idopont_kivalasztva','elvegezve');
