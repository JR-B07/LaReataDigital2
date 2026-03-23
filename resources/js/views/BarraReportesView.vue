<script setup>
import { ref, computed, onMounted } from 'vue';

const token = ref(localStorage.getItem('auth_token') || '');
const user = ref(null);
const loginName = ref('');
const loginPass = ref('');
const loginError = ref('');
const loggingIn = ref(false);

const events = ref([]);
const selectedEventId = ref('');
const activeTab = ref('product');
const loading = ref(false);

const salesByProduct = ref([]);
const salesByPayment = ref([]);
const salesByOperator = ref([]);
const revenueByEvent = ref([]);

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
        if (data.user?.role !== 'admin') { loginError.value = 'Solo administradores.'; return; }
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem('auth_token', data.token);
        await loadInitial();
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

const currency = v => '$' + Number(v || 0).toLocaleString('es-MX', { minimumFractionDigits: 0 });
const fmtDate = d => { if (!d) return ''; return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }); };

const loadInitial = async () => {
    try {
        const { data } = await ax().get('/api/admin/events');
        events.value = data.data || data;
    } catch {}
    await loadReport();
};

const params = computed(() => {
    const p = {};
    if (selectedEventId.value) p.event_id = selectedEventId.value;
    return p;
});

const loadReport = async () => {
    loading.value = true;
    try {
        const [prod, pay, op, ev] = await Promise.all([
            ax().get('/api/admin/bar/reports/sales-by-product', { params: params.value }),
            ax().get('/api/admin/bar/reports/sales-by-payment', { params: params.value }),
            ax().get('/api/admin/bar/reports/sales-by-operator', { params: params.value }),
            ax().get('/api/admin/bar/reports/revenue-by-event'),
        ]);
        salesByProduct.value = prod.data;
        salesByPayment.value = pay.data;
        salesByOperator.value = op.data;
        revenueByEvent.value = ev.data;
    } catch {}
    finally { loading.value = false; }
};

const onEventChange = () => loadReport();

// KPIs
const totalBarRevenue = computed(() => salesByPayment.value.reduce((s, r) => s + Number(r.monto_total || 0), 0));
const totalBarSales = computed(() => salesByPayment.value.reduce((s, r) => s + Number(r.num_ventas || 0), 0));
const totalUnits = computed(() => salesByProduct.value.reduce((s, r) => s + Number(r.cantidad_vendida || 0), 0));
const avgTicket = computed(() => totalBarSales.value > 0 ? Math.round(totalBarRevenue.value / totalBarSales.value) : 0);

// Donut for payment methods
const paymentColors = { efectivo: '#1A4A2E', tarjeta: '#42A5F5', transferencia: '#C8922A', otro: '#6B6055' };
const paymentDonut = computed(() => {
    const total = totalBarRevenue.value;
    if (total === 0) return [];
    const circumference = 2 * Math.PI * 35;
    let offset = 0;
    return salesByPayment.value.map(r => {
        const pct = Number(r.monto_total) / total;
        const dash = pct * circumference;
        const seg = { label: r.metodo_pago, monto: r.monto_total, pct: (pct * 100).toFixed(0), color: paymentColors[r.metodo_pago] || '#8B1A1A', dasharray: `${dash} ${circumference - dash}`, dashoffset: -offset };
        offset += dash;
        return seg;
    });
});

onMounted(async () => {
    if (token.value) {
        try {
            const { data } = await ax().get('/api/auth/me');
            if (data?.role !== 'admin') { clearInvalidSession(); loginError.value = 'Solo administradores.'; return; }
            user.value = data;
            await loadInitial();
        } catch { doLogout(); }
    }
});
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Marca <span>MGR</span></a>
        <div class="topbar-nav">
            <a v-if="token" href="#" class="active" @click.prevent="doLogout">Cerrar Sesion</a>
            <a v-else href="/">Inicio</a>
        </div>
    </nav>
    <div class="page-label">Reportes de Barra</div>

    <!-- Login -->
    <div v-if="!token" style="padding:80px 40px;text-align:center;">
        <div style="font-size:64px;margin-bottom:16px;">🔐</div>
        <div class="section-title" style="margin-bottom:8px;font-family:'Playfair Display',serif;font-size:24px;color:var(--dorado-claro);">Acceso a Reportes de Barra</div>
        <div style="font-family:'DM Mono',monospace;font-size:11px;color:var(--gris);margin-bottom:24px;">Solo administradores</div>
        <div style="max-width:350px;margin:0 auto;display:flex;flex-direction:column;gap:12px;">
            <input v-model="loginName" type="text" placeholder="Usuario" @keyup.enter="doLogin" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
            <input v-model="loginPass" type="password" placeholder="Contraseña" @keyup.enter="doLogin" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
            <div v-if="loginError" style="color:#F44336;font-family:'DM Mono',monospace;font-size:11px;">{{ loginError }}</div>
            <button @click="doLogin" :disabled="loggingIn" style="background:var(--rojo);border:none;padding:14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;letter-spacing:2px;cursor:pointer;">{{ loggingIn ? 'INICIANDO...' : 'INICIAR SESIÓN' }}</button>
        </div>
    </div>

    <!-- Reports Panel -->
    <div v-else class="report-layout">
        <div class="admin-sidebar">
            <div class="nav-group-label">Principal</div>
            <a href="/admin" class="nav-item">📊 Dashboard</a>
            <div class="nav-group-label">Administración</div>
            <a href="/usuarios" class="nav-item">👥 Usuarios</a>
            <a href="/barra" class="nav-item">🍻 POS Barra</a>
            <a href="/reportes" class="nav-item">📈 Reportes Tickets</a>
            <a href="/barra-reportes" class="nav-item active">🍺 Reportes Barra</a>
            <div class="nav-group-label">Sistema</div>
            <a href="#" class="nav-item" @click.prevent="doLogout">🚪 Cerrar sesión</a>
        </div>

        <div class="report-main">
            <div class="report-header">
                <div>
                    <div class="report-title">Reportes de Barra</div>
                    <div class="report-subtitle">{{ new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).toUpperCase() }}</div>
                </div>
                <div class="header-actions">
                    <select v-model="selectedEventId" @change="onEventChange" style="background:rgba(0,0,0,0.5);border:1px solid rgba(200,146,42,0.3);padding:10px 14px;color:var(--crema);font-family:'DM Mono',monospace;font-size:11px;outline:none;min-width:200px;">
                        <option value="">Todos los eventos</option>
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="loading" style="text-align:center;padding:60px;font-family:'DM Mono',monospace;color:var(--gris);">Cargando reportes...</div>

            <template v-else>
                <!-- KPIs -->
                <div class="kpi-row">
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Ingreso Barra</div>
                        <div class="kpi-mini-val">{{ currency(totalBarRevenue) }}</div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Ventas</div>
                        <div class="kpi-mini-val">{{ totalBarSales.toLocaleString() }}</div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Unidades</div>
                        <div class="kpi-mini-val">{{ totalUnits.toLocaleString() }}</div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-label">Ticket Promedio</div>
                        <div class="kpi-mini-val">{{ currency(avgTicket) }}</div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tab-bar">
                    <button :class="['tab-btn', activeTab === 'product' && 'active']" @click="activeTab = 'product'">Por Producto</button>
                    <button :class="['tab-btn', activeTab === 'payment' && 'active']" @click="activeTab = 'payment'">Por Método de Pago</button>
                    <button :class="['tab-btn', activeTab === 'operator' && 'active']" @click="activeTab = 'operator'">Por Operador</button>
                    <button :class="['tab-btn', activeTab === 'event' && 'active']" @click="activeTab = 'event'">Por Evento</button>
                </div>

                <!-- By Product -->
                <div v-if="activeTab === 'product'" class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Ventas por Producto</div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Precio Unit.</th>
                                <th>Cant. Vendida</th>
                                <th>Ingreso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, idx) in salesByProduct" :key="idx">
                                <td style="font-family:'Playfair Display',serif;color:var(--gris);">{{ idx + 1 }}</td>
                                <td><b>{{ r.producto }}</b></td>
                                <td><span class="cat-badge">{{ r.categoria }}</span></td>
                                <td>{{ currency(r.precio_unitario) }}</td>
                                <td>{{ Number(r.cantidad_vendida).toLocaleString() }}</td>
                                <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">{{ currency(r.ingreso_total) }}</td>
                            </tr>
                            <tr v-if="salesByProduct.length === 0">
                                <td colspan="6" style="text-align:center;padding:20px;font-family:'DM Mono',monospace;color:var(--gris);">Sin datos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- By Payment Method -->
                <div v-if="activeTab === 'payment'" class="two-col">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Distribución por Método de Pago</div>
                        </div>
                        <div class="donut-wrap" v-if="paymentDonut.length">
                            <div class="donut-svg-wrap">
                                <svg viewBox="0 0 100 100">
                                    <circle v-for="(seg, i) in paymentDonut" :key="i" cx="50" cy="50" r="35" fill="none" :stroke="seg.color" stroke-width="18" :stroke-dasharray="seg.dasharray" :stroke-dashoffset="seg.dashoffset" transform="rotate(-90 50 50)" />
                                    <circle cx="50" cy="50" r="25" fill="#1A0800" />
                                </svg>
                                <div class="donut-center">
                                    <div class="donut-center-val">{{ currency(totalBarRevenue) }}</div>
                                    <div class="donut-center-label">Total</div>
                                </div>
                            </div>
                            <div class="donut-legend">
                                <div v-for="seg in paymentDonut" :key="seg.label" class="donut-legend-item">
                                    <div class="donut-legend-left"><div class="donut-legend-dot" :style="{ background: seg.color }"></div><div class="donut-legend-name">{{ seg.label }}</div></div>
                                    <div class="donut-legend-pct">{{ seg.pct }}%</div>
                                </div>
                            </div>
                        </div>
                        <div v-else style="text-align:center;padding:30px;font-family:'DM Mono',monospace;color:var(--gris);font-size:11px;">Sin datos</div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Detalle</div>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Método</th>
                                    <th>Ventas</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in salesByPayment" :key="r.metodo_pago">
                                    <td><b>{{ r.metodo_pago }}</b></td>
                                    <td>{{ Number(r.num_ventas).toLocaleString() }}</td>
                                    <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">{{ currency(r.monto_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- By Operator -->
                <div v-if="activeTab === 'operator'" class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Ventas por Operador</div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Operador</th>
                                <th>Ventas</th>
                                <th>Monto Total</th>
                                <th>Ticket Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, idx) in salesByOperator" :key="idx">
                                <td style="font-family:'Playfair Display',serif;color:var(--gris);">{{ idx + 1 }}</td>
                                <td><b>{{ r.operador }}</b></td>
                                <td>{{ Number(r.num_ventas).toLocaleString() }}</td>
                                <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">{{ currency(r.monto_total) }}</td>
                                <td>{{ currency(r.ticket_promedio) }}</td>
                            </tr>
                            <tr v-if="salesByOperator.length === 0">
                                <td colspan="5" style="text-align:center;padding:20px;font-family:'DM Mono',monospace;color:var(--gris);">Sin datos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- By Event -->
                <div v-if="activeTab === 'event'" class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Ingresos de Barra por Evento</div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Evento</th>
                                <th>Fecha</th>
                                <th>Ventas</th>
                                <th>Ingreso Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, idx) in revenueByEvent" :key="idx">
                                <td style="font-family:'Playfair Display',serif;color:var(--gris);">{{ idx + 1 }}</td>
                                <td><b>{{ r.evento }}</b></td>
                                <td>{{ fmtDate(r.fecha_inicio) }}</td>
                                <td>{{ Number(r.num_ventas).toLocaleString() }}</td>
                                <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">{{ currency(r.ingreso_total) }}</td>
                            </tr>
                            <tr v-if="revenueByEvent.length === 0">
                                <td colspan="5" style="text-align:center;padding:20px;font-family:'DM Mono',monospace;color:var(--gris);">Sin datos.</td>
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

.kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
.kpi-mini { background: #1A0800; border: 1px solid rgba(200,146,42,0.2); padding: 16px; position: relative; overflow: hidden; }
.kpi-mini::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: var(--dorado); }
.kpi-mini-label { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; color: var(--gris); text-transform: uppercase; margin-bottom: 6px; }
.kpi-mini-val { font-family: 'Playfair Display', serif; font-size: 26px; color: var(--crema); }

.tab-bar { display: flex; gap: 0; margin-bottom: 20px; }
.tab-btn { padding: 10px 20px; font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--gris); border: 1px solid rgba(200,146,42,0.2); background: none; cursor: pointer; transition: all 0.2s; margin-right: -1px; }
.tab-btn:hover { color: var(--dorado); }
.tab-btn.active { background: rgba(200,146,42,0.15); color: var(--dorado-claro); border-color: var(--dorado); z-index: 1; }

.chart-card { background: #1A0800; border: 1px solid rgba(200,146,42,0.2); padding: 24px; margin-bottom: 20px; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.chart-title { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); }

.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.donut-wrap { display: flex; align-items: center; gap: 24px; }
.donut-svg-wrap { position: relative; width: 140px; height: 140px; flex-shrink: 0; }
.donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.donut-center-val { font-family: 'Playfair Display', serif; font-size: 18px; color: var(--crema); }
.donut-center-label { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 1px; }
.donut-legend { flex: 1; }
.donut-legend-item { display: flex; align-items: center; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed rgba(200,146,42,0.08); }
.donut-legend-left { display: flex; align-items: center; gap: 8px; }
.donut-legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.donut-legend-name { font-size: 12px; color: var(--crema); text-transform: capitalize; }
.donut-legend-pct { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--dorado); }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); padding: 10px 14px; text-align: left; border-bottom: 1px solid rgba(200,146,42,0.3); background: rgba(0,0,0,0.3); }
.data-table td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid rgba(200,146,42,0.06); }
.data-table tr:hover td { background: rgba(200,146,42,0.03); }

.cat-badge { display: inline-block; padding: 2px 8px; font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 1px; background: rgba(200,146,42,0.15); color: var(--dorado); border-radius: 3px; text-transform: uppercase; }

@media (max-width: 1024px) {
  .kpi-row { grid-template-columns: repeat(2, 1fr); }
  .two-col { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .report-layout { grid-template-columns: 1fr; }
  .admin-sidebar { display: none; }
  .tab-bar { flex-wrap: wrap; }
}
</style>
