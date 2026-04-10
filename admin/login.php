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
        body {
            font-family: 'Inter', 'Source Sans Pro', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
        }
        .login-page {
            background: transparent;
        }
        .login-box {
            width: 400px;
            max-width: 95vw;
        }
        .card {
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.05);
        }
        .card-header {
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            border-radius: 16px 16px 0 0 !important;
            padding: 30px;
        }
        .login-logo img {
            max-width: 180px;
            margin-bottom: 8px;
            background: #fff;
            padding: 6px 14px;
            border-radius: 8px;
        }
        .login-logo p {
            color: rgba(255,255,255,0.85);
            font-size: 13px;
            margin: 0;
        }
        .card-body {
            padding: 30px;
        }
        .input-group-text {
            background: #f8f9fa;
            border-right: 0;
        }
        .form-control {
            border-left: 0;
            font-size: 15px;
            padding: 10px 12px;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #4A90E2;
        }
        .form-control:focus + .input-group-append .input-group-text,
        .input-group:focus-within .input-group-text {
            border-color: #4A90E2;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: transform 0.1s, box-shadow 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(74,144,226,0.4);
            background: linear-gradient(135deg, #5a9ee6 0%, #4088c7 100%);
        }
        .alert {
            border-radius: 8px;
            font-size: 14px;
        }
        .login-footer {
            text-align: center;
            padding: 15px;
            color: rgba(255,255,255,0.4);
            font-size: 12px;
        }
    </style>
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="card">
            <div class="card-header text-center" id="login-header">
                <div class="login-logo">
                    <img src="../veresvill_logo.webp" alt="VeresVill">
                    <p>Villamos Felülvizsgálat CRM</p>
                </div>
            </div>
            <div class="card-body">
                <p class="login-box-msg text-muted">Jelentkezzen be a rendszerbe</p>

                <div id="login-error" class="alert alert-danger d-none"></div>

                <form id="login-form">
                    <div class="input-group mb-3">
                        <input type="email" id="login-email" class="form-control" placeholder="Email cím" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" id="login-password" class="form-control" placeholder="Jelszó" required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-lock"></span></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" id="login-btn" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Bejelentkezés
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="login-footer">
            &copy; <span id="year"></span> VeresVill CRM
        </div>
    </div>

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

        errorDiv.classList.add('d-none');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Bejelentkezés...';

        try {
            const apiBase = window.location.pathname.replace(/\/admin\/.*$/, '/api');
            const res = await fetch(apiBase + '/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });

            const data = await res.json();

            if (data.success) {
                localStorage.setItem('vv_token', data.data.token);
                localStorage.setItem('vv_user', JSON.stringify(data.data.user));
                window.location.href = 'index.php';
            } else {
                errorDiv.textContent = data.message || 'Hibás email vagy jelszó.';
                errorDiv.classList.remove('d-none');
            }
        } catch (err) {
            errorDiv.textContent = 'Hálózati hiba. Próbálja újra később.';
            errorDiv.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i>Bejelentkezés';
        }
    });
    </script>
</body>
</html>
