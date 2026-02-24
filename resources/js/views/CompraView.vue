<script setup>
import { ref, computed } from 'vue';

const prices = [850, 500, 350, 150];
const qtys = ref([2, 0, 0, 0]);

const subtotal = computed(() => qtys.value.reduce((sum, qty, index) => sum + qty * prices[index], 0));
const fee = computed(() => Math.round(subtotal.value * 0.1));
const total = computed(() => subtotal.value + fee.value);

const changeQty = (index, delta) => {
    const next = qtys.value[index] + delta;
    qtys.value[index] = Math.max(0, next);
};

const currency = (value) => `$${value.toLocaleString('es-MX')}`;
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Chárro<span>Tickets</span></a>
        <div class="topbar-nav">
            <a href="/">Inicio</a>
            <a href="/compra" class="active">Comprar</a>
            <a href="/validador">Validador</a>
            <a href="/admin">Admin</a>
            <a href="/reportes">Reportes</a>
        </div>
    </nav>
    <div class="page-label">Vista 2 / 5 — Compra de Boletos</div>

    <div class="event-banner">
        <div class="event-banner-icon">🐎</div>
        <div>
            <div class="event-banner-title">Gran Campeonato Nacional de Charreada 2025</div>
            <div class="event-banner-meta">📅 SAB 15 MAR 2025 · 10:00 AM &nbsp;&nbsp; 📍 Lienzo Charro Guadalajara, Jalisco</div>
        </div>
        <a href="/" class="event-banner-back">← Cambiar evento</a>
    </div>

    <div class="stepper">
        <div class="step done"><span class="step-num">✓</span>Evento</div>
        <div class="step current"><span class="step-num">2</span>Boletos</div>
        <div class="step pending"><span class="step-num">3</span>Tus datos</div>
        <div class="step pending"><span class="step-num">4</span>Pago</div>
        <div class="step pending"><span class="step-num">5</span>Confirmación</div>
    </div>

    <div class="buy-layout">
        <div class="buy-main">
            <span class="mono-label">Mapa del Lienzo Charro</span>
            <div class="zone-map">
                <div class="zone-map-title">Selecciona tu zona — haz clic para filtrar</div>
                <div class="lienzo-diagram">
                    <div class="lienzo-row"><div class="lienzo-zone vip" style="width:340px;">Palco VIP · Fila A–C</div></div>
                    <div class="lienzo-row"><div class="lienzo-zone premium" style="width:320px;">Gradería Premium · Fila D–H</div></div>
                    <div class="lienzo-row"><div class="lienzo-arena">🐎 ARENA — LIENZO CHARRO 🐎</div></div>
                    <div class="lienzo-row"><div class="lienzo-zone general" style="width:320px;">General · Zona Norte y Sur</div></div>
                </div>
            </div>

            <div class="section-title" style="margin-bottom: 16px;">Selecciona tus Boletos</div>
            <div class="seat-categories">
                <div class="seat-cat selected">
                    <div class="seat-cat-info">
                        <div class="seat-dot" style="background: var(--dorado);"></div>
                        <div>
                            <div class="seat-name">Palco VIP</div>
                            <div class="seat-desc">Asiento numerado · Vista preferencial · Incluye frigorífico</div>
                            <div class="seat-avail">✓ 353 lugares disponibles</div>
                        </div>
                    </div>
                    <div class="seat-right">
                        <div class="seat-price">$850</div>
                        <div class="qty-control">
                            <button class="qty-btn" @click="changeQty(0, -1)">−</button>
                            <div class="qty-num">{{ qtys[0] }}</div>
                            <button class="qty-btn" @click="changeQty(0, 1)">+</button>
                        </div>
                    </div>
                </div>

                <div class="seat-cat">
                    <div class="seat-cat-info">
                        <div class="seat-dot" style="background: #FF6B6B;"></div>
                        <div>
                            <div class="seat-name">Gradería Premium</div>
                            <div class="seat-desc">Asiento numerado · Buena vista lateral</div>
                            <div class="seat-avail">✓ 412 lugares disponibles</div>
                        </div>
                    </div>
                    <div class="seat-right">
                        <div class="seat-price">$500</div>
                        <div class="qty-control">
                            <button class="qty-btn" @click="changeQty(1, -1)">−</button>
                            <div class="qty-num">{{ qtys[1] }}</div>
                            <button class="qty-btn" @click="changeQty(1, 1)">+</button>
                        </div>
                    </div>
                </div>

                <div class="seat-cat">
                    <div class="seat-cat-info">
                        <div class="seat-dot" style="background: #6BFFAA;"></div>
                        <div>
                            <div class="seat-name">General</div>
                            <div class="seat-desc">Acceso libre · Zonas norte y sur</div>
                            <div class="seat-avail">✓ 200 lugares disponibles</div>
                        </div>
                    </div>
                    <div class="seat-right">
                        <div class="seat-price">$350</div>
                        <div class="qty-control">
                            <button class="qty-btn" @click="changeQty(2, -1)">−</button>
                            <div class="qty-num">{{ qtys[2] }}</div>
                            <button class="qty-btn" @click="changeQty(2, 1)">+</button>
                        </div>
                    </div>
                </div>

                <div class="seat-cat">
                    <div class="seat-cat-info">
                        <div class="seat-dot" style="background: var(--gris);"></div>
                        <div>
                            <div class="seat-name">Niños (3–12 años)</div>
                            <div class="seat-desc">Precio especial · Requiere acompañante adulto</div>
                            <div class="seat-avail">✓ Sin límite disponible</div>
                        </div>
                    </div>
                    <div class="seat-right">
                        <div class="seat-price">$150</div>
                        <div class="qty-control">
                            <button class="qty-btn" @click="changeQty(3, -1)">−</button>
                            <div class="qty-num">{{ qtys[3] }}</div>
                            <button class="qty-btn" @click="changeQty(3, 1)">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="promo-section">
                <span class="mono-label">¿Tienes código de descuento?</span>
                <div class="promo-input">
                    <input type="text" placeholder="INGRESA TU CÓDIGO">
                    <button>Aplicar</button>
                </div>
            </div>
        </div>

        <div class="buy-sidebar">
            <div class="order-title">Resumen de Orden</div>

            <div class="order-event">
                <div class="order-event-name">Gran Campeonato Nacional de Charreada</div>
                <div class="order-event-meta">📅 SAB 15 MAR 2025 · 10:00 AM<br>📍 Lienzo Charro, Guadalajara</div>
            </div>

            <div class="order-divider"></div>

            <div class="order-items">
                <div class="order-item">
                    <div>
                        <div class="order-item-name">Palco VIP</div>
                        <div class="order-item-qty">{{ qtys[0] }} boletos × $850</div>
                    </div>
                    <div class="order-item-price">{{ currency(qtys[0] * 850) }}</div>
                </div>
            </div>

            <div class="order-divider"></div>

            <div class="order-subtotals">
                <div class="order-line"><span>Subtotal</span><span>{{ currency(subtotal) }}</span></div>
                <div class="order-line"><span>Cargo por servicio (10%)</span><span>{{ currency(fee) }}</span></div>
                <div class="order-line"><span>Descuento</span><span style="color: #4CAF50;">—</span></div>
            </div>

            <div class="order-total">
                <div class="order-total-label">Total</div>
                <div class="order-total-price">{{ currency(total) }}</div>
            </div>

            <button class="btn-continuar">Continuar → Mis Datos</button>

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
.lienzo-arena { padding: 16px 40px; background: rgba(200,146,42,0.05); border: 1px dashed rgba(200,146,42,0.2); font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 2px; text-transform: uppercase; }
.seat-categories { display: flex; flex-direction: column; gap: 12px; }
.seat-cat { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; border: 1px solid rgba(200,146,42,0.2); background: rgba(0,0,0,0.2); transition: all 0.2s; cursor: pointer; }
.seat-cat:hover { border-color: var(--dorado); background: rgba(200,146,42,0.05); }
.seat-cat.selected { border-color: var(--dorado-claro); background: rgba(240,192,96,0.08); }
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
@media (max-width: 768px) {
  .buy-layout { grid-template-columns: 1fr; }
  .buy-sidebar { border-left: none; border-top: 1px solid rgba(200,146,42,0.3); }
  .event-banner { flex-wrap: wrap; }
}
</style>
