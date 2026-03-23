<script setup>
import { ref, computed, onMounted } from 'vue';

const token = ref(localStorage.getItem('auth_token') || '');
const user = ref(null);
const loginName = ref('');
const loginPass = ref('');
const loginError = ref('');
const loggingIn = ref(false);

const users = ref([]);
const loading = ref(true);
const search = ref('');
const roleFilter = ref('all');
const showForm = ref(false);
const editingUser = ref(null);
const formError = ref('');

const form = ref({ nombre: '', usuario: '', telefono: '', rol: 'vendedor', password: '' });

const roles = [
    { value: 'administrador', label: 'Administrador' },
    { value: 'vendedor', label: 'Vendedor' },
    { value: 'checador', label: 'Checador / Validador' },
    { value: 'promotor', label: 'Promotor' },
];

const roleLabel = (rol) => {
    const r = roles.find(x => x.value === rol);
    return r ? r.label : rol;
};

const roleBadgeColor = (rol) => {
    const map = { administrador: '#8B1A1A', superadministrador: '#8B1A1A', vendedor: '#1A4A2E', promotor: '#C8922A', checador: '#42A5F5' };
    return map[rol] || '#6B6055';
};

const ax = () => {
    const i = window.axios.create();
    if (token.value) i.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
    return i;
};

const doLogin = async () => {
    loggingIn.value = true; loginError.value = '';
    try {
        const { data } = await window.axios.post('/api/auth/login', {
            login: loginName.value,
            password: loginPass.value,
            intended_roles: ['admin'],
        });
        if (data.user?.role !== 'admin') { loginError.value = 'Solo administradores pueden gestionar usuarios.'; return; }
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem('auth_token', data.token);
        await loadUsers();
    } catch (e) { loginError.value = e.response?.data?.message || 'Credenciales inválidas.'; }
    finally { loggingIn.value = false; }
};

const doLogout = async () => {
    try { if (token.value) await ax().post('/api/auth/logout'); } catch {}
    token.value = ''; user.value = null;
    localStorage.removeItem('auth_token');
    window.location.href = '/';
};

const clearInvalidSession = () => {
    token.value = ''; user.value = null;
    localStorage.removeItem('auth_token');
};

const loadUsers = async () => {
    loading.value = true;
    try {
        const params = {};
        if (roleFilter.value !== 'all') params.role = roleFilter.value;
        if (search.value) params.search = search.value;
        const { data } = await ax().get('/api/admin/users', { params });
        users.value = data;
    } catch {}
    finally { loading.value = false; }
};

const filteredUsers = computed(() => users.value);

const openNew = () => {
    editingUser.value = null;
    form.value = { nombre: '', usuario: '', telefono: '', rol: 'vendedor', password: '' };
    formError.value = '';
    showForm.value = true;
};

const openEdit = (u) => {
    editingUser.value = u;
    form.value = { nombre: u.nombre, usuario: u.usuario, telefono: u.telefono || '', rol: u.rol, password: '' };
    formError.value = '';
    showForm.value = true;
};

const closeForm = () => { showForm.value = false; editingUser.value = null; formError.value = ''; };

const saveUser = async () => {
    formError.value = '';
    try {
        if (editingUser.value) {
            const payload = { ...form.value };
            if (!payload.password) delete payload.password;
            await ax().put(`/api/admin/users/${editingUser.value.id}`, payload);
        } else {
            await ax().post('/api/admin/users', form.value);
        }
        closeForm();
        await loadUsers();
    } catch (e) {
        const errors = e.response?.data?.errors;
        formError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || 'Error al guardar.');
    }
};

const toggleActive = async (u) => {
    try {
        await ax().patch(`/api/admin/users/${u.id}/toggle-active`);
        await loadUsers();
    } catch { alert('Error al cambiar estado.'); }
};

onMounted(async () => {
    if (token.value) {
        try {
            const { data } = await ax().get('/api/auth/me');
            if (data?.role !== 'admin') { clearInvalidSession(); loginError.value = 'Solo administradores.'; return; }
            user.value = data;
            await loadUsers();
        } catch { doLogout(); }
    }
});

const searchDebounce = ref(null);
const onSearch = () => {
    clearTimeout(searchDebounce.value);
    searchDebounce.value = setTimeout(() => loadUsers(), 350);
};

const onRoleFilter = () => loadUsers();
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Marca <span>MGR</span></a>
        <div class="topbar-nav">
            <a v-if="token" href="#" class="active" @click.prevent="doLogout">Cerrar Sesion</a>
            <a v-else href="/">Inicio</a>
        </div>
    </nav>
    <div class="page-label">Gestión de Usuarios</div>

    <!-- Login -->
    <div v-if="!token" style="padding:80px 40px;text-align:center;">
        <div style="font-size:64px;margin-bottom:16px;">🔐</div>
        <div class="section-title" style="margin-bottom:8px;font-family:'Playfair Display',serif;font-size:24px;color:var(--dorado-claro);">Acceso de Administrador</div>
        <div style="font-family:'DM Mono',monospace;font-size:11px;color:var(--gris);margin-bottom:24px;">Solo administradores pueden gestionar usuarios</div>
        <div style="max-width:350px;margin:0 auto;display:flex;flex-direction:column;gap:12px;">
            <input v-model="loginName" type="text" placeholder="Usuario" @keyup.enter="doLogin" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
            <input v-model="loginPass" type="password" placeholder="Contraseña" @keyup.enter="doLogin" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
            <div v-if="loginError" style="color:#F44336;font-family:'DM Mono',monospace;font-size:11px;">{{ loginError }}</div>
            <button @click="doLogin" :disabled="loggingIn" style="background:var(--rojo);border:none;padding:14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;letter-spacing:2px;cursor:pointer;">{{ loggingIn ? 'INICIANDO...' : 'INICIAR SESIÓN' }}</button>
        </div>
    </div>

    <!-- Users Panel -->
    <div v-else class="admin-layout">
        <div class="admin-sidebar">
            <div class="sidebar-user">
                <div class="sidebar-avatar">👤</div>
                <div class="sidebar-name">{{ user?.name || '...' }}</div>
                <div class="sidebar-role">Admin</div>
            </div>
            <div class="nav-group">
                <div class="nav-group-label">Principal</div>
                <a href="/admin" class="nav-item">📊 Dashboard</a>
                <a href="#" class="nav-item">📅 Eventos</a>
            </div>
            <div class="nav-group">
                <div class="nav-group-label">Administración</div>
                <a href="/usuarios" class="nav-item active">👥 Usuarios</a>
                <a href="/barra" class="nav-item">🍻 POS Barra</a>
                <a href="/reportes" class="nav-item">📈 Reportes Tickets</a>
                <a href="/barra-reportes" class="nav-item">🍺 Reportes Barra</a>
            </div>
            <div class="nav-group">
                <div class="nav-group-label">Sistema</div>
                <a href="#" class="nav-item" @click.prevent="doLogout">🚪 Cerrar sesión</a>
            </div>
        </div>

        <div class="admin-main">
            <div class="admin-header">
                <div>
                    <div class="admin-title">Usuarios del Sistema</div>
                    <div class="admin-subtitle">{{ users.length }} usuario(s) registrados</div>
                </div>
                <div class="header-actions">
                    <button class="btn-primary" @click="openNew">+ Nuevo Usuario</button>
                </div>
            </div>

            <!-- Filters -->
            <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
                <input class="search-mini" type="text" v-model="search" @input="onSearch" placeholder="Buscar por nombre, usuario o teléfono..." style="flex:1;min-width:200px;">
                <select v-model="roleFilter" @change="onRoleFilter" class="filter-select">
                    <option value="all">Todos los roles</option>
                    <option value="admin">Administradores</option>
                    <option value="seller">Vendedores</option>
                    <option value="validator">Validadores</option>
                </select>
            </div>

            <!-- Form Modal -->
            <div v-if="showForm" class="form-overlay" @click.self="closeForm">
                <div class="form-card">
                    <div class="form-title">{{ editingUser ? 'Editar Usuario' : 'Nuevo Usuario' }}</div>
                    <div v-if="formError" style="color:#F44336;font-family:'DM Mono',monospace;font-size:11px;margin-bottom:12px;">{{ formError }}</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre completo *</label>
                            <input v-model="form.nombre" type="text" placeholder="Juan Pérez">
                        </div>
                        <div class="form-group">
                            <label>Usuario (login) *</label>
                            <input v-model="form.usuario" type="text" placeholder="juanperez">
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input v-model="form.telefono" type="text" placeholder="(opcional)">
                        </div>
                        <div class="form-group">
                            <label>Rol *</label>
                            <select v-model="form.rol">
                                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Contraseña {{ editingUser ? '(dejar vacío para no cambiar)' : '*' }}</label>
                            <input v-model="form.password" type="password" placeholder="••••••">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn-outline" @click="closeForm">Cancelar</button>
                        <button class="btn-primary" @click="saveUser">{{ editingUser ? 'Guardar cambios' : 'Crear usuario' }}</button>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="chart-card">
                <div v-if="loading" style="text-align:center;padding:40px;font-family:'DM Mono',monospace;color:var(--gris);">Cargando usuarios...</div>
                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Teléfono</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in filteredUsers" :key="u.id">
                            <td style="font-family:'DM Mono',monospace;color:var(--gris);">{{ u.id }}</td>
                            <td><b>{{ u.nombre }}</b></td>
                            <td style="font-family:'DM Mono',monospace;">{{ u.usuario }}</td>
                            <td>{{ u.telefono || '—' }}</td>
                            <td>
                                <span class="role-badge" :style="{ background: roleBadgeColor(u.rol) }">{{ roleLabel(u.rol) }}</span>
                            </td>
                            <td>
                                <span :class="['status-dot', u.activo ? 'active' : 'inactive']"></span>
                                {{ u.activo ? 'Activo' : 'Inactivo' }}
                            </td>
                            <td class="action-cell">
                                <button class="action-btn edit" @click="openEdit(u)" title="Editar">✏️</button>
                                <button class="action-btn toggle" @click="toggleActive(u)" :title="u.activo ? 'Desactivar' : 'Activar'">
                                    {{ u.activo ? '🔒' : '🔓' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="filteredUsers.length === 0">
                            <td colspan="7" style="text-align:center;padding:30px;font-family:'DM Mono',monospace;color:var(--gris);">No se encontraron usuarios.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Baskerville:wght@400;700&family=DM+Mono:wght@400;500&display=swap');
:root {
  --rojo:#8B1A1A; --dorado:#C8922A; --dorado-strong:#D9A441;
  --dorado-claro:#F6C56F; --miel:#E2A85C; --crema:#F5EFE0; --cafe:#2B1606;
  --cafe-glass:rgba(43,22,6,0.55); --verde:#1A4A2E; --gris:#7A6F63; --blanco:#FDFAF4; --bg:#140700;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Libre Baskerville', serif; background: radial-gradient(circle at 20% 20%,rgba(217,164,65,0.06),transparent 45%), radial-gradient(circle at 80% 60%,rgba(139,26,26,0.10),transparent 45%), var(--bg); color: var(--crema); min-height: 100vh; }
.topbar { background:rgba(139,26,26,0.55); backdrop-filter:blur(18px); border-bottom:1px solid rgba(255,255,255,0.06); padding:14px 32px; display:flex; justify-content:space-between; align-items:center; }
.topbar-brand { font-family:'Playfair Display',serif; font-size:22px; font-weight:900; color:var(--crema); text-decoration:none; }
.topbar-brand span { color:var(--dorado-claro); }
.topbar-nav { display:flex; gap:6px; }
.topbar-nav a { padding:8px 16px; font-family:'DM Mono',monospace; font-size:10px; letter-spacing:2px; text-transform:uppercase; color:rgb(245,239,224); text-decoration:none; border-radius:10px; transition:all .3s ease; }
.topbar-nav a.active { color:white; background:rgb(199,12,12); backdrop-filter:blur(6px); box-shadow:0 0 0 1px rgba(246,197,111,0.25) inset,0 4px 14px rgba(0,0,0,0.4); }
.topbar-nav a:hover { color:white; background:rgba(0,0,0,0.74); backdrop-filter:blur(6px); box-shadow:0 0 0 1px rgba(246,197,111,0.25) inset,0 4px 14px rgba(0,0,0,0.4); }
.page-label { background:rgba(0,0,0,0.35); backdrop-filter:blur(14px); padding:10px 32px; font-family:'DM Mono',monospace; font-size:10px; letter-spacing:3px; text-transform:uppercase; display:flex; align-items:center; gap:10px; border-bottom:1px solid rgba(217,164,65,0.15); color:var(--gris); }

.admin-layout { display: grid; grid-template-columns: 230px 1fr; min-height: calc(100vh - 82px); }
.admin-sidebar { background: #100400; border-right: 1px solid rgba(200,146,42,0.2); padding-top: 8px; }
.sidebar-user { padding: 20px; border-bottom: 1px solid rgba(200,146,42,0.1); margin-bottom: 8px; text-align: center; }
.sidebar-avatar { font-size: 36px; margin-bottom: 4px; }
.sidebar-name { font-family: 'DM Mono', monospace; font-size: 12px; color: var(--crema); }
.sidebar-role { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--dorado); text-transform: uppercase; letter-spacing: 2px; }
.nav-group { margin-bottom: 4px; }
.nav-group-label { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 3px; color: rgba(107,96,85,0.5); padding: 12px 20px 6px; text-transform: uppercase; }
.nav-item { padding: 11px 20px; font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 1px; color: var(--gris); cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all 0.2s; text-decoration: none; text-transform: uppercase; border-left: 3px solid transparent; }
.nav-item:hover { color: var(--dorado-claro); background: rgba(200,146,42,0.06); }
.nav-item.active { color: var(--dorado-claro); background: rgba(200,146,42,0.1); border-left-color: var(--dorado); }

.admin-main { padding: 32px; overflow-y: auto; }
.admin-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; }
.admin-title { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--dorado-claro); }
.admin-subtitle { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; margin-top: 3px; }
.header-actions { display: flex; gap: 10px; }
.btn-primary { background: var(--rojo); border: none; padding: 11px 22px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; transition: background 0.2s; }
.btn-primary:hover { background: #A02020; }
.btn-outline { background: none; border: 1.5px solid var(--dorado); padding: 10px 20px; color: var(--dorado); font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; transition: all 0.2s; }
.btn-outline:hover { background: var(--dorado); color: var(--cafe); }

.search-mini { background: rgba(0,0,0,0.5); border: 1px solid rgba(200,146,42,0.3); padding: 10px 14px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 11px; outline: none; }
.filter-select { background: rgba(0,0,0,0.5); border: 1px solid rgba(200,146,42,0.3); padding: 10px 14px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 11px; outline: none; min-width: 180px; }

.chart-card { background: #1A0800; border: 1px solid rgba(200,146,42,0.2); padding: 24px; margin-bottom: 20px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); padding: 10px 14px; text-align: left; border-bottom: 1px solid rgba(200,146,42,0.3); background: rgba(0,0,0,0.3); }
.data-table td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid rgba(200,146,42,0.06); }
.data-table tr:hover td { background: rgba(200,146,42,0.03); }

.role-badge { display: inline-block; padding: 3px 10px; font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 1px; color: var(--crema); border-radius: 3px; text-transform: uppercase; }
.status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
.status-dot.active { background: #4CAF50; }
.status-dot.inactive { background: #F44336; }
.action-cell { display: flex; gap: 6px; }
.action-btn { background: none; border: 1px solid rgba(200,146,42,0.2); padding: 5px 10px; cursor: pointer; font-size: 14px; transition: all 0.2s; }
.action-btn:hover { background: rgba(200,146,42,0.1); }

.form-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 100; }
.form-card { background: #1A0800; border: 1px solid rgba(200,146,42,0.3); padding: 32px; width: 520px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
.form-title { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--dorado-claro); margin-bottom: 20px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.form-group label { display: block; font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); margin-bottom: 6px; }
.form-group input, .form-group select { width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(200,146,42,0.3); padding: 10px 12px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 12px; outline: none; }
.form-group input:focus, .form-group select:focus { border-color: var(--dorado); }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; }

@media (max-width: 768px) {
    .admin-layout { grid-template-columns: 1fr; }
    .admin-sidebar { display: none; }
    .form-grid { grid-template-columns: 1fr; }
}
</style>
