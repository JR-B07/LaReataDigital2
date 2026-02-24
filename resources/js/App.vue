<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Chárro<span>Tickets</span></a>
        <div class="topbar-nav">
            <a href="/" class="active">Inicio</a>
            <a href="/compra">Comprar</a>
            <a href="/validador">Validador</a>
            <a href="/admin">Admin</a>
            <a href="/reportes">Reportes</a>
        </div>
    </nav>
    <div class="page-label">Vista 1 / 5 — Portal Público</div>

    <section class="hero">
        <div class="hero-badge">Plataforma Oficial de Charreada · México</div>
        <h1 class="hero-title">Chárro<span>Tickets</span></h1>
        <p class="hero-sub">Tu acceso a las mejores charreadas de México — compra segura en línea</p>
        <div class="search-bar">
            <input type="text" placeholder="Buscar evento, ciudad, lienzo charro...">
            <button>Buscar</button>
        </div>
        <div class="filter-chips">
            <div class="chip active">Todos</div>
            <div class="chip">Jalisco</div>
            <div class="chip">CDMX</div>
            <div class="chip">Querétaro</div>
            <div class="chip">Guanajuato</div>
            <div class="chip">Nuevo León</div>
            <div class="chip">Este mes</div>
        </div>
        <div class="stats-bar">
            <div class="stat-item"><div class="stat-val">47</div><div class="stat-label">Eventos activos</div></div>
            <div class="stat-item"><div class="stat-val">18</div><div class="stat-label">Estados</div></div>
            <div class="stat-item"><div class="stat-val">12K+</div><div class="stat-label">Tickets vendidos</div></div>
        </div>
    </section>

    <div class="events-section">
        <div class="section-header">
            <div class="section-title">Evento Destacado</div>
        </div>
        <div class="featured-card">
            <div>
                <div class="featured-title">Gran Campeonato Nacional de Charreada 2025</div>
                <div class="featured-meta">📅 SAB 15 MAR 2025 · 10:00 AM &nbsp;&nbsp; 📍 Lienzo Charro Guadalajara, Jalisco</div>
                <div class="featured-tags">
                    <div class="tag">Campeonato Nacional</div>
                    <div class="tag">Charreada Completa</div>
                    <div class="tag">10 Suertes</div>
                    <div class="tag">Escaramuzas</div>
                </div>
                <div class="capacity-bar-wrap">
                    <div class="capacity-label"><span>Disponibilidad</span><span>847 / 1,200 vendidos</span></div>
                    <div class="capacity-bar"><div class="capacity-fill" style="width: 70%;"></div></div>
                </div>
            </div>
            <div class="featured-price">
                <span class="featured-price-val">$350</span>
                <span class="featured-price-label">Desde</span>
                <br><br>
                <a href="/compra"><button class="btn-comprar" style="padding: 14px 28px; font-size: 12px;">Comprar Ahora →</button></a>
            </div>
        </div>

        <div class="section-header">
            <div class="section-title">Próximos Eventos</div>
            <a href="#" class="view-all">Ver todos →</a>
        </div>
        <div class="events-grid">
            <div class="event-card">
                <div class="card-img" style="background: linear-gradient(135deg, #5A0000, #8B1A1A);">
                    🐎 <div class="card-date-badge">22 MAR</div>
                </div>
                <div class="card-body">
                    <div class="card-location">📍 Ciudad de México, CDMX</div>
                    <div class="card-title">Torneo Estatal de Escaramuzas</div>
                    <div class="card-desc">Competencia regional con los mejores equipos de escaramuzas del centro del país.</div>
                    <div class="card-footer">
                        <div class="price-tag"><small>Desde</small>$200</div>
                        <a href="/compra"><button class="btn-comprar">Comprar</button></a>
                    </div>
                </div>
            </div>

            <div class="event-card">
                <div class="card-img" style="background: linear-gradient(135deg, #1A4A2E, #2D7A4A);">
                    🤠 <div class="card-date-badge">05 ABR</div>
                    <div class="card-sold-badge">AGOTADO</div>
                </div>
                <div class="card-body">
                    <div class="card-location">📍 Querétaro, Qro.</div>
                    <div class="card-title">Lienzo Charro Internacional 2025</div>
                    <div class="card-desc">El evento charro más importante de la región, con equipos de México y el extranjero.</div>
                    <div class="card-footer">
                        <div class="price-tag"><small>Desde</small>$480</div>
                        <button class="btn-agotado">Agotado</button>
                    </div>
                </div>
            </div>

            <div class="event-card">
                <div class="card-img" style="background: linear-gradient(135deg, #3D2008, #7A4010);">
                    🏟️ <div class="card-date-badge">19 ABR</div>
                </div>
                <div class="card-body">
                    <div class="card-location">📍 Monterrey, Nuevo León</div>
                    <div class="card-title">Charreada Norteña Primavera</div>
                    <div class="card-desc">Tradición charra del norte. Coleadero, jineteo de toro y todas las suertes charras.</div>
                    <div class="card-footer">
                        <div class="price-tag"><small>Desde</small>$300</div>
                        <a href="/compra"><button class="btn-comprar">Comprar</button></a>
                    </div>
                </div>
            </div>

            <div class="event-card">
                <div class="card-img" style="background: linear-gradient(135deg, #0A2A4A, #1A4A7A);">
                    🎭 <div class="card-date-badge">03 MAY</div>
                </div>
                <div class="card-body">
                    <div class="card-location">📍 Guadalajara, Jalisco</div>
                    <div class="card-title">Fiesta Charra de Mayo</div>
                    <div class="card-desc">Celebración tradicional con charreada, música de mariachi y gastronomía jalisciense.</div>
                    <div class="card-footer">
                        <div class="price-tag"><small>Desde</small>$250</div>
                        <a href="/compra"><button class="btn-comprar">Comprar</button></a>
                    </div>
                </div>
            </div>

            <div class="event-card">
                <div class="card-img" style="background: linear-gradient(135deg, #3A1A4A, #6A3A7A);">
                    🌟 <div class="card-date-badge">17 MAY</div>
                </div>
                <div class="card-body">
                    <div class="card-location">📍 San Luis Potosí, SLP</div>
                    <div class="card-title">Campeonato Regional Bajío</div>
                    <div class="card-desc">Los mejores charros del Bajío se reúnen en este torneo regional.</div>
                    <div class="card-footer">
                        <div class="price-tag"><small>Desde</small>$320</div>
                        <a href="/compra"><button class="btn-comprar">Comprar</button></a>
                    </div>
                </div>
            </div>

            <div class="event-card">
                <div class="card-img" style="background: linear-gradient(135deg, #1A3A1A, #2A6A2A);">
                    🎪 <div class="card-date-badge">28 MAY</div>
                </div>
                <div class="card-body">
                    <div class="card-location">📍 León, Guanajuato</div>
                    <div class="card-title">Gran Festival Charro León 2025</div>
                    <div class="card-desc">Tres días de charreada en el corazón del Bajío. El evento más esperado del año.</div>
                    <div class="card-footer">
                        <div class="price-tag"><small>Desde</small>$400</div>
                        <a href="/compra"><button class="btn-comprar">Comprar</button></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-brand">Chárro<span>Tickets</span></div>
        <div class="footer-text">© 2025 ChárroTickets · Todos los derechos reservados · México</div>
    </footer>
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

.hero {
    background: linear-gradient(160deg, #1A0800 0%, #3D1200 50%, #1A0800 100%);
    padding: 72px 40px 60px;
    text-align: center;
    position: relative;
    overflow: hidden;
    border-bottom: 3px solid var(--dorado);
}
.hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        radial-gradient(circle at 20% 50%, rgba(200,146,42,0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(139,26,26,0.15) 0%, transparent 50%);
}
.hero-badge {
    display: inline-block;
    border: 1px solid var(--dorado);
    padding: 5px 20px;
    font-family: 'DM Mono', monospace;
    font-size: 10px; letter-spacing: 4px; text-transform: uppercase;
    color: var(--dorado); margin-bottom: 24px; position: relative;
}
.hero-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(40px, 7vw, 80px);
    font-weight: 900; color: var(--crema);
    line-height: 1.05; margin-bottom: 14px; position: relative;
}
.hero-title span { color: var(--dorado-claro); }
.hero-sub { font-size: 15px; color: var(--gris); margin-bottom: 40px; letter-spacing: 1px; position: relative; }

.search-bar {
    display: flex; max-width: 620px; margin: 0 auto 24px;
    border: 1.5px solid var(--dorado); background: rgba(0,0,0,0.4); position: relative;
}
.search-bar input {
    flex: 1; background: none; border: none;
    padding: 14px 20px; color: var(--crema);
    font-family: 'Libre Baskerville', serif; font-size: 14px; outline: none;
}
.search-bar input::placeholder { color: var(--gris); }
.search-bar button {
    background: var(--dorado); border: none; padding: 14px 28px;
    color: var(--cafe); font-family: 'DM Mono', monospace;
    font-size: 11px; letter-spacing: 2px; cursor: pointer;
    font-weight: 500; text-transform: uppercase;
}

.filter-chips { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 16px; position: relative; }
.chip {
    padding: 6px 16px; border: 1px solid rgba(200,146,42,0.4);
    background: rgba(200,146,42,0.08); font-family: 'DM Mono', monospace;
    font-size: 10px; letter-spacing: 1px; text-transform: uppercase;
    color: var(--dorado); cursor: pointer; transition: all 0.2s;
}
.chip:hover, .chip.active { background: var(--dorado); color: var(--cafe); }

.stats-bar {
    display: flex; justify-content: center; gap: 48px;
    padding: 20px; background: rgba(0,0,0,0.3); position: relative;
}
.stat-item { text-align: center; }
.stat-val { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--dorado-claro); }
.stat-label { font-family: 'DM Mono', monospace; font-size: 9px; letter-spacing: 2px; color: var(--gris); text-transform: uppercase; margin-top: 3px; }

.events-section { padding: 40px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid rgba(200,146,42,0.2); }
.section-title { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--dorado-claro); }
.view-all { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--dorado); text-decoration: none; }
.view-all:hover { color: var(--dorado-claro); }

.events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }
.event-card {
    background: linear-gradient(145deg, #2A1504, #1A0800);
    border: 1px solid rgba(200,146,42,0.3);
    overflow: hidden; transition: transform 0.3s, border-color 0.3s; cursor: pointer;
}
.event-card:hover { transform: translateY(-5px); border-color: var(--dorado); }

.card-img {
    height: 150px;
    display: flex; align-items: center; justify-content: center;
    font-size: 60px; position: relative; overflow: hidden;
}
.card-img::after {
    content: ''; position: absolute; bottom: 0; left: 0; right: 0;
    height: 50px; background: linear-gradient(transparent, #1A0800);
}
.card-date-badge {
    position: absolute; top: 12px; right: 12px;
    background: var(--dorado); color: var(--cafe);
    font-family: 'DM Mono', monospace; font-size: 10px;
    padding: 4px 12px; font-weight: 500;
}
.card-sold-badge {
    position: absolute; top: 12px; left: 12px;
    background: var(--rojo); color: var(--crema);
    font-family: 'DM Mono', monospace; font-size: 9px;
    padding: 4px 10px; letter-spacing: 1px;
}
.card-body { padding: 20px; }
.card-location { font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; color: var(--dorado); text-transform: uppercase; margin-bottom: 6px; }
.card-title { font-family: 'Playfair Display', serif; font-size: 18px; color: var(--crema); margin-bottom: 8px; line-height: 1.3; }
.card-desc { font-size: 13px; color: var(--gris); line-height: 1.5; margin-bottom: 14px; }

.card-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 14px; border-top: 1px solid rgba(200,146,42,0.15); }
.price-tag { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--dorado-claro); }
.price-tag small { font-size: 11px; color: var(--gris); font-family: 'DM Mono', monospace; display: block; }
.btn-comprar { background: var(--rojo); border: none; padding: 10px 18px; color: var(--crema); font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; cursor: pointer; text-transform: uppercase; transition: background 0.2s; }
.btn-comprar:hover { background: #A02020; }
.btn-agotado { background: rgba(107,96,85,0.3); border: 1px solid var(--gris); padding: 10px 18px; color: var(--gris); font-family: 'DM Mono', monospace; font-size: 10px; letter-spacing: 2px; cursor: not-allowed; text-transform: uppercase; }

.featured-card {
    background: linear-gradient(135deg, #3D0808, #1A0800);
    border: 1.5px solid var(--dorado); padding: 28px;
    display: grid; grid-template-columns: 1fr auto;
    gap: 24px; align-items: center; margin-bottom: 32px;
    position: relative; overflow: hidden;
}
.featured-card::before {
    content: '⭐ DESTACADO';
    position: absolute; top: 14px; left: 0;
    background: var(--dorado); color: var(--cafe);
    font-family: 'DM Mono', monospace; font-size: 9px;
    padding: 4px 14px; letter-spacing: 2px;
}
.featured-title { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--crema); margin-top: 16px; margin-bottom: 8px; }
.featured-meta { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--gris); letter-spacing: 1px; margin-bottom: 16px; }
.featured-tags { display: flex; gap: 8px; flex-wrap: wrap; }
.tag { padding: 4px 12px; border: 1px solid rgba(200,146,42,0.3); font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 1px; }
.featured-price { text-align: right; }
.featured-price-val { font-family: 'Playfair Display', serif; font-size: 40px; color: var(--dorado-claro); display: block; }
.featured-price-label { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 2px; }

.capacity-bar-wrap { margin-top: 12px; }
.capacity-label { font-family: 'DM Mono', monospace; font-size: 9px; color: var(--gris); letter-spacing: 1px; margin-bottom: 5px; display: flex; justify-content: space-between; }
.capacity-bar { height: 4px; background: rgba(200,146,42,0.15); border-radius: 2px; overflow: hidden; }
.capacity-fill { height: 100%; background: linear-gradient(90deg, var(--rojo), var(--dorado)); border-radius: 2px; }

.site-footer { background: #100400; border-top: 1px solid rgba(200,146,42,0.2); padding: 32px 40px; display: flex; justify-content: space-between; align-items: center; margin-top: 48px; }
.footer-brand { font-family: 'Playfair Display', serif; font-size: 18px; color: var(--crema); }
.footer-brand span { color: var(--dorado-claro); }
.footer-text { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--gris); letter-spacing: 1px; }
</style>
