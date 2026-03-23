<script setup>
import { ref } from 'vue';

const code = ref('');
const loading = ref(false);
const error = ref('');
const ticket = ref(null);

const currency = (v) => `$${Number(v || 0).toLocaleString('es-MX')}`;
const formatDate = (d) => {
    if (!d) return 'Por definir';
    return new Date(d).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    }).toUpperCase();
};

const searchTicket = async () => {
    const clean = code.value.trim();
    if (!clean) return;

    loading.value = true;
    error.value = '';
    ticket.value = null;

    try {
        const { data } = await window.axios.get(`/api/tickets/${encodeURIComponent(clean)}`);
        ticket.value = data;
    } catch {
        error.value = 'No encontramos ese ticket. Revisa el codigo e intenta nuevamente.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Charro<span>Tickets</span></a>
        <div class="topbar-nav">
            <a href="/">Inicio</a>
            <a href="/vendedor">Iniciar sesion</a>
        </div>
    </nav>

    <div class="page-label">Impresion de Ticket</div>

    <section class="hero" style="padding-top:40px;padding-bottom:32px;">
        <div class="hero-badge">Acceso rapido sin cuenta</div>
        <h1 class="hero-title" style="font-size:44px;">Consulta e Imprime tu Ticket</h1>
        <p class="hero-sub">Ingresa tu codigo de ticket para descargar el PDF oficial.</p>

        <div class="search-bar" style="max-width:760px;">
            <input v-model="code" type="text" placeholder="Ej. CHT-2026-03-08-00001" @keyup.enter="searchTicket">
            <button @click="searchTicket" :disabled="loading">{{ loading ? 'Buscando...' : 'Buscar' }}</button>
        </div>
    </section>

    <div class="events-section" style="padding-top:0;">
        <div v-if="error" style="max-width:760px;margin:0 auto 20px;border:1px solid rgba(244,67,54,0.4);background:rgba(244,67,54,0.08);padding:14px 16px;color:#ffb3b3;font-family:'DM Mono',monospace;font-size:11px;letter-spacing:1px;">{{ error }}</div>

        <div v-if="ticket" class="featured-card" style="max-width:760px;margin:0 auto;">
            <div>
                <div class="featured-title">{{ ticket.event?.name }}</div>
                <div class="featured-meta">{{ formatDate(ticket.event?.starts_at) }} · {{ ticket.event?.venue }}, {{ ticket.event?.city }}</div>
                <div class="featured-tags" style="margin-top:14px;">
                    <div class="tag">Codigo: {{ ticket.ticket_code }}</div>
                    <div class="tag">Zona: {{ ticket.zone?.name || 'General' }}</div>
                    <div class="tag">Estado: {{ ticket.status }}</div>
                </div>
                <div class="featured-meta" style="margin-top:14px;">Comprador: {{ ticket.order?.buyer_name }} · {{ ticket.order?.buyer_email }}</div>
            </div>
            <div class="featured-price">
                <span class="featured-price-val">{{ currency(ticket.item?.unit_price || 0) }}</span>
                <span class="featured-price-label">Precio</span>
                <br><br>
                <a :href="`/api/tickets/${ticket.ticket_code}/pdf`" target="_blank">
                    <button class="btn-comprar" style="padding: 14px 24px; font-size: 12px;">Descargar PDF</button>
                </a>
            </div>
        </div>
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

.topbar-nav a:hover{
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
position:relative;
}

.hero-title{
font-family:'Playfair Display',serif;
font-size:clamp(44px,7vw,82px);
font-weight:900;
line-height:1.05;
margin-bottom:14px;
position:relative;
}

.hero-sub{
font-size:15px;
color:rgba(255,255,255,0.65);
margin-bottom:42px;
letter-spacing:1px;
position:relative;
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
position:relative;
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

.events-section{padding:48px 40px}

.featured-card{
background:var(--cafe-glass);
backdrop-filter:blur(18px);
border:1px solid rgba(255,255,255,0.08);
border-radius:18px;
padding:32px;
display:flex;
justify-content:space-between;
align-items:flex-start;
gap:24px;
transition:.35s;
}

.featured-card:hover{
border-color:rgba(246,197,111,0.35);
box-shadow:0 25px 50px rgba(0,0,0,0.6);
}

.featured-title{
font-family:'Playfair Display',serif;
font-size:26px;
font-weight:900;
margin-bottom:10px;
}

.featured-meta{
font-family:'DM Mono',monospace;
font-size:11px;
letter-spacing:1px;
color:var(--gris);
}

.featured-tags{
display:flex;
flex-wrap:wrap;
gap:8px;
}

.tag{
padding:5px 14px;
border-radius:999px;
background:rgba(217,164,65,0.08);
border:1px solid rgba(217,164,65,0.2);
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:1px;
text-transform:uppercase;
color:var(--miel);
}

.featured-price{
text-align:right;
flex-shrink:0;
}

.featured-price-val{
display:block;
font-family:'Playfair Display',serif;
font-size:32px;
color:var(--dorado-claro);
}

.featured-price-label{
font-family:'DM Mono',monospace;
font-size:10px;
letter-spacing:2px;
color:var(--gris);
text-transform:uppercase;
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
</style>
