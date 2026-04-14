<!-- Beállítások oldal -->
<section class="content-header">
    <div class="container-fluid">
        <h1><i class="fas fa-sliders-h mr-2"></i>Beállítások</h1>
    </div>
</section>

<section class="content">
    <div class="container-fluid">

        <!-- Google Naptár -->
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-google mr-2" style="color:#4285F4;"></i>Google Naptár szinkron</h3>
            </div>
            <div class="card-body">
                <div id="gcal-loading" class="text-center py-3">
                    <i class="fas fa-spinner fa-spin text-primary"></i> Betöltés...
                </div>

                <!-- Nem csatlakoztatva -->
                <div id="gcal-disconnected" style="display:none;">
                    <div style="text-align:center;padding:20px 0;">
                        <div style="width:56px;height:56px;border-radius:14px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <i class="fab fa-google" style="font-size:24px;color:#4285F4;"></i>
                        </div>
                        <h4 style="font-size:16px;font-weight:700;color:#1E293B;margin-bottom:8px;">Google Naptár csatlakoztatása</h4>
                        <p style="color:#64748B;font-size:13px;max-width:360px;margin:0 auto 20px;line-height:1.6;">
                            Csatlakoztasd a Google Naptárad, hogy az időpontok automatikusan szinkronizálódjanak mindkét irányba.
                        </p>
                        <button class="btn btn-primary" onclick="connectGoogle()">
                            <i class="fab fa-google mr-2"></i>Google Naptár csatlakoztatása
                        </button>
                    </div>
                </div>

                <!-- Csatlakoztatva -->
                <div id="gcal-connected" style="display:none;">
                    <div class="d-flex align-items-center mb-3" style="background:#D1FAE5;padding:12px 16px;border-radius:10px;">
                        <i class="fas fa-check-circle text-success mr-2"></i>
                        <div>
                            <strong style="font-size:14px;color:#059669;">Google Naptár csatlakoztatva</strong><br>
                            <small class="text-muted" id="gcal-last-sync"></small>
                        </div>
                    </div>

                    <!-- Naptár kiválasztás -->
                    <div class="form-group">
                        <label>Szinkronizálandó naptár</label>
                        <select id="gcal-calendar-select" class="form-control" onchange="changeCalendar()">
                            <option value="primary">Elsődleges naptár</option>
                        </select>
                        <small class="text-muted">Válaszd ki, melyik Google naptáradba szinkronizáljon.</small>
                    </div>

                    <!-- Műveletek -->
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <button class="btn btn-primary" onclick="manualSync()" id="gcal-sync-btn">
                            <i class="fas fa-sync-alt mr-1"></i>Szinkronizálás most
                        </button>
                        <button class="btn btn-outline-danger" onclick="disconnectGoogle()">
                            <i class="fas fa-unlink mr-1"></i>Lecsatlakoztatás
                        </button>
                    </div>

                    <!-- Szinkron eredmény -->
                    <div id="gcal-sync-result" style="display:none;" class="mt-3"></div>
                </div>

                <div id="gcal-setup-guide" style="display:none;"></div>
            </div>
        </div>

        <!-- Push értesítések -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bell mr-2"></i>Push értesítések</h3>
            </div>
            <div class="card-body">
                <p>Kapjon azonnali értesítést, ha új megrendelés érkezik vagy árajánlatot fogadnak el.</p>
                <div id="push-status" class="mb-3">
                    <span class="badge badge-secondary" id="push-badge">Ellenőrzés...</span>
                </div>
                <button class="btn btn-primary" id="push-enable-btn" onclick="enablePush()" style="display:none">
                    <i class="fas fa-bell mr-1"></i>Értesítések engedélyezése
                </button>
                <button class="btn btn-outline-danger" id="push-disable-btn" onclick="disablePush()" style="display:none">
                    <i class="fas fa-bell-slash mr-1"></i>Értesítések kikapcsolása
                </button>
                <button class="btn btn-outline-secondary ml-2" onclick="testPush()">
                    <i class="fas fa-paper-plane mr-1"></i>Teszt üzenet
                </button>
            </div>
        </div>

        <!-- Profil -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user mr-2"></i>Profil</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Név</label>
                            <input type="text" id="profile-name" class="form-control" disabled>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="profile-email" class="form-control" disabled>
                        </div>
                    </div>
                </div>
                <p class="text-muted"><small>A profil módosításához forduljon az adminisztrátorhoz.</small></p>
            </div>
        </div>

        <!-- Rendszer info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Rendszer</h3>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="font-weight-bold">Verzió</td><td>VeresVill CRM v1.0</td></tr>
                    <tr><td class="font-weight-bold">Felhasználó</td><td id="sys-user"></td></tr>
                    <tr><td class="font-weight-bold">Szerepkör</td><td id="sys-role"></td></tr>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
async function init_settings() {
    var user = VV.getUser();
    if (user) {
        document.getElementById('profile-name').value = user.name;
        document.getElementById('profile-email').value = user.email;
        document.getElementById('sys-user').textContent = user.name + ' (' + user.email + ')';
        document.getElementById('sys-role').textContent = user.role === 'admin' ? 'Adminisztrátor' : 'Munkatárs';
    }

    // Redirect URI megjelenítés
    var redirectUri = VV.apiBase + '/google/callback';
    var el = document.getElementById('gcal-redirect-uri');
    if (el) el.textContent = window.location.origin + redirectUri;

    checkPushStatus();
    checkGoogleStatus();
}

// ============================================
// Google Naptár
// ============================================
async function checkGoogleStatus() {
    document.getElementById('gcal-loading').style.display = '';
    document.getElementById('gcal-disconnected').style.display = 'none';
    document.getElementById('gcal-connected').style.display = 'none';

    var data = await VV.get('google/status');
    document.getElementById('gcal-loading').style.display = 'none';

    if (data && data.success && data.data.connected) {
        document.getElementById('gcal-connected').style.display = '';
        document.getElementById('gcal-setup-guide').style.display = 'none';

        var lastSync = data.data.last_sync;
        document.getElementById('gcal-last-sync').textContent = lastSync
            ? 'Utolsó szinkron: ' + VV.formatDateTime(lastSync)
            : 'Még nem szinkronizált';

        // Naptárak betöltése
        loadGoogleCalendars(data.data.calendar_id);
    } else {
        document.getElementById('gcal-disconnected').style.display = '';
        document.getElementById('gcal-setup-guide').style.display = '';
    }
}

async function connectGoogle() {
    var data = await VV.get('google/auth');
    if (data && data.success && data.data.url) {
        // Popup ablakban nyitjuk meg
        var popup = window.open(data.data.url, 'google_auth', 'width=500,height=700,scrollbars=yes');

        // Figyeljük mikor zárul be
        var check = setInterval(function() {
            if (popup.closed) {
                clearInterval(check);
                checkGoogleStatus();
                VV.toast('Google Naptár állapot frissítve.', 'info');
            }
        }, 1000);
    } else {
        VV.toast('A Google Client ID nincs beállítva a .env fájlban. Nézd meg az útmutatót lent.', 'error');
    }
}

async function disconnectGoogle() {
    if (!await VV.confirm('Biztosan lecsatlakoztatod a Google Naptárat? A szinkron leáll.')) return;
    var res = await VV.post('google/disconnect');
    if (res && res.success) {
        VV.toast('Google Naptár lecsatlakoztatva.', 'success');
        checkGoogleStatus();
    }
}

async function manualSync() {
    var btn = document.getElementById('gcal-sync-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Szinkronizálás...';

    var res = await VV.post('google/sync');

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Szinkronizálás most';

    var resultDiv = document.getElementById('gcal-sync-result');
    if (res && res.success) {
        var d = res.data;
        resultDiv.innerHTML = '<div style="background:#D1FAE5;padding:12px 16px;border-radius:8px;font-size:13px;color:#065F46;">' +
            '<i class="fas fa-check-circle mr-1"></i>' +
            '<strong>Szinkron kész!</strong> ' +
            'Feltöltve: ' + (d.pushed?.synced || 0) + ', ' +
            'Importálva: ' + (d.pulled?.created || 0) + ', ' +
            'Frissítve: ' + (d.pulled?.updated || 0) + ', ' +
            'Törölve: ' + (d.pulled?.deleted || 0) +
            '</div>';
        resultDiv.style.display = '';

        checkGoogleStatus();
    } else {
        resultDiv.innerHTML = '<div style="background:#FEE2E2;padding:12px 16px;border-radius:8px;font-size:13px;color:#991B1B;">' +
            '<i class="fas fa-exclamation-circle mr-1"></i>' + (res?.message || 'Szinkronizálási hiba') + '</div>';
        resultDiv.style.display = '';
    }
}

async function loadGoogleCalendars(currentId) {
    var data = await VV.get('google/calendars');
    if (data && data.success) {
        var select = document.getElementById('gcal-calendar-select');
        select.innerHTML = data.data.map(function(cal) {
            var selected = (cal.id === currentId) ? ' selected' : '';
            var label = cal.summary + (cal.primary ? ' (Elsődleges)' : '');
            return '<option value="' + cal.id + '"' + selected + '>' + label + '</option>';
        }).join('');
    }
}

async function changeCalendar() {
    var calendarId = document.getElementById('gcal-calendar-select').value;
    var res = await VV.post('google/calendar-id', { calendar_id: calendarId });
    if (res && res.success) {
        VV.toast('Naptár beállítva.', 'success');
    }
}

function copyRedirectUri() {
    var text = document.getElementById('gcal-redirect-uri').textContent;
    navigator.clipboard.writeText(text).then(function() {
        VV.toast('Redirect URI másolva!', 'success');
    });
}

// ============================================
// Push értesítések
// ============================================
async function checkPushStatus() {
    var badge = document.getElementById('push-badge');
    var enableBtn = document.getElementById('push-enable-btn');
    var disableBtn = document.getElementById('push-disable-btn');

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        badge.textContent = 'Nem támogatott';
        badge.className = 'badge badge-warning';
        return;
    }

    try {
        var reg = await navigator.serviceWorker.getRegistration('sw.js');
        if (reg) {
            var sub = await reg.pushManager.getSubscription();
            if (sub) {
                badge.textContent = 'Bekapcsolva';
                badge.className = 'badge badge-success';
                disableBtn.style.display = '';
                return;
            }
        }
    } catch (e) {}

    badge.textContent = 'Kikapcsolva';
    badge.className = 'badge badge-secondary';
    enableBtn.style.display = '';
}

function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - base64String.length % 4) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(base64);
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
}

async function enablePush() {
    try {
        var permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            VV.toast('Értesítések engedélyezése szükséges.', 'warning');
            return;
        }

        // Service worker regisztráció (az admin/ gyökérben)
        var reg = await navigator.serviceWorker.register('sw.js');
        await navigator.serviceWorker.ready;

        // VAPID public key lekérése
        var keyResp = await VV.get('push/vapid-key');
        if (!keyResp || !keyResp.success || !keyResp.data.public_key) {
            VV.toast('VAPID kulcs hiányzik a szerveren.', 'error');
            return;
        }
        var applicationServerKey = urlBase64ToUint8Array(keyResp.data.public_key);

        // Push subscribe
        var sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey
        });

        // Elküldés a backendnek
        var res = await VV.post('push/subscribe', {
            platform: 'web',
            subscription: sub
        });

        if (res && res.success) {
            VV.toast('Push értesítések engedélyezve!', 'success');
            checkPushStatus();
        } else {
            VV.toast('Hiba a feliratkozás mentésekor.', 'error');
        }
    } catch (e) {
        console.error(e);
        VV.toast('Hiba: ' + e.message, 'error');
    }
}

async function testPush() {
    var res = await VV.post('push/test');
    if (res && res.success) {
        VV.toast('Teszt üzenet elküldve, várd meg a notifot!', 'success');
    }
}

async function disablePush() {
    try {
        var reg = await navigator.serviceWorker.getRegistration('sw.js');
        if (reg) {
            var sub = await reg.pushManager.getSubscription();
            if (sub) {
                await sub.unsubscribe();
                await VV.del('push/subscribe');
            }
        }
        VV.toast('Értesítések kikapcsolva.', 'success');
        checkPushStatus();
    } catch (e) {
        VV.toast('Hiba: ' + e.message, 'error');
    }
}

init_settings();
</script>
