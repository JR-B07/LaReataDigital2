<script setup>
import { computed, onMounted, ref } from 'vue';

const token = ref(localStorage.getItem('auth_token') || '');
const user = ref(null);
const loginName = ref('');
const loginPass = ref('');
const loginError = ref('');
const loggingIn = ref(false);
const allowedRoles = ['seller', 'admin'];

const shortcuts = [
    {
        title: 'Venta en mostrador',
        text: 'Acceso directo para vendedores de taquilla con el mismo inventario en tiempo real.',
    },
    {
        title: 'Corte rápido',
        text: 'Los vendedores entran por aquí y continúan al panel existente sin tocar la portada pública.',
    },
    {
        title: 'Operación separada',
        text: 'La identidad visual distingue la operación de escritorio del sitio de compra en línea.',
    },
    {
        title: 'POS de barra',
        text: 'Incluye punto de venta para alcohol durante el evento desde un flujo separado.',
    },
];

const ax = () => {
    const instance = window.axios.create();
    if (token.value) {
        instance.defaults.headers.common.Authorization = `Bearer ${token.value}`;
    }

    return instance;
};

const roleLabel = computed(() => {
    return {
        seller: 'Vendedor',
        admin: 'Administrador',
        validator: 'Validador',
    }[user.value?.role] || 'Operador';
});

const hasAllowedRole = (role) => allowedRoles.includes(role);

const redirectByRole = (role) => {
    if (role === 'validator') {
        window.location.href = '/validador';
        return;
    }

    window.location.href = '/vendedor';
};

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
        redirectByRole(data.user?.role);
    } catch (error) {
        loginError.value = error.response?.data?.message || 'No fue posible iniciar sesión.';
    } finally {
        loggingIn.value = false;
    }
};

const doLogout = async () => {
    try {
        if (token.value) {
            await ax().post('/api/auth/logout');
        }
    } catch {
        // Sin acción: el cierre local es suficiente si la sesión remota ya expiró.
    }

    token.value = '';
    user.value = null;
    localStorage.removeItem('auth_token');
};

const clearInvalidSession = () => {
    token.value = '';
    user.value = null;
    localStorage.removeItem('auth_token');
};

onMounted(async () => {
    if (!token.value) {
        return;
    }

    try {
        const { data } = await ax().get('/api/auth/me');

        if (!hasAllowedRole(data?.role)) {
            clearInvalidSession();
            loginError.value = 'Esta cuenta no tiene acceso a taquilla.';
            return;
        }

        user.value = data;
    } catch {
        await doLogout();
    }
});
</script>

<template>
    <div class="taquilla-shell">
        <aside class="taquilla-aside">
            <a href="/" class="taquilla-brand">Marca <span>MGR</span></a>
            <div class="taquilla-kicker">Acceso operativo</div>
            <h1 class="taquilla-title">Punto de venta de taquilla</h1>
            <p class="taquilla-copy">
                Esta entrada separa la operación presencial del portal público y dirige a vendedores y administradores al flujo interno.
            </p>

            <div class="taquilla-shortcuts">
                <article v-for="item in shortcuts" :key="item.title" class="shortcut-card">
                    <div class="shortcut-title">{{ item.title }}</div>
                    <p>{{ item.text }}</p>
                </article>
            </div>

            <div class="taquilla-links">
                <a href="/">Volver al portal público</a>
                <a href="/barra">Ir a POS barra</a>
                <a href="/validador">Acceso de validador</a>
            </div>
        </aside>

        <main class="taquilla-main">
            <section class="access-panel" v-if="!user">
                <div class="panel-badge">Solo personal autorizado</div>
                <h2>Ingreso de taquilla</h2>
                <p>
                    Usa tu usuario operativo. Si la cuenta corresponde a validación, el sistema la enviará automáticamente al módulo correcto.
                </p>

                <label class="field-label" for="loginName">Usuario o correo</label>
                <input id="loginName" v-model="loginName" class="field-input" type="text" autocomplete="username" @keyup.enter="doLogin">

                <label class="field-label" for="loginPass">Contraseña</label>
                <input id="loginPass" v-model="loginPass" class="field-input" type="password" autocomplete="current-password" @keyup.enter="doLogin">

                <div v-if="loginError" class="field-error">{{ loginError }}</div>

                <button class="primary-action" type="button" :disabled="loggingIn" @click="doLogin">
                    {{ loggingIn ? 'Validando acceso...' : 'Entrar a taquilla' }}
                </button>
            </section>

            <section class="access-panel" v-else>
                <div class="panel-badge">Sesión detectada</div>
                <h2>Acceso ya iniciado</h2>
                <p>
                    {{ user.name }} · {{ roleLabel }}
                </p>

                <div class="session-actions">
                    <button class="primary-action" type="button" @click="redirectByRole(user.role)">
                        Continuar al panel
                    </button>
                    <button class="secondary-action" type="button" @click="window.location.href = '/barra'">
                        Abrir POS Barra
                    </button>
                    <button class="secondary-action" type="button" @click="doLogout">
                        Cerrar sesión
                    </button>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Baskerville:wght@400;700&family=DM+Mono:wght@400;500&display=swap');

:global(body) {
    margin: 0;
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(200, 146, 42, 0.16), transparent 32%),
        linear-gradient(135deg, #120701 0%, #251105 46%, #4a1d08 100%);
    color: #f5efe0;
    font-family: 'Libre Baskerville', serif;
}

.taquilla-shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(320px, 460px) 1fr;
}

.taquilla-aside {
    padding: 56px 40px;
    background: rgba(17, 7, 1, 0.86);
    border-right: 1px solid rgba(240, 192, 96, 0.18);
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
.shortcut-title,
.taquilla-links a,
.panel-badge,
.field-label,
.primary-action,
.secondary-action {
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

.taquilla-shortcuts {
    display: grid;
    gap: 14px;
}

.shortcut-card {
    padding: 18px;
    border: 1px solid rgba(240, 192, 96, 0.16);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.02));
}

.shortcut-title {
    margin-bottom: 8px;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #f0c060;
}

.shortcut-card p {
    margin: 0;
    color: rgba(245, 239, 224, 0.72);
    line-height: 1.6;
    font-size: 14px;
}

.taquilla-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: auto;
}

.taquilla-links a {
    color: #f5efe0;
    text-decoration: none;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    padding-bottom: 4px;
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
    line-height: 1.05;
}

.access-panel p {
    margin: 0 0 24px;
    line-height: 1.7;
    color: rgba(47, 25, 8, 0.72);
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
    margin-bottom: 8px;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #6b6055;
}

.field-input {
    width: 100%;
    margin-bottom: 18px;
    padding: 14px 16px;
    border: 1px solid rgba(61, 32, 8, 0.18);
    background: #fffdf8;
    color: #2f1908;
    font-size: 15px;
    font-family: 'Libre Baskerville', serif;
    outline: none;
}

.field-input:focus {
    border-color: rgba(139, 26, 26, 0.6);
    box-shadow: 0 0 0 3px rgba(139, 26, 26, 0.08);
}

.field-error {
    margin-bottom: 16px;
    color: #b11d1d;
    font-size: 13px;
}

.primary-action,
.secondary-action {
    border: none;
    padding: 14px 18px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-size: 11px;
    cursor: pointer;
}

.primary-action {
    width: 100%;
    background: linear-gradient(135deg, #8b1a1a, #ba4b2a);
    color: #fdfaf4;
}

.primary-action:disabled {
    opacity: 0.7;
    cursor: wait;
}

.session-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.session-actions .primary-action,
.session-actions .secondary-action {
    width: auto;
    min-width: 180px;
}

.secondary-action {
    background: #f2e9d8;
    color: #3d2008;
    border: 1px solid rgba(61, 32, 8, 0.14);
}

@media (max-width: 920px) {
    .taquilla-shell {
        grid-template-columns: 1fr;
    }

    .taquilla-aside {
        border-right: 0;
        border-bottom: 1px solid rgba(240, 192, 96, 0.18);
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

    .session-actions .primary-action,
    .session-actions .secondary-action {
        width: 100%;
    }
}
</style>
