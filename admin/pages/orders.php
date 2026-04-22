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
var ordersPage = 1;
var ordersPerPage = 20;

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
                    ${o.energy_certificate == 1 ? '<br><span class="badge badge-warning" style="font-size:10px;"><i class="fas fa-bolt"></i> Energetikai</span>' : ''}
                </td>
                <td class="d-none d-md-table-cell">
                    <small>${escHtml(truncate(o.customer_address, 40))}</small>
                </td>
                <td>${VV.statusBadge(o.status)}${o.slots_rejected_at && o.status === 'ajanlat_kuldve' ? ' <span class="badge badge-warning ml-1" title="Ügyfél elutasította az időpontokat"><i class="fas fa-exclamation-triangle"></i></span>' : ''}</td>
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
                                <div class="order-detail-label">Megrendelő típusa</div>
                                <div class="order-detail-value d-flex align-items-center">
                                    ${o.is_company ? '<span class="badge badge-info mr-2"><i class="fas fa-building mr-1"></i>Céges</span>' : '<span class="badge badge-secondary mr-2"><i class="fas fa-user mr-1"></i>Magánszemély</span>'}
                                    ${isAdmin ? `<div class="custom-control custom-switch ml-2">
                                        <input type="checkbox" class="custom-control-input" id="is-company-toggle" ${o.is_company ? 'checked' : ''} onchange="toggleCompany(${o.id}, this.checked)">
                                        <label class="custom-control-label" for="is-company-toggle">Céges</label>
                                    </div>` : ''}
                                </div>
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
                            ${o.energy_certificate == 1 ? `
                            <div class="col-md-6 mb-3">
                                <div class="order-detail-label">Energetikai tanúsítvány</div>
                                <div class="order-detail-value"><span class="badge badge-warning" style="font-size:14px;"><i class="fas fa-bolt mr-1"></i>Igen, kér árajánlatot</span></div>
                            </div>` : ''}
                            <div class="col-12 mb-3">
                                <div class="order-detail-label">Beérkezés</div>
                                <div class="order-detail-value">${VV.formatDateTime(o.created_at)}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Árajánlat küldés / módosítás -->
                ${(o.status === 'uj' || o.status === 'ajanlat_kuldve') && isAdmin ? `
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-file-invoice mr-2"></i>${o.status === 'ajanlat_kuldve' ? 'Árajánlat módosítása' : 'Árajánlat küldése'}</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Árajánlat összege (Ft)</label>
                            <input type="number" id="quote-amount" class="form-control form-control-lg" placeholder="pl. 35000" min="1000" step="1000" value="${o.quote_amount || ''}" style="font-size:1.4rem;font-weight:700;">
                            <small class="text-muted">Bruttó ár ÁFÁ-val (10% kedvezmény automatikusan megjelenik az emailben)</small>
                        </div>

                        ${o.energy_certificate == 1 ? `
                        <div class="form-group">
                            <label><i class="fas fa-bolt text-warning mr-1"></i>Energetikai tanúsítvány ára (Ft)</label>
                            <input type="number" id="energy-cert-amount" class="form-control" placeholder="pl. 25000" min="1000" step="1000" value="${o.energy_certificate_amount || ''}">
                            <small class="text-muted">Ez az összeg kedvezmény nélkül jelenik meg az emailben</small>
                        </div>` : ''}

                        <label class="mb-2">Válassz 2-3 szabad időpontot <small class="text-muted">(kattints a zöld cellákra)</small></label>

                        <!-- Hét navigáció -->
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="slotPickerPrevWeek()"><i class="fas fa-chevron-left"></i></button>
                            <strong id="slot-picker-week-label"></strong>
                            <button class="btn btn-sm btn-outline-secondary" onclick="slotPickerNextWeek()"><i class="fas fa-chevron-right"></i></button>
                        </div>

                        <!-- Naptár rács -->
                        <div id="slot-picker-grid" style="overflow-x:auto;"></div>

                        <!-- Kiválasztott időpontok -->
                        <div id="slot-picker-selected" class="mt-3"></div>

                        <button class="btn btn-primary btn-lg btn-block mt-3" onclick="sendQuote(${o.id})" id="send-quote-btn">
                            <i class="fas fa-paper-plane mr-1"></i>${o.status === 'ajanlat_kuldve' ? 'Módosítás és újraküldés emailben' : 'Árajánlat küldése emailben'}
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
                        <h3 class="text-primary">${VV.formatMoney(o.quote_amount)} <small class="text-muted" style="font-size:14px;">(villamos felülvizsgálat)</small></h3>
                        ${o.energy_certificate_amount ? `<h4 class="text-warning mb-1"><i class="fas fa-bolt mr-1"></i>${VV.formatMoney(o.energy_certificate_amount)} <small class="text-muted" style="font-size:13px;">(energetikai tanúsítvány)</small></h4>` : ''}
                        <small class="text-muted">Küldve: ${VV.formatDateTime(o.quote_sent_at)}</small>
                        ${o.quote_accepted_at ? `<br><small class="text-success"><i class="fas fa-check mr-1"></i>Elfogadva: ${VV.formatDateTime(o.quote_accepted_at)}</small>` : ''}

                        ${o.slots_rejected_at && o.status === 'ajanlat_kuldve' ? `
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Az ügyfél jelezte, hogy egyik kiajánlott időpont sem felel meg neki.</strong><br>
                            <small>Jelezve: ${VV.formatDateTime(o.slots_rejected_at)}. Kérjük, ajánljon fel új időpontokat vagy egyeztessen telefonon.</small>
                        </div>` : ''}

                        ${o.time_slots && o.time_slots.length ? `
                        <hr>
                        <strong>Felajánlott időpontok:</strong>
                        <ul class="list-unstyled mt-2">
                            ${o.time_slots.map(s => `
                                <li class="mb-2 d-flex align-items-center flex-wrap">
                                    <span class="mr-2">
                                        ${s.is_selected ? '<i class="fas fa-check-circle text-success mr-1"></i>' : '<i class="far fa-circle text-muted mr-1"></i>'}
                                        ${VV.formatSlot(s.slot_date, s.slot_start, s.slot_end)}
                                        ${s.is_selected ? ' <strong class="text-success">(Kiválasztva)</strong>' : ''}
                                    </span>
                                    ${isAdmin && !s.is_selected && o.status === 'ajanlat_kuldve' ? `
                                    <span class="ml-auto">
                                        <button class="btn btn-xs btn-success mr-1" onclick="confirmSlotById(${o.id}, ${s.id})" title="Véglegesítés (email küldés az ügyfélnek)">
                                            <i class="fas fa-check mr-1"></i>Megbeszélve
                                        </button>
                                        <button class="btn btn-xs btn-outline-danger" onclick="deleteSlot(${o.id}, ${s.id})" title="Időpont törlése">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </span>` : ''}
                                </li>
                            `).join('')}
                        </ul>` : ''}

                        ${isAdmin && o.status === 'ajanlat_kuldve' ? `
                        <hr>
                        <strong><i class="fas fa-phone mr-1"></i>Manuális időpont (telefonos egyeztetés)</strong>
                        <p class="text-muted mb-2"><small>Ha telefonon egyeztettek időpontot, itt véglegesítheti. Automatikusan visszaigazoló emailt küld az ügyfélnek.</small></p>
                        <div class="form-row">
                            <div class="col-md-5 mb-2">
                                <input type="date" id="manual-slot-date" class="form-control form-control-sm" min="${new Date().toISOString().slice(0,10)}">
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <input type="time" id="manual-slot-start" class="form-control form-control-sm" value="09:00" step="900">
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <input type="time" id="manual-slot-end" class="form-control form-control-sm" value="10:00" step="900">
                            </div>
                        </div>
                        <button class="btn btn-success btn-block btn-sm" onclick="confirmSlotManual(${o.id})">
                            <i class="fas fa-calendar-check mr-1"></i>Időpont véglegesítése + visszaigazoló email
                        </button>` : ''}
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
                        ${isAdmin ? `<hr>
                        <button class="btn btn-outline-danger btn-block btn-sm" onclick="deleteOrder(${o.id})">
                            <i class="fas fa-trash mr-1"></i>Megrendelés törlése
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

    // Slot picker inicializálás ha új megrendelés vagy árajánlat módosítása
    if ((o.status === 'uj' || o.status === 'ajanlat_kuldve') && isAdmin) {
        setTimeout(() => initSlotPicker(), 100);
    }
}

function hideOrderDetail() {
    document.getElementById('orders-detail-view').style.display = 'none';
    document.getElementById('orders-list-view').style.display = '';
}

// ============================================
// VIZUÁLIS IDŐPONT VÁLASZTÓ (Slot Picker)
// ============================================
var slotPickerWeekStart = null;
var slotPickerSelected = []; // [{date, start, end}]
var slotPickerBusy = [];     // foglalt időpontok az API-ból

const SLOT_HOURS = [8,9,10,11,12,13,14,15,16]; // 8:00-17:00
const SLOT_DAY_NAMES = ['Hé','Ke','Sze','Csü','Pé','Szo','Va'];
const SLOT_MAX = 8;

function initSlotPicker() {
    // Következő hétfő
    const today = new Date();
    slotPickerWeekStart = new Date(today);
    const dayOfWeek = today.getDay();
    const diff = dayOfWeek === 0 ? 1 : (dayOfWeek === 6 ? 2 : (dayOfWeek === 1 ? 0 : 0));
    if (diff > 0) slotPickerWeekStart.setDate(today.getDate() + diff);
    // Ha ma hétköznap, kezdjük mától
    if (dayOfWeek >= 1 && dayOfWeek <= 5) {
        slotPickerWeekStart = new Date(today);
    }
    slotPickerWeekStart.setHours(0,0,0,0);

    slotPickerSelected = [];
    renderSlotPicker();
}

function slotPickerPrevWeek() {
    slotPickerWeekStart.setDate(slotPickerWeekStart.getDate() - 7);
    const today = new Date(); today.setHours(0,0,0,0);
    if (slotPickerWeekStart < today) slotPickerWeekStart = new Date(today);
    renderSlotPicker();
}

function slotPickerNextWeek() {
    slotPickerWeekStart.setDate(slotPickerWeekStart.getDate() + 7);
    renderSlotPicker();
}

async function renderSlotPicker() {
    const grid = document.getElementById('slot-picker-grid');
    const label = document.getElementById('slot-picker-week-label');
    if (!grid || !label) return;

    // Hét napjainak kiszámítása (H-P, 5 nap)
    const days = [];
    const d = new Date(slotPickerWeekStart);
    // Hétfőre igazítás
    while (d.getDay() !== 1 && d.getDay() !== 0) d.setDate(d.getDate() - 1);
    if (d.getDay() === 0) d.setDate(d.getDate() + 1);

    for (let i = 0; i < 5; i++) {
        days.push(new Date(d));
        d.setDate(d.getDate() + 1);
    }

    // Hét label
    const weekStart = days[0];
    const weekEnd = days[4];
    const months = ['jan','feb','már','ápr','máj','jún','júl','aug','sze','okt','nov','dec'];
    label.textContent = `${months[weekStart.getMonth()]} ${weekStart.getDate()}. - ${months[weekEnd.getMonth()]} ${weekEnd.getDate()}.`;

    // Helyi datum YYYY-MM-DD (UTC konverzio kerulese)
    const ymd = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const day = String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${day}`;
    };

    // Foglalt időpontok betöltése
    const user = VV.getUser();
    const startStr = ymd(days[0]);
    const endStr = ymd(days[4]);
    const busyData = await VV.get(`calendar/events?start=${startStr}&end=${endStr}`);
    slotPickerBusy = (busyData && busyData.success) ? busyData.data : [];

    const now = new Date();

    // Header
    let html = '<table class="table table-bordered mb-0" style="table-layout:fixed;font-size:13px;"><thead><tr>';
    html += '<th style="width:50px;text-align:center;background:#f8f9fa;"></th>';
    days.forEach(day => {
        const isToday = day.toDateString() === now.toDateString();
        html += `<th style="text-align:center;background:${isToday ? '#e3f2fd' : '#f8f9fa'};padding:6px 2px;">
            <div style="font-weight:700;font-size:12px;">${SLOT_DAY_NAMES[day.getDay()-1]}</div>
            <div style="font-size:16px;font-weight:700;">${day.getDate()}</div>
        </th>`;
    });
    html += '</tr></thead><tbody>';

    // Sorok (órák)
    SLOT_HOURS.forEach(hour => {
        html += '<tr>';
        html += `<td style="text-align:center;font-weight:600;color:#6c757d;padding:4px;vertical-align:middle;font-size:12px;">${hour}:00</td>`;

        days.forEach(day => {
            const dateStr = ymd(day);
            const startStr = `${String(hour).padStart(2,'0')}:00`;
            const endStr = `${String(hour+1).padStart(2,'0')}:00`;
            const slotKey = `${dateStr}_${startStr}`;

            // Múlt?
            const slotTime = new Date(dateStr + 'T' + startStr);
            const isPast = slotTime < now;

            // Foglalasok szamlalasa — max 2 lehet egy slotra
            const busyCount = slotPickerBusy.reduce((n, b) => {
                return (b.start < dateStr+'T'+endStr+':00' && b.end > dateStr+'T'+startStr+':00')
                    ? n + 1 : n;
            }, 0);
            const isFull = busyCount >= 2;
            const isPartial = busyCount === 1;

            // Kiválasztva?
            const isSelected = slotPickerSelected.some(s => s.date === dateStr && s.start === startStr);

            let cellClass = '';
            let cellStyle = 'padding:4px;text-align:center;cursor:pointer;transition:all 0.1s;height:36px;vertical-align:middle;';
            let cellContent = '';
            let onclick = '';

            if (isPast) {
                cellStyle += 'background:#f5f5f5;cursor:default;';
                cellContent = '';
            } else if (isFull) {
                cellStyle += 'background:#ffcdd2;cursor:default;';
                cellContent = '<i class="fas fa-ban" style="color:#e57373;font-size:11px;"></i>';
            } else if (isSelected) {
                cellStyle += 'background:#4A90E2;color:#fff;border-radius:4px;';
                cellContent = '<i class="fas fa-check" style="font-size:14px;"></i>';
                onclick = `onclick="toggleSlot('${dateStr}','${startStr}','${endStr}')"`;
            } else if (isPartial) {
                // Egy foglalas mar van — meg egyszer kiadhato
                cellStyle += 'background:#fff3e0;';
                cellContent = '<span style="color:#f57c00;font-size:11px;font-weight:700;">1/2</span>';
                onclick = `onclick="toggleSlot('${dateStr}','${startStr}','${endStr}')"`;
            } else {
                cellStyle += 'background:#e8f5e9;';
                cellContent = '';
                onclick = `onclick="toggleSlot('${dateStr}','${startStr}','${endStr}')"`;
            }

            html += `<td style="${cellStyle}" ${onclick}>${cellContent}</td>`;
        });

        html += '</tr>';
    });

    html += '</tbody></table>';
    grid.innerHTML = html;

    renderSelectedSlots();
}

function toggleSlot(date, start, end) {
    const idx = slotPickerSelected.findIndex(s => s.date === date && s.start === start);
    if (idx >= 0) {
        slotPickerSelected.splice(idx, 1);
    } else {
        if (slotPickerSelected.length >= SLOT_MAX) {
            VV.toast(`Maximum ${SLOT_MAX} időpont választható.`, 'warning');
            return;
        }
        slotPickerSelected.push({ date, start, end });
    }
    renderSlotPicker();
}

function renderSelectedSlots() {
    const container = document.getElementById('slot-picker-selected');
    const btn = document.getElementById('send-quote-btn');
    if (!container) return;

    if (slotPickerSelected.length === 0) {
        container.innerHTML = '<p class="text-muted text-center mb-0"><small>Kattints a zöld cellákra az időpont kiválasztásához (opcionális)</small></p>';
        return;
    }

    container.innerHTML = '<strong class="d-block mb-2">Kiválasztott időpontok:</strong>' +
        slotPickerSelected.map((s, i) => {
            const d = new Date(s.date);
            const dayName = d.toLocaleDateString('hu-HU', { weekday: 'long' });
            const dateStr = d.toLocaleDateString('hu-HU', { year: 'numeric', month: 'long', day: 'numeric' });
            return `<div class="d-flex align-items-center justify-content-between mb-1 p-2" style="background:#e3f2fd;border-radius:8px;">
                <span><strong>${dateStr}</strong> (${dayName}) ${s.start} - ${s.end}</span>
                <button class="btn btn-sm btn-link text-danger p-0 ml-2" onclick="toggleSlot('${s.date}','${s.start}','${s.end}')"><i class="fas fa-times"></i></button>
            </div>`;
        }).join('');
}

// Árajánlat küldése
async function sendQuote(orderId) {
    const amount = parseInt(document.getElementById('quote-amount').value);
    if (!amount || amount < 1000) {
        VV.toast('Adjon meg érvényes összeget (min. 1000 Ft).', 'error');
        return;
    }

    const user = VV.getUser();
    const slots = slotPickerSelected.map(s => ({
        worker_id: user.id,
        date: s.date,
        start: s.start,
        end: s.end
    }));

    const energyCertEl = document.getElementById('energy-cert-amount');
    const energyCertAmount = energyCertEl ? parseInt(energyCertEl.value) || 0 : 0;

    const btn = document.getElementById('send-quote-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Küldés...';

    const body = { amount, slots };
    if (energyCertAmount > 0) body.energy_cert_amount = energyCertAmount;

    const res = await VV.post(`orders/${orderId}/quote`, body);
    if (res && res.success) {
        VV.toast('Árajánlat sikeresen elküldve!', 'success');
        showOrder(orderId);
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane mr-1"></i>Árajánlat küldése emailben';
    }
}

// Kiajanlott idopont torlese
async function deleteSlot(orderId, slotId) {
    if (!await VV.confirm('Biztosan törli ezt a kiajánlott időpontot?')) return;
    const res = await VV.del(`orders/${orderId}/slots/${slotId}`);
    if (res && res.success) {
        VV.toast('Időpont törölve.', 'success');
        showOrder(orderId);
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
    }
}

// Megbeszelt idopont veglegesitese (meglevo slot alapjan)
async function confirmSlotById(orderId, slotId) {
    if (!await VV.confirm('Véglegesíti ezt az időpontot? Az ügyfél visszaigazoló emailt fog kapni.')) return;
    const res = await VV.post(`orders/${orderId}/confirm-slot`, { slot_id: slotId });
    if (res && res.success) {
        VV.toast('Időpont véglegesítve, visszaigazoló email elküldve.', 'success');
        showOrder(orderId);
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
    }
}

// Manualis idopont veglegesitese (datum + ido mezokbol)
async function confirmSlotManual(orderId) {
    const date  = document.getElementById('manual-slot-date').value;
    const start = document.getElementById('manual-slot-start').value;
    const end   = document.getElementById('manual-slot-end').value;

    if (!date || !start || !end) {
        VV.toast('Adjon meg dátumot, kezdési és befejezési időt.', 'error');
        return;
    }
    if (end <= start) {
        VV.toast('A befejezési időnek későbbinek kell lennie a kezdésnél.', 'error');
        return;
    }
    if (!await VV.confirm(`Véglegesíti: ${date} ${start}-${end}? Az ügyfél visszaigazoló emailt fog kapni.`)) return;

    const res = await VV.post(`orders/${orderId}/confirm-slot`, { date, start, end });
    if (res && res.success) {
        VV.toast('Időpont véglegesítve, visszaigazoló email elküldve.', 'success');
        showOrder(orderId);
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
    }
}

// Uj idopontok kikuldese — visszallit 'uj' statuszba, regi slotok torlodnek
async function resendQuote(orderId) {
    if (!await VV.confirm('Biztosan új időpontokat küld ki? A korábban kiajánlott időpontok törlődnek, és új ajánlatot állíthat össze.')) return;
    const res = await VV.post(`orders/${orderId}/resend-quote`, {});
    if (res && res.success) {
        VV.toast('Régi időpontok törölve. Válasszon újakat a naptárban.', 'success');
        showOrder(orderId);
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
    }
}

// Megrendelés törlése
async function deleteOrder(orderId) {
    if (!await VV.confirm('Biztosan törli ezt a megrendelést? Ez nem vonható vissza!')) return;
    const res = await VV.del(`orders/${orderId}`);
    if (res && res.success) {
        VV.toast('Megrendelés törölve.', 'success');
        hideOrderDetail();
        loadOrders();
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

// Céges jelölő toggle
async function toggleCompany(orderId, isCompany) {
    const res = await VV.put(`orders/${orderId}`, { is_company: isCompany ? 1 : 0 });
    if (res && res.success) {
        VV.toast(isCompany ? 'Céges megrendelőként jelölve.' : 'Magánszemélyként jelölve.', 'success');
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
    }
}

// Megrendelés megnyitása külső hívásból (dashboard-ról)
window.showOrder = showOrder;

init_orders();
</script>
