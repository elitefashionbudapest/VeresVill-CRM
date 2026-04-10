<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Árajánlat elfogadva - VeresVill</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #F0F4F8;
            color: #2C3E50;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { max-width: 580px; width: 100%; }
        .card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .check-icon {
            width: 80px; height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
            color: #fff;
            animation: scaleIn 0.5s ease;
        }
        @keyframes scaleIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .header h1 { color: #fff; font-size: 24px; font-weight: 700; }
        .header p { color: rgba(255,255,255,0.85); font-size: 14px; margin-top: 8px; }

        .body {
            padding: 35px 30px;
            text-align: center;
        }
        .body h2 { font-size: 20px; margin-bottom: 15px; color: #2C3E50; }
        .body p { color: #5A6C7D; line-height: 1.7; margin-bottom: 10px; }

        .steps {
            background: #F8FAFB;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .step:last-child { margin-bottom: 0; }
        .step-num {
            background: #4A90E2;
            color: #fff;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            margin-right: 12px;
            margin-top: 2px;
        }
        .step-text strong { color: #2C3E50; }
        .step-text small { color: #5A6C7D; }

        .contact {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #E8F4FD;
        }
        .contact a {
            color: #4A90E2;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
        }

        .footer {
            background: #2C3E50;
            padding: 20px;
            text-align: center;
        }
        .footer img { max-width: 100px; margin-bottom: 5px; }
        .footer p { color: rgba(255,255,255,0.5); font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="check-icon">&#10003;</div>
                <h1>Árajánlat elfogadva!</h1>
                <p>Köszönjük bizalmát!</p>
            </div>
            <div class="body">
                <h2>Mi történik ezután?</h2>
                <div class="steps">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-text">
                            <strong>Visszaigazolás</strong><br>
                            <small>Kollégánk hamarosan felveszi Önnel a kapcsolatot az egyeztetett időpont megerősítéséhez.</small>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-text">
                            <strong>Helyszíni felülvizsgálat</strong><br>
                            <small>Szakembereink a megbeszélt időpontban érkeznek és elvégzik a méréseket (30-45 perc).</small>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-text">
                            <strong>Jegyzőkönyv kézbesítés</strong><br>
                            <small>A mérést követő munkanapon emailben megküldjük a hivatalos jegyzőkönyvet.</small>
                        </div>
                    </div>
                </div>

                <div class="contact">
                    <p>Kérdése van? Hívjon minket bátran:</p>
                    <a href="tel:+36703686638">+36 70 368 6638</a>
                </div>
            </div>
            <div class="footer">
                <img src="../veresvill_logo.webp" alt="VeresVill">
                <p>Villamos Biztonsági Felülvizsgálat - Budapest és Pest megye</p>
            </div>
        </div>
    </div>
</body>
</html>
