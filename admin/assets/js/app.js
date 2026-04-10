/**
 * VeresVill CRM - Globális API kliens és utility-k
 */
const VV = {
    // API base URL (az admin/index.php-ből nézve)
    apiBase: (function() {
        // Az aktuális oldal URL-jéből kiszámoljuk az API útvonalat
        const path = window.location.pathname;
        const adminPos = path.indexOf('/admin/');
        if (adminPos !== -1) {
            return path.substring(0, adminPos) + '/api';
        }
        return '../api';
    })(),

    // ============================================
    // AUTH
    // ============================================
    getToken() {
        return localStorage.getItem('vv_token');
    },

    getUser() {
        try {
            return JSON.parse(localStorage.getItem('vv_user'));
        } catch {
            return null;
        }
    },

    isAdmin() {
        const user = this.getUser();
        return user && user.role === 'admin';
    },

    logout() {
        const token = this.getToken();
        if (token) {
            fetch(this.apiBase + '/auth/logout', {
                method: 'POST',
                headers: this.authHeaders()
            }).catch(() => {});
        }
        localStorage.removeItem('vv_token');
        localStorage.removeItem('vv_user');
        window.location.href = 'login.php';
    },

    checkAuth() {
        if (!this.getToken()) {
            window.location.href = 'login.php';
            return false;
        }
        return true;
    },

    // ============================================
    // API FETCH HELPER
    // ============================================
    authHeaders() {
        return {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + this.getToken()
        };
    },

    async api(endpoint, options = {}) {
        const url = this.apiBase + '/' + endpoint.replace(/^\//, '');
        const config = {
            headers: this.authHeaders(),
            ...options
        };

        if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        try {
            const res = await fetch(url, config);

            if (res.status === 401) {
                this.logout();
                return null;
            }

            const data = await res.json();
            return data;
        } catch (err) {
            console.error('API hiba:', err);
            VV.toast('Hálózati hiba. Próbálja újra.', 'error');
            return null;
        }
    },

    async get(endpoint) {
        return this.api(endpoint, { method: 'GET' });
    },

    async post(endpoint, body = {}) {
        return this.api(endpoint, { method: 'POST', body });
    },

    async put(endpoint, body = {}) {
        return this.api(endpoint, { method: 'PUT', body });
    },

    async del(endpoint) {
        return this.api(endpoint, { method: 'DELETE' });
    },

    // ============================================
    // UI SEGÉDEK
    // ============================================

    // Státusz badge
    statusBadge(status) {
        const map = {
            'uj':                  { label: 'Új',                 class: 'badge-danger' },
            'ajanlat_kuldve':      { label: 'Árajánlat küldve',   class: 'badge-warning' },
            'elfogadva':           { label: 'Elfogadva',          class: 'badge-info' },
            'idopont_kivalasztva': { label: 'Időpont kiválasztva', class: 'badge-primary' },
            'elvegezve':           { label: 'Elvégezve',          class: 'badge-success' }
        };
        const s = map[status] || { label: status, class: 'badge-secondary' };
        return `<span class="badge ${s.class} px-2 py-1">${s.label}</span>`;
    },

    // Összeg formázás (pl. 35000 → "35.000 Ft")
    formatMoney(amount) {
        if (!amount) return '-';
        return new Intl.NumberFormat('hu-HU').format(amount) + ' Ft';
    },

    // Dátum formázás
    formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('hu-HU', { year: 'numeric', month: '2-digit', day: '2-digit' });
    },

    formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('hu-HU', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit'
        });
    },

    // Magyar napnév
    dayName(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('hu-HU', { weekday: 'long' });
    },

    // Időpont formázás (pl. "2026-04-15 09:00" → "ápr. 15. (szerda) 09:00")
    formatSlot(date, start, end) {
        const d = new Date(date);
        const dayName = d.toLocaleDateString('hu-HU', { weekday: 'long' });
        const dateStr = d.toLocaleDateString('hu-HU', { month: 'short', day: 'numeric' });
        return `${dateStr} (${dayName}) ${start.substring(0,5)} - ${end.substring(0,5)}`;
    },

    // Toast üzenet (AdminLTE toasts)
    toast(message, type = 'success') {
        const icons = { success: 'fas fa-check-circle', error: 'fas fa-exclamation-circle', info: 'fas fa-info-circle', warning: 'fas fa-exclamation-triangle' };
        const bgs = { success: 'bg-success', error: 'bg-danger', info: 'bg-info', warning: 'bg-warning' };

        $(document).Toasts('create', {
            title: type === 'error' ? 'Hiba' : type === 'success' ? 'Siker' : 'Értesítés',
            body: message,
            autohide: true,
            delay: 4000,
            class: bgs[type] || 'bg-info',
            icon: icons[type] || icons.info
        });
    },

    // Megerősítő dialógus
    async confirm(message) {
        return window.confirm(message);
    },

    // Sidebar badge frissítés
    async updateSidebarBadges() {
        const data = await this.get('dashboard/stats');
        if (data && data.success) {
            const newCount = data.data.counts?.uj || 0;
            const badge = document.getElementById('sidebar-orders-badge');
            if (badge) {
                if (newCount > 0) {
                    badge.textContent = newCount;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            }
        }
    },

    // Oldal betöltés
    async loadPage(page) {
        const content = document.getElementById('page-content');
        if (!content) return;

        content.innerHTML = '<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';

        try {
            const res = await fetch('pages/' + page + '.php');
            if (res.ok) {
                const html = await res.text();
                content.innerHTML = html;

                // innerHTML-lel beszúrt <script> tagok nem futnak le
                // Ezért kézzel kell létrehozni és végrehajtani őket
                const scripts = content.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    } else {
                        newScript.textContent = oldScript.textContent;
                    }
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            } else {
                content.innerHTML = '<div class="alert alert-danger m-3">Az oldal nem található.</div>';
            }
        } catch (err) {
            content.innerHTML = '<div class="alert alert-danger m-3">Hiba az oldal betöltésekor.</div>';
        }
    }
};

// Auth ellenőrzés (login.php kivétel)
if (!window.location.pathname.includes('login.php')) {
    VV.checkAuth();
}
