<script setup>
import { computed } from 'vue';

const weeklyData = [
    { label: 'Sem 1', vip: 40, prem: 30, gen: 20 },
    { label: 'Sem 2', vip: 65, prem: 45, gen: 35 },
    { label: 'Sem 3', vip: 30, prem: 25, gen: 15 },
    { label: 'Sem 4', vip: 90, prem: 70, gen: 50 },
    { label: 'Sem 5', vip: 75, prem: 55, gen: 40 },
    { label: 'Sem 6', vip: 110, prem: 80, gen: 55 },
    { label: 'Sem 7', vip: 85, prem: 65, gen: 45 },
    { label: 'Sem 8', vip: 130, prem: 90, gen: 60 },
];

const bars = computed(() => {
    const max = Math.max(...weeklyData.map((item) => item.vip + item.prem + item.gen));

    return weeklyData.map((item) => {
        const total = item.vip + item.prem + item.gen;
        return {
            ...item,
            total,
            stackHeight: `${(total / max) * 128}px`,
            vipHeight: `${(item.vip / total) * 100}%`,
            premHeight: `${(item.prem / total) * 100}%`,
            genHeight: `${(item.gen / total) * 100}%`,
        };
    });
});
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Chárro<span>Tickets</span></a>
        <div class="topbar-nav">
            <a href="/">Inicio</a>
            <a href="/compra">Comprar</a>
            <a href="/validador">Validador</a>
            <a href="/admin">Admin</a>
            <a href="/reportes" class="active">Reportes</a>
        </div>
    </nav>
    <div class="page-label">Vista 5 / 5 — Reportes y Estadísticas</div>

    <div class="report-layout">
        <div class="admin-sidebar">
            <div class="nav-group-label">Principal</div>
            <a href="/admin" class="nav-item">📊 Dashboard</a>
            <a href="/admin" class="nav-item">📅 Eventos</a>
            <div class="nav-group-label">Reportes</div>
            <a href="#" class="nav-item active">📈 Ventas</a>
            <a href="#" class="nav-item">👥 Compradores</a>
            <a href="#" class="nav-item">🎫 Por Evento</a>
            <a href="#" class="nav-item">💳 Pagos</a>
            <a href="#" class="nav-item">✅ Asistencia</a>
            <a href="#" class="nav-item">🛡️ Seguridad</a>
        </div>

        <div class="report-main">
            <div class="report-header">
                <div>
                    <div class="report-title">Reportes de Ventas</div>
                    <div class="report-subtitle">ENERO — MARZO 2025 · Todos los eventos</div>
                </div>
                <div class="header-actions">
                    <button class="btn-outline">⬇ Excel</button>
                    <button class="btn-outline">⬇ PDF</button>
                    <button class="btn-primary">📊 Personalizar</button>
                </div>
            </div>

            <div class="date-tabs">
                <div class="date-tab">Hoy</div>
                <div class="date-tab">7 días</div>
                <div class="date-tab active">30 días</div>
                <div class="date-tab">Este año</div>
                <div class="date-tab">Personalizado</div>
            </div>

            <div class="kpi-row">
                <div class="kpi-mini">
                    <div class="kpi-mini-label">Total Vendido</div>
                    <div class="kpi-mini-val">$1.24M</div>
                    <div class="kpi-mini-trend trend-up">▲ 8.2%</div>
                </div>
                <div class="kpi-mini">
                    <div class="kpi-mini-label">Tickets</div>
                    <div class="kpi-mini-val">3,847</div>
                    <div class="kpi-mini-trend trend-up">▲ 12.1%</div>
                </div>
                <div class="kpi-mini">
                    <div class="kpi-mini-label">Ticket Prom.</div>
                    <div class="kpi-mini-val">$492</div>
                    <div class="kpi-mini-trend trend-up">▲ 3.4%</div>
                </div>
                <div class="kpi-mini">
                    <div class="kpi-mini-label">Conversión</div>
                    <div class="kpi-mini-val">6.8%</div>
                    <div class="kpi-mini-trend trend-down">▼ 0.3%</div>
                </div>
                <div class="kpi-mini">
                    <div class="kpi-mini-label">Reembolsos</div>
                    <div class="kpi-mini-val">$8,400</div>
                    <div class="kpi-mini-trend trend-up">▼ 2.1%</div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Ventas Semanales — Últimas 8 Semanas</div>
                    <div class="chart-legend">
                        <div class="legend-item"><div class="legend-dot" style="background:var(--rojo)"></div>Palco VIP</div>
                        <div class="legend-item"><div class="legend-dot" style="background:var(--dorado)"></div>Gradería</div>
                        <div class="legend-item"><div class="legend-dot" style="background:var(--verde)"></div>General</div>
                    </div>
                </div>
                <div class="bar-chart-wrap">
                    <div class="bar-chart">
                        <div class="y-labels">
                            <div class="y-label">0</div>
                            <div class="y-label">150</div>
                            <div class="y-label">300</div>
                            <div class="y-label">450</div>
                            <div class="y-label">600</div>
                        </div>
                        <div v-for="item in bars" :key="item.label" class="bar-group">
                            <div class="bar-val">{{ item.total }}</div>
                            <div class="bar-stack" :style="{ height: item.stackHeight }">
                                <div class="bar" :style="{ height: item.genHeight, background: '#1A4A2E' }" :title="`General: ${item.gen}`"></div>
                                <div class="bar" :style="{ height: item.premHeight, background: '#C8922A' }" :title="`Gradería: ${item.prem}`"></div>
                                <div class="bar" :style="{ height: item.vipHeight, background: '#8B1A1A' }" :title="`VIP: ${item.vip}`"></div>
                                <div class="bar-label">{{ item.label }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="two-col">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Distribución por Zona</div>
                    </div>
                    <div class="donut-wrap">
                        <div class="donut-svg-wrap">
                            <svg viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="35" fill="none" stroke="#8B1A1A" stroke-width="18" stroke-dasharray="79.2 221" stroke-dashoffset="0" transform="rotate(-90 50 50)" />
                                <circle cx="50" cy="50" r="35" fill="none" stroke="#C8922A" stroke-width="18" stroke-dasharray="61.6 238.6" stroke-dashoffset="-79.2" transform="rotate(-90 50 50)" />
                                <circle cx="50" cy="50" r="35" fill="none" stroke="#1A4A2E" stroke-width="18" stroke-dasharray="41.8 258.4" stroke-dashoffset="-140.8" transform="rotate(-90 50 50)" />
                                <circle cx="50" cy="50" r="35" fill="none" stroke="#6B6055" stroke-width="18" stroke-dasharray="37.4 262.8" stroke-dashoffset="-182.6" transform="rotate(-90 50 50)" />
                                <circle cx="50" cy="50" r="25" fill="#1A0800" />
                            </svg>
                            <div class="donut-center">
                                <div class="donut-center-val">3,847</div>
                                <div class="donut-center-label">Tickets</div>
                            </div>
                        </div>
                        <div class="donut-legend">
                            <div class="donut-legend-item">
                                <div class="donut-legend-left"><div class="donut-legend-dot" style="background:var(--rojo)"></div><div class="donut-legend-name">Palco VIP</div></div>
                                <div class="donut-legend-pct">36%</div>
                            </div>
                            <div class="donut-legend-item">
                                <div class="donut-legend-left"><div class="donut-legend-dot" style="background:var(--dorado)"></div><div class="donut-legend-name">Gradería Premium</div></div>
                                <div class="donut-legend-pct">28%</div>
                            </div>
                            <div class="donut-legend-item">
                                <div class="donut-legend-left"><div class="donut-legend-dot" style="background:var(--verde)"></div><div class="donut-legend-name">General</div></div>
                                <div class="donut-legend-pct">19%</div>
                            </div>
                            <div class="donut-legend-item">
                                <div class="donut-legend-left"><div class="donut-legend-dot" style="background:var(--gris)"></div><div class="donut-legend-name">Niños</div></div>
                                <div class="donut-legend-pct">17%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Métricas Clave del Período</div>
                    </div>
                    <div>
                        <div class="metric-row"><div class="metric-name">Ingresos Totales</div><div class="metric-val">$1,243,400</div></div>
                        <div class="metric-row"><div class="metric-name">Ticket Promedio</div><div class="metric-val">$492</div></div>
                        <div class="metric-row"><div class="metric-name">Tasa de Asistencia</div><div class="metric-val up">89.2%</div></div>
                        <div class="metric-row"><div class="metric-name">Tickets Fraudulentos</div><div class="metric-val up">0.02%</div></div>
                        <div class="metric-row"><div class="metric-name">Tasa de Reembolso</div><div class="metric-val up">0.68%</div></div>
                        <div class="metric-row"><div class="metric-name">Eventos Completados</div><div class="metric-val">24</div></div>
                        <div class="metric-row"><div class="metric-name">Satisfacción Cliente</div><div class="metric-val up">4.7 / 5</div></div>
                        <div class="metric-row" style="border:none"><div class="metric-name">Métodos de Pago — Top</div><div class="metric-val" style="font-size:13px;">Tarjeta 64%</div></div>
                    </div>
                </div>
            </div>

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
                            <th>Asistencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span style="font-family:'Playfair Display',serif;color:var(--dorado-claro);font-size:18px;">1</span></td>
                            <td><b>Lienzo Charro Internacional</b></td>
                            <td>05 Abr 2025</td>
                            <td>1,200</td>
                            <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">$576,000</td>
                            <td>$480</td>
                            <td><span style="color:#4CAF50;">94%</span></td>
                        </tr>
                        <tr>
                            <td><span style="font-family:'Playfair Display',serif;color:var(--crema);font-size:18px;">2</span></td>
                            <td><b>Gran Campeonato Nacional</b></td>
                            <td>15 Mar 2025</td>
                            <td>847</td>
                            <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">$423,500</td>
                            <td>$500</td>
                            <td><span style="color:#4CAF50;">91%</span></td>
                        </tr>
                        <tr>
                            <td><span style="font-family:'Playfair Display',serif;color:var(--gris);font-size:18px;">3</span></td>
                            <td><b>Charreada Norteña Primavera</b></td>
                            <td>19 Abr 2025</td>
                            <td>620</td>
                            <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">$186,000</td>
                            <td>$300</td>
                            <td><span style="color:#FF9800;">82%</span></td>
                        </tr>
                        <tr>
                            <td><span style="font-family:'Playfair Display',serif;color:var(--gris);font-size:18px;">4</span></td>
                            <td><b>Torneo Estatal Escaramuzas</b></td>
                            <td>22 Mar 2025</td>
                            <td>342</td>
                            <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">$68,400</td>
                            <td>$200</td>
                            <td><span style="color:#FF9800;">78%</span></td>
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
