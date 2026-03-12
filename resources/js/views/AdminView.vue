<script setup>
import { ref, computed, onMounted } from 'vue';

const token = ref(localStorage.getItem('auth_token') || '');
const user = ref(null);
const loginName = ref('');
const loginPass = ref('');
const loginError = ref('');
const loggingIn = ref(false);
const allowedRoles = ['seller', 'admin'];

const events = ref([]);
const summary = ref({ orders_count: 0, tickets_sold: 0, revenue_total: 0, attendance_rate: 0, fraud_attempts: 0 });
const loading = ref(true);
const search = ref('');
const statusFilter = ref('all');
const showForm = ref(false);
const editingEvent = ref(null);

const form = ref({ name: '', description: '', city: '', venue: '', starts_at: '', ends_at: '', barcode_format: 'qr', status: 'draft', zones: [{ name: '', capacity: '', price: '' }] });

const ax = () => {
    const i = window.axios.create();
    if (token.value) i.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
    return i;
};

const isAdmin = computed(() => user.value?.role === 'admin');
const showSidebar = computed(() => isAdmin.value);
const dashboardTitle = computed(() => (isAdmin.value ? 'Dashboard Admin' : 'Panel de Venta'));
const pageLabel = computed(() => (isAdmin.value ? 'Panel de Administracion' : 'Panel de Venta'));
const apiPrefix = computed(() => (isAdmin.value ? '/api/admin' : '/api/seller'));
const hasAllowedRole = (role) => allowedRoles.includes(role);

const doLogin = async () => {
    loggingIn.value = true; loginError.value = '';
    try {
        const { data } = await window.axios.post('/api/auth/login', {
            login: loginName.value,
            password: loginPass.value,
            intended_roles: allowedRoles,
        });
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem('auth_token', data.token);

        await loadData();
    } catch (e) { loginError.value = e.response?.data?.message || 'Credenciales inválidas.'; }
    finally { loggingIn.value = false; }
};

const doLogout = async () => {
    try {
        if (token.value) await ax().post('/api/auth/logout');
    } catch {
        // Ignore logout API errors and continue local cleanup.
    }

    token.value = '';
    user.value = null;
    localStorage.removeItem('auth_token');
    window.location.href = '/';
};

const clearInvalidSession = () => {
    token.value = '';
    user.value = null;
    localStorage.removeItem('auth_token');
};

const loadData = async () => {
    loading.value = true;
    try {
        const [evRes, sumRes] = await Promise.all([
            ax().get(`${apiPrefix.value}/events`),
            ax().get(`${apiPrefix.value}/reports/summary`),
        ]);
        events.value = evRes.data.data || evRes.data;
        summary.value = sumRes.data;
    } catch { /* noop */ }
    finally { loading.value = false; }
};

onMounted(async () => {
    if (token.value) {
        try {
            const { data } = await ax().get('/api/auth/me');
            if (!hasAllowedRole(data?.role)) {
                clearInvalidSession();
                loginError.value = 'Esta cuenta no tiene acceso a operaciones.';
                return;
            }

            user.value = data;
            await loadData();
        }
        catch { doLogout(); }
    }
});

const currency = v => '$' + Number(v || 0).toLocaleString('es-MX', { minimumFractionDigits: 0 });
const fmtDate = d => { if (!d) return ''; const dt = new Date(d); return dt.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }); };
const fmtTime = d => { if (!d) return ''; return new Date(d).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }); };

const totalCap = ev => (ev.zones || []).reduce((s, z) => s + (z.capacity || 0), 0);
const totalSold = ev => (ev.zones || []).reduce((s, z) => s + (z.tickets_count ?? z.sold ?? 0), 0);
const totalRevenue = ev => (ev.zones || []).reduce((s, z) => s + (z.tickets_count ?? z.sold ?? 0) * (z.price || 0), 0);
const occupancy = ev => { const cap = totalCap(ev); return cap > 0 ? ((totalSold(ev) / cap) * 100).toFixed(1) : '0'; };
const statusLabel = s => ({ draft: 'Borrador', published: 'Activo', canceled: 'Cancelado' })[s] || s;
const statusClass = s => ({ draft: 'draft', published: 'active', canceled: 'sold' })[s] || 'draft';
const activeEvents = computed(() => events.value.filter((e) => e.status === 'published').length);

const toggleStatusFilter = () => {
    const order = ['all', 'published', 'draft', 'canceled'];
    const current = order.indexOf(statusFilter.value);
    statusFilter.value = order[(current + 1) % order.length];
};

const statusFilterLabel = computed(() => {
    return {
        all: 'Todos',
        published: 'Activos',
        draft: 'Borradores',
        canceled: 'Cancelados',
    }[statusFilter.value] || 'Todos';
});

const filteredEvents = computed(() => {
    let base = events.value;

    if (statusFilter.value !== 'all') {
        base = base.filter((e) => e.status === statusFilter.value);
    }

    if (!search.value) return base;
    const q = search.value.toLowerCase();
    return base.filter(e => e.name.toLowerCase().includes(q) || (e.city || '').toLowerCase().includes(q));
});

const icons = ['🐎','🤠','🏟️','🎪','🎭','🎯','🎵','🎶'];

const recentSales = computed(() => {
    return [...events.value]
        .map((ev) => {
            const sold = totalSold(ev);
            const amount = totalRevenue(ev);
            return {
                id: ev.id,
                name: ev.name,
                meta: `${sold.toLocaleString()} boletos vendidos`,
                amount,
            };
        })
        .filter((row) => row.amount > 0 || row.meta)
        .sort((a, b) => b.amount - a.amount)
        .slice(0, 4);
});

const adminAlerts = computed(() => {
    const alerts = [];

    const lowOccupancy = events.value
        .filter((ev) => ev.status === 'published')
        .filter((ev) => Number(occupancy(ev)) < 50)
        .slice(0, 2);

    lowOccupancy.forEach((ev) => {
        alerts.push({
            id: `low-${ev.id}`,
            kind: 'warning',
            icon: '⚠️',
            text: `${ev.name} tiene ocupacion menor al 50%. Considera una promocion.`,
            meta: 'alerta de rendimiento',
        });
    });

    const soldOut = events.value
        .filter((ev) => ev.status === 'published' && Number(occupancy(ev)) >= 100)
        .slice(0, 2);

    soldOut.forEach((ev) => {
        alerts.push({
            id: `sold-${ev.id}`,
            kind: 'info',
            icon: 'ℹ️',
            text: `${ev.name} se encuentra agotado.`,
            meta: 'estado de inventario',
        });
    });

    if (alerts.length === 0) {
        alerts.push({
            id: 'ok',
            kind: 'info',
            icon: '✅',
            text: 'Sin alertas criticas por el momento.',
            meta: 'monitoreo en linea',
        });
    }

    return alerts.slice(0, 4);
});

const startNewSale = () => {
    const targetEvent = events.value.find((e) => e.status === 'published') || events.value[0];

    if (!targetEvent) {
        alert('No hay eventos disponibles para iniciar una venta.');
        return;
    }

    window.location.href = `/compra?event=${targetEvent.id}`;
};

const sellEvent = (eventId) => {
    window.location.href = `/compra?event=${eventId}`;
};

const openNew = () => {
    if (!isAdmin.value) return;
    editingEvent.value = null;
    form.value = { name: '', description: '', city: '', venue: '', starts_at: '', ends_at: '', barcode_format: 'qr', status: 'draft', zones: [{ name: '', capacity: '', price: '' }] };
    showForm.value = true;
};
const openEdit = (ev) => {
    if (!isAdmin.value) return;
    editingEvent.value = ev;
    form.value = { name: ev.name, description: ev.description || '', city: ev.city, venue: ev.venue, starts_at: ev.starts_at?.slice(0, 16) || '', ends_at: ev.ends_at?.slice(0, 16) || '', barcode_format: ev.barcode_format || 'qr', status: ev.status, zones: (ev.zones || []).map(z => ({ name: z.name, capacity: z.capacity, price: z.price })) };
    showForm.value = true;
};
const addZone = () => form.value.zones.push({ name: '', capacity: '', price: '' });
const removeZone = i => form.value.zones.splice(i, 1);

const saveEvent = async () => {
    if (!isAdmin.value) return;
    const payload = { ...form.value, zones: form.value.zones.map(z => ({ name: z.name, capacity: Number(z.capacity), price: Number(z.price) })) };
    try {
        if (editingEvent.value) await ax().put(`${apiPrefix.value}/events/${editingEvent.value.id}`, payload);
        else await ax().post(`${apiPrefix.value}/events`, payload);
        showForm.value = false; await loadData();
    } catch (e) { alert(e.response?.data?.message || 'Error al guardar.'); }
};

const deleteEvent = async (ev) => {
    if (!isAdmin.value) return;
    if (!confirm(`¿Eliminar "${ev.name}"?`)) return;
    try { await ax().delete(`${apiPrefix.value}/events/${ev.id}`); await loadData(); } catch { alert('Error al eliminar.'); }
};
const publishEvent = async (ev) => {
    if (!isAdmin.value) return;
    try { await ax().post(`${apiPrefix.value}/events/${ev.id}/publish`); await loadData(); } catch { alert('Error al publicar.'); }
};
const cancelEvent = async (ev) => {
    if (!isAdmin.value) return;
    try { await ax().post(`${apiPrefix.value}/events/${ev.id}/cancel`); await loadData(); } catch { alert('Error al cancelar.'); }
};
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Marca <span>MGR</span></a>
        <div class="topbar-nav">
            <a v-if="token" href="#" class="active" @click.prevent="doLogout">Cerrar Sesion</a>
            <a v-else href="/">Inicio</a>
        </div>
    </nav>
    <div class="page-label">{{ pageLabel }}</div>

    <!-- Login -->
    <div v-if="!token" style="padding:80px 40px;text-align:center;">
        <div style="font-size:64px;margin-bottom:16px;">🔐</div>
        <div class="section-title" style="margin-bottom:8px;font-family:'Playfair Display',serif;font-size:24px;color:var(--dorado-claro);">Acceso de Operaciones</div>
        <div style="font-family:'DM Mono',monospace;font-size:11px;color:var(--gris);margin-bottom:24px;">Inicia sesion con tu cuenta autorizada</div>
        <div style="max-width:350px;margin:0 auto;display:flex;flex-direction:column;gap:12px;">
            <input v-model="loginName" type="text" placeholder="Usuario o correo" @keyup.enter="doLogin" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
            <input v-model="loginPass" type="password" placeholder="Contraseña" @keyup.enter="doLogin" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
            <div v-if="loginError" style="color:#F44336;font-family:'DM Mono',monospace;font-size:11px;">{{ loginError }}</div>
            <button @click="doLogin" :disabled="loggingIn" style="background:var(--rojo);border:none;padding:14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;letter-spacing:2px;cursor:pointer;">{{ loggingIn ? 'INICIANDO...' : 'INICIAR SESIÓN' }}</button>
        </div>
    </div>

    <!-- Admin Panel -->
    <div v-else :class="['admin-layout', { 'no-sidebar': !showSidebar }]">
        <div v-if="showSidebar" class="admin-sidebar">
            <div class="sidebar-user">
                <div class="sidebar-avatar">👤</div>
                <div class="sidebar-name">{{ user?.name || '...' }}</div>
                <div class="sidebar-role">{{ user?.role === 'seller' ? 'Vendedor' : user?.role }}</div>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Principal</div>
                <a href="/vendedor" class="nav-item active">📊 Dashboard</a>
                <a href="#" class="nav-item">📅 Eventos <span class="nav-badge">{{ events.length }}</span></a>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Operaciones</div>
                <a href="/barra" class="nav-item">🍻 POS Barra</a>
                <a href="/vendedor/reportes" class="nav-item">📈 Reportes</a>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Sistema</div>
                <a href="#" class="nav-item" @click.prevent="doLogout">🚪 Cerrar sesión</a>
            </div>
        </div>

        <div class="admin-main">
            <div class="admin-header">
                <div>
                    <div class="admin-title">{{ dashboardTitle }}</div>
                    <div class="admin-subtitle">{{ new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }).toUpperCase() }}</div>
                </div>
                <div class="header-actions">
                    <button class="btn-primary" @click="isAdmin ? openNew() : startNewSale()">{{ isAdmin ? '+ Nuevo Evento' : 'Nueva venta' }}</button>
                    <button class="btn-primary" @click="window.location.href = '/barra'">POS Barra</button>
                </div>
            </div>

            <!-- KPIs -->
            <div :class="['kpi-grid', isAdmin ? 'admin-kpi' : 'seller-kpi']">
                <div class="kpi-card">
                    <div class="kpi-icon">🎫</div>
                    <div class="kpi-label">Tickets Vendidos</div>
                    <div class="kpi-value">{{ summary.tickets_sold?.toLocaleString() }}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon">💰</div>
                    <div class="kpi-label">Ingresos Totales</div>
                    <div class="kpi-value">{{ currency(summary.revenue_total) }}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon">✅</div>
                    <div class="kpi-label">Tasa de Asistencia</div>
                    <div class="kpi-value">{{ summary.attendance_rate }}%</div>
                </div>
                <div v-if="isAdmin" class="kpi-card">
                    <div class="kpi-icon">📅</div>
                    <div class="kpi-label">Eventos Activos</div>
                    <div class="kpi-value">{{ activeEvents.toLocaleString() }}</div>
                </div>
            </div>

            <!-- Event table -->
            <div class="section-header">
                <div class="section-title">{{ isAdmin ? 'Gestión de Eventos' : 'Eventos Disponibles para Venta' }}</div>
                <div class="section-actions">
                    <button class="filter-btn" @click="toggleStatusFilter">Estado: {{ statusFilterLabel }}</button>
                    <input class="search-mini" type="text" v-model="search" placeholder="Buscar evento...">
                </div>
            </div>

            <div v-if="loading" style="text-align:center;padding:40px;font-family:'DM Mono',monospace;color:var(--gris);">Cargando...</div>

            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Fecha</th>
                        <th>Lugar</th>
                        <th>Vendidos</th>
                        <th>Ocupación</th>
                        <th>Ingresos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ev, idx) in filteredEvents" :key="ev.id">
                        <td>
                            <div class="event-name-cell">
                                <div class="event-thumb" style="background: rgba(139,26,26,0.2);">{{ icons[idx % icons.length] }}</div>
                                <div>
                                    <div style="font-weight: bold; color: var(--crema);">{{ ev.name }}</div>
                                    <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">{{ ev.venue }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>{{ fmtDate(ev.starts_at) }}</div>
                            <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">{{ fmtTime(ev.starts_at) }}</div>
                        </td>
                        <td>{{ ev.city }}</td>
                        <td><b>{{ totalSold(ev).toLocaleString() }}</b></td>
                        <td>
                            <div class="progress-mini">
                                <div class="progress-mini-bar">
                                    <div class="progress-mini-fill" :style="{ width: occupancy(ev) + '%' }"></div>
                                </div>
                                <div class="progress-mini-text">{{ totalSold(ev).toLocaleString() }} / {{ totalCap(ev).toLocaleString() }} — {{ occupancy(ev) }}%</div>
                            </div>
                        </td>
                        <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">{{ currency(totalRevenue(ev)) }}</td>
                        <td><span :class="'status-pill ' + statusClass(ev.status)">{{ statusLabel(ev.status) }}</span></td>
                        <td>
                            <div class="table-actions">
                                <template v-if="isAdmin">
                                    <button class="action-btn" @click="openEdit(ev)">✏ Editar</button>
                                    <button v-if="ev.status === 'draft'" class="action-btn" @click="publishEvent(ev)">📢 Publicar</button>
                                    <button v-if="ev.status === 'published'" class="action-btn" @click="cancelEvent(ev)">⛔ Cancelar</button>
                                    <button class="action-btn danger" @click="deleteEvent(ev)">🗑</button>
                                </template>
                                <button v-else class="action-btn" @click="sellEvent(ev.id)">🎟 Vender</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="filteredEvents.length === 0">
                        <td colspan="8" style="text-align:center;padding:30px;font-family:'DM Mono',monospace;color:var(--gris);">No hay eventos.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="isAdmin" class="bottom-grid">
                <div class="widget">
                    <div class="widget-title">Eventos con Mayor Venta</div>
                    <div v-for="(sale, idx) in recentSales" :key="sale.id" class="recent-sale">
                        <div class="sale-avatar">{{ icons[idx % icons.length] }}</div>
                        <div class="sale-info">
                            <div class="sale-name">{{ sale.name }}</div>
                            <div class="sale-meta">{{ sale.meta }}</div>
                        </div>
                        <div class="sale-amount">{{ currency(sale.amount) }}</div>
                    </div>
                    <div v-if="recentSales.length === 0" class="sale-meta">Sin movimientos para mostrar.</div>
                </div>

                <div class="widget">
                    <div class="widget-title">Alertas del Sistema</div>
                    <div v-for="alert in adminAlerts" :key="alert.id" :class="['alert-item', alert.kind]">
                        <div class="alert-icon">{{ alert.icon }}</div>
                        <div>
                            <div class="alert-text">{{ alert.text }}</div>
                            <div class="alert-meta">{{ alert.meta }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div v-if="showForm" style="position:fixed;inset:0;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:100;">
        <div style="background:#1A0800;border:1px solid rgba(200,146,42,0.3);padding:32px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
            <div style="font-family:'Playfair Display',serif;font-size:22px;color:var(--dorado-claro);margin-bottom:20px;">{{ editingEvent ? 'Editar Evento' : 'Nuevo Evento' }}</div>
            <div style="display:grid;gap:12px;">
                <input v-model="form.name" placeholder="Nombre del evento" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                <textarea v-model="form.description" placeholder="Descripción" rows="2" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;resize:vertical;"></textarea>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <input v-model="form.city" placeholder="Ciudad" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                    <input v-model="form.venue" placeholder="Lugar/Venue" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="font-family:'DM Mono',monospace;font-size:9px;color:var(--gris);letter-spacing:1px;">INICIO</label>
                        <input v-model="form.starts_at" type="datetime-local" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;width:100%;">
                    </div>
                    <div>
                        <label style="font-family:'DM Mono',monospace;font-size:9px;color:var(--gris);letter-spacing:1px;">FIN (OPCIONAL)</label>
                        <input v-model="form.ends_at" type="datetime-local" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;width:100%;">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <select v-model="form.barcode_format" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                        <option value="qr">QR Code</option>
                        <option value="code128">Code 128</option>
                    </select>
                    <select v-model="form.status" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                        <option value="draft">Borrador</option>
                        <option value="published">Publicado</option>
                    </select>
                </div>

                <div style="font-family:'DM Mono',monospace;font-size:10px;letter-spacing:2px;color:var(--dorado);margin-top:8px;">ZONAS</div>
                <div v-for="(z, zi) in form.zones" :key="zi" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;align-items:center;">
                    <input v-model="z.name" placeholder="Nombre zona" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:8px 12px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                    <input v-model="z.capacity" type="number" placeholder="Capacidad" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:8px 12px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                    <input v-model="z.price" type="number" placeholder="Precio" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:8px 12px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                    <button v-if="form.zones.length > 1" @click="removeZone(zi)" style="background:none;border:1px solid rgba(244,67,54,0.4);color:#F44336;padding:6px 10px;cursor:pointer;font-size:12px;">✕</button>
                </div>
                <button @click="addZone" style="background:none;border:1px dashed rgba(200,146,42,0.3);padding:8px;color:var(--gris);font-family:'DM Mono',monospace;font-size:11px;cursor:pointer;">+ Agregar zona</button>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
                <button @click="showForm = false" class="btn-outline">Cancelar</button>
                <button @click="saveEvent" class="btn-primary">{{ editingEvent ? 'Guardar Cambios' : 'Crear Evento' }}</button>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Baskerville:wght@400;700&family=DM+Mono:wght@400;500&display=swap');
:root {
  --rojo: #8B1A1A; --dorado: #C8922A; --dorado-claro: #F0C060;
  --crema: #F5EFE0; --cafe: #3D2008; --verde: #1A4A2E;
  --gris: #6B6055; --blanco: #FDFAF4; --bg: #2A1504;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Libre Baskerville', serif; background: var(--cafe); color: var(--crema); min-height: 100vh; }
.topbar { background: var(--rojo); padding: 12px 32px; display: flex; align-items: center; justify-content: space-between; }
.topbar-brand { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 900; color: var(--crema); text-decoration: none; }
.topbar-brand span { color: var(--dorado-claro); }
.topbar-nav { display: flex; gap: 4px; }
.topbar-nav a { padding: 7px 14px; font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: rgba(245,239,224,0.6); text-decoration: none; transition: color 0.2s; border-bottom: 2px solid transparent; }
.topbar-nav a:hover, .topbar-nav a.active { color: var(--dorado-claro); border-bottom-color: var(--dorado-claro); }
.page-label { background: var(--bg); padding: 8px 32px; font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: var(--gris); border-bottom: 1px solid rgba(200,146,42,0.2); }
.admin-layout { display: grid; grid-template-columns: 230px 1fr; min-height: calc(100vh - 82px); }
.admin-layout.no-sidebar { grid-template-columns: 1fr; }
.admin-sidebar { background: #100400; border-right: 1px solid rgba(200,146,42,0.2); padding-top: 8px; }
.sidebar-user { padding: 20px; border-bottom: 1px solid rgba(200,146,42,0.1); margin-bottom: 8px; }
.sidebar-avatar { width: 40px; height: 40px; background: var(--rojo); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 8px; }
.sidebar-name { font-family: 'Playfair Display', serif; font-size: 14px; color: var(--crema); }
.sidebar-role { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }
.nav-group { margin-bottom: 4px; }
.nav-group-label { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 3px; color: rgba(107,96,85,0.5); padding: 12px 20px 6px; text-transform: uppercase; }
.nav-item {
  padding: 11px 20px; font-family: 'DM Mono', monospace; font-size: 11px;
  letter-spacing: 1px; color: var(--gris); cursor: pointer;
  display: flex; align-items: center; gap: 10px; transition: all 0.2s;
  text-decoration: none; text-transform: uppercase; border-left: 3px solid transparent;
}
.nav-item:hover { color: var(--dorado-claro); background: rgba(200,146,42,0.06); }
.nav-item.active { color: var(--dorado-claro); background: rgba(200,146,42,0.1); border-left-color: var(--dorado); }
.nav-badge { margin-left: auto; background: var(--rojo); color: var(--crema); font-size: 9px; padding: 2px 7px; border-radius: 2px; }
.admin-main { padding: 32px; overflow-y: auto; }
.admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
.admin-title { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--dorado-claro); }
.admin-subtitle { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; margin-top: 3px; }
.header-actions { display: flex; gap: 10px; }
.btn-primary { background: var(--rojo); border: none; padding: 11px 22px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; transition: background 0.2s; }
.btn-primary:hover { background: #A02020; }
.btn-outline { background: none; border: 1.5px solid var(--dorado); padding: 10px 20px; color: var(--dorado); font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; transition: all 0.2s; }
.btn-outline:hover { background: var(--dorado); color: var(--cafe); }
.kpi-grid { display: grid; gap: 16px; margin-bottom: 28px; }
.kpi-grid.admin-kpi { grid-template-columns: repeat(4, 1fr); }
.kpi-grid.seller-kpi { grid-template-columns: repeat(3, 1fr); }
.kpi-card {
  background: #1A0800; border: 1px solid rgba(200,146,42,0.2);
  padding: 22px; position: relative; overflow: hidden; cursor: pointer;
  transition: border-color 0.2s;
}
.kpi-card:hover { border-color: rgba(200,146,42,0.5); }
.kpi-card::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: var(--dorado); }
.kpi-icon { font-size: 24px; margin-bottom: 10px; }
.kpi-label { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; color: var(--gris); text-transform: uppercase; margin-bottom: 8px; }
.kpi-value { font-family: 'Playfair Display', serif; font-size: 34px; color: var(--crema); line-height: 1; }
.kpi-trend { font-family: 'DM Mono', monospace; font-size: 10px; margin-top: 6px; }
.kpi-trend.up { color: #4CAF50; }
.kpi-trend.down { color: #F44336; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid rgba(200,146,42,0.15); }
.section-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--dorado-claro); }
.section-actions { display: flex; gap: 8px; align-items: center; }
.search-mini { background: rgba(0,0,0,0.4); border: 1px solid rgba(200,146,42,0.2); padding: 8px 14px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 11px; outline: none; width: 200px; }
.search-mini:focus { border-color: var(--dorado); }
.filter-btn { background: rgba(200,146,42,0.1); border: 1px solid rgba(200,146,42,0.2); padding: 8px 14px; color: var(--gris); font-family: 'DM Mono', monospace; font-size: 10px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); padding: 12px 16px; text-align: left; border-bottom: 1px solid rgba(200,146,42,0.3); background: rgba(0,0,0,0.3); white-space: nowrap; }
.data-table th.sortable { cursor: pointer; }
.data-table th.sortable:hover { color: var(--dorado-claro); }
.data-table td { padding: 13px 16px; font-size: 13px; border-bottom: 1px solid rgba(200,146,42,0.06); vertical-align: middle; }
.data-table tr:hover td { background: rgba(200,146,42,0.03); }
.event-name-cell { display: flex; align-items: center; gap: 12px; }
.event-thumb { width: 36px; height: 36px; border-radius: 2px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.progress-mini { margin-top: 4px; }
.progress-mini-bar { height: 3px; background: rgba(200,146,42,0.1); border-radius: 2px; overflow: hidden; }
.progress-mini-fill { height: 100%; background: linear-gradient(90deg, var(--rojo), var(--dorado)); }
.progress-mini-text { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); margin-top: 3px; }
.status-pill { display: inline-block; padding: 3px 10px; font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 1px; text-transform: uppercase; border-radius: 2px; }
.status-pill.active { background: rgba(76,175,80,0.12); color: #4CAF50; border: 1px solid rgba(76,175,80,0.3); }
.status-pill.upcoming { background: rgba(33,150,243,0.12); color: #42A5F5; border: 1px solid rgba(33,150,243,0.3); }
.status-pill.sold { background: rgba(200,146,42,0.12); color: var(--dorado); border: 1px solid rgba(200,146,42,0.3); }
.status-pill.draft { background: rgba(107,96,85,0.12); color: var(--gris); border: 1px solid rgba(107,96,85,0.3); }
.table-actions { display: flex; gap: 6px; }
.action-btn { background: none; border: 1px solid rgba(200,146,42,0.2); padding: 5px 10px; color: var(--gris); font-family: 'DM Mono', monospace; font-size: 10px; cursor: pointer; transition: all 0.2s; }
.action-btn:hover { border-color: var(--dorado); color: var(--dorado); }
.action-btn.danger:hover { border-color: #F44336; color: #F44336; }
.bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 24px; }
.widget { background: #1A0800; border: 1px solid rgba(200,146,42,0.2); padding: 20px; }
.widget-title { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); margin-bottom: 16px; }
.recent-sale { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px dashed rgba(200,146,42,0.08); }
.sale-avatar { width: 32px; height: 32px; background: rgba(200,146,42,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.sale-info { flex: 1; }
.sale-name { font-size: 13px; color: var(--crema); }
.sale-meta { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 1px; margin-top: 2px; }
.sale-amount { font-family: 'Playfair Display', serif; font-size: 16px; color: var(--dorado-claro); }
.alert-item { display: flex; gap: 10px; padding: 10px; border: 1px solid; margin-bottom: 8px; }
.alert-item.warning { border-color: rgba(255,152,0,0.3); background: rgba(255,152,0,0.05); }
.alert-item.info { border-color: rgba(33,150,243,0.3); background: rgba(33,150,243,0.05); }
.alert-icon { font-size: 16px; }
.alert-text { font-size: 12px; color: var(--crema); line-height: 1.4; }
.alert-meta { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); margin-top: 3px; }
@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 768px) {
  .admin-layout { grid-template-columns: 1fr; }
  .admin-sidebar { display: none; }
    .admin-main { padding: 20px; }
  .bottom-grid { grid-template-columns: 1fr; }
}
</style>
