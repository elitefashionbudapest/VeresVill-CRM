<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Árajánlat - VeresVill</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #F0F4F8;
            color: #2C3E50;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 580px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            padding: 30px;
            text-align: center;
        }
        .header img { max-width: 180px; }
        .header p { color: rgba(255,255,255,0.85); font-size: 13px; margin-top: 8px; }
        .body { padding: 30px; }
        .greeting { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
        .subtitle { color: #5A6C7D; line-height: 1.6; margin-bottom: 25px; }

        .summary {
            background: #F8FAFB;
            border: 1px solid #E8F4FD;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 25px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }
        .summary-label { color: #5A6C7D; font-weight: 600; }
        .summary-value { color: #2C3E50; }

        .price-box {
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin-bottom: 30px;
        }
        .price-label { color: rgba(255,255,255,0.85); font-size: 14px; margin-bottom: 5px; }
        .price-amount { color: #fff; font-size: 36px; font-weight: 800; }
        .price-note { color: rgba(255,255,255,0.7); font-size: 12px; margin-top: 5px; }

        .slots-title { font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #2C3E50; }
        .slot-btn {
            display: block;
            width: 100%;
            padding: 16px 20px;
            margin-bottom: 10px;
            background: #fff;
            border: 2px solid #E8F4FD;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            color: #2C3E50;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }
        .slot-btn:hover {
            border-color: #4A90E2;
            background: #F0F7FF;
        }
        .slot-btn.selected {
            border-color: #4A90E2;
            background: #E8F4FD;
        }
        .slot-btn .slot-icon { color: #4A90E2; margin-right: 10px; }
        .slot-btn .slot-date { display: block; font-size: 13px; color: #5A6C7D; font-weight: 400; margin-top: 3px; margin-left: 28px; }

        .accept-btn {
            display: block;
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.2s;
        }
        .accept-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(76,175,80,0.4); }
        .accept-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        .footer {
            background: #2C3E50;
            padding: 20px 30px;
            text-align: center;
        }
        .footer p { color: rgba(255,255,255,0.5); font-size: 12px; }
        .footer a { color: rgba(255,255,255,0.7); text-decoration: none; }

        .error-msg {
            text-align: center;
            padding: 60px 30px;
        }
        .error-msg h2 { color: #FF6B6B; margin-bottom: 10px; }

        .loading { text-align: center; padding: 60px; color: #5A6C7D; }
        .loading .spinner {
            width: 40px; height: 40px;
            border: 4px solid #E8F4FD;
            border-top-color: #4A90E2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 480px) {
            body { padding: 10px; }
            .body { padding: 20px; }
            .price-amount { font-size: 28px; }
            .slot-btn { font-size: 14px; padding: 14px 16px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <img src="../veresvill_logo.webp" alt="VeresVill">
                <p>Villamos Biztonsági Felülvizsgálat</p>
            </div>
            <div id="content">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Árajánlat betöltése...</p>
                </div>
            </div>
            <div class="footer">
                <p>&copy; <script>document.write(new Date().getFullYear())</script> VeresVill</p>
                <p><a href="mailto:veresvill.ads@gmail.com">veresvill.ads@gmail.com</a> | <a href="tel:+36703686638">+36 70 368 6638</a></p>
            </div>
        </div>
    </div>

    <script>
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    const preselectedSlot = params.get('slot');
    let selectedSlotId = preselectedSlot ? parseInt(preselectedSlot) : null;

    async function loadQuote() {
        if (!token) {
            showError('Érvénytelen link.');
            return;
        }

        try {
            const res = await fetch(`../api/quote/view/${token}`);
            const data = await res.json();

            if (!data.success) {
                showError(data.message || 'Az árajánlat nem található vagy lejárt.');
                return;
            }

            renderQuote(data.data);
        } catch (e) {
            showError('Hiba történt. Kérjük, próbálja újra később.');
        }
    }

    function renderQuote(q) {
        // Magyar neveknél az utolsó szó a keresztnév (pl. "Németh Ádám" → "Ádám")
        const nameParts = q.customer_name.trim().split(' ');
        const firstName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : nameParts[0];
        const discountedAmount = q.quote_amount;
        const originalAmount = Math.ceil(discountedAmount / 0.9 / 1000) * 1000;
        const savings = originalAmount - discountedAmount;
        const amount = new Intl.NumberFormat('hu-HU').format(discountedAmount);
        const originalFormatted = new Intl.NumberFormat('hu-HU').format(originalAmount);
        const savingsFormatted = new Intl.NumberFormat('hu-HU').format(savings);

        const slotsHtml = (q.time_slots || []).map(s => {
            const d = new Date(s.slot_date);
            const dayName = d.toLocaleDateString('hu-HU', { weekday: 'long' });
            const dateStr = d.toLocaleDateString('hu-HU', { year: 'numeric', month: 'long', day: 'numeric' });
            const startTime = s.slot_start.substring(0, 5);
            const endTime = s.slot_end.substring(0, 5);
            const isSelected = s.id === selectedSlotId;
            const isAvailable = s.is_available !== false;

            if (!isAvailable) {
                return `
                    <div class="slot-btn" style="cursor:default;background:#fff3f3;border-color:#ffcdd2;">
                        <span style="color:#e57373;font-weight:700;">&#10005; Ezt az időpontot időközben lefoglalták</span>
                        <span class="slot-date" style="text-decoration:line-through;color:#bbb;">${dateStr} (${dayName}) ${startTime} - ${endTime}</span>
                    </div>
                `;
            }

            return `
                <button class="slot-btn ${isSelected ? 'selected' : ''}" onclick="selectSlot(${s.id}, this)">
                    <span class="slot-icon">&#128197;</span>${startTime} - ${endTime}
                    <span class="slot-date">${dateStr} (${dayName})</span>
                </button>
            `;
        }).join('');

        document.getElementById('content').innerHTML = `
            <div class="body">
                <div class="greeting">Tisztelt ${escHtml(firstName)}!</div>
                <p class="subtitle">Köszönjük megrendelését! Az Ön árajánlata elkészült. Kérjük, válasszon az alábbi időpontok közül.</p>

                <div class="summary">
                    <div class="summary-row">
                        <span class="summary-label">Helyszín</span>
                        <span class="summary-value">${escHtml(q.customer_address)}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Ingatlan</span>
                        <span class="summary-value">${escHtml(q.property_type_label)}, ${q.size} m²</span>
                    </div>
                </div>

                <div class="price-box">
                    <div style="color:rgba(255,255,255,0.6);font-size:14px;text-decoration:line-through;">${originalFormatted} Ft</div>
                    <div class="price-amount">${amount} Ft</div>
                    <div style="color:#FFD54F;font-size:15px;font-weight:700;margin-top:4px;">-10% kedvezmény (megtakarítás: ${savingsFormatted} Ft)</div>
                    <div class="price-note">Bruttó ár, tartalmazza az ÁFÁ-t</div>
                </div>

                <div class="slots-title">Válasszon időpontot:</div>
                ${slotsHtml}

                <button class="accept-btn" id="accept-btn" onclick="acceptQuote()" ${selectedSlotId ? '' : 'disabled'}>
                    &#10003; Elfogadom az árajánlatot és az időpontot
                </button>
            </div>
        `;
    }

    function selectSlot(slotId, btn) {
        selectedSlotId = slotId;
        document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('accept-btn').disabled = false;
    }

    async function acceptQuote() {
        if (!selectedSlotId) return;

        const btn = document.getElementById('accept-btn');
        btn.disabled = true;
        btn.textContent = 'Feldolgozás...';

        try {
            const res = await fetch(`../api/quote/accept/${token}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slot_id: selectedSlotId })
            });

            const data = await res.json();

            if (data.success) {
                window.location.href = 'quote-confirmed.php';
            } else {
                btn.disabled = false;
                btn.textContent = '✓ Elfogadom az árajánlatot és az időpontot';
                alert(data.message || 'Hiba történt. Kérjük, próbálja újra.');
            }
        } catch (e) {
            btn.disabled = false;
            btn.textContent = '✓ Elfogadom az árajánlatot és az időpontot';
            alert('Hálózati hiba. Kérjük, próbálja újra.');
        }
    }

    function showError(msg) {
        document.getElementById('content').innerHTML = `
            <div class="error-msg">
                <h2>&#9888; ${escHtml(msg)}</h2>
                <p style="color: #5A6C7D; margin-top: 15px;">Ha kérdése van, keressen minket:</p>
                <p style="margin-top: 10px;">
                    <a href="tel:+36703686638" style="color: #4A90E2; font-weight: 600;">+36 70 368 6638</a>
                </p>
            </div>
        `;
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    loadQuote();
    </script>
</body>
</html>
