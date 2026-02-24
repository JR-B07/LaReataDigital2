<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Chárro<span>Tickets</span></a>
        <div class="topbar-nav">
            <a href="/">Inicio</a>
            <a href="/compra">Comprar</a>
            <a href="/validador">Validador</a>
            <a href="/admin" class="active">Admin</a>
            <a href="/reportes">Reportes</a>
        </div>
    </nav>
    <div class="page-label">Vista 4 / 5 — Panel de Administración</div>

    <div class="admin-layout">
        <div class="admin-sidebar">
            <div class="sidebar-user">
                <div class="sidebar-avatar">👤</div>
                <div class="sidebar-name">Ing. Raúl Vega</div>
                <div class="sidebar-role">Administrador General</div>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Principal</div>
                <a href="#" class="nav-item active">📊 Dashboard</a>
                <a href="#" class="nav-item">📅 Eventos <span class="nav-badge">7</span></a>
                <a href="#" class="nav-item">🎫 Boletos</a>
                <a href="#" class="nav-item">👥 Compradores</a>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Operaciones</div>
                <a href="#" class="nav-item">💳 Pagos</a>
                <a href="#" class="nav-item">📷 Validadores</a>
                <a href="/reportes" class="nav-item">📈 Reportes</a>
                <a href="#" class="nav-item">🏷️ Descuentos</a>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Sistema</div>
                <a href="#" class="nav-item">⚙️ Configuración</a>
                <a href="#" class="nav-item">👤 Usuarios Admin</a>
                <a href="#" class="nav-item">📋 Auditoría</a>
            </div>
        </div>

        <div class="admin-main">
            <div class="admin-header">
                <div>
                    <div class="admin-title">Dashboard</div>
                    <div class="admin-subtitle">MARTES 15 MAR 2025 · Última actualización: hace 3 min</div>
                </div>
                <div class="header-actions">
                    <button class="btn-outline">⬇ Exportar</button>
                    <button class="btn-primary">+ Nuevo Evento</button>
                </div>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon">🎫</div>
                    <div class="kpi-label">Tickets Vendidos</div>
                    <div class="kpi-value">3,847</div>
                    <div class="kpi-trend up">▲ 12% vs semana pasada</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon">💰</div>
                    <div class="kpi-label">Ingresos Totales</div>
                    <div class="kpi-value">$1.2M</div>
                    <div class="kpi-trend up">▲ 8% este mes</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon">📅</div>
                    <div class="kpi-label">Eventos Activos</div>
                    <div class="kpi-value">7</div>
                    <div class="kpi-trend up">3 en próximas 2 semanas</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon">✅</div>
                    <div class="kpi-label">Tasa de Asistencia</div>
                    <div class="kpi-value">89%</div>
                    <div class="kpi-trend up">▲ 3% vs mes anterior</div>
                </div>
            </div>

            <div class="section-header">
                <div class="section-title">Gestión de Eventos</div>
                <div class="section-actions">
                    <input class="search-mini" type="text" placeholder="Buscar evento...">
                    <button class="filter-btn">⚙ Filtros</button>
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th class="sortable">Evento ↕</th>
                        <th class="sortable">Fecha ↕</th>
                        <th>Lugar</th>
                        <th class="sortable">Vendidos ↕</th>
                        <th>Ocupación</th>
                        <th class="sortable">Ingresos ↕</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="event-name-cell">
                                <div class="event-thumb" style="background: rgba(139,26,26,0.2);">🐎</div>
                                <div>
                                    <div style="font-weight: bold; color: var(--crema);">Gran Campeonato Nacional</div>
                                    <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">Charreada Completa</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>15 Mar 2025</div>
                            <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">10:00 AM</div>
                        </td>
                        <td>Guadalajara, Jal.</td>
                        <td><b>847</b></td>
                        <td>
                            <div class="progress-mini">
                                <div class="progress-mini-bar">
                                    <div class="progress-mini-fill" style="width:70.6%"></div>
                                </div>
                                <div class="progress-mini-text">847 / 1,200 — 70.6%</div>
                            </div>
                        </td>
                        <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">$423,500</td>
                        <td><span class="status-pill active">Activo</span></td>
                        <td>
                            <div class="table-actions">
                                <button class="action-btn">✏ Editar</button><button class="action-btn">👁 Ver</button><button
                                    class="action-btn danger">🗑</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="event-name-cell">
                                <div class="event-thumb" style="background: rgba(26,74,46,0.2);">🤠</div>
                                <div>
                                    <div style="font-weight: bold; color: var(--crema);">Torneo Estatal Escaramuzas</div>
                                    <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">Escaramuzas Charras</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>22 Mar 2025</div>
                            <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">12:00 PM</div>
                        </td>
                        <td>CDMX</td>
                        <td><b>342</b></td>
                        <td>
                            <div class="progress-mini">
                                <div class="progress-mini-bar">
                                    <div class="progress-mini-fill" style="width:42.75%"></div>
                                </div>
                                <div class="progress-mini-text">342 / 800 — 42.8%</div>
                            </div>
                        </td>
                        <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">$68,400</td>
                        <td><span class="status-pill upcoming">Próximo</span></td>
                        <td>
                            <div class="table-actions">
                                <button class="action-btn">✏ Editar</button><button class="action-btn">👁 Ver</button><button
                                    class="action-btn danger">🗑</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="event-name-cell">
                                <div class="event-thumb" style="background: rgba(61,32,8,0.4);">🏟️</div>
                                <div>
                                    <div style="font-weight: bold; color: var(--crema);">Lienzo Charro Internacional</div>
                                    <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">Internacional</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>05 Abr 2025</div>
                            <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">11:00 AM</div>
                        </td>
                        <td>Querétaro, Qro.</td>
                        <td><b>1,200</b></td>
                        <td>
                            <div class="progress-mini">
                                <div class="progress-mini-bar">
                                    <div class="progress-mini-fill" style="width:100%"></div>
                                </div>
                                <div class="progress-mini-text">1,200 / 1,200 — 100%</div>
                            </div>
                        </td>
                        <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">$576,000</td>
                        <td><span class="status-pill sold">Agotado</span></td>
                        <td>
                            <div class="table-actions">
                                <button class="action-btn">✏ Editar</button><button class="action-btn">👁 Ver</button><button
                                    class="action-btn danger">🗑</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="event-name-cell">
                                <div class="event-thumb" style="background: rgba(61,32,8,0.2);">🎪</div>
                                <div>
                                    <div style="font-weight: bold; color: var(--crema);">Fiesta Charra de Mayo</div>
                                    <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">Festival</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>03 May 2025</div>
                            <div style="font-family:'DM Mono',monospace;font-size:10px;color:var(--gris);">10:00 AM</div>
                        </td>
                        <td>Guadalajara, Jal.</td>
                        <td><b>0</b></td>
                        <td>
                            <div class="progress-mini">
                                <div class="progress-mini-bar">
                                    <div class="progress-mini-fill" style="width:0%"></div>
                                </div>
                                <div class="progress-mini-text">0 / 900 — 0%</div>
                            </div>
                        </td>
                        <td style="font-family:'Playfair Display',serif;color:var(--dorado-claro);">$0</td>
                        <td><span class="status-pill draft">Borrador</span></td>
                        <td>
                            <div class="table-actions">
                                <button class="action-btn">✏ Editar</button><button class="action-btn">👁 Ver</button><button
                                    class="action-btn danger">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="bottom-grid">
                <div class="widget">
                    <div class="widget-title">Ventas Recientes</div>
                    <div class="recent-sale">
                        <div class="sale-avatar">🤠</div>
                        <div class="sale-info">
                            <div class="sale-name">Carlos Mendoza</div>
                            <div class="sale-meta">2× Palco VIP · Campeonato Nacional</div>
                        </div>
                        <div class="sale-amount">$1,700</div>
                    </div>
                    <div class="recent-sale">
                        <div class="sale-avatar">👩</div>
                        <div class="sale-info">
                            <div class="sale-name">Ana González</div>
                            <div class="sale-meta">4× General · Torneo Escaramuzas</div>
                        </div>
                        <div class="sale-amount">$800</div>
                    </div>
                    <div class="recent-sale">
                        <div class="sale-avatar">👨</div>
                        <div class="sale-info">
                            <div class="sale-name">Roberto Hernández</div>
                            <div class="sale-meta">1× Gradería Premium · Campeonato</div>
                        </div>
                        <div class="sale-amount">$500</div>
                    </div>
                    <div class="recent-sale">
                        <div class="sale-avatar">👴</div>
                        <div class="sale-info">
                            <div class="sale-name">José Morales Cruz</div>
                            <div class="sale-meta">3× General + 2× Niños · Campeonato</div>
                        </div>
                        <div class="sale-amount">$1,350</div>
                    </div>
                </div>

                <div class="widget">
                    <div class="widget-title">Alertas del Sistema</div>
                    <div class="alert-item warning">
                        <div class="alert-icon">⚠️</div>
                        <div>
                            <div class="alert-text">Torneo Escaramuzas tiene menos del 50% de ocupación — considera una promoción.</div>
                            <div class="alert-meta">hace 2 horas</div>
                        </div>
                    </div>
                    <div class="alert-item info">
                        <div class="alert-icon">ℹ️</div>
                        <div>
                            <div class="alert-text">Lienzo Charro Internacional — se detectaron 3 intentos de uso de tickets duplicados.</div>
                            <div class="alert-meta">hace 4 horas</div>
                        </div>
                    </div>
                    <div class="alert-item info">
                        <div class="alert-icon">📧</div>
                        <div>
                            <div class="alert-text">847 correos de confirmación enviados exitosamente hoy.</div>
                            <div class="alert-meta">hace 6 horas</div>
                        </div>
                    </div>
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
.admin-layout { display: grid; grid-template-columns: 230px 1fr; min-height: calc(100vh - 82px); }
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
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
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
  .kpi-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  .admin-layout { grid-template-columns: 1fr; }
  .admin-sidebar { display: none; }
  .bottom-grid { grid-template-columns: 1fr; }
}
</style>
