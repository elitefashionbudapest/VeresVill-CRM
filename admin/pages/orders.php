<!-- Megrendelések oldal -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-clipboard-list mr-2"></i>Megrendelések</h1>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">

        <!-- Lista nézet -->
        <div id="orders-list-view">
            <!-- Szűrők -->
            <div class="card card-outline card-primary">
                <div class="card-header py-2">
                    <div class="row align-items-center">
                        <div class="col-md-4 col-12 mb-2 mb-md-0">
                            <select id="filter-status" class="form-control form-control-sm">
                                <option value="">Minden státusz</option>
                                <option value="uj">Új</option>
                                <option value="ajanlat_kuldve">Árajánlat küldve</option>
                                <option value="elfogadva">Elfogadva</option>
                                <option value="idopont_kivalasztva">Időpont kiválasztva</option>
                                <option value="elvegezve">Elvégezve</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-12 mb-2 mb-md-0">
                            <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Keresés (név, email, telefon, cím)...">
                        </div>
                        <div class="col-md-4 col-12 text-md-right">
                            <button class="btn btn-sm btn-outline-secondary" onclick="loadOrders()">
                                <i class="fas fa-sync-alt"></i> Frissítés
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="orders-table">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>Megrendelő</th>
                                    <th class="d-none d-lg-table-cell">Ingatlan</th>
                                    <th class="d-none d-md-table-cell">Cím</th>
                                    <th>Státusz</th>
                                    <th class="d-none d-md-table-cell">Összeg</th>
                                    <th class="d-none d-sm-table-cell">Dátum</th>
                                </tr>
                            </thead>
                            <tbody id="orders-tbody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-spinner fa-spin mr-2"></i>Betöltés...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer clearfix">
                    <div class="float-left text-muted" id="orders-info"></div>
                    <ul class="pagination pagination-sm m-0 float-right" id="orders-pagination"></ul>
                </div>
            </div>
        </div>

        <!-- Részletes nézet (rejtve) -->
        <div id="orders-detail-view" style="display: none;">
            <button class="btn btn-sm btn-outline-secondary mb-3" onclick="hideOrderDetail()">
                <i class="fas fa-arrow-left mr-1"></i>Vissza a listához
            </button>
            <div id="order-detail-content"></div>
        </div>

    </div>
</section>

<script>
let ordersPage = 1;
const ordersPerPage = 20;

async function init_orders() {
    // Szűrők eseménykezelő
    document.getElementById('filter-status').addEventListener('change', () => { ordersPage = 1; loadOrders(); });

    let searchTimer;
    document.getElementById('filter-search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { ordersPage = 1; loadOrders(); }, 400);
    });

    loadOrders();
}

async function loadOrders() {
    const status = document.getElementById('filter-status').value;
    const search = document.getElementById('filter-search').value.trim();

    let url = `orders?page=${ordersPage}&per_page=${ordersPerPage}`;
    if (status) url += `&status=${status}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;

    const data = await VV.get(url);
    if (!data || !data.success) return;

    const tbody = document.getElementById('orders-tbody');

    if (data.data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="vv-empty">
                    <i class="fas fa-inbox"></i>
                    <p>Nincsenek megrendelések</p>
                </td>
            </tr>`;
    } else {
        tbody.innerHTML = data.data.map(o => `
            <tr onclick="showOrder(${o.id})" style="cursor: pointer;">
                <td><strong>#${o.id}</strong></td>
                <td>
                    <strong>${escHtml(o.customer_name)}</strong><br>
                    <small class="text-muted">
                        <i class="fas fa-phone fa-xs mr-1"></i>${escHtml(o.customer_phone)}
                    </small>
                </td>
                <td class="d-none d-lg-table-cell">
                    <small>${escHtml(o.property_type_label)}</small><br>
                    <small class="text-muted">${o.size} m²</small>
                </td>
                <td class="d-none d-md-table-cell">
                    <small>${escHtml(truncate(o.customer_address, 40))}</small>
                </td>
                <td>${VV.statusBadge(o.status)}</td>
                <td class="d-none d-md-table-cell">${VV.formatMoney(o.quote_amount)}</td>
                <td class="d-none d-sm-table-cell">
                    <small>${VV.formatDateTime(o.created_at)}</small>
                </td>
            </tr>
        `).join('');
    }

    // Paginálás
    renderPagination(data.meta);
}

function renderPagination(meta) {
    if (!meta) return;
    const info = document.getElementById('orders-info');
    const pag = document.getElementById('orders-pagination');

    const from = (meta.page - 1) * meta.per_page + 1;
    const to = Math.min(meta.page * meta.per_page, meta.total);
    info.textContent = `${from}-${to} / ${meta.total} megrendelés`;

    let html = '';
    if (meta.page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="ordersPage=${meta.page - 1}; loadOrders(); return false;">&laquo;</a></li>`;
    }
    for (let i = 1; i <= meta.last_page; i++) {
        if (i === meta.page) {
            html += `<li class="page-item active"><a class="page-link" href="#">${i}</a></li>`;
        } else if (Math.abs(i - meta.page) < 3 || i === 1 || i === meta.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="ordersPage=${i}; loadOrders(); return false;">${i}</a></li>`;
        } else if (Math.abs(i - meta.page) === 3) {
            html += `<li class="page-item disabled"><a class="page-link">...</a></li>`;
        }
    }
    if (meta.page < meta.last_page) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="ordersPage=${meta.page + 1}; loadOrders(); return false;">&raquo;</a></li>`;
    }
    pag.innerHTML = html;
}

async function showOrder(id) {
    document.getElementById('orders-list-view').style.display = 'none';
    document.getElementById('orders-detail-view').style.display = '';

    const content = document.getElementById('order-detail-content');
    content.innerHTML = '<div class="vv-loading"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    const data = await VV.get(`orders/${id}`);
    if (!data || !data.success) {
        content.innerHTML = '<div class="alert alert-danger">Megrendelés nem található.</div>';
        return;
    }

    const o = data.data;
    const isAdmin = VV.isAdmin();

    content.innerHTML = `
        <div class="row">
            <!-- Bal: megrendelés adatok -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">
                            <i class="fas fa-user mr-2"></i>Megrendelés #${o.id}
                        </h3>
                        ${VV.statusBadge(o.status)}
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="order-detail-label">Megrendelő neve</div>
                                <div class="order-detail-value">${escHtml(o.customer_name)}</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="order-detail-label">Email</div>
                                <div class="order-detail-value">
                                    <a href="mailto:${escHtml(o.customer_email)}">${escHtml(o.customer_email)}</a>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="order-detail-label">Telefon</div>
                                <div class="order-detail-value">
                                    <a href="tel:${escHtml(o.customer_phone)}">${escHtml(o.customer_phone)}</a>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="order-detail-label">Cím</div>
                                <div class="order-detail-value">${escHtml(o.customer_address)}</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="order-detail-label">Ingatlan típus</div>
                                <div class="order-detail-value">${escHtml(o.property_type_label)}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="order-detail-label">Méret</div>
                                <div class="order-detail-value">${o.size} m²</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="order-detail-label">Sürgősség</div>
                                <div class="order-detail-value">${escHtml(o.urgency_label)}</div>
                            </div>
                            ${o.message ? `
                            <div class="col-12 mb-3">
                                <div class="order-detail-label">Megjegyzés</div>
                                <div class="order-detail-value">${escHtml(o.message)}</div>
                            </div>` : ''}
                            <div class="col-12 mb-3">
                                <div class="order-detail-label">Beérkezés</div>
                                <div class="order-detail-value">${VV.formatDateTime(o.created_at)}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Árajánlat küldés (ha státusz = uj) -->
                ${o.status === 'uj' && isAdmin ? `
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-file-invoice mr-2"></i>Árajánlat küldése</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Árajánlat összege (Ft)</label>
                            <input type="number" id="quote-amount" class="form-control" placeholder="pl. 35000" min="1000" step="1000">
                            <small class="text-muted">Bruttó ár ÁFÁ-val</small>
                        </div>
                        <div class="form-group">
                            <label>Felajánlott időpontok (2-3 db)</label>
                            <div id="quote-slots">
                                <div class="slot-row mb-2">
                                    <div class="row">
                                        <div class="col-5">
                                            <input type="date" class="form-control form-control-sm slot-date" min="${new Date().toISOString().split('T')[0]}">
                                        </div>
                                        <div class="col-3">
                                            <input type="time" class="form-control form-control-sm slot-start" value="09:00" step="3600">
                                        </div>
                                        <div class="col-3">
                                            <input type="time" class="form-control form-control-sm slot-end" value="10:00" step="3600">
                                        </div>
                                        <div class="col-1">
                                            <button class="btn btn-sm btn-outline-danger" onclick="this.closest('.slot-row').remove()"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary mt-1" onclick="addSlotRow()">
                                <i class="fas fa-plus mr-1"></i>Időpont hozzáadása
                            </button>
                        </div>
                        <button class="btn btn-primary" onclick="sendQuote(${o.id})">
                            <i class="fas fa-paper-plane mr-1"></i>Árajánlat küldése emailben
                        </button>
                    </div>
                </div>` : ''}

                <!-- Árajánlat info (ha már elküldve) -->
                ${o.quote_amount ? `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-file-invoice mr-2"></i>Árajánlat</h3>
                    </div>
                    <div class="card-body">
                        <h3 class="text-primary">${VV.formatMoney(o.quote_amount)}</h3>
                        <small class="text-muted">Küldve: ${VV.formatDateTime(o.quote_sent_at)}</small>
                        ${o.quote_accepted_at ? `<br><small class="text-success"><i class="fas fa-check mr-1"></i>Elfogadva: ${VV.formatDateTime(o.quote_accepted_at)}</small>` : ''}
                        ${o.time_slots && o.time_slots.length ? `
                        <hr>
                        <strong>Felajánlott időpontok:</strong>
                        <ul class="list-unstyled mt-2">
                            ${o.time_slots.map(s => `
                                <li class="mb-1">
                                    ${s.is_selected ? '<i class="fas fa-check-circle text-success mr-1"></i>' : '<i class="far fa-circle text-muted mr-1"></i>'}
                                    ${VV.formatSlot(s.slot_date, s.slot_start, s.slot_end)}
                                    ${s.is_selected ? ' <strong class="text-success">(Kiválasztva)</strong>' : ''}
                                </li>
                            `).join('')}
                        </ul>` : ''}
                    </div>
                </div>` : ''}

                <!-- Admin megjegyzések -->
                ${isAdmin ? `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-sticky-note mr-2"></i>Belső megjegyzések</h3>
                    </div>
                    <div class="card-body">
                        <textarea id="admin-notes" class="form-control" rows="3" placeholder="Megjegyzések (csak admin látja)...">${escHtml(o.admin_notes || '')}</textarea>
                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="saveNotes(${o.id})">
                            <i class="fas fa-save mr-1"></i>Mentés
                        </button>
                    </div>
                </div>` : ''}
            </div>

            <!-- Jobb: műveletek + napló -->
            <div class="col-lg-4">
                <!-- Gyors műveletek -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Műveletek</h3>
                    </div>
                    <div class="card-body">
                        <a href="tel:${escHtml(o.customer_phone)}" class="btn btn-success btn-block mb-2">
                            <i class="fas fa-phone mr-1"></i>Hívás: ${escHtml(o.customer_phone)}
                        </a>
                        <a href="mailto:${escHtml(o.customer_email)}" class="btn btn-outline-primary btn-block mb-2">
                            <i class="fas fa-envelope mr-1"></i>Email küldése
                        </a>
                        ${o.status === 'idopont_kivalasztva' && isAdmin ? `
                        <button class="btn btn-primary btn-block mb-2" onclick="markDone(${o.id})">
                            <i class="fas fa-check mr-1"></i>Munka elvégezve
                        </button>` : ''}
                    </div>
                </div>

                <!-- Státusz napló -->
                ${o.status_log && o.status_log.length ? `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-2"></i>Előzmények</h3>
                    </div>
                    <div class="card-body">
                        <div class="status-timeline">
                            ${o.status_log.map(log => `
                                <div class="timeline-item">
                                    <strong>${escHtml(log.new_status_label || log.new_status)}</strong><br>
                                    <small class="text-muted">
                                        ${VV.formatDateTime(log.created_at)}
                                        ${log.changed_by_name ? ` - ${escHtml(log.changed_by_name)}` : ''}
                                    </small>
                                    ${log.note ? `<br><small>${escHtml(log.note)}</small>` : ''}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>` : ''}
            </div>
        </div>
    `;
}

function hideOrderDetail() {
    document.getElementById('orders-detail-view').style.display = 'none';
    document.getElementById('orders-list-view').style.display = '';
}

// Árajánlat slot hozzáadása
function addSlotRow() {
    const container = document.getElementById('quote-slots');
    if (container.querySelectorAll('.slot-row').length >= 3) {
        VV.toast('Maximum 3 időpont adható meg.', 'warning');
        return;
    }
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];

    const div = document.createElement('div');
    div.className = 'slot-row mb-2';
    div.innerHTML = `
        <div class="row">
            <div class="col-5">
                <input type="date" class="form-control form-control-sm slot-date" value="${dateStr}" min="${new Date().toISOString().split('T')[0]}">
            </div>
            <div class="col-3">
                <input type="time" class="form-control form-control-sm slot-start" value="09:00" step="3600">
            </div>
            <div class="col-3">
                <input type="time" class="form-control form-control-sm slot-end" value="10:00" step="3600">
            </div>
            <div class="col-1">
                <button class="btn btn-sm btn-outline-danger" onclick="this.closest('.slot-row').remove()"><i class="fas fa-times"></i></button>
            </div>
        </div>
    `;
    container.appendChild(div);
}

// Árajánlat küldése
async function sendQuote(orderId) {
    const amount = parseInt(document.getElementById('quote-amount').value);
    if (!amount || amount < 1000) {
        VV.toast('Adjon meg érvényes összeget (min. 1000 Ft).', 'error');
        return;
    }

    const slotRows = document.querySelectorAll('#quote-slots .slot-row');
    if (slotRows.length === 0) {
        VV.toast('Adjon meg legalább 1 időpontot.', 'error');
        return;
    }

    const user = VV.getUser();
    const slots = [];
    for (const row of slotRows) {
        const date = row.querySelector('.slot-date').value;
        const start = row.querySelector('.slot-start').value;
        const end = row.querySelector('.slot-end').value;
        if (!date || !start || !end) {
            VV.toast('Töltse ki az összes időpont mezőt.', 'error');
            return;
        }
        slots.push({ worker_id: user.id, date, start, end });
    }

    const res = await VV.post(`orders/${orderId}/quote`, { amount, slots });
    if (res && res.success) {
        VV.toast('Árajánlat sikeresen elküldve!', 'success');
        showOrder(orderId); // Újratöltés
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
    }
}

// Munka elvégezve
async function markDone(orderId) {
    if (!await VV.confirm('Biztosan megjelöli elvégzettnek?')) return;
    const res = await VV.put(`orders/${orderId}/status`, { status: 'elvegezve' });
    if (res && res.success) {
        VV.toast('Megrendelés lezárva!', 'success');
        showOrder(orderId);
    }
}

// Admin megjegyzés mentése
async function saveNotes(orderId) {
    const notes = document.getElementById('admin-notes').value;
    const res = await VV.put(`orders/${orderId}`, { admin_notes: notes });
    if (res && res.success) {
        VV.toast('Megjegyzés mentve.', 'success');
    }
}

function truncate(str, max) {
    if (!str) return '';
    return str.length > max ? str.substring(0, max) + '...' : str;
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Megrendelés megnyitása külső hívásból (dashboard-ról)
window.showOrder = showOrder;

init_orders();
</script>
