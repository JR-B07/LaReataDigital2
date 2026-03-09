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
