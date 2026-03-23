<script setup>
import { computed, onMounted, ref } from 'vue';

const token = ref(localStorage.getItem('auth_token') || '');
const user = ref(null);
const loginName = ref('');
const loginPass = ref('');
const loginError = ref('');
const loggingIn = ref(false);
const loading = ref(false);
const processing = ref(false);
const allowedRoles = ['seller', 'admin'];

const events = ref([]);
const selectedEventId = ref(null);
const selectedZoneId = ref(null);
const quantity = ref(1);
const buyerName = ref('');
const buyerPhone = ref('');
const paymentMethod = ref('cash');
const paymentReference = ref('');
const successMessage = ref('');
const errorMessage = ref('');
const recentSales = ref([]);
const cancelModal = ref(null);
const cancelReason = ref('');
const cancelProcessing = ref(false);
const cancelMessage = ref('');
const lastSale = ref(null);

const ax = () => {
    const instance = window.axios.create();
    if (token.value) {
        instance.defaults.headers.common.Authorization = `Bearer ${token.value}`;
    }
    return instance;
};

const hasAllowedRole = (role) => allowedRoles.includes(role);
const isAdmin = computed(() => user.value?.role === 'admin');
const apiPrefix = computed(() => (isAdmin.value ? '/api/admin' : '/api/seller'));

const roleLabel = computed(() => {
    return { seller: 'Vendedor', admin: 'Administrador' }[user.value?.role] || 'Operador';
});

const selectedEvent = computed(() => events.value.find(e => e.id === selectedEventId.value));
const selectedZone = computed(() => selectedEvent.value?.zonas?.find(z => z.id === selectedZoneId.value));

const formatCurrency = (v) => {
    const n = parseFloat(v);
    return isNaN(n) ? '$0.00' : '$' + n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDateTime = (str) => {
    if (!str) return '';
    const d = new Date(str);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
};

const paymentLabel = (m) => ({ efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia' }[m] || m);

const orderTotal = computed(() => {
    if (!selectedZone.value) return 0;
    return (parseFloat(selectedZone.value.precio) || 0) * (quantity.value || 0);
});

// ── Cargar datos ─────────────────────────────────────────

const loadEvents = async () => {
    try {
        const { data } = await ax().get(`${apiPrefix.value}/taquilla/events`);
        events.value = data;
    } catch {
        events.value = [];
    }
};

const loadRecentSales = async () => {
    try {
        const { data } = await ax().get(`${apiPrefix.value}/taquilla/sales/recent`);
        recentSales.value = data;
    } catch {
        recentSales.value = [];
    }
};

const loadDashboard = async () => {
    loading.value = true;
    try {
        await Promise.all([loadEvents(), loadRecentSales()]);
    } finally {
        loading.value = false;
    }
};

// ── Selección ────────────────────────────────────────────

const selectEvent = (eventId) => {
    selectedEventId.value = eventId;
    selectedZoneId.value = null;
    quantity.value = 1;
    lastSale.value = null;
};

const selectZone = (zoneId) => {
    selectedZoneId.value = zoneId;
    quantity.value = 1;
    lastSale.value = null;
};

// ── Vender ───────────────────────────────────────────────

const submitSale = async () => {
    if (!selectedEventId.value || !selectedZoneId.value || !buyerName.value.trim()) return;
    processing.value = true;
    successMessage.value = '';
    errorMessage.value = '';
    lastSale.value = null;

    try {
        const { data } = await ax().post(`${apiPrefix.value}/taquilla/sell`, {
            event_id: selectedEventId.value,
            zone_id: selectedZoneId.value,
            quantity: quantity.value,
            buyer_name: buyerName.value.trim(),
            buyer_phone: buyerPhone.value.trim() || null,
            payment_method: paymentMethod.value,
            payment_reference: paymentReference.value.trim() || null,
        });

        successMessage.value = `Venta #${data.sale.order_id} registrada — ${data.sale.tickets.length} boleto(s)`;
        lastSale.value = data.sale;
        buyerName.value = '';
        buyerPhone.value = '';
        paymentReference.value = '';
        quantity.value = 1;
        await Promise.all([loadEvents(), loadRecentSales()]);
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Error al procesar la venta.';
    } finally {
        processing.value = false;
    }
};

// ── Cancelar venta ───────────────────────────────────────

const openCancelModal = (sale) => {
    cancelModal.value = sale;
    cancelReason.value = '';
    cancelMessage.value = '';
};

const closeCancelModal = () => {
    cancelModal.value = null;
    cancelReason.value = '';
    cancelMessage.value = '';
};

const submitCancel = async () => {
    if (!cancelModal.value || !cancelReason.value.trim()) return;
    cancelProcessing.value = true;
    cancelMessage.value = '';
    try {
        await ax().post(`${apiPrefix.value}/taquilla/sales/${cancelModal.value.id}/cancel`, {
            motivo: cancelReason.value.trim(),
        });
        closeCancelModal();
        successMessage.value = 'Venta cancelada y boletos liberados.';
        await Promise.all([loadEvents(), loadRecentSales()]);
    } catch (error) {
        cancelMessage.value = error.response?.data?.message || 'Error al cancelar.';
    } finally {
        cancelProcessing.value = false;
    }
};

// ── Auth ─────────────────────────────────────────────────

const doLogin = async () => {
    loggingIn.value = true;
    loginError.value = '';
    try {
        const { data } = await window.axios.post('/api/auth/login', {
            login: loginName.value,
            password: loginPass.value,
            intended_roles: allowedRoles,
        });
        token.value = data.token;
        user.value = data.user;
        localStorage.setItem('auth_token', data.token);
        if (data.user?.role === 'validator') {
            window.location.href = '/validador';
            return;
        }
        await loadDashboard();
    } catch (error) {
        loginError.value = error.response?.data?.message || 'Credenciales inválidas.';
    } finally {
        loggingIn.value = false;
    }
};

const doLogout = async () => {
    try {
        if (token.value) await ax().post('/api/auth/logout');
    } catch {}
    token.value = '';
    user.value = null;
    localStorage.removeItem('auth_token');
    events.value = [];
    recentSales.value = [];
};

onMounted(async () => {
    if (!token.value) return;
    try {
        const { data } = await ax().get('/api/auth/me');
        if (!hasAllowedRole(data?.role)) {
            token.value = '';
            user.value = null;
            localStorage.removeItem('auth_token');
            loginError.value = 'Esta cuenta no tiene acceso a taquilla.';
            return;
        }
        user.value = data;
        await loadDashboard();
    } catch {
        await doLogout();
    }
});
</script>

<template>
    <div v-if="!user" class="taquilla-shell">
        <aside class="taquilla-aside">
            <a href="/" class="taquilla-brand">Marca <span>MGR</span></a>
            <div class="taquilla-kicker">Acceso operativo</div>
            <h1 class="taquilla-title">Punto de venta de taquilla</h1>
            <p class="taquilla-copy">Venta presencial de boletos, separada del portal público de compra en línea.</p>
            <div class="taquilla-links">
                <a href="/">Portal público</a>
                <a href="/barra">POS Barra</a>
                <a href="/validador">Validador</a>
            </div>
        </aside>
        <main class="taquilla-main">
            <section class="access-panel">
                <div class="panel-badge">Solo personal autorizado</div>
                <h2>Ingreso de taquilla</h2>
                <p>Usa tu usuario operativo para acceder al punto de venta.</p>

                <label class="field-label">Usuario o correo</label>
                <input v-model="loginName" class="field-input" type="text" autocomplete="username" @keyup.enter="doLogin">

                <label class="field-label">Contraseña</label>
                <input v-model="loginPass" class="field-input" type="password" autocomplete="current-password" @keyup.enter="doLogin">

                <div v-if="loginError" class="field-error">{{ loginError }}</div>

                <button class="primary-action" :disabled="loggingIn" @click="doLogin">
                    {{ loggingIn ? 'Validando...' : 'Entrar a taquilla' }}
                </button>
            </section>
        </main>
    </div>

    <!-- POS TAQUILLA -->
    <div v-else class="pos-shell">
        <header class="pos-header">
            <div class="pos-brand">Marca <span>MGR</span> · Taquilla</div>
            <div class="pos-user">{{ user.name }} · {{ roleLabel }}</div>
            <div class="pos-actions">
                <a href="/barra" class="pos-link">POS Barra</a>
                <a href="/admin" class="pos-link">Panel Admin</a>
                <button class="pos-logout" @click="doLogout">Cerrar sesión</button>
            </div>
        </header>

        <div v-if="loading" class="pos-loading">Cargando datos...</div>

        <main v-else class="pos-main">
            <!-- Columna izquierda: Selección y formulario -->
            <section class="pos-left">
                <!-- Selector de evento -->
                <div class="pos-card">
                    <h3>Seleccionar Evento</h3>
                    <div class="event-grid">
                        <button v-for="ev in events" :key="ev.id"
                            class="event-btn"
                            :class="{ active: selectedEventId === ev.id }"
                            @click="selectEvent(ev.id)">
                            <div class="event-name">{{ ev.nombre }}</div>
                            <div class="event-date">{{ formatDateTime(ev.fecha_inicio) }}</div>
                        </button>
                    </div>
                    <div v-if="events.length === 0" class="state-msg">No hay eventos activos.</div>
                </div>

                <!-- Zonas del evento -->
                <div v-if="selectedEvent" class="pos-card">
                    <h3>Zonas — {{ selectedEvent.nombre }}</h3>
                    <div class="zone-grid">
                        <button v-for="z in selectedEvent.zonas" :key="z.id"
                            class="zone-btn"
                            :class="{ active: selectedZoneId === z.id, disabled: z.disponibles === 0 }"
                            :disabled="z.disponibles === 0"
                            @click="selectZone(z.id)">
                            <div class="zone-name">{{ z.nombre }}</div>
                            <div class="zone-price">{{ formatCurrency(z.precio) }}</div>
                            <div class="zone-avail" :class="{ low: z.disponibles <= 5 && z.disponibles > 0, out: z.disponibles === 0 }">
                                {{ z.disponibles === 0 ? 'Agotado' : z.disponibles + ' disp.' }}
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Formulario de venta -->
                <div v-if="selectedZone" class="pos-card">
                    <h3>Nueva Venta</h3>
                    <div class="form-grid">
                        <div>
                            <label class="field-label">Cantidad</label>
                            <input v-model.number="quantity" class="field-input" type="number" min="1" :max="Math.min(20, selectedZone.disponibles)">
                        </div>
                        <div>
                            <label class="field-label">Método de pago</label>
                            <select v-model="paymentMethod" class="field-input">
                                <option value="cash">Efectivo</option>
                                <option value="card">Tarjeta</option>
                                <option value="transfer">Transferencia</option>
                            </select>
                        </div>
                        <div class="full-width">
                            <label class="field-label">Nombre del comprador</label>
                            <input v-model="buyerName" class="field-input" placeholder="Nombre completo">
                        </div>
                        <div>
                            <label class="field-label">Teléfono (opcional)</label>
                            <input v-model="buyerPhone" class="field-input" placeholder="Teléfono">
                        </div>
                        <div>
                            <label class="field-label">Referencia pago (opcional)</label>
                            <input v-model="paymentReference" class="field-input" placeholder="Folio / ref">
                        </div>
                    </div>

                    <div class="sale-summary">
                        <div>{{ selectedZone.nombre }} × {{ quantity }}</div>
                        <strong>{{ formatCurrency(orderTotal) }}</strong>
                    </div>

                    <div v-if="errorMessage" class="field-error">{{ errorMessage }}</div>

                    <button class="sell-btn" :disabled="processing || !buyerName.trim() || quantity < 1" @click="submitSale">
                        {{ processing ? 'Procesando...' : `Vender ${quantity} boleto(s) — ${formatCurrency(orderTotal)}` }}
                    </button>
                </div>

                <!-- Resultado de última venta -->
                <div v-if="lastSale" class="pos-card success-card">
                    <h3>Venta Exitosa</h3>
                    <div class="sale-result">
                        <div>Orden #{{ lastSale.order_id }} — {{ lastSale.tickets.length }} boleto(s) — {{ formatCurrency(lastSale.total) }}</div>
                        <div class="ticket-codes">
                            <span v-for="(t, i) in lastSale.tickets" :key="i" class="ticket-code">{{ t.ticket_code }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Columna derecha: Ventas recientes -->
            <section class="pos-right">
                <div class="pos-card">
                    <h3>Ventas Recientes</h3>
                    <div v-if="successMessage" class="field-ok">{{ successMessage }}</div>
                    <div class="sales-list">
                        <div v-for="s in recentSales" :key="s.id" class="sale-row"
                            :class="{ cancelled: s.estado_pago === 'cancelado' }">
                            <div style="flex:1;">
                                <div class="sale-title">
                                    Venta #{{ s.id }} · {{ s.nombre_cliente }}
                                    <span v-if="s.estado_pago === 'cancelado'" class="badge-cancelled">CANCELADA</span>
                                </div>
                                <div class="sale-meta">
                                    {{ s.vendedor || 'Operador' }} · {{ paymentLabel(s.metodo_pago) }} · {{ s.num_boletos }} boleto(s) · {{ formatDateTime(s.created_at) }}
                                </div>
                            </div>
                            <strong :style="s.estado_pago === 'cancelado' ? 'text-decoration:line-through;opacity:.5;' : ''">{{ formatCurrency(s.total) }}</strong>
                            <button v-if="s.estado_pago !== 'cancelado'" class="btn-cancel" @click="openCancelModal(s)">Cancelar</button>
                        </div>
                        <div v-if="recentSales.length === 0" class="state-msg">Sin ventas de taquilla aún.</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal Cancelación -->
    <div v-if="cancelModal" class="modal-overlay" @click.self="closeCancelModal">
        <div class="modal-box">
            <h3>Cancelar Venta #{{ cancelModal.id }}</h3>
            <p class="modal-sub">{{ cancelModal.nombre_cliente }} · {{ formatCurrency(cancelModal.total) }} · {{ cancelModal.num_boletos }} boleto(s)</p>
            <label class="field-label">Motivo de cancelación</label>
            <textarea v-model="cancelReason" class="field-input" rows="3" placeholder="Escribe el motivo..." style="resize:vertical;"></textarea>
            <div v-if="cancelMessage" class="field-error">{{ cancelMessage }}</div>
            <div class="modal-actions">
                <button class="sell-btn danger" :disabled="cancelProcessing || !cancelReason.trim()" @click="submitCancel">
                    {{ cancelProcessing ? 'Procesando...' : 'Confirmar cancelación' }}
                </button>
                <button class="btn-secondary" @click="closeCancelModal">Cerrar</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Baskerville:wght@400;700&family=DM+Mono:wght@400;500&display=swap');

:global(body) {
    margin: 0;
    min-height: 100vh;
    background:
        radial-gradient(circle at 20% 20%, rgba(217,164,65,0.06), transparent 45%),
        radial-gradient(circle at 80% 60%, rgba(139,26,26,0.10), transparent 45%),
        #140700;
    color: #f5efe0;
    font-family: 'Libre Baskerville', serif;
}

/* ── Login ────────────────────────────────────────────── */

.taquilla-shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(320px, 460px) 1fr;
}

.taquilla-aside {
    padding: 56px 40px;
    background: rgba(139,26,26,0.35);
    backdrop-filter: blur(18px);
    border-right: 1px solid rgba(255,255,255,0.06);
    display: flex;
    flex-direction: column;
    gap: 22px;
}

.taquilla-brand {
    color: #fdfaf4;
    text-decoration: none;
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 900;
}

.taquilla-brand span,
.taquilla-kicker,
.panel-badge,
.field-label,
.primary-action {
    font-family: 'DM Mono', monospace;
}

.taquilla-brand span,
.taquilla-kicker,
.panel-badge {
    color: #f0c060;
}

.taquilla-kicker {
    font-size: 11px;
    letter-spacing: 3px;
    text-transform: uppercase;
}

.taquilla-title {
    margin: 0;
    font-family: 'Playfair Display', serif;
    font-size: clamp(34px, 5vw, 56px);
    line-height: 1.02;
}

.taquilla-copy {
    margin: 0;
    max-width: 32rem;
    color: rgba(245, 239, 224, 0.72);
    line-height: 1.7;
}

.taquilla-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: auto;
}

.taquilla-links a {
    font-family: 'DM Mono', monospace;
    color: #f5efe0;
    text-decoration: none;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    border-bottom: 1px solid rgba(240, 192, 96, 0.28);
}

.taquilla-main {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 32px;
}

.access-panel {
    width: min(460px, 100%);
    padding: 34px;
    background: rgba(253, 250, 244, 0.96);
    color: #2f1908;
    box-shadow: 0 28px 72px rgba(0, 0, 0, 0.28);
    border: 1px solid rgba(240, 192, 96, 0.35);
}

.access-panel h2 {
    margin: 12px 0 10px;
    font-family: 'Playfair Display', serif;
    font-size: 34px;
}

.access-panel p {
    margin: 0 0 24px;
    color: rgba(47, 25, 8, 0.72);
    line-height: 1.7;
}

.panel-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border: 1px solid rgba(139, 26, 26, 0.2);
    background: rgba(240, 192, 96, 0.16);
    color: #7f5011;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.field-label {
    display: block;
    margin-bottom: 6px;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(245, 239, 224, 0.6);
}

.access-panel .field-label {
    color: #6b6055;
}

.field-input {
    width: 100%;
    margin-bottom: 14px;
    padding: 10px 12px;
    border: 1px solid rgba(240, 192, 96, 0.2);
    background: rgba(255, 255, 255, 0.06);
    color: #f5efe0;
    font-size: 14px;
    font-family: 'Libre Baskerville', serif;
    outline: none;
    border-radius: 4px;
    box-sizing: border-box;
}

.access-panel .field-input {
    background: #fffdf8;
    color: #2f1908;
    border-color: rgba(61, 32, 8, 0.18);
}

.field-input:focus {
    border-color: #f0c060;
}

.field-error {
    margin-bottom: 12px;
    color: #e53935;
    font-size: 13px;
}

.field-ok {
    margin-bottom: 12px;
    color: #4CAF50;
    font-size: 13px;
    font-family: 'DM Mono', monospace;
}

.primary-action {
    width: 100%;
    border: none;
    padding: 14px 18px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-size: 11px;
    cursor: pointer;
    background: linear-gradient(135deg, #8b1a1a, #ba4b2a);
    color: #fdfaf4;
}

.primary-action:disabled {
    opacity: 0.7;
    cursor: wait;
}

/* ── POS ──────────────────────────────────────────────── */

.pos-shell {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.pos-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 24px;
    background: rgba(139,26,26,0.55);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.pos-brand {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    font-weight: 900;
}

.pos-brand span {
    color: #f0c060;
}

.pos-user {
    font-family: 'DM Mono', monospace;
    font-size: 12px;
    color: rgba(245, 239, 224, 0.6);
    margin-left: auto;
}

.pos-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.pos-link {
    font-family: 'DM Mono', monospace;
    color: rgb(245, 239, 224);
    text-decoration: none;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 8px 16px;
    border-radius: 10px;
    transition: all .3s ease;
}

.pos-link:hover {
    color: white;
    background: rgba(0, 0, 0, 0.74);
    backdrop-filter: blur(6px);
    box-shadow: 0 0 0 1px rgba(246,197,111,0.25) inset, 0 4px 14px rgba(0,0,0,0.4);
}

.pos-logout {
    font-family: 'DM Mono', monospace;
    background: transparent;
    border: 1px solid rgba(240, 192, 96, 0.3);
    color: #f5efe0;
    padding: 8px 16px;
    font-size: 11px;
    cursor: pointer;
    letter-spacing: 1px;
    text-transform: uppercase;
    border-radius: 10px;
    transition: all .3s ease;
}

.pos-logout:hover {
    color: white;
    background: rgba(0, 0, 0, 0.74);
    backdrop-filter: blur(6px);
    box-shadow: 0 0 0 1px rgba(246,197,111,0.25) inset, 0 4px 14px rgba(0,0,0,0.4);
}

.pos-loading {
    padding: 48px;
    text-align: center;
    color: rgba(245, 239, 224, 0.5);
    font-family: 'DM Mono', monospace;
}

.pos-main {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 0;
}

.pos-left {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    overflow-y: auto;
    max-height: calc(100vh - 56px);
}

.pos-right {
    padding: 20px;
    border-left: 1px solid rgba(240, 192, 96, 0.12);
    overflow-y: auto;
    max-height: calc(100vh - 56px);
}

.pos-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(240, 192, 96, 0.12);
    border-radius: 8px;
    padding: 18px;
}

.pos-card h3 {
    margin: 0 0 14px;
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    color: #f0c060;
}

.event-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
}

.event-btn {
    text-align: left;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(240, 192, 96, 0.15);
    border-radius: 6px;
    padding: 14px;
    cursor: pointer;
    color: #f5efe0;
    transition: border-color 0.2s;
}

.event-btn.active {
    border-color: #f0c060;
    background: rgba(240, 192, 96, 0.1);
}

.event-name {
    font-family: 'Playfair Display', serif;
    font-size: 15px;
    font-weight: 700;
}

.event-date {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    color: rgba(245, 239, 224, 0.5);
    margin-top: 4px;
}

.zone-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
}

.zone-btn {
    text-align: center;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(240, 192, 96, 0.15);
    border-radius: 6px;
    padding: 14px 10px;
    cursor: pointer;
    color: #f5efe0;
    transition: border-color 0.2s;
}

.zone-btn.active {
    border-color: #f0c060;
    background: rgba(240, 192, 96, 0.1);
}

.zone-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.zone-name {
    font-weight: 700;
    font-size: 14px;
}

.zone-price {
    font-family: 'DM Mono', monospace;
    font-size: 16px;
    color: #f0c060;
    margin: 6px 0 4px;
}

.zone-avail {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    color: #4CAF50;
}

.zone-avail.low {
    color: #FF9800;
}

.zone-avail.out {
    color: #e53935;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 16px;
}

.full-width {
    grid-column: 1 / -1;
}

.sale-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    margin: 12px 0;
    background: rgba(240, 192, 96, 0.08);
    border: 1px solid rgba(240, 192, 96, 0.2);
    border-radius: 6px;
    font-family: 'DM Mono', monospace;
    font-size: 14px;
}

.sell-btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 6px;
    background: linear-gradient(135deg, #2e7d32, #4CAF50);
    color: #fff;
    font-family: 'DM Mono', monospace;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
}

.sell-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.sell-btn.danger {
    background: linear-gradient(135deg, #c62828, #e53935);
}

.success-card {
    border-color: #4CAF50;
    background: rgba(76, 175, 80, 0.08);
}

.sale-result {
    font-family: 'DM Mono', monospace;
    font-size: 13px;
}

.ticket-codes {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.ticket-code {
    padding: 4px 10px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(240, 192, 96, 0.2);
    border-radius: 4px;
    font-size: 11px;
    letter-spacing: 1px;
}

/* ── Ventas recientes ─────────────────────────────────── */

.sales-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sale-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(240, 192, 96, 0.08);
    border-radius: 6px;
}

.sale-row.cancelled {
    opacity: 0.6;
}

.sale-title {
    font-size: 13px;
    font-weight: 700;
}

.sale-meta {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    color: rgba(245, 239, 224, 0.5);
    margin-top: 2px;
}

.badge-cancelled {
    color: #e53935;
    font-size: 10px;
    font-family: 'DM Mono', monospace;
    margin-left: 6px;
}

.btn-cancel {
    padding: 4px 10px;
    border: 1px solid rgba(229, 57, 53, 0.4);
    background: transparent;
    color: #e53935;
    font-family: 'DM Mono', monospace;
    font-size: 10px;
    cursor: pointer;
    border-radius: 4px;
    letter-spacing: 1px;
    text-transform: uppercase;
    white-space: nowrap;
}

.btn-cancel:hover {
    background: rgba(229, 57, 53, 0.1);
}

.state-msg {
    padding: 16px;
    text-align: center;
    color: rgba(245, 239, 224, 0.4);
    font-family: 'DM Mono', monospace;
    font-size: 12px;
}

/* ── Modal ────────────────────────────────────────────── */

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-box {
    background: #1e1e1e;
    border: 1px solid rgba(240, 192, 96, 0.2);
    border-radius: 10px;
    padding: 24px;
    min-width: 360px;
    max-width: 480px;
}

.modal-box h3 {
    margin: 0 0 6px;
    font-family: 'Playfair Display', serif;
    color: #f0c060;
}

.modal-sub {
    margin: 0 0 16px;
    font-family: 'DM Mono', monospace;
    font-size: 12px;
    color: rgba(245, 239, 224, 0.5);
}

.modal-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.btn-secondary {
    padding: 10px 18px;
    border: 1px solid rgba(240, 192, 96, 0.2);
    border-radius: 6px;
    background: transparent;
    color: #f5efe0;
    font-family: 'DM Mono', monospace;
    font-size: 12px;
    cursor: pointer;
}

@media (max-width: 920px) {
    .taquilla-shell {
        grid-template-columns: 1fr;
    }

    .pos-main {
        grid-template-columns: 1fr;
    }

    .pos-right {
        border-left: none;
        border-top: 1px solid rgba(240, 192, 96, 0.12);
    }

    .pos-left,
    .pos-right {
        max-height: none;
    }
}

@media (max-width: 640px) {
    .taquilla-aside,
    .taquilla-main {
        padding: 28px 20px;
    }

    .access-panel {
        padding: 24px 20px;
    }

    .pos-header {
        flex-wrap: wrap;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
