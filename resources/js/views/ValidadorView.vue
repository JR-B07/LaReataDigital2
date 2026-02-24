<script setup>
import { computed, ref } from 'vue';

const mockTickets = ref({
    'CHT-2025-03-15-00001': { name: 'Luis Ramírez', zone: 'PALCO VIP · A-01', status: 'valid' },
    'CHT-2025-03-15-00002': { name: 'Elena Castro', zone: 'GENERAL · Norte', status: 'used' },
    'CHT-2025-03-15-00003': { name: 'Pedro Morales', zone: 'GRADERÍA PREMIUM · D-05', status: 'valid' },
});

const manualCode = ref('');
const countValid = ref(847);
const countPending = ref(353);
const countInvalid = ref(12);
const sessionCount = ref(5);

const resultType = ref('hidden');
const resultEmoji = ref('');
const resultText = ref('');
const scanHint = ref('Cámara lista · Esperando código');

const logs = ref([
    { type: 'valid', icon: '✓', name: 'Carlos Mendoza Rivas', code: 'CHT-2025-03-15-00847', detail: 'PALCO VIP · Asiento A-07', badge: 'VÁLIDO', time: 'hace 2 min' },
    { type: 'used', icon: '!', name: 'Ana González Pérez', code: 'CHT-2025-03-15-00631', detail: 'GENERAL · Zona Norte', badge: 'YA UTILIZADO', time: 'hace 8 min' },
    { type: 'invalid', icon: '✕', name: 'Código desconocido', code: 'XXX-0000-00-00-00000', detail: 'No encontrado en el sistema', badge: 'NO VÁLIDO', time: 'hace 15 min' },
    { type: 'valid', icon: '✓', name: 'Roberto Hernández Cruz', code: 'CHT-2025-03-15-00844', detail: 'GRADERÍA PREMIUM · Fila E-12', badge: 'VÁLIDO', time: 'hace 18 min' },
    { type: 'valid', icon: '✓', name: 'María Torres Salinas', code: 'CHT-2025-03-15-00840', detail: 'PALCO VIP · Asiento B-03', badge: 'VÁLIDO', time: 'hace 22 min' },
]);

const progressFill = computed(() => `${(countValid.value / 1200) * 100}%`);
const progressText = computed(() => `${countValid.value} / 1,200`);
const logCountText = computed(() => `${sessionCount.value} en esta sesión`);

const resetResult = () => {
    resultType.value = 'hidden';
    resultEmoji.value = '';
    resultText.value = '';
    scanHint.value = 'Cámara lista · Esperando código';
};

const showResult = (type, name, code, detail, badge) => {
    const mapping = {
        valid: { emoji: '✅', text: 'Acceso Permitido', icon: '✓' },
        used: { emoji: '⚠️', text: 'Ya Utilizado', icon: '!' },
        invalid: { emoji: '❌', text: 'Ticket No Válido', icon: '✕' },
    };

    resultType.value = type;
    resultEmoji.value = mapping[type].emoji;
    resultText.value = mapping[type].text;
    scanHint.value = mapping[type].text;

    sessionCount.value++;
    logs.value.unshift({
        type,
        icon: mapping[type].icon,
        name,
        code,
        detail,
        badge,
        time: 'ahora',
    });

    setTimeout(() => {
        resetResult();
    }, 2500);
};

const validateManual = () => {
    const code = manualCode.value.trim().toUpperCase();
    if (!code) {
        return;
    }

    const ticket = mockTickets.value[code];

    if (!ticket) {
        countInvalid.value++;
        showResult('invalid', 'Código desconocido', code, 'No encontrado en el sistema', 'NO VÁLIDO');
        manualCode.value = '';
        return;
    }

    if (ticket.status === 'used') {
        showResult('used', ticket.name, code, ticket.zone, 'YA UTILIZADO');
        manualCode.value = '';
        return;
    }

    ticket.status = 'used';
    countValid.value++;
    countPending.value = Math.max(0, countPending.value - 1);
    showResult('valid', ticket.name, code, ticket.zone, 'VÁLIDO');
    manualCode.value = '';
};
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Chárro<span>Tickets</span></a>
        <div class="topbar-nav">
            <a href="/">Inicio</a>
            <a href="/compra">Comprar</a>
            <a href="/validador" class="active">Validador</a>
            <a href="/admin">Admin</a>
            <a href="/reportes">Reportes</a>
        </div>
    </nav>
    <div class="page-label">Vista 3 / 5 — Validador de Entrada</div>

    <div class="event-header">
        <div class="event-header-info">
            <div class="event-header-name">Gran Campeonato Nacional de Charreada 2025</div>
            <div class="event-header-meta">SAB 15 MAR 2025 · 10:00 AM · PUERTA PRINCIPAL · OPERADOR: Juan Reyes</div>
        </div>
        <div class="live-badge"><div class="live-dot"></div>EN VIVO</div>
    </div>

    <div class="validator-layout">
        <div class="scanner-panel">
            <div class="scanner-title">Validador de Acceso</div>
            <div class="scanner-sub">Apunta al código de barras del boleto</div>

            <div class="scanner-box">
                <div class="scanner-corner tl"></div>
                <div class="scanner-corner tr"></div>
                <div class="scanner-corner bl"></div>
                <div class="scanner-corner br"></div>
                <div class="scan-line" v-if="resultType === 'hidden'"></div>
                <div class="scan-icon">📷</div>
                <div v-if="resultType !== 'hidden'" :class="`result-overlay ${resultType}`">
                    {{ resultEmoji }}
                    <div style="font-family:'DM Mono',monospace;font-size:14px;letter-spacing:2px;margin-top:8px;color:var(--crema);">
                        {{ resultText }}
                    </div>
                </div>
            </div>

            <div class="scan-hint">{{ scanHint }}</div>

            <div class="or-divider">— o ingresa manualmente —</div>

            <div class="manual-input">
                <input v-model="manualCode" type="text" placeholder="CHT-2025-03-15-XXXXX" maxlength="22" @keyup.enter="validateManual">
                <button @click="validateManual">Validar</button>
            </div>

            <div class="progress-stats">
                <div style="font-family:'DM Mono',monospace; font-size:10px; letter-spacing:2px; color:var(--dorado); text-transform:uppercase; margin-bottom:12px;">Progreso del Evento</div>
                <div class="progress-label">
                    <span>Asistencia</span>
                    <span>{{ progressText }}</span>
                </div>
                <div class="progress-bar"><div class="progress-fill" :style="{ width: progressFill }"></div></div>
                <div class="mini-stats">
                    <div class="mini-stat">
                        <div class="mini-stat-val" style="color: var(--dorado-claro);">{{ countValid }}</div>
                        <div class="mini-stat-label">Ingresados</div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-val" style="color: #42A5F5;">{{ countPending }}</div>
                        <div class="mini-stat-label">Pendientes</div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-val" style="color: #F44336;">{{ countInvalid }}</div>
                        <div class="mini-stat-label">Rechazados</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="log-panel">
            <div class="log-header">
                <div class="log-title">Historial de Escaneos</div>
                <div class="log-count">{{ logCountText }}</div>
            </div>
            <div class="log-list">
                <div v-for="(entry, index) in logs" :key="`${entry.code}-${index}`" class="log-entry">
                    <div :class="`log-status ${entry.type}`">{{ entry.icon }}</div>
                    <div class="log-info">
                        <div class="log-name">{{ entry.name }}</div>
                        <div class="log-code">{{ entry.code }}</div>
                        <div class="log-detail">{{ entry.detail }}</div>
                        <span :class="`log-badge ${entry.type}`">{{ entry.badge }}</span>
                    </div>
                    <div class="log-time">{{ entry.time }}</div>
                </div>
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
.event-header { background: linear-gradient(135deg, #2A0000, #1A0800); padding: 16px 32px; display: flex; align-items: center; gap: 16px; border-bottom: 1px solid rgba(200,146,42,0.3); }
.event-header-info { flex: 1; }
.event-header-name { font-family: 'Playfair Display', serif; font-size: 18px; color: var(--crema); }
.event-header-meta { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; margin-top: 3px; }
.live-badge { background: rgba(76,175,80,0.2); border: 1px solid #4CAF50; padding: 5px 14px; font-family: 'DM Mono', monospace; font-size: 9px; color: #4CAF50; letter-spacing: 2px; text-transform: uppercase; display: flex; align-items: center; gap: 6px; }
.live-dot { width: 7px; height: 7px; background: #4CAF50; border-radius: 50%; animation: blink 1.2s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
.validator-layout { display: grid; grid-template-columns: 1fr 420px; min-height: calc(100vh - 150px); }
.scanner-panel { padding: 40px; display: flex; flex-direction: column; align-items: center; }
.scanner-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--dorado-claro); margin-bottom: 6px; }
.scanner-sub { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 32px; text-align: center; }
.scanner-box { width: 300px; height: 300px; border: 2px solid rgba(200,146,42,0.4); position: relative; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
.scanner-corner { position: absolute; width: 28px; height: 28px; }
.scanner-corner.tl { top: -2px; left: -2px; border-top: 3px solid var(--dorado-claro); border-left: 3px solid var(--dorado-claro); }
.scanner-corner.tr { top: -2px; right: -2px; border-top: 3px solid var(--dorado-claro); border-right: 3px solid var(--dorado-claro); }
.scanner-corner.bl { bottom: -2px; left: -2px; border-bottom: 3px solid var(--dorado-claro); border-left: 3px solid var(--dorado-claro); }
.scanner-corner.br { bottom: -2px; right: -2px; border-bottom: 3px solid var(--dorado-claro); border-right: 3px solid var(--dorado-claro); }
.scan-line { position: absolute; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, var(--dorado), transparent); animation: scanAnim 2s ease-in-out infinite; box-shadow: 0 0 12px var(--dorado); }
@keyframes scanAnim { 0%{top:15%} 50%{top:85%} 100%{top:15%} }
.scan-icon { font-size: 72px; opacity: 0.15; }
.result-overlay { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 80px; transition: all 0.3s; }
.result-overlay.valid { background: rgba(76,175,80,0.25); }
.result-overlay.invalid { background: rgba(244,67,54,0.25); }
.result-overlay.used { background: rgba(255,152,0,0.25); }
.scan-hint { font-family: 'DM Mono', monospace; font-size: 11px; letter-spacing: 2px; color: var(--gris); text-transform: uppercase; text-align: center; }
.or-divider { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); margin: 16px 0; letter-spacing: 2px; }
.manual-input { display: flex; gap: 0; width: 100%; max-width: 340px; }
.manual-input input { flex: 1; background: rgba(0,0,0,0.5); border: 1px solid rgba(200,146,42,0.3); border-right: none; padding: 12px 16px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 12px; letter-spacing: 2px; outline: none; text-transform: uppercase; }
.manual-input input:focus { border-color: var(--dorado); }
.manual-input button { background: var(--dorado); border: none; padding: 12px 22px; color: var(--cafe); font-family: 'DM Mono', monospace; font-size: 11px; cursor: pointer; letter-spacing: 2px; text-transform: uppercase; transition: background 0.2s; }
.manual-input button:hover { background: var(--dorado-claro); }
.progress-stats { width: 100%; max-width: 340px; margin-top: 32px; }
.progress-label { display: flex; justify-content: space-between; font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); margin-bottom: 8px; letter-spacing: 1px; }
.progress-bar { height: 6px; background: rgba(200,146,42,0.1); border-radius: 3px; overflow: hidden; margin-bottom: 16px; }
.progress-fill { height: 100%; background: linear-gradient(90deg, var(--rojo), var(--dorado)); transition: width 0.5s; }
.mini-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.mini-stat { border: 1px solid rgba(200,146,42,0.2); padding: 12px; text-align: center; }
.mini-stat-val { font-family: 'Playfair Display', serif; font-size: 26px; }
.mini-stat-label { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 1px; text-transform: uppercase; margin-top: 3px; }
.log-panel { background: #1A0800; border-left: 1px solid rgba(200,146,42,0.3); display: flex; flex-direction: column; }
.log-header { padding: 20px 24px; border-bottom: 1px solid rgba(200,146,42,0.2); display: flex; align-items: center; justify-content: space-between; }
.log-title { font-family: 'Playfair Display', serif; font-size: 18px; color: var(--dorado-claro); }
.log-count { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--gris); letter-spacing: 1px; }
.log-list { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.log-entry { border: 1px solid rgba(200,146,42,0.15); padding: 14px 16px; display: flex; gap: 14px; align-items: flex-start; transition: background 0.2s; cursor: default; animation: slideIn 0.3s ease; }
@keyframes slideIn { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: translateX(0); } }
.log-entry:hover { background: rgba(200,146,42,0.03); }
.log-status { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.log-status.valid { background: rgba(76,175,80,0.15); color: #4CAF50; border: 1px solid rgba(76,175,80,0.3); }
.log-status.invalid { background: rgba(244,67,54,0.15); color: #F44336; border: 1px solid rgba(244,67,54,0.3); }
.log-status.used { background: rgba(255,152,0,0.15); color: #FF9800; border: 1px solid rgba(255,152,0,0.3); }
.log-info { flex: 1; }
.log-name { font-family: 'Playfair Display', serif; font-size: 16px; color: var(--crema); }
.log-code { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); margin-top: 2px; letter-spacing: 1px; }
.log-detail { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); margin-top: 4px; }
.log-badge { display: inline-block; padding: 2px 9px; font-family: 'DM Mono', monospace; font-size: 9px; margin-top: 5px; letter-spacing: 1px; }
.log-badge.valid { background: rgba(76,175,80,0.1); color: #4CAF50; border: 1px solid rgba(76,175,80,0.3); }
.log-badge.invalid { background: rgba(244,67,54,0.1); color: #F44336; border: 1px solid rgba(244,67,54,0.3); }
.log-badge.used { background: rgba(255,152,0,0.1); color: #FF9800; border: 1px solid rgba(255,152,0,0.3); }
.log-time { font-family: 'DM Mono', monospace; font-size: 9px; color: rgba(107,96,85,0.6); flex-shrink: 0; }
@media (max-width: 768px) {
  .validator-layout { grid-template-columns: 1fr; }
  .log-panel { border-left: none; border-top: 1px solid rgba(200,146,42,0.3); }
}
</style>
