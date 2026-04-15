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
        $slots  = $input['slots'] ?? [];

        if (empty($slots) || !is_array($slots)) {
            Response::error('Legalább egy időpont megadása kötelező.', 422);
        }

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
                SET status = ?, quote_amount = ?, quote_token = ?, quote_token_expires = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([ORDER_STATUS_QUOTE_SENT, $amount, $quoteToken, $expiresAt, $orderId]);

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
            $stmt->execute([
                $orderId,
                $order['status'],
                ORDER_STATUS_QUOTE_SENT,
                $user['id'],
                "Árajánlat küldve: {$amount} Ft",
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
                o.property_type,
                o.size,
                o.urgency,
                o.status,
                o.quote_amount,
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

        $v = new Validator($input);
        $v->required('slot_id', 'Időpont');

        if ($v->fails()) {
            Response::error($v->firstError(), 422, $v->errors());
        }

        $slotId = $v->getInt('slot_id');

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

        // Utkozes ellenorzes — max 2 foglalas engedelyezett ugyanarra az idopontra.
        // Osszeszamoljuk a megerositett esemenyek + mas megrendelesek elfogadott slotjait.
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

        $pdo->beginTransaction();

        try {
            // Slot kijelölése
            $stmt = $pdo->prepare("
                UPDATE vv_time_slots SET is_selected = 0 WHERE order_id = ?
            ");
            $stmt->execute([$order['id']]);

            $stmt = $pdo->prepare("
                UPDATE vv_time_slots SET is_selected = 1 WHERE id = ?
            ");
            $stmt->execute([$slotId]);

            // Megrendelés státusz frissítés
            $stmt = $pdo->prepare("
                UPDATE vv_orders
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([ORDER_STATUS_TIME_SELECTED, $order['id']]);

            // Státusz napló
            $stmt = $pdo->prepare("
                INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note)
                VALUES (?, ?, ?, NULL, ?)
            ");
            $stmt->execute([
                $order['id'],
                $order['status'],
                ORDER_STATUS_TIME_SELECTED,
                "Ügyfél kiválasztotta az időpontot: {$slot['slot_date']} {$slot['slot_start']}-{$slot['slot_end']}",
            ]);

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

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            Response::error('Hiba történt az időpont kiválasztásakor.', 500);
        }

        // Push értesítés küldése (placeholder - PushService még nem létezik)
        // PushService::notifyNewAppointment($slot['worker_id'], $order['id'], $slot);

        // Google Sheets: elfogadott időpont új sorként (nem kritikus)
        try {
            require_once __DIR__ . '/../services/GoogleCalendarService.php';
            GoogleCalendarService::appendAcceptedSlotRow($order, $slot);
        } catch (\Exception $sheetErr) {
            error_log('Sheets append exception: ' . $sheetErr->getMessage());
        }

        // Megrendelői visszaigazoló email az elfogadott időponttal (nem kritikus)
        try {
            require_once __DIR__ . '/../services/MailService.php';
            MailService::sendCustomerConfirmation($order, $slot);
        } catch (\Exception $mailErr) {
            error_log('Customer confirmation mail exception: ' . $mailErr->getMessage());
        }

        Response::success([
            'order_id' => $order['id'],
            'slot'     => $slot,
        ], 'Időpont sikeresen kiválasztva. Köszönjük!');
    }
}
