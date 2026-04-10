<!-- Beállítások oldal -->
<section class="content-header">
    <div class="container-fluid">
        <h1><i class="fas fa-cog mr-2"></i>Beállítások</h1>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
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
    const user = VV.getUser();
    if (user) {
        document.getElementById('profile-name').value = user.name;
        document.getElementById('profile-email').value = user.email;
        document.getElementById('sys-user').textContent = user.name + ' (' + user.email + ')';
        document.getElementById('sys-role').textContent = user.role === 'admin' ? 'Adminisztrátor' : 'Munkatárs';
    }

    // Push státusz ellenőrzés
    checkPushStatus();
}

async function checkPushStatus() {
    const badge = document.getElementById('push-badge');
    const enableBtn = document.getElementById('push-enable-btn');
    const disableBtn = document.getElementById('push-disable-btn');

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        badge.textContent = 'Nem támogatott';
        badge.className = 'badge badge-warning';
        return;
    }

    try {
        const reg = await navigator.serviceWorker.getRegistration('sw.js');
        if (reg) {
            const sub = await reg.pushManager.getSubscription();
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

async function enablePush() {
    try {
        const reg = await navigator.serviceWorker.register('sw.js');
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            VV.toast('Értesítések engedélyezése szükséges.', 'warning');
            return;
        }

        // VAPID public key-t kellene betölteni az API-ból
        // Egyelőre placeholder
        VV.toast('Push értesítések engedélyezve! (Teljes konfiguráció szerveren szükséges)', 'success');
        checkPushStatus();
    } catch (e) {
        VV.toast('Hiba: ' + e.message, 'error');
    }
}

async function disablePush() {
    try {
        const reg = await navigator.serviceWorker.getRegistration('sw.js');
        if (reg) {
            const sub = await reg.pushManager.getSubscription();
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
