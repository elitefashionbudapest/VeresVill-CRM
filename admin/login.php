<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés - VeresVill CRM</title>
    <!-- AdminLTE CSS (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --vv-blue: #3B82F6;
            --vv-blue-dark: #2563EB;
            --vv-text: #1E293B;
            --vv-text-muted: #64748B;
            --vv-border: #E2E8F0;
        }
        * { -webkit-font-smoothing: antialiased; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #0F172A;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(59,130,246,0.12) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(59,130,246,0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(16,185,129,0.06) 0%, transparent 50%);
            animation: bgShift 20s ease-in-out infinite alternate;
        }
        @keyframes bgShift {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-2%, 2%) rotate(1deg); }
        }
        .login-page { background: transparent; }
        .login-box {
            width: 420px;
            max-width: 92vw;
            position: relative;
            z-index: 1;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05);
            border: none;
            backdrop-filter: blur(20px);
            background: rgba(255,255,255,0.97);
            overflow: hidden;
        }
        .login-header-section {
            padding: 36px 36px 24px;
            text-align: center;
        }
        .login-logo img {
            max-width: 170px;
            margin-bottom: 12px;
        }
        .login-subtitle {
            color: var(--vv-text-muted);
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 0;
        }
        .card-body {
            padding: 0 36px 36px;
        }
        .login-box-msg {
            font-size: 15px;
            font-weight: 500;
            color: var(--vv-text);
            margin-bottom: 24px;
            text-align: center;
        }
        .form-group-modern {
            margin-bottom: 16px;
        }
        .form-group-modern label {
            font-size: 12px;
            font-weight: 600;
            color: var(--vv-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
            display: block;
        }
        .form-group-modern .form-control {
            border: 1.5px solid var(--vv-border);
            border-radius: 10px;
            font-size: 15px;
            padding: 12px 16px;
            width: 100%;
            outline: none;
            transition: all 0.2s;
            background: #F8FAFC;
            color: var(--vv-text);
        }
        .form-group-modern .form-control:focus {
            border-color: var(--vv-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
            background: #fff;
        }
        .form-group-modern .form-control::placeholder {
            color: #94A3B8;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            background: var(--vv-blue);
            color: #fff;
            cursor: pointer;
            transition: all 0.2s;
            letter-spacing: 0.01em;
            margin-top: 8px;
        }
        .btn-login:hover {
            background: var(--vv-blue-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59,130,246,0.35);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .alert {
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            padding: 12px 16px;
        }
        .alert-danger {
            background: #FEF2F2;
            color: #DC2626;
        }
        .login-footer {
            text-align: center;
            padding: 20px;
            color: rgba(255,255,255,0.25);
            font-size: 12px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        .divider {
            height: 1px;
            background: var(--vv-border);
            margin: 0 36px;
        }
    </style>
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="card">
            <div class="login-header-section">
                <div class="login-logo">
                    <img src="../veresvill_logo.webp" alt="VeresVill">
                </div>
                <p class="login-subtitle">CRM Rendszer</p>
            </div>
            <div class="divider"></div>
            <div class="card-body">
                <p class="login-box-msg">Bejelentkezés</p>

                <div id="login-error" class="alert alert-danger d-none"></div>

                <form id="login-form">
                    <div class="form-group-modern">
                        <label for="login-email">Email cím</label>
                        <input type="email" id="login-email" class="form-control" placeholder="nev@veresvill.hu" required autofocus>
                    </div>
                    <div class="form-group-modern">
                        <label for="login-password">Jelszó</label>
                        <input type="password" id="login-password" class="form-control" placeholder="Jelszó" required>
                    </div>
                    <div class="form-group-modern" style="display:flex;justify-content:center;">
                        <div class="g-recaptcha" data-sitekey="6LeSaLcsAAAAAAn62qYS_ij6_sZCUpARPTAF0IqM"></div>
                    </div>
                    <button type="submit" id="login-btn" class="btn-login">
                        Bejelentkezés
                    </button>
                </form>
            </div>
        </div>
        <div class="login-footer">
            &copy; <span id="year"></span> VeresVill
        </div>
    </div>

    <!-- reCAPTCHA v2 -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

    <script>
    document.getElementById('year').textContent = new Date().getFullYear();

    // Ha már be van jelentkezve, irányítsuk az admin oldalra
    if (localStorage.getItem('vv_token')) {
        window.location.href = 'index.php';
    }

    document.getElementById('login-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('login-btn');
        const errorDiv = document.getElementById('login-error');
        const email = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value;
        const captcha = (typeof grecaptcha !== 'undefined') ? grecaptcha.getResponse() : '';

        if (!captcha) {
            errorDiv.textContent = 'Kérjük, igazolja hogy nem robot!';
            errorDiv.classList.remove('d-none');
            return;
        }

        errorDiv.classList.add('d-none');
        btn.disabled = true;
        btn.innerHTML = 'Bejelentkezés...';

        try {
            const apiBase = window.location.pathname.replace(/\/admin\/.*$/, '/api');
            const res = await fetch(apiBase + '/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password, recaptcha: captcha })
            });

            const data = await res.json();

            if (data.success) {
                localStorage.setItem('vv_token', data.data.token);
                localStorage.setItem('vv_user', JSON.stringify(data.data.user));
                window.location.href = 'index.php';
            } else {
                errorDiv.textContent = data.message || 'Hibás email vagy jelszó.';
                errorDiv.classList.remove('d-none');
                if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
            }
        } catch (err) {
            errorDiv.textContent = 'Hálózati hiba. Próbálja újra később.';
            errorDiv.classList.remove('d-none');
            if (typeof grecaptcha !== 'undefined') grecaptcha.reset();
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Bejelentkezés';
        }
    });
    </script>
</body>
</html>
