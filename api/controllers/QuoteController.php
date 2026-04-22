<?php
/**
 * QuoteController - Árajánlatok kezelése
 */
class QuoteController {

    /**
     * POST orders/{id}/quote
     * Árajánlat küldése (admin)
     */
    public function sendQuote(string $orderId, array $input = []): void {
        $pdo  = getDbConnection();
        $user = Auth::user();
        $orderId = (int) $orderId;

        $v = new Validator($input);
        $v->required('amount', 'Összeg')
          ->numeric('amount', 'Összeg');

        if ($v->fails()) {
            Response::error($v->firstError(), 422, $v->errors());
        }

        $amount = $v->getInt('amount');
        $energyCertAmount = isset($input['energy_cert_amount']) && (int) $input['energy_cert_amount'] > 0
            ? (int) $input['energy_cert_amount']
            : null;
        $slots  = $input['slots'] ?? [];

        // Megrendelés lekérése
        $stmt = $pdo->prepare("SELECT * FROM vv_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Megrendelés nem található.', 404);
        }

        // Státusz ellenőrzés - csak 'uj' státuszból lehet ajánlatot küldeni
        $allowedTransitions = ORDER_STATUS_TRANSITIONS[$order['status']] ?? [];
        if (!in_array(ORDER_STATUS_QUOTE_SENT, $allowedTransitions, true)) {
            Response::error('Ebben a státuszban nem küldhető árajánlat.', 422);
        }

        // Slot-ok validálása
        foreach ($slots as $i => $slot) {
            $sv = new Validator($slot);
            $sv->required('worker_id', 'Munkás')
               ->required('date', 'Dátum')
               ->date('date', 'Y-m-d', 'Dátum')
               ->required('start', 'Kezdés')
               ->time('start', 'Kezdés')
               ->required('end', 'Befejezés')
               ->time('end', 'Befejezés');

            if ($sv->fails()) {
                Response::error("Időpont #" . ($i + 1) . ": " . $sv->firstError(), 422);
            }
        }

        // Quote token generálás
        $quoteToken = bin2hex(random_bytes(16));
        $expiresAt  = date('Y-m-d H:i:s', strtotime('+' . QUOTE_TOKEN_EXPIRY_DAYS . ' days'));

        $pdo->beginTransaction();

        try {
            // Megrendelés frissítése
            $stmt = $pdo->prepare("
                UPDATE vv_orders
                SET status = ?, quote_amount = ?, energy_certificate_amount = ?, quote_token = ?, quote_token_expires = ?, slots_rejected_at = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([ORDER_STATUS_QUOTE_SENT, $amount, $energyCertAmount, $quoteToken, $expiresAt, $orderId]);

            // Régi slot-ok törlése (ha újraküldjük)
            $stmt = $pdo->prepare("DELETE FROM vv_time_slots WHERE order_id = ?");
            $stmt->execute([$orderId]);

            // Új slot-ok mentése
            $stmt = $pdo->prepare("
                INSERT INTO vv_time_slots (order_id, worker_id, slot_date, slot_start, slot_end)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($slots as $slot) {
                $stmt->execute([
                    $orderId,
                    (int) $slot['worker_id'],
                    $slot['date'],
                    $slot['start'],
                    $slot['end'],
                ]);
            }

            // Státusz napló
            $stmt = $pdo->prepare("
                INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note)
                VALUES (?, ?, ?, ?, ?)
            ");
            $logNote = $order['status'] === ORDER_STATUS_QUOTE_SENT
                ? "Árajánlat módosítva és újraküldve: {$amount} Ft"
                : "Árajánlat küldve: {$amount} Ft";
            $stmt->execute([
                $orderId,
                $order['status'],
                ORDER_STATUS_QUOTE_SENT,
                $user['id'],
                $logNote,
            ]);

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            Response::error('Hiba történt az árajánlat mentésekor.', 500);
        }

        // Email küldés az ügyfélnek
        try {
            require_once __DIR__ . '/../services/MailService.php';
            // Frissített order adatok a tokennel
            $stmt = $pdo->prepare("SELECT * FROM vv_orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $updatedOrder = $stmt->fetch();

            // Slotok az adatbázisból (ID-val együtt)
            $stmt = $pdo->prepare("SELECT * FROM vv_time_slots WHERE order_id = ? ORDER BY slot_date ASC, slot_start ASC");
            $stmt->execute([$orderId]);
            $savedSlots = $stmt->fetchAll();

            // Slot formátum a sablonhoz
            $emailSlots = array_map(function($s) {
                return [
                    'id' => $s['id'],
                    'start_time' => $s['slot_date'] . ' ' . $s['slot_start'],
                    'end_time' => $s['slot_date'] . ' ' . $s['slot_end'],
                ];
            }, $savedSlots);

            MailService::sendQuoteEmail($updatedOrder, $amount, $emailSlots);
        } catch (\Exception $mailError) {
            error_log('Quote email error: ' . $mailError->getMessage());
        }

        Response::success([
            'order_id'    => $orderId,
            'quote_token' => $quoteToken,
            'amount'      => $amount,
            'expires_at'  => $expiresAt,
            'slots_count' => count($slots),
        ], 'Árajánlat sikeresen elküldve.');
    }

    /**
     * GET quote/view/{token}
     * Árajánlat megtekintése (ügyfél - publikus)
     */
    public function viewQuote(string $token): void {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.customer_name,
                o.customer_email,
                o.customer_phone,
                o.customer_address,
                o.is_company,
                o.property_type,
                o.size,
                o.urgency,
                o.status,
                o.quote_amount,
                o.energy_certificate_amount,
                o.quote_token_expires,
                o.message
            FROM vv_orders o
            WHERE o.quote_token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Az árajánlat nem található vagy érvénytelen.', 404);
        }

        // Lejárat ellenőrzés
        if (strtotime($order['quote_token_expires']) < time()) {
            Response::error('Az árajánlat lejárt.', 410);
        }

        // Label-ek
        $order['property_type_label'] = PROPERTY_TYPES[$order['property_type']] ?? $order['property_type'];
        $order['urgency_label']       = URGENCY_LABELS[$order['urgency']] ?? $order['urgency'];
        $order['status_label']        = ORDER_STATUSES[$order['status']] ?? $order['status'];

        // Időpontok
        $stmt = $pdo->prepare("
            SELECT ts.id, ts.slot_date, ts.slot_start, ts.slot_end, ts.is_selected, u.name as worker_name
            FROM vv_time_slots ts
            LEFT JOIN vv_users u ON ts.worker_id = u.id
            WHERE ts.order_id = ?
            ORDER BY ts.slot_date ASC, ts.slot_start ASC
        ");
        $stmt->execute([$order['id']]);
        $timeSlots = $stmt->fetchAll();

        // Ellenőrizzük, hogy az egyes slotok még szabadak-e
        foreach ($timeSlots as &$ts) {
            // Naptár események ütközés
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) as cnt FROM vv_calendar_events
                WHERE user_id = ? AND event_date = ? AND start_time < ? AND end_time > ?
            ");
            $checkStmt->execute([$ts['worker_id'] ?? 0, $ts['slot_date'], $ts['slot_end'], $ts['slot_start']]);
            $calConflict = (int) $checkStmt->fetch()['cnt'] > 0;

            // Másik megrendelés elfogadott slotja ütközik
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) as cnt FROM vv_time_slots
                WHERE worker_id = ? AND slot_date = ? AND slot_start < ? AND slot_end > ?
                AND is_selected = 1 AND order_id != ?
            ");
            $checkStmt->execute([$ts['worker_id'] ?? 0, $ts['slot_date'], $ts['slot_end'], $ts['slot_start'], $order['id']]);
            $slotConflict = (int) $checkStmt->fetch()['cnt'] > 0;

            $ts['is_available'] = !$calConflict && !$slotConflict;
        }
        unset($ts);

        $order['time_slots'] = $timeSlots;

        // Már elfogadott-e
        $order['is_accepted'] = in_array($order['status'], [
            ORDER_STATUS_ACCEPTED,
            ORDER_STATUS_TIME_SELECTED,
            ORDER_STATUS_DONE,
        ], true);

        Response::success($order);
    }

    /**
     * POST quote/accept/{token}
     * Árajánlat elfogadása + időpont kiválasztása (ügyfél - publikus)
     */
    public function acceptQuote(string $token, array $input = []): void {
        $pdo = getDbConnection();

        $slotId = isset($input['slot_id']) ? (int) $input['slot_id'] : 0;

        // Megrendelés lekérése token alapján
        $stmt = $pdo->prepare("
            SELECT o.*
            FROM vv_orders o
            WHERE o.quote_token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Az árajánlat nem található vagy érvénytelen.', 404);
        }

        // Lejárat ellenőrzés
        if (strtotime($order['quote_token_expires']) < time()) {
            Response::error('Az árajánlat lejárt.', 410);
        }

        // Státusz ellenőrzés - csak 'ajanlat_kuldve' státuszból fogadható el
        if ($order['status'] !== ORDER_STATUS_QUOTE_SENT) {
            Response::error('Ez az árajánlat már el lett fogadva.', 422);
        }

        $slot = null;

        if ($slotId > 0) {
            // Slot ellenőrzés
            $stmt = $pdo->prepare("
                SELECT ts.*, u.name as worker_name
                FROM vv_time_slots ts
                LEFT JOIN vv_users u ON ts.worker_id = u.id
                WHERE ts.id = ? AND ts.order_id = ?
                LIMIT 1
            ");
            $stmt->execute([$slotId, $order['id']]);
            $slot = $stmt->fetch();

            if (!$slot) {
                Response::error('A kiválasztott időpont nem tartozik ehhez a megrendeléshez.', 422);
            }

            // Ütközés ellenőrzés — max 2 foglalás engedélyezett ugyanarra az időpontra.
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt FROM vv_calendar_events
                WHERE user_id = ? AND event_date = ? AND start_time < ? AND end_time > ?
            ");
            $stmt->execute([$slot['worker_id'], $slot['slot_date'], $slot['slot_end'], $slot['slot_start']]);
            $calendarCount = (int) $stmt->fetch()['cnt'];

            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt FROM vv_time_slots
                WHERE worker_id = ? AND slot_date = ? AND slot_start < ? AND slot_end > ?
                AND is_selected = 1 AND order_id != ?
            ");
            $stmt->execute([$slot['worker_id'], $slot['slot_date'], $slot['slot_end'], $slot['slot_start'], $order['id']]);
            $otherSelectedCount = (int) $stmt->fetch()['cnt'];

            if ($calendarCount + $otherSelectedCount >= 2) {
                Response::error('Sajnáljuk, ezt az időpontot már ketten lefoglalták. Kérjük, válasszon másik időpontot!', 409);
            }
        }

        $newStatus = $slot ? ORDER_STATUS_TIME_SELECTED : ORDER_STATUS_ACCEPTED;

        $pdo->beginTransaction();

        try {
            if ($slot) {
                // Többi felajánlott slot törlése, a kiválasztott megmarad
                $pdo->prepare("DELETE FROM vv_time_slots WHERE order_id = ? AND id != ?")->execute([$order['id'], $slotId]);
                $pdo->prepare("UPDATE vv_time_slots SET is_selected = 1 WHERE id = ?")->execute([$slotId]);
            }

            // Megrendelés státusz frissítés
            $stmt = $pdo->prepare("UPDATE vv_orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $order['id']]);

            // Státusz napló
            $logNote = $slot
                ? "Ügyfél kiválasztotta az időpontot: {$slot['slot_date']} {$slot['slot_start']}-{$slot['slot_end']}"
                : 'Ügyfél elfogadta az árajánlatot (időpont nélkül).';
            $stmt = $pdo->prepare("
                INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note)
                VALUES (?, ?, ?, NULL, ?)
            ");
            $stmt->execute([$order['id'], $order['status'], $newStatus, $logNote]);

            if ($slot) {
                // Naptár esemény létrehozása
                $title = ($order['customer_name'] ?? '') . ' - ' . ($order['customer_address'] ?? '');
                $stmt = $pdo->prepare("
                    INSERT INTO vv_calendar_events (user_id, order_id, title, event_date, start_time, end_time, event_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $slot['worker_id'],
                    $order['id'],
                    $title,
                    $slot['slot_date'],
                    $slot['slot_start'],
                    $slot['slot_end'],
                    EVENT_APPOINTMENT,
                ]);
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            Response::error('Hiba történt az árajánlat elfogadásakor.', 500);
        }

        // Google Sheets + visszaigazoló email csak ha van slot
        if ($slot) {
            try {
                require_once __DIR__ . '/../services/GoogleCalendarService.php';
                GoogleCalendarService::appendAcceptedSlotRow($order, $slot);
            } catch (\Exception $sheetErr) {
                error_log('Sheets append exception: ' . $sheetErr->getMessage());
            }

            try {
                require_once __DIR__ . '/../services/MailService.php';
                MailService::sendCustomerConfirmation($order, $slot);
            } catch (\Exception $mailErr) {
                error_log('Customer confirmation mail exception: ' . $mailErr->getMessage());
            }
        }

        $msg = $slot ? 'Időpont sikeresen kiválasztva. Köszönjük!' : 'Árajánlat elfogadva. Hamarosan felvesszük Önnel a kapcsolatot!';
        Response::success([
            'order_id' => $order['id'],
            'slot'     => $slot,
        ], $msg);
    }

    /**
     * POST quote/reject-slots/{token}
     * Ugyfel jelzi: egyik idopont sem felel meg. Admin ertesitest kap.
     */
    public function rejectSlots(string $token, array $input = []): void {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT * FROM vv_orders WHERE quote_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Az árajánlat nem található vagy érvénytelen.', 404);
        }

        if (strtotime($order['quote_token_expires']) < time()) {
            Response::error('Az árajánlat lejárt.', 410);
        }

        if ($order['status'] !== ORDER_STATUS_QUOTE_SENT) {
            Response::error('Ez az árajánlat már el lett fogadva vagy lezárult.', 422);
        }

        $note = isset($input['note']) ? trim((string) $input['note']) : '';
        if (strlen($note) > 1000) {
            $note = substr($note, 0, 1000);
        }

        $stmt = $pdo->prepare("UPDATE vv_orders SET slots_rejected_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$order['id']]);

        $logNote = 'Ügyfél jelezte: egyik kiajánlott időpont sem felel meg.';
        if ($note !== '') {
            $logNote .= ' Megjegyzés: ' . $note;
        }

        $stmt = $pdo->prepare("
            INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note)
            VALUES (?, ?, ?, NULL, ?)
        ");
        $stmt->execute([$order['id'], $order['status'], $order['status'], $logNote]);

        try {
            require_once __DIR__ . '/../services/MailService.php';
            MailService::sendSlotRejectionAdminNotification($order, $note);
        } catch (\Exception $mailErr) {
            error_log('Slot rejection admin mail exception: ' . $mailErr->getMessage());
        }

        Response::success([
            'order_id' => $order['id'],
        ], 'Köszönjük, hogy jelezte! Hamarosan új időpontokkal keressük.');
    }

    /**
     * POST orders/{id}/resend-quote
     * Visszaallitja a megrendelest 'uj' statuszba hogy az admin uj idopontokat kuldhessen.
     * A regi kiajanlott slotok torlodnek. A quote_amount megmarad (elore kitoltheto).
     */
    public function resendQuote(string $orderId, array $input = []): void {
        $pdo  = getDbConnection();
        $user = Auth::user();
        $orderId = (int) $orderId;

        $stmt = $pdo->prepare("SELECT * FROM vv_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Megrendelés nem található.', 404);
        }

        if ($order['status'] !== ORDER_STATUS_QUOTE_SENT) {
            Response::error('Csak "Árajánlat küldve" állapotban lehet új időpontokat kiküldeni.', 422);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM vv_time_slots WHERE order_id = ?")->execute([$orderId]);

            $stmt = $pdo->prepare("
                UPDATE vv_orders
                SET status = ?, slots_rejected_at = NULL, quote_token = NULL, quote_token_expires = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([ORDER_STATUS_NEW, $orderId]);

            $stmt = $pdo->prepare("
                INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $order['status'],
                ORDER_STATUS_NEW,
                $user['id'],
                'Admin: új időpontok kiküldése — régi kiajánlott időpontok törölve.',
            ]);

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            Response::error('Hiba történt az új időpontok előkészítésekor.', 500);
        }

        Response::success(['order_id' => $orderId], 'Új időpontokat adhat meg a naptárban.');
    }

    /**
     * DELETE orders/{id}/slots/{slotId}
     * Kiajánlott időpont törlése (admin). Kiválasztott slot nem törölhető.
     */
    public function deleteSlot(string $orderId, string $slotId, array $input = []): void {
        $pdo = getDbConnection();
        $orderId = (int) $orderId;
        $slotId  = (int) $slotId;

        $stmt = $pdo->prepare("SELECT * FROM vv_time_slots WHERE id = ? AND order_id = ?");
        $stmt->execute([$slotId, $orderId]);
        $slot = $stmt->fetch();

        if (!$slot) {
            Response::error('Az időpont nem található.', 404);
        }

        if ((int) $slot['is_selected'] === 1) {
            Response::error('A már kiválasztott időpont nem törölhető.', 422);
        }

        $stmt = $pdo->prepare("DELETE FROM vv_time_slots WHERE id = ?");
        $stmt->execute([$slotId]);

        Response::success(['deleted' => $slotId], 'Időpont törölve.');
    }

    /**
     * POST orders/{id}/confirm-slot
     * Admin manuálisan véglegesít egy időpontot (pl. telefonos egyeztetés után).
     * Input: vagy { slot_id } egy meglévő kiajánlott időponthoz,
     *        vagy { date, start, end, worker_id? } új időpont hozzáadásához.
     * Státuszváltás idopont_kivalasztva-ra, naptár esemény + visszaigazoló email.
     */
    public function confirmSlot(string $orderId, array $input = []): void {
        $pdo  = getDbConnection();
        $user = Auth::user();
        $orderId = (int) $orderId;

        $stmt = $pdo->prepare("SELECT * FROM vv_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Megrendelés nem található.', 404);
        }

        $allowed = ORDER_STATUS_TRANSITIONS[$order['status']] ?? [];
        if (!in_array(ORDER_STATUS_TIME_SELECTED, $allowed, true)) {
            Response::error('Ebben a státuszban nem lehet időpontot véglegesíteni.', 422);
        }

        $slotId = isset($input['slot_id']) ? (int) $input['slot_id'] : 0;
        $slot = null;

        if ($slotId > 0) {
            $stmt = $pdo->prepare("
                SELECT ts.*, u.name as worker_name
                FROM vv_time_slots ts
                LEFT JOIN vv_users u ON ts.worker_id = u.id
                WHERE ts.id = ? AND ts.order_id = ?
                LIMIT 1
            ");
            $stmt->execute([$slotId, $orderId]);
            $slot = $stmt->fetch();

            if (!$slot) {
                Response::error('A megadott időpont nem tartozik ehhez a megrendeléshez.', 422);
            }
        } else {
            $v = new Validator($input);
            $v->required('date', 'Dátum')
              ->date('date', 'Y-m-d', 'Dátum')
              ->required('start', 'Kezdés')
              ->time('start', 'Kezdés')
              ->required('end', 'Befejezés')
              ->time('end', 'Befejezés');

            if ($v->fails()) {
                Response::error($v->firstError(), 422, $v->errors());
            }

            $workerId = isset($input['worker_id']) && (int) $input['worker_id'] > 0
                ? (int) $input['worker_id']
                : (int) $user['id'];

            $stmt = $pdo->prepare("
                INSERT INTO vv_time_slots (order_id, worker_id, slot_date, slot_start, slot_end)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$orderId, $workerId, $input['date'], $input['start'], $input['end']]);
            $newId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                SELECT ts.*, u.name as worker_name
                FROM vv_time_slots ts
                LEFT JOIN vv_users u ON ts.worker_id = u.id
                WHERE ts.id = ?
            ");
            $stmt->execute([$newId]);
            $slot = $stmt->fetch();
        }

        $pdo->beginTransaction();

        try {
            // Többi felajánlott slot törlése, a kiválasztott megmarad
            $pdo->prepare("DELETE FROM vv_time_slots WHERE order_id = ? AND id != ?")->execute([$orderId, $slot['id']]);
            $pdo->prepare("UPDATE vv_time_slots SET is_selected = 1 WHERE id = ?")->execute([$slot['id']]);

            $stmt = $pdo->prepare("
                UPDATE vv_orders
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([ORDER_STATUS_TIME_SELECTED, $orderId]);

            $stmt = $pdo->prepare("
                INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $order['status'],
                ORDER_STATUS_TIME_SELECTED,
                $user['id'],
                "Admin manuálisan véglegesítette az időpontot: {$slot['slot_date']} {$slot['slot_start']}-{$slot['slot_end']}",
            ]);

            $title = ($order['customer_name'] ?? '') . ' - ' . ($order['customer_address'] ?? '');
            $stmt = $pdo->prepare("
                INSERT INTO vv_calendar_events (user_id, order_id, title, event_date, start_time, end_time, event_type)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $slot['worker_id'],
                $orderId,
                $title,
                $slot['slot_date'],
                $slot['slot_start'],
                $slot['slot_end'],
                EVENT_APPOINTMENT,
            ]);

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            Response::error('Hiba történt az időpont véglegesítésekor.', 500);
        }

        try {
            require_once __DIR__ . '/../services/GoogleCalendarService.php';
            GoogleCalendarService::appendAcceptedSlotRow($order, $slot);
        } catch (\Exception $sheetErr) {
            error_log('Sheets append exception: ' . $sheetErr->getMessage());
        }

        try {
            require_once __DIR__ . '/../services/MailService.php';
            MailService::sendCustomerConfirmation($order, $slot);
        } catch (\Exception $mailErr) {
            error_log('Customer confirmation mail exception: ' . $mailErr->getMessage());
        }

        Response::success([
            'order_id' => $orderId,
            'slot'     => $slot,
        ], 'Időpont véglegesítve, visszaigazoló email elküldve.');
    }
}
