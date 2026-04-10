<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>VeresVill CRM</title>
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    <!-- FullCalendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <!-- Egyedi stílusok -->
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Bal: sidebar toggle -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link font-weight-bold" id="navbar-title">Dashboard</span>
            </li>
        </ul>

        <!-- Jobb: értesítések + profil -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item" id="notification-bell">
                <a class="nav-link" href="#" id="notification-toggle">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-danger navbar-badge d-none" id="notification-count">0</span>
                </a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#">
                    <i class="fas fa-user-circle mr-1"></i>
                    <span id="navbar-username"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" onclick="VV.logout(); return false;">
                        <i class="fas fa-sign-out-alt mr-2"></i>Kijelentkezés
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Logo -->
        <a href="#" class="brand-link text-center" onclick="navigateTo('dashboard'); return false;">
            <img src="../veresvill_logo.webp" alt="VeresVill" style="max-height: 36px;">
        </a>

        <!-- Sidebar menü -->
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="dashboard" onclick="navigateTo('dashboard'); return false;">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="orders" onclick="navigateTo('orders'); return false;">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>
                                Megrendelések
                                <span class="badge badge-danger right d-none" id="sidebar-orders-badge">0</span>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="calendar" onclick="navigateTo('calendar'); return false;">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>Naptár</p>
                        </a>
                    </li>
                    <li class="nav-item" id="nav-users" style="display: none;">
                        <a href="#" class="nav-link" data-page="users" onclick="navigateTo('users'); return false;">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Felhasználók</p>
                        </a>
                    </li>
                    <li class="nav-header">RENDSZER</li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-page="settings" onclick="navigateTo('settings'); return false;">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Beállítások</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Tartalom -->
    <div class="content-wrapper">
        <div id="page-content">
            <div class="vv-loading">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="main-footer text-sm">
        <div class="float-right d-none d-sm-inline">
            VeresVill CRM v1.0
        </div>
        <strong>&copy; <span id="footer-year"></span> VeresVill</strong> - Villamos Felülvizsgálat
    </footer>
</div>

<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
<!-- FullCalendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.17/locales/hu.global.min.js"></script>
<!-- App JS -->
<script src="assets/js/app.js"></script>

<script>
// Oldal inicializálás
document.getElementById('footer-year').textContent = new Date().getFullYear();

// Felhasználó név a navbarban
const user = VV.getUser();
if (user) {
    document.getElementById('navbar-username').textContent = user.name;
    // Admin menüpontok megjelenítése
    if (user.role === 'admin') {
        document.getElementById('nav-users').style.display = '';
    }
}

// Navigáció
function navigateTo(page) {
    // Aktív menüpont
    document.querySelectorAll('.nav-sidebar .nav-link').forEach(el => el.classList.remove('active'));
    const activeLink = document.querySelector(`.nav-link[data-page="${page}"]`);
    if (activeLink) activeLink.classList.add('active');

    // Navbar cím
    const titles = {
        dashboard: 'Dashboard',
        orders: 'Megrendelések',
        calendar: 'Naptár',
        users: 'Felhasználók',
        settings: 'Beállítások'
    };
    document.getElementById('navbar-title').textContent = titles[page] || page;

    // URL hash frissítés
    window.location.hash = page;

    // Oldal betöltés
    VV.loadPage(page);

    // Mobilon sidebar bezárás
    if (window.innerWidth < 992) {
        $('body').removeClass('sidebar-open').addClass('sidebar-collapse');
    }
}

// Hash-alapú navigáció
function handleHash() {
    const page = window.location.hash.replace('#', '') || 'dashboard';
    navigateTo(page);
}

window.addEventListener('hashchange', handleHash);

// Első betöltés
handleHash();

// Sidebar badge-ek frissítése 30 másodpercenként
VV.updateSidebarBadges();
setInterval(() => VV.updateSidebarBadges(), 30000);
</script>
</body>
</html>
