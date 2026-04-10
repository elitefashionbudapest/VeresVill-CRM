<!-- Dashboard oldal -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</h1>
            </div>
            <div class="col-sm-6">
                <small class="float-sm-right text-muted" id="dash-last-update"></small>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <!-- Összesítő kártyák -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3 id="stat-new">-</h3>
                        <p>Új megrendelés</p>
                    </div>
                    <div class="icon"><i class="fas fa-bell"></i></div>
                    <a href="#" onclick="navigateTo('orders'); return false;" class="small-box-footer">
                        Részletek <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3 id="stat-pending">-</h3>
                        <p>Függő árajánlat</p>
                    </div>
                    <div class="icon"><i class="fas fa-file-invoice"></i></div>
                    <a href="#" onclick="navigateTo('orders'); return false;" class="small-box-footer">
                        Részletek <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3 id="stat-today">-</h3>
                        <p>Mai munka</p>
                    </div>
                    <div class="icon"><i class="fas fa-calendar-day"></i></div>
                    <a href="#" onclick="navigateTo('calendar'); return false;" class="small-box-footer">
                        Naptár <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3 id="stat-revenue">-</h3>
                        <p>Havi bevétel</p>
                    </div>
                    <div class="icon"><i class="fas fa-coins"></i></div>
                    <a href="#" class="small-box-footer">&nbsp;</a>
                </div>
            </div>
        </div>

        <!-- Legutóbbi megrendelések -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-clipboard-list mr-2"></i>Legutóbbi megrendelések</h3>
                        <div class="card-tools">
                            <a href="#" onclick="navigateTo('orders'); return false;" class="btn btn-sm btn-primary">
                                Összes megtekintése
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Megrendelő</th>
                                        <th class="d-none d-md-table-cell">Cím</th>
                                        <th>Státusz</th>
                                        <th class="d-none d-sm-table-cell">Dátum</th>
                                    </tr>
                                </thead>
                                <tbody id="dash-recent-orders">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="fas fa-spinner fa-spin mr-2"></i>Betöltés...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
async function init_dashboard() {
    // Statisztikák betöltése
    const stats = await VV.get('dashboard/stats');
    if (stats && stats.success) {
        const d = stats.data;
        document.getElementById('stat-new').textContent = d.counts?.uj || 0;
        document.getElementById('stat-pending').textContent = d.counts?.ajanlat_kuldve || 0;
        document.getElementById('stat-today').textContent = d.today_appointments || 0;
        document.getElementById('stat-revenue').textContent = VV.formatMoney(d.monthly_revenue || 0);
    }

    // Legutóbbi megrendelések
    const orders = await VV.get('orders?per_page=10&page=1');
    if (orders && orders.success) {
        const tbody = document.getElementById('dash-recent-orders');
        if (orders.data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-inbox mr-2"></i>Nincsenek megrendelések
                    </td>
                </tr>`;
        } else {
            tbody.innerHTML = orders.data.map(o => `
                <tr onclick="navigateTo('orders'); setTimeout(() => window.showOrder && showOrder(${o.id}), 300);" style="cursor: pointer;">
                    <td><strong>#${o.id}</strong></td>
                    <td>
                        <strong>${escHtml(o.customer_name)}</strong><br>
                        <small class="text-muted">${escHtml(o.customer_phone)}</small>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <small>${escHtml(o.customer_address)}</small>
                    </td>
                    <td>${VV.statusBadge(o.status)}</td>
                    <td class="d-none d-sm-table-cell">
                        <small>${VV.formatDateTime(o.created_at)}</small>
                    </td>
                </tr>
            `).join('');
        }
    }

    // Utolsó frissítés idő
    document.getElementById('dash-last-update').textContent =
        'Frissítve: ' + new Date().toLocaleTimeString('hu-HU');
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

init_dashboard();
</script>
