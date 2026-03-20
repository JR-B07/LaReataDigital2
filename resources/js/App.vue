<script setup>
import { ref, computed, onMounted } from 'vue';

const events = ref([]);
const loading = ref(true);
const search = ref('');

const gradients = [
    'linear-gradient(135deg, #5A0000, #8B1A1A)',
    'linear-gradient(135deg, #1A4A2E, #2D7A4A)',
    'linear-gradient(135deg, #3D2008, #7A4010)',
    'linear-gradient(135deg, #0A2A4A, #1A4A7A)',
    'linear-gradient(135deg, #3A1A4A, #6A3A7A)',
    'linear-gradient(135deg, #1A3A1A, #2A6A2A)',
];
const icons = ['🐎', '🤠', '🏟️', '🎭', '🌟', '🎪'];

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/events');
        events.value = data.data || data;
    } catch (e) {
        console.error('Error cargando eventos:', e);
    } finally {
        loading.value = false;
    }
});

const featured = computed(() => events.value[0] || null);
const rest = computed(() => events.value.slice(1));

const totalSold = computed(() => {
    return events.value.reduce((sum, ev) => {
        return sum + (ev.zones || []).reduce((s, z) => s + z.sold_count, 0);
    }, 0);
});

const totalCapacity = (ev) => (ev.zones || []).reduce((s, z) => s + z.capacity, 0);
const totalSoldEv = (ev) => (ev.zones || []).reduce((s, z) => s + z.sold_count, 0);
const minPrice = (ev) => {
    const prices = (ev.zones || []).map(z => parseFloat(z.price));
    return prices.length ? Math.min(...prices) : 0;
};
const isSoldOut = (ev) => {
    const cap = totalCapacity(ev);
    return cap > 0 && totalSoldEv(ev) >= cap;
};
const fillPct = (ev) => {
    const cap = totalCapacity(ev);
    return cap > 0 ? ((totalSoldEv(ev) / cap) * 100).toFixed(0) : 0;
};
const formatDate = (d) => {
    if (!d) return '';
    const dt = new Date(d);
    return dt.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).toUpperCase();
};
const shortDate = (d) => {
    if (!d) return '';
    const dt = new Date(d);
    return dt.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }).toUpperCase();
};
const currency = (v) => `$${Number(v).toLocaleString('es-MX')}`;
</script>

<template>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <nav class="topbar">
        <a href="/" class="topbar-brand">Marca <span>MGR</span></a>
        <div class="topbar-nav">
            <a href="/" class="active">Inicio</a>
        </div>
    </nav>
    <div class="page-label">Portal Público — Eventos en vivo <div class="live-indicator"></div></div>

    <section class="hero">
        <div class="hero-badge">Plataforma Oficial de Charreada · México</div>
        <h1 class="hero-title">Marca <span>MGR</span></h1>
        <p class="hero-sub">Tu acceso a las mejores charreadas de México — compra segura en línea</p>
        <div class="search-bar">
            <input v-model="search" type="text" placeholder="Buscar evento, ciudad, lienzo charro...">
            <button>Buscar</button>
        </div>
        <div class="stats-bar">
            <div class="stat-item"><div class="stat-val">{{ events.length }}</div><div class="stat-label">Eventos activos</div></div>
            <div class="stat-item"><div class="stat-val">{{ totalSold.toLocaleString() }}</div><div class="stat-label">Tickets vendidos</div></div>
        </div>
    </section>

    <div v-if="loading" style="text-align:center;padding:60px;font-family:'DM Mono',monospace;color:var(--gris);letter-spacing:2px;">Cargando eventos...</div>

    <div v-else-if="events.length === 0" style="text-align:center;padding:60px;font-family:'DM Mono',monospace;color:var(--gris);letter-spacing:2px;">No hay eventos publicados aún.</div>

    <div v-else class="events-section">
        <template v-if="featured">
            <div class="section-header">
                <div class="section-title">Evento Destacado</div>
            </div>
            <div class="featured-card">
                <div>
                    <div class="featured-title">{{ featured.name }}</div>
                    <div class="featured-meta">📅 {{ formatDate(featured.starts_at) }} &nbsp;&nbsp; 📍 {{ featured.venue }}, {{ featured.city }}</div>
                    <div class="featured-tags" v-if="featured.description">
                        <div class="tag">{{ featured.status }}</div>
                        <div class="tag" v-for="zone in (featured.zones || [])" :key="zone.id">{{ zone.name }}</div>
                    </div>
                    <div class="capacity-bar-wrap">
                        <div class="capacity-label"><span>Disponibilidad</span><span>{{ totalSoldEv(featured).toLocaleString() }} / {{ totalCapacity(featured).toLocaleString() }} vendidos</span></div>
                        <div class="capacity-bar"><div class="capacity-fill" :style="{ width: fillPct(featured) + '%' }"></div></div>
                    </div>
                </div>
                <div class="featured-price">
                    <span class="featured-price-val">{{ currency(minPrice(featured)) }}</span>
                    <span class="featured-price-label">Desde</span>
                    <br><br>
                    <a :href="`/compra?event=${featured.id}`"><button class="btn-comprar" style="padding: 14px 28px; font-size: 12px;">Comprar Ahora →</button></a>
                </div>
            </div>
        </template>

        <div class="section-header" v-if="rest.length">
            <div class="section-title">Próximos Eventos</div>
        </div>
        <div class="events-grid">
            <div v-for="(ev, idx) in rest" :key="ev.id" class="event-card">
                <div class="card-img" :style="{ background: gradients[idx % gradients.length] }">
                    {{ icons[idx % icons.length] }}
                    <div class="card-date-badge">{{ shortDate(ev.starts_at) }}</div>
                    <div v-if="isSoldOut(ev)" class="card-sold-badge">AGOTADO</div>
                </div>
                <div class="card-body">
                    <div class="card-location">📍 {{ ev.city }}</div>
                    <div class="card-title">{{ ev.name }}</div>
                    <div class="card-desc">{{ ev.description || ev.venue }}</div>
                    <div class="card-footer">
                        <div class="price-tag"><small>Desde</small>{{ currency(minPrice(ev)) }}</div>
                        <a v-if="!isSoldOut(ev)" :href="`/compra?event=${ev.id}`"><button class="btn-comprar">Comprar</button></a>
                        <button v-else class="btn-agotado">Agotado</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-brand">Marca <span>MGR</span></div>
        <div class="footer-meta">
            <div class="footer-text">© 2025 Marca MGR · Todos los derechos reservados · México</div>
            <a href="/taquilla" class="footer-access">Acceso interno de taquilla</a>
        </div>
    </footer>
<div class="social-float">
    <a href="https://facebook.com" target="_blank" class="social-btn fb">
        <i class="bi bi-facebook"></i>
    </a>
    <a href="https://instagram.com" target="_blank" class="social-btn ig">
        <i class="bi bi-instagram"></i>
    </a>
</div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Baskerville:wght@400;700&family=DM+Mono:wght@400;500&display=swap');

:root{
--rojo:rgba(139,26,26,0.85);
--rojo-solid:#8B1A1A;
--dorado:rgba(217,164,65,0.85);
--dorado-strong:#D9A441;
--dorado-claro:#F6C56F;
--miel:#E2A85C;
--crema:#F5EFE0;
--cafe:#2B1606;
--cafe-glass:rgba(43,22,6,0.55);
--verde:#1A4A2E;
--gris:#7A6F63;
--blanco:#FDFAF4;
--bg:#140700;
}

*{margin:0;padding:0;box-sizing:border-box}

body{
font-family:'Libre Baskerville',serif;
background:
radial-gradient(circle at 20% 20%,rgba(217,164,65,0.06),transparent 45%),
radial-gradient(circle at 80% 60%,rgba(139,26,26,0.10),transparent 45%),
var(--bg);
color:var(--crema);
min-height:100vh;
}

.topbar{
background:rgba(139,26,26,0.55);
backdrop-filter:blur(18px);
border-bottom:1px solid rgba(255,255,255,0.06);
padding:14px 32px;
display:flex;
justify-content:space-between;
align-items:center;
}

.topbar-brand{
font-family:'Playfair Display',serif;
font-size:22px;
font-weight:900;
color:var(--crema);
text-decoration:none;
}

.topbar-brand span{color:var(--dorado-claro)}

.topbar-nav{display:flex;gap:6px;}

.topbar-nav a{
padding:8px 16px;
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:2px;
text-transform:uppercase;
color:rgb(245, 239, 224);
text-decoration:none;
border-radius:10px;
transition:all .3s ease;
}


.topbar-nav a.active{
color:white;
background:rgb(199, 12, 12);
backdrop-filter:blur(6px);
box-shadow:0 0 0 1px rgba(246,197,111,0.25) inset,0 4px 14px rgba(0,0,0,0.4);
}

.topbar-nav:hover{
color:white;
background:rgba(0, 0, 0, 0.74);
backdrop-filter:blur(6px);
box-shadow:0 0 0 1px rgba(246,197,111,0.25) inset,0 4px 14px rgba(0,0,0,0.4);
}

.page-label{
background:rgba(0,0,0,0.35);
backdrop-filter:blur(14px);
padding:10px 32px;
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:3px;
text-transform:uppercase;
display:flex;
align-items:center;
gap:10px;
border-bottom:1px solid rgba(217,164,65,0.15);
}

.hero{
padding:90px 40px 70px;
text-align:center;
position:relative;
overflow:hidden;
}

.hero::before{
content:'';
position:absolute;
inset:0;
background:
radial-gradient(circle at 30% 40%,rgba(217,164,65,0.10),transparent 60%),
radial-gradient(circle at 70% 60%,rgba(139,26,26,0.14),transparent 60%);
}

.hero-badge{
display:inline-block;
border:1px solid rgba(246,197,111,0.4);
background:rgba(217,164,65,0.06);
backdrop-filter:blur(8px);
padding:6px 22px;
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:4px;
text-transform:uppercase;
color:var(--miel);
margin-bottom:26px;
}

.hero-title{
font-family:'Playfair Display',serif;
font-size:clamp(44px,7vw,82px);
font-weight:900;
line-height:1.05;
margin-bottom:14px;
}

.hero-title span{
background:linear-gradient(90deg,var(--dorado-claro),var(--miel));
-webkit-background-clip:text;
color:transparent;
}

.hero-sub{
font-size:15px;
color:rgba(255,255,255,0.65);
margin-bottom:42px;
letter-spacing:1px;
}

.search-bar{
display:flex;
max-width:640px;
margin:0 auto 26px;
background:rgba(0,0,0,0.35);
backdrop-filter:blur(16px);
border:1px solid rgba(246,197,111,0.2);
border-radius:16px;
overflow:hidden;
transition:.3s;
}

.search-bar:focus-within{
border-color:rgba(246,197,111,0.5);
box-shadow:0 0 0 1px rgba(246,197,111,0.2);
}

.search-bar input{
flex:1;
background:none;
border:none;
padding:15px 20px;
color:var(--crema);
font-size:14px;
outline:none;
}

.search-bar input::placeholder{color:rgba(255,255,255,0.3)}

.search-bar button{
background:linear-gradient(135deg,var(--dorado-strong),var(--miel));
border:none;
padding:15px 28px;
color:#1a0d02;
font-family:'DM Mono',monospace;
font-size:11px;
letter-spacing:2px;
cursor:pointer;
text-transform:uppercase;
transition:.3s;
}

.search-bar button:hover{
filter:brightness(1.15) saturate(1.1);
transform:translateY(-1px);
}

.filter-chips{
display:flex;
gap:10px;
justify-content:center;
flex-wrap:wrap;
margin-bottom:20px;
}

.chip{
padding:7px 18px;
border-radius:999px;
background:rgba(217,164,65,0.06);
border:1px solid rgba(217,164,65,0.2);
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:1px;
text-transform:uppercase;
color:var(--miel);
cursor:pointer;
transition:.3s;
}

.chip:hover,
.chip.active{
background:linear-gradient(135deg,var(--dorado-strong),var(--miel));
color:#2b1606;
box-shadow:0 4px 14px rgba(0,0,0,0.4);
}

.stats-bar{
display:flex;
justify-content:center;
gap:50px;
padding:22px;
background:rgba(0,0,0,0.25);
backdrop-filter:blur(14px);
border-top:1px solid rgba(255,255,255,0.05);
border-bottom:1px solid rgba(255,255,255,0.05);
}

.stat-val{
font-family:'Playfair Display',serif;
font-size:30px;
background:linear-gradient(90deg,var(--dorado-claro),var(--miel));
-webkit-background-clip:text;
color:transparent;
}

.stat-label{
font-family:'DM Mono',monospace;
font-size:9px;
letter-spacing:2px;
color:var(--gris);
margin-top:4px;
}

.events-section{padding:48px 40px}

.section-header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:26px;
padding-bottom:14px;
border-bottom:1px solid rgba(217,164,65,0.15);
}

.section-title{
font-family:'Playfair Display',serif;
font-size:30px;
color:var(--dorado-claro);
}

.view-all{
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:2px;
text-transform:uppercase;
color:var(--miel);
text-decoration:none;
}

.events-grid{
display:grid;
grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
gap:26px;
}

.event-card{
background:var(--cafe-glass);
backdrop-filter:blur(18px);
border:1px solid rgba(255,255,255,0.05);
border-radius:18px;
overflow:hidden;
transition:.35s;
}

.event-card:hover{
transform:translateY(-8px) scale(1.02);
border-color:rgba(246,197,111,0.35);
box-shadow:0 25px 50px rgba(0,0,0,0.6);
}

.card-img{
height:150px;
display:flex;
align-items:center;
justify-content:center;
font-size:60px;
position:relative;
}

.card-date-badge{
position:absolute;
top:12px;
right:12px;
background:linear-gradient(135deg,var(--dorado-strong),var(--miel));
color:#2b1606;
font-family:'DM Mono',monospace;
font-size:10px;
padding:5px 12px;
border-radius:6px;
}

.card-body{padding:22px}

.card-location{
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:2px;
color:var(--miel);
margin-bottom:6px;
}

.card-title{
font-family:'Playfair Display',serif;
font-size:19px;
margin-bottom:8px;
}

.card-desc{
font-size:13px;
color:var(--gris);
margin-bottom:16px;
}

.card-footer{
display:flex;
justify-content:space-between;
align-items:center;
padding-top:14px;
border-top:1px solid rgba(255,255,255,0.05);
}

.price-tag{
font-family:'Playfair Display',serif;
font-size:26px;
color:var(--dorado-claro);
}

.btn-comprar{
background:linear-gradient(135deg,var(--rojo-solid),#a52a2a);
border:none;
padding:10px 18px;
color:white;
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:2px;
border-radius:10px;
cursor:pointer;
transition:.3s;
}

.btn-comprar:hover{
filter:brightness(1.2) saturate(1.1);
transform:translateY(-2px);
box-shadow:0 8px 20px rgba(0,0,0,0.5);
}

.site-footer{
background:rgba(0,0,0,0.5);
backdrop-filter:blur(16px);
border-top:1px solid rgba(255,255,255,0.06);
padding:32px 40px;
display:flex;
justify-content:space-between;
flex-wrap:wrap;
}

.footer-brand{
font-family:'Playfair Display',serif;
font-size:18px;
}

.footer-brand span{color:var(--miel)}

.footer-text{
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:1px;
color:rgba(255,255,255,0.55);
}

.live-indicator{
width:10px;
height:10px;
border-radius:50%;
background:#ff2b2b;
box-shadow:0 0 14px rgba(255,0,0,0.9);
animation:livePulse 1.5s infinite;
}

@keyframes livePulse{
0%{transform:scale(1)}
50%{transform:scale(1.25)}
100%{transform:scale(1)}
}
.social-float{
    position:fixed;
    right:20px;
    bottom:20px;
    display:flex;
    flex-direction:column;
    gap:12px;
    z-index:999;
}

.social-btn{
    width:46px;
    height:46px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:14px;
    font-size:18px;
    text-decoration:none;
    font-family:'DM Mono', monospace;
    color:white;
    backdrop-filter:blur(14px);
    border:1px solid rgba(255,255,255,0.1);
    box-shadow:0 10px 25px rgba(0,0,0,0.5);
    transition:all .3s ease;
}

.social-btn.fb{
    background:linear-gradient(135deg,#1877f2,#0d5bd3);
}


.social-btn.ig{
    background:linear-gradient(135deg,#f58529,#dd2a7b,#8134af,#515bd4);
}

.social-btn:hover{
    transform:translateY(-4px) scale(1.08);
    box-shadow:0 14px 30px rgba(0,0,0,0.7);
}
</style>