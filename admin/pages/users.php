<!-- Felhasználók oldal (csak admin) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-users mr-2"></i>Felhasználók</h1>
            </div>
            <div class="col-sm-6 text-sm-right">
                <button class="btn btn-sm btn-primary" onclick="showUserModal()">
                    <i class="fas fa-plus mr-1"></i>Új felhasználó
                </button>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Név</th>
                            <th>Email</th>
                            <th>Szerepkör</th>
                            <th>Státusz</th>
                            <th>Utolsó belépés</th>
                            <th width="80"></th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody">
                        <tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- User modal -->
<div class="modal fade" id="user-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="user-modal-title">Új felhasználó</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="user-id">
                <div class="form-group">
                    <label>Név</label>
                    <input type="text" id="user-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="user-email" class="form-control" required>
                </div>
                <div class="form-group" id="user-password-group">
                    <label>Jelszó</label>
                    <input type="password" id="user-password" class="form-control" minlength="8">
                    <small class="text-muted" id="user-password-hint">Minimum 8 karakter</small>
                </div>
                <div class="form-group">
                    <label>Szerepkör</label>
                    <select id="user-role" class="form-control">
                        <option value="worker">Munkatárs</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="user-active" checked>
                        <label class="custom-control-label" for="user-active">Aktív</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Mégse</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">
                    <i class="fas fa-save mr-1"></i>Mentés
                </button>
            </div>
        </div>
    </div>
</div>

<script>
async function init_users() {
    if (!VV.isAdmin()) {
        document.getElementById('page-content').innerHTML = '<div class="alert alert-danger m-3">Nincs jogosultsága.</div>';
        return;
    }
    loadUsers();
}

async function loadUsers() {
    const data = await VV.get('users');
    if (!data || !data.success) return;

    const tbody = document.getElementById('users-tbody');
    tbody.innerHTML = data.data.map(u => `
        <tr>
            <td><strong>${escHtml(u.name)}</strong></td>
            <td>${escHtml(u.email)}</td>
            <td><span class="badge ${u.role === 'admin' ? 'badge-primary' : 'badge-secondary'}">${u.role === 'admin' ? 'Admin' : 'Munkatárs'}</span></td>
            <td>${u.is_active ? '<span class="badge badge-success">Aktív</span>' : '<span class="badge badge-danger">Inaktív</span>'}</td>
            <td><small>${u.last_login ? VV.formatDateTime(u.last_login) : 'Még nem lépett be'}</small></td>
            <td>
                <button class="btn btn-xs btn-outline-primary" onclick='editUser(${JSON.stringify(u)})'>
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function showUserModal() {
    document.getElementById('user-modal-title').textContent = 'Új felhasználó';
    document.getElementById('user-id').value = '';
    document.getElementById('user-name').value = '';
    document.getElementById('user-email').value = '';
    document.getElementById('user-password').value = '';
    document.getElementById('user-role').value = 'worker';
    document.getElementById('user-active').checked = true;
    document.getElementById('user-password-hint').textContent = 'Minimum 8 karakter';
    document.getElementById('user-password').required = true;
    $('#user-modal').modal('show');
}

function editUser(u) {
    document.getElementById('user-modal-title').textContent = 'Felhasználó szerkesztése';
    document.getElementById('user-id').value = u.id;
    document.getElementById('user-name').value = u.name;
    document.getElementById('user-email').value = u.email;
    document.getElementById('user-password').value = '';
    document.getElementById('user-role').value = u.role;
    document.getElementById('user-active').checked = !!u.is_active;
    document.getElementById('user-password-hint').textContent = 'Hagyja üresen ha nem változtatja';
    document.getElementById('user-password').required = false;
    $('#user-modal').modal('show');
}

async function saveUser() {
    const id = document.getElementById('user-id').value;
    const payload = {
        name: document.getElementById('user-name').value.trim(),
        email: document.getElementById('user-email').value.trim(),
        role: document.getElementById('user-role').value,
        is_active: document.getElementById('user-active').checked ? 1 : 0
    };

    const password = document.getElementById('user-password').value;
    if (password) payload.password = password;

    if (!payload.name || !payload.email) {
        VV.toast('Töltse ki a kötelező mezőket.', 'error');
        return;
    }

    let res;
    if (id) {
        res = await VV.put(`users/${id}`, payload);
    } else {
        if (!password || password.length < 8) {
            VV.toast('Jelszó legalább 8 karakter.', 'error');
            return;
        }
        res = await VV.post('users', payload);
    }

    if (res && res.success) {
        VV.toast(id ? 'Felhasználó frissítve.' : 'Felhasználó létrehozva.', 'success');
        $('#user-modal').modal('hide');
        loadUsers();
    } else {
        VV.toast(res?.message || 'Hiba történt.', 'error');
    }
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

init_users();
</script>
