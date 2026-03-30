<script setup>
import { ref, computed, onMounted } from 'vue';

const event = ref(null);
const zones = ref([]);
const loading = ref(true);
const qtys = ref([]);
const selectedZoneIndex = ref(-1);
const step = ref(2);
const buyerName = ref('');
const buyerEmail = ref('');
const buyerPhone = ref('');
const paymentMethod = ref('card');
const promoCode = ref('');
const promoMsg = ref('');
const purchasing = ref(false);
const orderResult = ref(null);
const purchaseSnapshot = ref(null);
const errorMsg = ref('');
const pendingCardCheckoutKey = 'pending_card_checkout';

const eventId = new URLSearchParams(window.location.search).get('event');

const consumeMercadoPagoReturn = async () => {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status') || params.get('collection_status');
    const paymentId = params.get('payment_id') || params.get('collection_id');

    if (!status) return;

    const raw = localStorage.getItem(pendingCardCheckoutKey);
    if (!raw) return;

    let payload = null;
    try {
        payload = JSON.parse(raw);
    } catch {
        localStorage.removeItem(pendingCardCheckoutKey);
        return;
    }

    if (String(payload?.event_id) !== String(eventId)) {
        return;
    }

    if (status !== 'approved') {
        errorMsg.value = 'El pago con tarjeta no fue aprobado. Intenta nuevamente.';
        localStorage.removeItem(pendingCardCheckoutKey);
        return;
    }

    purchasing.value = true;
    errorMsg.value = '';

    try {
        purchaseSnapshot.value = {
            zoneName: zones.value.find((z) => z.id === payload.event_zone_id)?.name,
            unitPrice: Number(zones.value.find((z) => z.id === payload.event_zone_id)?.price || 0),
            holder: payload.buyer_name,
            paidAt: new Date().toISOString(),
        };

        const { data } = await window.axios.post('/api/checkout', {
            ...payload,
            payment_method: 'card',
            payment_reference: paymentId || null,
        });

        orderResult.value = data;
        step.value = 5;
        localStorage.removeItem(pendingCardCheckoutKey);
        window.history.replaceState({}, document.title, `/compra?event=${eventId}`);
    } catch (e) {
        let msg = e.response?.data?.message || 'No se pudo finalizar la compra tras el pago con tarjeta.';
        if (e.response?.data?.details) {
            msg += '\nDetalles: ' + JSON.stringify(e.response.data.details);
        }
        errorMsg.value = msg;
    } finally {
        purchasing.value = false;
    }
};

onMounted(async () => {
    if (!eventId) { loading.value = false; return; }
    try {
        const { data } = await window.axios.get(`/api/events/${eventId}`);
        event.value = data;
        zones.value = data.zones || [];
        qtys.value = zones.value.map(() => 0);

        await consumeMercadoPagoReturn();
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
});

const subtotal = computed(() => qtys.value.reduce((sum, qty, i) => sum + qty * parseFloat(zones.value[i]?.price || 0), 0));
const fee = computed(() => Math.round(subtotal.value * 0.1));
const total = computed(() => subtotal.value + fee.value);
const hasItems = computed(() => qtys.value.some(q => q > 0));
const currency = (v) => `$${Number(v).toLocaleString('es-MX')}`;
const selectedZone = computed(() => selectedZoneIndex.value >= 0 ? zones.value[selectedZoneIndex.value] : null);
const selectedQty = computed(() => selectedZoneIndex.value >= 0 ? qtys.value[selectedZoneIndex.value] : 0);
const emailDelivery = computed(() => orderResult.value?.email_delivery || null);

const selectZone = (index) => {
    selectedZoneIndex.value = index;
    qtys.value = qtys.value.map((value, i) => (i === index ? value : 0));
};

const maxAvailable = (zone) => Math.max(0, Number(zone.capacity || 0) - Number(zone.sold_count || 0));

const changeQty = (i, delta) => {
    selectZone(i);
    const max = Math.min(20, maxAvailable(zones.value[i] || {}));
    qtys.value[i] = Math.max(0, Math.min(max, qtys.value[i] + delta));
};

const formatDate = (d) => {
    if (!d) return '';
    const dt = new Date(d);
    return dt.toLocaleDateString('es-MX', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).toUpperCase();
};

const formatTicketStamp = (d) => {
    if (!d) return '';
    const dt = new Date(d);
    return dt.toLocaleString('es-MX', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const renderedTickets = computed(() => {
    const tickets = orderResult.value?.tickets || [];
    if (!tickets.length) return [];

    const snap = purchaseSnapshot.value || {};

    return tickets.map((ticket, idx) => ({
        code: ticket.ticket_code,
        folio: String(idx + 1).padStart(3, '0'),
        zone: snap.zoneName || selectedZone.value?.name || 'General',
        holder: snap.holder || buyerName.value || 'Publico General',
        paidAt: snap.paidAt || new Date().toISOString(),
        unitPrice: snap.unitPrice ?? selectedZone.value?.price ?? 0,
    }));
});

const printTickets = () => {
    window.print();
};

const barcodeBars = (code) => {
    const input = String(code || 'LRD');
    const bars = [];

    for (let i = 0; i < input.length; i++) {
        const n = input.charCodeAt(i);
        bars.push(1 + (n % 3));
        bars.push(1);
        bars.push(1 + ((n >> 2) % 3));
        bars.push(1);
    }

    return bars;
};

const barcodeRects = (code) => {
    const bars = barcodeBars(code);
    const rects = [];
    let x = 0;

    bars.forEach((w, i) => {
        // Paint only odd segments as black bars.
        if (i % 2 === 0) {
            rects.push({ x, w });
        }
        x += w;
    });

    return {
        rects,
        width: Math.max(1, x),
    };
};

const goToStep3 = () => { if (hasItems.value) step.value = 3; };
const goToStep4 = () => { if (buyerName.value && buyerEmail.value) step.value = 4; };

const purchase = async () => {
    purchasing.value = true;
    errorMsg.value = '';
    const selectedIdx = selectedZoneIndex.value >= 0 ? selectedZoneIndex.value : qtys.value.findIndex(q => q > 0);
    if (selectedIdx === -1) return;

    const checkoutPayload = {
        event_id: event.value.id,
        event_zone_id: zones.value[selectedIdx].id,
        quantity: qtys.value[selectedIdx],
        buyer_name: buyerName.value,
        buyer_email: buyerEmail.value,
        buyer_phone: buyerPhone.value || null,
        payment_method: paymentMethod.value,
        discount_code: promoCode.value || null,
    };

    try {
        purchaseSnapshot.value = {
            zoneName: zones.value[selectedIdx]?.name,
            unitPrice: Number(zones.value[selectedIdx]?.price || 0),
            holder: buyerName.value,
            paidAt: new Date().toISOString(),
        };

        if (paymentMethod.value === 'card') {
            const successUrl = `${window.location.origin}/compra?event=${event.value.id}`;
            localStorage.setItem(pendingCardCheckoutKey, JSON.stringify(checkoutPayload));

            const { data } = await window.axios.post('/api/checkout/mercadopago/preference', {
                ...checkoutPayload,
                success_url: successUrl,
                failure_url: successUrl,
                pending_url: successUrl,
            });

            if (!data?.redirect_url) {
                throw new Error('Mercado Pago no devolvió URL de pago.');
            }

            window.location.href = data.redirect_url;
            return;
        }

        const { data } = await window.axios.post('/api/checkout', checkoutPayload);
        orderResult.value = data;
        step.value = 5;
    } catch (e) {
        let msg = e.response?.data?.message || 'Error al procesar la compra.';
        if (e.response?.data?.details) {
            msg += '\nDetalles: ' + JSON.stringify(e.response.data.details);
        }
        errorMsg.value = msg;
    } finally {
        purchasing.value = false;
    }
};
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Marca <span>MGR</span></a>
        <div class="topbar-nav">
            <a href="/">Inicio</a>
            <a href="/vendedor">Iniciar sesion</a>
        </div>
    </nav>
    <div class="page-label">Compra de Boletos</div>

    <div v-if="loading" style="text-align:center;padding:60px;font-family:'DM Mono',monospace;color:var(--gris);">Cargando evento...</div>
    <div v-else-if="!event" style="text-align:center;padding:60px;font-family:'DM Mono',monospace;color:var(--gris);">
        No se seleccionó un evento. <a href="/" style="color:var(--dorado);">Volver al inicio</a>
    </div>

    <template v-else>
        <div class="event-banner">
            <div class="event-banner-icon">🐎</div>
            <div>
                <div class="event-banner-title">{{ event.name }}</div>
                <div class="event-banner-meta">📅 {{ formatDate(event.starts_at) }} &nbsp;&nbsp; 📍 {{ event.venue }}, {{ event.city }}</div>
            </div>
            <a href="/" class="event-banner-back">← Cambiar evento</a>
        </div>

        <div class="stepper">
            <div :class="['step', step > 1 ? 'done' : 'current']"><span class="step-num">{{ step > 1 ? '✓' : '1' }}</span>Evento</div>
            <div :class="['step', step > 2 ? 'done' : step === 2 ? 'current' : 'pending']"><span class="step-num">{{ step > 2 ? '✓' : '2' }}</span>Boletos</div>
            <div :class="['step', step > 3 ? 'done' : step === 3 ? 'current' : 'pending']"><span class="step-num">{{ step > 3 ? '✓' : '3' }}</span>Tus datos</div>
            <div :class="['step', step > 4 ? 'done' : step === 4 ? 'current' : 'pending']"><span class="step-num">{{ step > 4 ? '✓' : '4' }}</span>Pago</div>
            <div :class="['step', step === 5 ? 'done' : 'pending']"><span class="step-num">{{ step === 5 ? '✓' : '5' }}</span>Confirmación</div>
        </div>

        <!-- STEP 5: Confirmación -->
        <div v-if="step === 5" class="ticket-confirmation" style="padding:60px 40px;text-align:center;">
            <div class="screen-only" style="font-size:72px;margin-bottom:16px;">✅</div>
            <div class="section-title screen-only" style="margin-bottom:8px;">¡Compra Exitosa!</div>
            <div class="screen-only" style="font-family:'DM Mono',monospace;font-size:12px;color:var(--gris);margin-bottom:24px;">
                Tu orden ha sido procesada.
                <template v-if="emailDelivery?.sent">
                    Los boletos fueron enviados a <b style="color:var(--dorado-claro);">{{ buyerEmail }}</b>.
                </template>
                <template v-else-if="emailDelivery?.message">
                    {{ emailDelivery.message }}
                </template>
                <template v-else>
                    Puedes descargar tus PDFs desde esta misma confirmación.
                </template>
            </div>
            <div v-if="orderResult" class="screen-only" style="background:#1A0800;border:1px solid rgba(200,146,42,0.3);padding:20px;max-width:500px;margin:0 auto 20px;text-align:left;">
                <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--dorado);letter-spacing:2px;margin-bottom:12px;">DETALLE DE ORDEN</div>
                <div style="font-size:13px;margin-bottom:6px;">Orden: <b style="color:var(--dorado-claro);">#{{ orderResult.order?.id }}</b></div>
                <div style="font-size:13px;margin-bottom:6px;">Total: <b style="color:var(--dorado-claro);">{{ currency(orderResult.order?.total || 0) }}</b></div>
                <div style="font-size:13px;margin-bottom:12px;">Tickets: <b style="color:var(--dorado-claro);">{{ orderResult.tickets?.length || 0 }}</b></div>
                <div v-for="t in (orderResult.tickets || [])" :key="t.ticket_code" style="padding:8px 0;border-top:1px dashed rgba(200,146,42,0.15);font-family:'DM Mono',monospace;font-size:11px;color:var(--crema);">
                    {{ t.ticket_code }}
                    <a :href="`/api/tickets/${t.ticket_code}/pdf`" target="_blank" style="color:var(--dorado);margin-left:12px;">⬇ PDF</a>
                </div>
            </div>

            <div v-if="renderedTickets.length" class="ticket-list-wrap">
                <div v-for="ticket in renderedTickets" :key="ticket.code" class="purchase-ticket-card">
                    <div class="purchase-ticket-brand">Marca MGR</div>
                    <div class="purchase-ticket-event">{{ event.name }}</div>
                    <div class="purchase-ticket-meta">📅 {{ formatDate(event.starts_at) }}</div>
                    <div class="purchase-ticket-meta">📍 {{ event.venue }}, {{ event.city }}</div>

                    <div class="purchase-ticket-grid">
                        <div class="label">BOLETO</div><div class="val">#{{ ticket.folio }}</div>
                        <div class="label">ZONA</div><div class="val">{{ ticket.zone }}</div>
                        <div class="label">PRECIO</div><div class="val">{{ currency(ticket.unitPrice) }} MXN</div>
                        <div class="label">TITULAR</div><div class="val">{{ ticket.holder }}</div>
                        <div class="label">COMPRA</div><div class="val">{{ formatTicketStamp(ticket.paidAt) }}</div>
                    </div>

                    <div class="purchase-ticket-barcode" aria-hidden="true">
                        <svg
                            class="barcode-svg"
                            :viewBox="`0 0 ${barcodeRects(ticket.code).width} 56`"
                            preserveAspectRatio="none"
                        >
                            <rect x="0" y="0" :width="barcodeRects(ticket.code).width" height="56" fill="#fff" />
                            <rect
                                v-for="(rect, i) in barcodeRects(ticket.code).rects"
                                :key="`${ticket.code}-r-${i}`"
                                :x="rect.x"
                                y="0"
                                :width="rect.w"
                                height="56"
                                fill="#000"
                            />
                        </svg>
                    </div>
                    <div class="purchase-ticket-code">{{ ticket.code }}</div>
                </div>
            </div>

            <div class="ticket-actions screen-only">
                <button class="btn-outline" @click="printTickets">Imprimir Tickets</button>
                <a class="btn-home" href="/">← Volver al Inicio</a>
            </div>
        </div>

        <!-- STEP 2-4: Main layout -->
        <div v-else class="buy-layout">
            <div class="buy-main">
                <!-- STEP 2: Boletos -->
                <template v-if="step === 2">
                    <span class="mono-label">Mapa del Lienzo Charro</span>
                    <div class="zone-map">
                        <div class="zone-map-title">Zonas disponibles para este evento</div>
                        <div class="lienzo-diagram">
                            <div v-for="(zone, i) in zones" :key="zone.id" class="lienzo-row">
                                <button class="lienzo-zone vip" style="width:340px;"
                                    :class="{ selected: selectedZoneIndex === i }"
                                    @click="selectZone(i)">
                                    {{ zone.name }} · {{ maxAvailable(zone) }} disponibles
                                </button>
                            </div>
                            <div class="lienzo-row"><div class="lienzo-arena">🐎 ARENA — LIENZO CHARRO 🐎</div></div>
                        </div>
                    </div>

                    <div class="ticket-selector">
                        <div class="ticket-selector-title">Apartado de Seleccion de Boletos</div>
                        <div v-if="zones.length === 0" class="ticket-selector-empty">
                            No hay zonas configuradas para este evento.
                        </div>
                        <div v-else-if="selectedZoneIndex < 0" class="ticket-selector-empty">
                            Elige una zona para habilitar la seleccion de boletos.
                        </div>
                        <div v-else class="ticket-selector-content">
                            <div class="ticket-chip">Zona: <b>{{ selectedZone?.name }}</b></div>
                            <div class="ticket-chip">Disponibles: <b>{{ maxAvailable(selectedZone || {}) }}</b></div>
                            <div class="ticket-chip">Precio: <b>{{ currency(selectedZone?.price || 0) }}</b></div>

                            <div class="ticket-counter">
                                <button class="qty-btn" @click="changeQty(selectedZoneIndex, -1)">−</button>
                                <div class="ticket-counter-value">{{ selectedQty }}</div>
                                <button class="qty-btn" @click="changeQty(selectedZoneIndex, 1)">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="section-title" style="margin-bottom: 16px;">Selecciona tus Boletos</div>
                    <div class="seat-categories">
                        <div v-for="(zone, i) in zones" :key="zone.id" :class="['seat-cat', qtys[i] > 0 ? 'selected' : '', selectedZoneIndex === i ? 'active-zone' : '']" @click="selectZone(i)">
                            <div class="seat-cat-info">
                                <div class="seat-dot" :style="{ background: ['var(--dorado)', '#FF6B6B', '#6BFFAA', 'var(--gris)'][i % 4] }"></div>
                                <div>
                                    <div class="seat-name">{{ zone.name }}</div>
                                    <div class="seat-desc">Capacidad: {{ zone.capacity }}</div>
                                    <div class="seat-avail">✓ {{ maxAvailable(zone) }} lugares disponibles</div>
                                </div>
                            </div>
                            <div class="seat-right">
                                <div class="seat-price">{{ currency(zone.price) }}</div>
                                <div class="qty-control">
                                    <button class="qty-btn" @click="changeQty(i, -1)">−</button>
                                    <div class="qty-num">{{ qtys[i] }}</div>
                                    <button class="qty-btn" @click="changeQty(i, 1)">+</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="promo-section">
                        <span class="mono-label">¿Tienes código de descuento?</span>
                        <div class="promo-input">
                            <input v-model="promoCode" type="text" placeholder="INGRESA TU CÓDIGO">
                            <button>Aplicar</button>
                        </div>
                    </div>
                </template>

                <!-- STEP 3: Datos del comprador -->
                <template v-if="step === 3">
                    <div class="section-title" style="margin-bottom:20px;">Tus Datos</div>
                    <div style="max-width:500px;display:flex;flex-direction:column;gap:16px;">
                        <div>
                            <span class="mono-label">Nombre completo</span>
                            <input v-model="buyerName" type="text" placeholder="Tu nombre" style="width:100%;background:rgba(0,0,0,0.4);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                        </div>
                        <div>
                            <span class="mono-label">Correo electrónico</span>
                            <input v-model="buyerEmail" type="email" placeholder="tu@correo.com" style="width:100%;background:rgba(0,0,0,0.4);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                        </div>
                        <div>
                            <span class="mono-label">Teléfono (opcional)</span>
                            <input v-model="buyerPhone" type="tel" placeholder="55 1234 5678" style="width:100%;background:rgba(0,0,0,0.4);border:1px solid rgba(200,146,42,0.3);padding:12px 16px;color:var(--crema);font-family:'DM Mono',monospace;font-size:12px;outline:none;">
                        </div>
                        <div style="display:flex;gap:10px;margin-top:8px;">
                            <button @click="step = 2" class="btn-outline" style="padding:12px 20px;">← Regresar</button>
                            <button @click="goToStep4" class="btn-continuar" style="flex:1;" :disabled="!buyerName || !buyerEmail">Continuar → Pago</button>
                        </div>
                    </div>
                </template>

                <!-- STEP 4: Pago -->
                <template v-if="step === 4">
                    <div class="section-title" style="margin-bottom:20px;">Método de Pago</div>
                    <div style="max-width:500px;display:flex;flex-direction:column;gap:12px;">
                        <label v-for="m in [{v:'card', l:'💳 Tarjeta de crédito/débito'}, {v:'oxxo', l:'💵 Efectivo'}, {v:'transfer', l:'🏦 Transferencia bancaria'}]" :key="m.v"
                            :style="{display:'flex',alignItems:'center',gap:'12px',padding:'14px 16px',border:'1px solid ' + (paymentMethod === m.v ? 'var(--dorado-claro)' : 'rgba(200,146,42,0.2)'),background: paymentMethod === m.v ? 'rgba(240,192,96,0.08)' : 'rgba(0,0,0,0.2)',cursor:'pointer'}">
                            <input type="radio" :value="m.v" v-model="paymentMethod" style="accent-color:var(--dorado);">
                            <span style="font-family:'DM Mono',monospace;font-size:12px;color:var(--crema);">{{ m.l }}</span>
                        </label>
                        <div v-if="errorMsg" style="padding:10px;border:1px solid #F44336;background:rgba(244,67,54,0.1);color:#F44336;font-family:'DM Mono',monospace;font-size:11px;">{{ errorMsg }}</div>
                        <div style="display:flex;gap:10px;margin-top:12px;">
                            <button @click="step = 3" class="btn-outline" style="padding:12px 20px;">← Regresar</button>
                            <button @click="purchase" class="btn-continuar" style="flex:1;" :disabled="purchasing">{{ purchasing ? 'Procesando...' : 'Confirmar Compra →' }}</button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="buy-sidebar">
                <div class="order-title">Resumen de Orden</div>
                <div class="order-event">
                    <div class="order-event-name">{{ event.name }}</div>
                    <div class="order-event-meta">📅 {{ formatDate(event.starts_at) }}<br>📍 {{ event.venue }}, {{ event.city }}</div>
                </div>
                <div class="order-divider"></div>
                <div class="order-items">
                    <template v-for="(zone, i) in zones" :key="zone.id">
                        <div v-if="qtys[i] > 0" class="order-item">
                            <div>
                                <div class="order-item-name">{{ zone.name }}</div>
                                <div class="order-item-qty">{{ qtys[i] }} boletos × {{ currency(zone.price) }}</div>
                            </div>
                            <div class="order-item-price">{{ currency(qtys[i] * parseFloat(zone.price)) }}</div>
                        </div>
                    </template>
                </div>
                <div class="order-divider"></div>
                <div class="order-subtotals">
                    <div class="order-line"><span>Subtotal</span><span>{{ currency(subtotal) }}</span></div>
                    <div class="order-line"><span>Cargo por servicio (10%)</span><span>{{ currency(fee) }}</span></div>
                </div>
                <div class="order-total">
                    <div class="order-total-label">Total</div>
                    <div class="order-total-price">{{ currency(total) }}</div>
                </div>
                <button v-if="step === 2" @click="goToStep3" class="btn-continuar" :disabled="!hasItems">Continuar → Mis Datos</button>
                <div class="secure-badge">🔒 PAGO 100% SEGURO · SSL CIFRADO<br>Los tickets se envían a tu correo electrónico</div>
                <div class="payment-icons">
                    <div class="pay-icon">💳 Visa</div>
                    <div class="pay-icon">💳 MC</div>
                    <div class="pay-icon">🏪 OXXO</div>
                    <div class="pay-icon">📱 MercadoPago</div>
                </div>
            </div>
        </div>
    </template>
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
.event-banner { background: linear-gradient(135deg, #3D0808 0%, #1A0800 100%); padding: 20px 40px; display: flex; align-items: center; gap: 20px; border-bottom: 2px solid var(--dorado); }
.event-banner-icon { font-size: 40px; }
.event-banner-title { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--crema); }
.event-banner-meta { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; margin-top: 4px; }
.event-banner-back { margin-left: auto; font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; color: var(--dorado); text-decoration: none; text-transform: uppercase; }
.event-banner-back:hover { color: var(--dorado-claro); }
.stepper { display: flex; background: #1A0800; border-bottom: 1px solid rgba(200,146,42,0.2); }
.step { flex: 1; padding: 14px 8px; text-align: center; font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; color: var(--gris); border-bottom: 3px solid transparent; position: relative; }
.step::after { content: '→'; position: absolute; right: 0; top: 50%; transform: translateY(-50%); color: rgba(200,146,42,0.2); font-size: 14px; }
.step:last-child::after { display: none; }
.step.done { color: var(--dorado); border-bottom-color: rgba(200,146,42,0.4); }
.step.current { color: var(--dorado-claro); border-bottom-color: var(--dorado-claro); font-weight: 500; }
.step-num { display: inline-block; width: 20px; height: 20px; border-radius: 50%; font-size: 9px; line-height: 20px; text-align: center; margin-right: 6px; }
.step.done .step-num { background: var(--dorado); color: var(--cafe); }
.step.current .step-num { background: var(--dorado-claro); color: var(--cafe); }
.step.pending .step-num { border: 1px solid var(--gris); color: var(--gris); }
.buy-layout { display: grid; grid-template-columns: 1fr 380px; min-height: calc(100vh - 190px); }
.buy-main { padding: 40px; }
.buy-sidebar { background: #1A0800; border-left: 1px solid rgba(200,146,42,0.3); padding: 32px; }
.section-title { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--dorado-claro); margin-bottom: 20px; }
.mono-label { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); margin-bottom: 12px; display: block; }
.zone-map { background: #100400; border: 1px solid rgba(200,146,42,0.2); padding: 24px; margin-bottom: 28px; text-align: center; }
.zone-map-title { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; color: var(--gris); text-transform: uppercase; margin-bottom: 16px; }
.lienzo-diagram { display: flex; flex-direction: column; align-items: center; gap: 6px; max-width: 400px; margin: 0 auto; }
.lienzo-row { display: flex; gap: 6px; align-items: center; }
.lienzo-zone { padding: 10px 16px; text-align: center; font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; transition: all 0.2s; border: 1px solid; }
.lienzo-zone.vip { background: rgba(200,146,42,0.15); border-color: var(--dorado); color: var(--dorado); }
.lienzo-zone.premium { background: rgba(139,26,26,0.15); border-color: var(--rojo); color: #FF6B6B; }
.lienzo-zone.general { background: rgba(26,74,46,0.15); border-color: var(--verde); color: #6BFFAA; }
.lienzo-zone:hover { transform: scale(1.03); }
.lienzo-zone.selected { border-color: var(--dorado-claro); box-shadow: 0 0 0 1px var(--dorado-claro) inset; }
.lienzo-arena { padding: 16px 40px; background: rgba(200,146,42,0.05); border: 1px dashed rgba(200,146,42,0.2); font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 2px; text-transform: uppercase; }
.ticket-selector { border: 1px solid rgba(200,146,42,0.25); background: rgba(0,0,0,0.25); padding: 14px; margin-bottom: 22px; }
.ticket-selector-title { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); margin-bottom: 10px; }
.ticket-selector-empty { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--gris); }
.ticket-selector-content { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ticket-chip { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--crema); border: 1px dashed rgba(200,146,42,0.3); padding: 6px 10px; }
.ticket-counter { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.ticket-counter-value { min-width: 36px; text-align: center; font-family: 'Playfair Display', serif; font-size: 24px; color: var(--dorado-claro); }
.seat-categories { display: flex; flex-direction: column; gap: 12px; }
.seat-cat { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; border: 1px solid rgba(200,146,42,0.2); background: rgba(0,0,0,0.2); transition: all 0.2s; cursor: pointer; }
.seat-cat:hover { border-color: var(--dorado); background: rgba(200,146,42,0.05); }
.seat-cat.selected { border-color: var(--dorado-claro); background: rgba(240,192,96,0.08); }
.seat-cat.active-zone { box-shadow: 0 0 0 1px rgba(240,192,96,0.3) inset; }
.seat-cat-info { display: flex; align-items: center; gap: 14px; }
.seat-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.seat-name { font-family: 'Playfair Display', serif; font-size: 17px; color: var(--crema); }
.seat-desc { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); margin-top: 3px; }
.seat-avail { font-family: 'DM Mono', monospace; font-size: 9px; color: #4CAF50; margin-top: 2px; }
.seat-right { display: flex; align-items: center; gap: 20px; }
.seat-price { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--dorado-claro); }
.qty-control { display: flex; align-items: center; gap: 10px; }
.qty-btn { width: 30px; height: 30px; border: 1px solid var(--dorado); background: none; color: var(--dorado); font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.qty-btn:hover { background: var(--dorado); color: var(--cafe); }
.qty-num { font-family: 'DM Mono', monospace; font-size: 16px; min-width: 24px; text-align: center; color: var(--crema); }
.promo-section { margin-top: 28px; }
.promo-input { display: flex; gap: 0; }
.promo-input input { flex: 1; background: rgba(0,0,0,0.4); border: 1px solid rgba(200,146,42,0.3); border-right: none; padding: 11px 16px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 12px; outline: none; letter-spacing: 2px; }
.promo-input input:focus { border-color: var(--dorado); }
.promo-input button { background: rgba(200,146,42,0.2); border: 1px solid rgba(200,146,42,0.3); padding: 11px 20px; color: var(--dorado); font-family: 'DM Mono', monospace; font-size: 10px; cursor: pointer; letter-spacing: 2px; text-transform: uppercase; transition: all 0.2s; }
.promo-input button:hover { background: var(--dorado); color: var(--cafe); }
.order-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--dorado-claro); margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid rgba(200,146,42,0.2); }
.order-event { margin-bottom: 20px; }
.order-event-name { font-family: 'Playfair Display', serif; font-size: 15px; color: var(--crema); margin-bottom: 4px; }
.order-event-meta { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; line-height: 1.6; }
.order-divider { height: 1px; background: rgba(200,146,42,0.15); margin: 16px 0; }
.order-items { margin-bottom: 16px; }
.order-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed rgba(200,146,42,0.1); }
.order-item-name { font-size: 13px; color: var(--crema); }
.order-item-qty { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); }
.order-item-price { font-family: 'DM Mono', monospace; font-size: 13px; color: var(--dorado); }
.order-subtotals { margin-bottom: 12px; }
.order-line { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; }
.order-line span:first-child { color: var(--gris); }
.order-line span:last-child { font-family: 'DM Mono', monospace; color: var(--crema); }
.order-total { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-top: 1.5px solid var(--dorado); }
.order-total-label { font-family: 'Playfair Display', serif; font-size: 18px; color: var(--crema); }
.order-total-price { font-family: 'Playfair Display', serif; font-size: 26px; color: var(--dorado-claro); }
.btn-continuar { width: 100%; background: var(--rojo); border: none; padding: 16px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 12px; letter-spacing: 3px; text-transform: uppercase; cursor: pointer; margin-top: 20px; transition: background 0.2s; }
.btn-continuar:hover { background: #A02020; }
.secure-badge { margin-top: 14px; text-align: center; font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 1px; line-height: 1.8; }
.payment-icons { display: flex; justify-content: center; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.pay-icon { padding: 4px 10px; border: 1px solid rgba(200,146,42,0.2); font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); }
.ticket-list-wrap { display: flex; justify-content: center; flex-wrap: wrap; gap: 20px; margin: 10px 0 0; }
.purchase-ticket-card { width: 340px; border: 2px solid var(--dorado); border-radius: 10px; padding: 16px; text-align: left; background: linear-gradient(180deg, #2e0f00 0%, #1a0800 100%); box-shadow: inset 0 0 0 1px rgba(240,192,96,0.15); }
.purchase-ticket-brand { font-family: 'Playfair Display', serif; font-size: 36px; line-height: 1; color: var(--dorado-claro); margin-bottom: 10px; }
.purchase-ticket-event { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--crema); margin-bottom: 6px; }
.purchase-ticket-meta { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; margin-bottom: 4px; }
.purchase-ticket-grid { margin: 14px 0; border-top: 1px dashed rgba(200,146,42,0.35); border-bottom: 1px dashed rgba(200,146,42,0.35); padding: 10px 0; display: grid; grid-template-columns: 1fr auto; row-gap: 8px; column-gap: 14px; }
.purchase-ticket-grid .label { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; }
.purchase-ticket-grid .val { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--crema); text-align: right; }
.purchase-ticket-barcode { height: 56px; border-radius: 4px; border: 6px solid #fff; margin-top: 10px; background: #fff; overflow: hidden; }
.barcode-svg { width: 100%; height: 100%; display: block; shape-rendering: crispEdges; }
.purchase-ticket-code { text-align: center; margin-top: 8px; font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; color: var(--dorado-claro); }
.ticket-actions { margin-top: 14px; display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap; }
.btn-home { display: inline-block; padding: 12px 24px; background: var(--rojo); color: var(--crema); font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; text-decoration: none; text-transform: uppercase; }
@media (max-width: 768px) {
  .buy-layout { grid-template-columns: 1fr; }
  .buy-sidebar { border-left: none; border-top: 1px solid rgba(200,146,42,0.3); }
  .event-banner { flex-wrap: wrap; }
    .purchase-ticket-card { width: 100%; max-width: 380px; }
    .ticket-actions { flex-direction: column; }
}

@media print {
    @page { margin: 8mm; }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    html,
    body {
        background: #fff !important;
        min-height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .topbar,
    .page-label,
    .event-banner,
    .stepper,
    .buy-layout,
    .screen-only,
    .ticket-actions {
        display: none !important;
    }

    .ticket-confirmation {
        padding: 0 !important;
        margin: 0 !important;
        text-align: left !important;
    }

    .ticket-list-wrap {
        position: static !important;
        width: 100% !important;
        display: flex !important;
        gap: 12px;
        justify-content: flex-start;
        align-items: flex-start;
        flex-wrap: wrap;
        background: #fff !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .purchase-ticket-card {
        width: 86mm;
        min-height: 145mm;
        border: 1px solid #000;
        border-radius: 0;
        background: #fff;
        box-shadow: none;
        color: #000;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .purchase-ticket-brand,
    .purchase-ticket-event,
    .purchase-ticket-grid .val,
    .purchase-ticket-code {
        color: #000;
    }

    .purchase-ticket-meta,
    .purchase-ticket-grid .label {
        color: #333;
    }

    .purchase-ticket-barcode {
        border-color: #000;
    }
}
</style>
