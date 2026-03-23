<script setup>
import { ref, computed, onMounted } from 'vue';

const token = ref(localStorage.getItem('auth_token') || '');
const user = ref(null);
const loginName = ref('');
const loginPass = ref('');
const loginError = ref('');
const loggingIn = ref(false);
const allowedRoles = ['seller', 'admin'];

const summary = ref({ orders_count: 0, tickets_sold: 0, revenue_total: 0, attendance_rate: 0, fraud_attempts: 0 });
const events = ref([]);
const selectedEventId = ref(null);
const zoneData = ref([]);
const loading = ref(true);

const ax = () => {
    const i = window.axios.create();
    if (token.value) i.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
    return i;
};

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

        window.location.href = '/vendedor';
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
        const [sumRes, evRes] = await Promise.all([ax().get('/api/seller/reports/summary'), ax().get('/api/seller/events')]);
        summary.value = sumRes.data;
        events.value = evRes.data.data || evRes.data;
        if (events.value.length > 0 && !selectedEventId.value) {
            selectedEventId.value = events.value[0].id;
            await loadZones();
        }
    } catch { /* noop */ }
    finally { loading.value = false; }
};

const loadZones = async () => {
    if (!selectedEventId.value) return;
    try { const { data } = await ax().get('/api/seller/reports/sales-by-zone', { params: { event_id: selectedEventId.value } }); zoneData.value = data; }
    catch { zoneData.value = []; }
};

const selectEvent = async (id) => { selectedEventId.value = id; await loadZones(); };

onMounted(async () => {
    if (token.value) {
        try {
            const { data } = await ax().get('/api/auth/me');
            if (!hasAllowedRole(data?.role)) {
                clearInvalidSession();
                loginError.value = 'Esta cuenta no tiene acceso a reportes.';
                return;
            }

            window.location.href = '/vendedor';
        }
        catch { doLogout(); }
    }
});

const currency = v => '$' + Number(v || 0).toLocaleString('es-MX', { minimumFractionDigits: 0 });
const fmtDate = d => { if (!d) return ''; return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }); };
const totalCap = ev => (ev.zones || []).reduce((s, z) => s + (z.capacity || 0), 0);
const totalSold = ev => (ev.zones || []).reduce((s, z) => s + (z.tickets_count ?? z.sold ?? 0), 0);
const totalRevenue = ev => (ev.zones || []).reduce((s, z) => s + (z.tickets_count ?? z.sold ?? 0) * (z.price || 0), 0);
const avgTicketPrice = computed(() => summary.value.tickets_sold > 0 ? Math.round(summary.value.revenue_total / summary.value.tickets_sold) : 0);

const totalZoneTickets = computed(() => zoneData.value.reduce((s, z) => s + Number(z.tickets || 0), 0));
const donutSegments = computed(() => {
    const total = totalZoneTickets.value;
    if (total === 0) return [];
    const colors = ['#8B1A1A', '#C8922A', '#1A4A2E', '#6B6055', '#42A5F5', '#FF9800', '#9C27B0'];
    const circumference = 2 * Math.PI * 35;
    let offset = 0;
    return zoneData.value.map((z, i) => {
        const pct = Number(z.tickets) / total;
        const dash = pct * circumference;
        const seg = { zone: z.zone, tickets: z.tickets, pct: (pct * 100).toFixed(0), color: colors[i % colors.length], dasharray: `${dash} ${circumference - dash}`, dashoffset: -offset };
        offset += dash;
        return seg;
    });
});

const sortedEvents = computed(() => [...events.value].sort((a, b) => totalRevenue(b) - totalRevenue(a)).slice(0, 10));
const showSidebar = computed(() => user.value?.role === 'admin');
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Marca <span>MGR</span></a>
        <div class="topbar-nav">
            <a v-if="token" href="#" class="active" @click.prevent="doLogout">Cerrar Sesion</a>
            <a v-else href="/">Inicio</a>
        </div>
    </nav>
    <div class="page-label">Reportes y Estadísticas</div>

    <!-- Login -->
    <div v-if="!token" style="padding:80px 40px;text-align:center;">
        <div style="font-size:64px;margin-bottom:16px;">🔐</div>
        <div class="section-title" style="margin-bottom:8px;font-family:'Playfair Display',serif;font-size:24px;color:var(--dorado-claro);">Acceso a Reportes</div>
        <div style="font-family:'DM Mono',monospace;font-size:11px;color:var(--gris);margin-bottom:24px;">Inicia sesión con tu cuenta de vendedor</div>
        <div style="max-width:350px;margin:0 auto;display:flex;flex-direction:column;gap:12px;">
            <input v-model="loginName" type="text" placeholder="Usuario o correo" @keyup.enter="doLogin" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
            <input v-model="loginPass" type="password" placeholder="Contraseña" @keyup.enter="doLogin" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
            <div v-if="loginError" style="color:#F44336;font-family:'DM Mono',monospace;font-size:11px;">{{ loginError }}</div>
            <button @click="doLogin" :disabled="loggingIn" style="background:var(--rojo);border:none;padding:14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;letter-spacing:2px;cursor:pointer;">{{ loggingIn ? 'INICIANDO...' : 'INICIAR SESIÓN' }}</button>
        </div>
    </div>

    <!-- Reportes -->
    <div v-else :class="['report-layout', { 'no-sidebar': !showSidebar }]">
        <div v-if="showSidebar" class="admin-sidebar">
            <div class="nav-group-label">Principal</div>
            <a href="/vendedor" class="nav-item">📊 Dashboard</a>
            <div class="nav-group-label">Reportes</div>
            <a href="/reportes" class="nav-item active">📈 Ventas</a>
            <div class="nav-group-label">Sistema</div>
            <a href="#" class="nav-item" @click.prevent="doLogout">🚪 Cerrar sesión</a>
        </div>

        <div class="report-main">
            <div class="report-header">
                <div>
                    <div class="report-title">Reportes de Ventas</div>
                    <div class="report-subtitle">{{ new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).toUpperCase() }} · Todos los eventos</div>
                </div>
            </div>

            <div v-if="loading" style="text-align:center;padding:60px;font-family:'DM Mono',monospace;color:var(--gris);">Cargando reportes...</div>

            <template v-else>
                <!-- KPIs -->
                <div class="kpi-row">
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Total Vendido</div>
                        <div class="kpi-mini-val">{{ currency(summary.revenue_total) }}</div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Tickets</div>
                        <div class="kpi-mini-val">{{ summary.tickets_sold?.toLocaleString() }}</div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Ticket Prom.</div>
                        <div class="kpi-mini-val">{{ currency(avgTicketPrice) }}</div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Asistencia</div>
                        <div class="kpi-mini-val">{{ summary.attendance_rate }}%</div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Órdenes</div>
                        <div class="kpi-mini-val">{{ summary.orders_count?.toLocaleString() }}</div>
                    </div>
                </div>

                <!-- Zone distribution for selected event -->
                <div class="two-col">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Distribución por Zona</div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <select @change="selectEvent(Number($event.target.value))" :value="selectedEventId"
                                style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:8px 12px;color:var(--crema);font-family:'DM Mono',monospace;font-size:11px;outline:none;width:100%;">
                                <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                            </select>
                        </div>
                        <div class="donut-wrap" v-if="donutSegments.length">
                            <div class="donut-svg-wrap">
                                <svg viewBox="0 0 100 100">
                                    <circle v-for="(seg, i) in donutSegments" :key="i" cx="50" cy="50" r="35" fill="none" :stroke="seg.color" stroke-width="18" :stroke-dasharray="seg.dasharray" :stroke-dashoffset="seg.dashoffset" transform="rotate(-90 50 50)" />
                                    <circle cx="50" cy="50" r="25" fill="#1A0800" />
                                </svg>
                                <div class="donut-center">
                                    <div class="donut-center-val">{{ totalZoneTickets.toLocaleString() }}</div>
                                    <div class="donut-center-label">Tickets</div>
                                </div>
                            </div>
                            <div class="donut-legend">
                                <div v-for="seg in donutSegments" :key="seg.zone" class="donut-legend-item">
                                    <div class="donut-legend-left"><div class="donut-legend-dot" :style="{ background: seg.color }"></div><div class="donut-legend-name">{{ seg.zone }}</div></div>
                                    <div class="donut-legend-pct">{{ seg.pct }}%</div>
                                </div>
                            </div>
                        </div>
                        <div v-else style="text-align:center;padding:30px;font-family:'DM Mono',monospace;color:var(--gris);font-size:11px;">Sin datos de zona</div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Métricas Clave</div>
                        </div>
                        <div>
                            <div class="metric-row"><div class="metric-name">Ingresos Totales</div><div class="metric-val">{{ currency(summary.revenue_total) }}</div></div>
                            <div class="metric-row"><div class="metric-name">Ticket Promedio</div><div class="metric-val">{{ currency(avgTicketPrice) }}</div></div>
                            <div class="metric-row"><div class="metric-name">Tasa de Asistencia</div><div class="metric-val up">{{ summary.attendance_rate }}%</div></div>
                            <div class="metric-row"><div class="metric-name">Intentos Inválidos</div><div class="metric-val">{{ summary.fraud_attempts }}</div></div>
                            <div class="metric-row"><div class="metric-name">Órdenes Totales</div><div class="metric-val">{{ summary.orders_count }}</div></div>
                            <div class="metric-row" style="border:none"><div class="metric-name">Eventos Registrados</div><div class="metric-val">{{ events.length }}</div></div>
                        </div>
                    </div>
                </div>

                <!-- Top Events -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Top Eventos por Ingresos</div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Evento</th>
                                <th>Fecha</th>
                                <th>Tickets</th>
                                <th>Ingreso</th>
                                <th>Ticket Prom.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(ev, idx) in sortedEvents" :key="ev.id">
                                <td><span :style="{ fontFamily: 'Playfair Display, serif', color: idx < 1 ? 'var(--dorado-claro)' : 'var(--gris)', fontSize: '18px' }">{{ idx + 1 }}</span></td>
                                <td><b>{{ ev.name }}</b></td>
                                <td>{{ fmtDate(ev.starts_at) }}</td>
                                <td>{{ totalSold(ev).toLocaleString() }}</td>
                                <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">{{ currency(totalRevenue(ev)) }}</td>
                                <td>{{ totalCap(ev) > 0 ? currency(Math.round(totalRevenue(ev) / Math.max(1, totalSold(ev)))) : '$0' }}</td>
                            </tr>
                            <tr v-if="sortedEvents.length === 0">
                                <td colspan="6" style="text-align:center;padding:20px;font-family:'DM Mono',monospace;color:var(--gris);">No hay eventos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Zone details table -->
                <div v-if="zoneData.length" class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Detalle por Zona — {{ events.find(e => e.id === selectedEventId)?.name || '' }}</div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Zona</th>
                                <th>Tickets Vendidos</th>
                                <th>Ingreso</th>
                                <th>Asistieron</th>
                                <th>% Asistencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="z in zoneData" :key="z.zone">
                                <td><b>{{ z.zone }}</b></td>
                                <td>{{ Number(z.tickets).toLocaleString() }}</td>
                                <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">{{ currency(z.amount) }}</td>
                                <td>{{ Number(z.assisted).toLocaleString() }}</td>
                                <td><span :style="{ color: Number(z.attendance_rate) >= 80 ? '#4CAF50' : '#FF9800' }">{{ z.attendance_rate }}%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
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
.report-layout { display: grid; grid-template-columns: 230px 1fr; min-height: calc(100vh - 82px); }
.report-layout.no-sidebar { grid-template-columns: 1fr; }
.admin-sidebar { background: #100400; border-right: 1px solid rgba(200,146,42,0.2); padding-top: 8px; }
.nav-group-label { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 3px; color: rgba(107,96,85,0.5); padding: 12px 20px 6px; text-transform: uppercase; }
.nav-item { padding: 11px 20px; font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 1px; color: var(--gris); cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all 0.2s; text-decoration: none; text-transform: uppercase; border-left: 3px solid transparent; }
.nav-item:hover { color: var(--dorado-claro); background: rgba(200,146,42,0.06); }
.nav-item.active { color: var(--dorado-claro); background: rgba(200,146,42,0.1); border-left-color: var(--dorado); }
.report-main { padding: 32px; overflow-y: auto; }
.report-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; }
.report-title { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--dorado-claro); }
.report-subtitle { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; margin-top: 3px; }
.header-actions { display: flex; gap: 10px; }
.btn-primary { background: var(--rojo); border: none; padding: 11px 22px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; transition: background 0.2s; }
.btn-primary:hover { background: #A02020; }
.btn-outline { background: none; border: 1.5px solid var(--dorado); padding: 10px 20px; color: var(--dorado); font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; transition: all 0.2s; }
.btn-outline:hover { background: var(--dorado); color: var(--cafe); }
.date-tabs { display: flex; gap: 0; margin-bottom: 28px; }
.date-tab { padding: 9px 20px; font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--gris); border: 1px solid rgba(200,146,42,0.2); cursor: pointer; transition: all 0.2s; margin-right: -1px; }
.date-tab:hover { color: var(--dorado); }
.date-tab.active { background: rgba(200,146,42,0.15); color: var(--dorado-claro); border-color: var(--dorado); z-index: 1; }
.kpi-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 24px; }
.kpi-mini { background: #1A0800; border: 1px solid rgba(200,146,42,0.2); padding: 16px; position: relative; overflow: hidden; }
.kpi-mini::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: var(--dorado); }
.kpi-mini-label { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; color: var(--gris); text-transform: uppercase; margin-bottom: 6px; }
.kpi-mini-val { font-family: 'Playfair Display', serif; font-size: 26px; color: var(--crema); }
.kpi-mini-trend { font-family: 'DM Mono', monospace; font-size: 10px; margin-top: 4px; }
.trend-up { color: #4CAF50; }
.trend-down { color: #F44336; }
.chart-card { background: #1A0800; border: 1px solid rgba(200,146,42,0.2); padding: 24px; margin-bottom: 20px; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.chart-title { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); }
.chart-legend { display: flex; gap: 16px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.bar-chart-wrap { position: relative; }
.bar-chart {
  display: flex; align-items: flex-end; gap: 10px;
  height: 160px; padding-bottom: 32px; padding-left: 40px;
  border-left: 1px solid rgba(200,146,42,0.15);
  border-bottom: 1px solid rgba(200,146,42,0.15);
  position: relative;
}
.y-labels { position: absolute; left: 0; top: 0; height: calc(100% - 32px); display: flex; flex-direction: column-reverse; justify-content: space-between; }
.y-label { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); }
.bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.bar-stack { width: 100%; display: flex; flex-direction: column-reverse; justify-content: flex-end; position: relative; }
.bar { width: 100%; border-radius: 1px 1px 0 0; transition: opacity 0.2s; cursor: pointer; }
.bar:hover { opacity: 0.8; }
.bar-label { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); text-align: center; position: absolute; bottom: -24px; left: 0; right: 0; }
.bar-val { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--crema); text-align: center; margin-bottom: 3px; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.donut-wrap { display: flex; align-items: center; gap: 24px; }
.donut-svg-wrap { position: relative; width: 140px; height: 140px; flex-shrink: 0; }
.donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.donut-center-val { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--crema); }
.donut-center-label { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 1px; }
.donut-legend { flex: 1; }
.donut-legend-item { display: flex; align-items: center; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed rgba(200,146,42,0.08); }
.donut-legend-left { display: flex; align-items: center; gap: 8px; }
.donut-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.donut-legend-name { font-size: 12px; color: var(--crema); }
.donut-legend-pct { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--dorado); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); padding: 10px 14px; text-align: left; border-bottom: 1px solid rgba(200,146,42,0.3); background: rgba(0,0,0,0.3); }
.data-table td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid rgba(200,146,42,0.06); }
.data-table tr:hover td { background: rgba(200,146,42,0.03); }
.metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.metric-row { display: flex; justify-content: space-between; align-items: center; padding: 13px 0; border-bottom: 1px solid rgba(200,146,42,0.08); }
.metric-name { font-size: 13px; color: var(--crema); }
.metric-val { font-family: 'Playfair Display', serif; font-size: 17px; color: var(--dorado-claro); }
.metric-val.up { color: #4CAF50; }
.metric-val.down { color: #F44336; }
@media (max-width: 1024px) {
  .kpi-row { grid-template-columns: repeat(3, 1fr); }
  .two-col { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .report-layout { grid-template-columns: 1fr; }
  .admin-sidebar { display: none; }
  .kpi-row { grid-template-columns: 1fr 1fr; }
}
</style>
