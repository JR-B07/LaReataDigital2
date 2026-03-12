<script setup>
import { computed, onMounted, ref } from 'vue';

const token = ref(localStorage.getItem('auth_token') || '');
const user = ref(null);
const loginName = ref('');
const loginPass = ref('');
const loginError = ref('');
const loggingIn = ref(false);
const loading = ref(false);
const processing = ref(false);
const processingCut = ref(false);
const allowedRoles = ['seller', 'admin'];

const events = ref([]);
const products = ref([]);
const recentSales = ref([]);
const selectedEventId = ref(null);
const paymentMethod = ref('cash');
const notes = ref('');
const cutOpenNotes = ref('');
const cutCloseNotes = ref('');
const openingCash = ref('0');
const closingCash = ref('0');
const search = ref('');
const statusFilter = ref('all');
const minPrice = ref('');
const maxPrice = ref('');
const successMessage = ref('');
const cutMessage = ref('');
const currentCut = ref(null);
const cutHistory = ref([]);
const globalSummary = ref(null);
const inventoryMessage = ref('');
const inventoryForm = ref({
    id: null,
    nombre: '',
    precio: '',
    stock: '',
    activo: true,
});

const cart = ref({});

const ax = () => {
    const instance = window.axios.create();
    if (token.value) {
        instance.defaults.headers.common.Authorization = `Bearer ${token.value}`;
    }

    return instance;
};

const hasAllowedRole = (role) => allowedRoles.includes(role);
const isAdmin = computed(() => user.value?.role === 'admin');
const apiPrefix = computed(() => (isAdmin.value ? '/api/admin' : '/api/seller'));
const pageLabel = computed(() => (isAdmin.value ? 'POS Barra - Administracion' : 'POS Barra - Operacion'));
const hasOpenCut = computed(() => !!currentCut.value && currentCut.value.estado === 'abierto');
const expectedCash = computed(() => Number(currentCut.value?.monto_efectivo_esperado || 0));
const globalTotals = computed(() => globalSummary.value?.totals || null);
const globalSales = computed(() => globalSummary.value?.sales || null);
const globalOperators = computed(() => globalSummary.value?.operators || []);

const filteredProducts = computed(() => {
    const q = search.value.trim().toLowerCase();
    const min = minPrice.value === '' ? null : Number(minPrice.value);
    const max = maxPrice.value === '' ? null : Number(maxPrice.value);

    return products.value.filter((p) => {
        const nameMatch = !q || p.nombre.toLowerCase().includes(q);
        const price = Number(p.precio || 0);

        const minOk = min === null || (!Number.isNaN(min) && price >= min);
        const maxOk = max === null || (!Number.isNaN(max) && price <= max);

        const statusOk = (() => {
            if (statusFilter.value === 'active') return Boolean(p.activo) && Number(p.stock) > 0;
            if (statusFilter.value === 'inactive') return !Boolean(p.activo);
            if (statusFilter.value === 'out') return Number(p.stock) <= 0;
            return true;
        })();

        return nameMatch && minOk && maxOk && statusOk;
    });
});

const clearFilters = () => {
    search.value = '';
    statusFilter.value = 'all';
    minPrice.value = '';
    maxPrice.value = '';
};

const cartItems = computed(() => {
    return products.value
        .map((product) => ({
            ...product,
            quantity: cart.value[product.id] || 0,
        }))
        .filter((product) => product.quantity > 0);
});

const cartTotal = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + Number(item.precio) * item.quantity, 0);
});

const cartCount = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + item.quantity, 0);
});

const resetSaleForm = () => {
    cart.value = {};
    paymentMethod.value = 'cash';
    notes.value = '';
};

const resetInventoryForm = () => {
    inventoryForm.value = {
        id: null,
        nombre: '',
        precio: '',
        stock: '',
        activo: true,
    };
};

const paymentLabel = (value) => {
    return {
        efectivo: 'Efectivo',
        tarjeta: 'Tarjeta',
        transferencia: 'Transferencia',
    }[value] || value;
};

const formatCurrency = (value) => `$${Number(value || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatDateTime = (value) => new Date(value).toLocaleString('es-MX');

const addProduct = (product) => {
    const current = cart.value[product.id] || 0;
    if (current >= product.stock) return;
    cart.value[product.id] = current + 1;
};

const removeProduct = (product) => {
    const current = cart.value[product.id] || 0;
    if (current <= 1) {
        delete cart.value[product.id];
        return;
    }

    cart.value[product.id] = current - 1;
};

const updateStock = async (product, nextStock) => {
    if (!isAdmin.value) return;

    const stock = Number(nextStock);
    if (Number.isNaN(stock) || stock < 0) return;

    try {
        await ax().patch(`${apiPrefix.value}/bar/products/${product.id}/stock`, { stock });
        await loadProducts();
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo actualizar stock.');
    }
};

const createProduct = async () => {
    if (!isAdmin.value) return;
    resetInventoryForm();
};

const editProduct = (product) => {
    inventoryForm.value = {
        id: product.id,
        nombre: product.nombre,
        precio: String(product.precio ?? ''),
        stock: String(product.stock ?? ''),
        activo: Boolean(product.activo),
    };
};

const submitInventoryForm = async () => {
    if (!isAdmin.value) return;

    inventoryMessage.value = '';

    const payload = {
        nombre: inventoryForm.value.nombre?.trim(),
        precio: Number(inventoryForm.value.precio),
        stock: Number(inventoryForm.value.stock),
        activo: Boolean(inventoryForm.value.activo),
    };

    if (!payload.nombre || Number.isNaN(payload.precio) || payload.precio < 0 || Number.isNaN(payload.stock) || payload.stock < 0) {
        alert('Completa nombre, precio y stock con valores válidos.');
        return;
    }

    try {
        if (inventoryForm.value.id) {
            await ax().put(`${apiPrefix.value}/bar/products/${inventoryForm.value.id}`, payload);
            inventoryMessage.value = 'Producto actualizado.';
        } else {
            await ax().post(`${apiPrefix.value}/bar/products`, payload);
            inventoryMessage.value = 'Producto agregado.';
        }

        resetInventoryForm();
        await loadProducts();
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo guardar el producto.');
    }
};

const deleteProduct = async (product) => {
    if (!isAdmin.value) return;
    if (!window.confirm(`¿Eliminar ${product.nombre}?`)) return;

    try {
        const { data } = await ax().delete(`${apiPrefix.value}/bar/products/${product.id}`);
        inventoryMessage.value = data?.message || 'Producto eliminado.';
        if (inventoryForm.value.id === product.id) {
            resetInventoryForm();
        }
        await loadProducts();
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo eliminar el producto.');
    }
};

const submitSale = async () => {
    successMessage.value = '';
    cutMessage.value = '';

    if (!hasOpenCut.value) {
        alert('Debes abrir corte de caja antes de registrar ventas.');
        return;
    }

    if (!selectedEventId.value) {
        alert('Selecciona un evento para registrar la venta.');
        return;
    }

    if (cartItems.value.length === 0) {
        alert('Agrega al menos un producto.');
        return;
    }

    processing.value = true;

    try {
        await ax().post(`${apiPrefix.value}/bar/sales`, {
            event_id: selectedEventId.value,
            payment_method: paymentMethod.value,
            notes: notes.value || null,
            items: cartItems.value.map((item) => ({
                product_id: item.id,
                quantity: item.quantity,
            })),
        });

        successMessage.value = 'Venta registrada correctamente.';
        resetSaleForm();
        await Promise.all([loadProducts(), loadRecentSales()]);
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo registrar la venta.');
    } finally {
        processing.value = false;
    }
};

const loadEvents = async () => {
    const { data } = await ax().get(`${apiPrefix.value}/events`);
    const rows = data.data || data;
    events.value = rows.filter((ev) => ev.status === 'published' || ev.status === 'activo' || ev.status === 'active');

    if (!selectedEventId.value && events.value.length > 0) {
        selectedEventId.value = events.value[0].id;
    }
};

const loadProducts = async () => {
    const { data } = await ax().get(`${apiPrefix.value}/bar/products`, {
        params: {
            include_inactive: isAdmin.value ? 1 : 0,
        },
    });
    products.value = data;
};

const loadRecentSales = async () => {
    const { data } = await ax().get(`${apiPrefix.value}/bar/sales/recent`, {
        params: {
            event_id: selectedEventId.value || undefined,
        },
    });
    recentSales.value = data;
};

const loadCurrentCut = async () => {
    if (!selectedEventId.value) {
        currentCut.value = null;
        return;
    }

    const { data } = await ax().get(`${apiPrefix.value}/bar/cuts/current`, {
        params: {
            event_id: selectedEventId.value,
        },
    });

    currentCut.value = data || null;
};

const loadCutHistory = async () => {
    const { data } = await ax().get(`${apiPrefix.value}/bar/cuts/history`, {
        params: {
            event_id: selectedEventId.value || undefined,
        },
    });

    cutHistory.value = data;
};

const loadGlobalSummary = async () => {
    if (!isAdmin.value || !selectedEventId.value) {
        globalSummary.value = null;
        return;
    }

    const { data } = await ax().get(`${apiPrefix.value}/bar/cuts/global-summary`, {
        params: {
            event_id: selectedEventId.value,
        },
    });

    globalSummary.value = data;
};

const openCut = async () => {
    cutMessage.value = '';

    if (!selectedEventId.value) {
        alert('Selecciona un evento antes de abrir corte.');
        return;
    }

    const value = Number(openingCash.value);
    if (Number.isNaN(value) || value < 0) {
        alert('Monto de apertura inválido.');
        return;
    }

    processingCut.value = true;
    try {
        const { data } = await ax().post(`${apiPrefix.value}/bar/cuts/open`, {
            event_id: selectedEventId.value,
            opening_cash: value,
            notes: cutOpenNotes.value || null,
        });

        currentCut.value = data;
        closingCash.value = String(data.monto_efectivo_esperado || value);
        cutOpenNotes.value = '';
        cutMessage.value = 'Corte abierto correctamente.';
        await loadCutHistory();
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo abrir corte.');
    } finally {
        processingCut.value = false;
    }
};

const closeCut = async () => {
    if (!currentCut.value?.id) {
        alert('No hay corte abierto para cerrar.');
        return;
    }

    const value = Number(closingCash.value);
    if (Number.isNaN(value) || value < 0) {
        alert('Monto de cierre inválido.');
        return;
    }

    processingCut.value = true;
    try {
        const { data } = await ax().post(`${apiPrefix.value}/bar/cuts/close`, {
            cut_id: currentCut.value.id,
            closing_cash: value,
            notes: cutCloseNotes.value || null,
        });

        cutMessage.value = `Corte cerrado. Diferencia: ${formatCurrency(data.summary?.difference || 0)}`;
        currentCut.value = null;
        cutCloseNotes.value = '';
        await Promise.all([loadCutHistory(), loadRecentSales()]);
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo cerrar corte.');
    } finally {
        processingCut.value = false;
    }
};

const onEventChange = async () => {
    await Promise.all([loadCurrentCut(), loadRecentSales(), loadCutHistory(), loadGlobalSummary()]);
};

const loadDashboard = async () => {
    loading.value = true;
    try {
        await loadEvents();
        await Promise.all([loadProducts(), loadCurrentCut(), loadRecentSales(), loadCutHistory(), loadGlobalSummary()]);
    } finally {
        loading.value = false;
    }
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
        await loadDashboard();
    } catch (error) {
        loginError.value = error.response?.data?.message || 'Credenciales inválidas.';
    } finally {
        loggingIn.value = false;
    }
};

const doLogout = async () => {
    try {
        if (token.value) await ax().post('/api/auth/logout');
    } catch {
        // Ignorar error de cierre remoto y limpiar sesion local.
    }

    token.value = '';
    user.value = null;
    localStorage.removeItem('auth_token');
    window.location.href = '/';
};

onMounted(async () => {
    if (!token.value) return;

    try {
        const { data } = await ax().get('/api/auth/me');
        if (!hasAllowedRole(data?.role)) {
            localStorage.removeItem('auth_token');
            token.value = '';
            loginError.value = 'Esta cuenta no tiene acceso a POS Barra.';
            return;
        }

        user.value = data;
        await loadDashboard();
    } catch {
        await doLogout();
    }
});
</script>

<template>
    <nav class="topbar">
        <a href="/" class="topbar-brand">Marca <span>MGR</span></a>
        <div class="topbar-nav">
            <a href="/vendedor">Panel</a>
            <a href="/barra" class="active">POS Barra</a>
            <a v-if="token" href="#" @click.prevent="doLogout">Cerrar Sesion</a>
        </div>
    </nav>

    <div class="page-label">{{ pageLabel }}</div>

    <div v-if="!token" class="login-wrap">
        <div class="login-card">
            <div class="login-icon">🍻</div>
            <h1>Punto de Venta de Alcohol</h1>
            <p>Acceso para vendedores y administradores durante el evento.</p>
            <input v-model="loginName" type="text" placeholder="Usuario" @keyup.enter="doLogin">
            <input v-model="loginPass" type="password" placeholder="Contraseña" @keyup.enter="doLogin">
            <div v-if="loginError" class="login-error">{{ loginError }}</div>
            <button :disabled="loggingIn" @click="doLogin">{{ loggingIn ? 'INGRESANDO...' : 'INGRESAR' }}</button>
        </div>
    </div>

    <main v-else class="layout">
        <section class="products-panel">
            <div class="panel-head">
                <h2>Catalogo de Bebidas</h2>
                <button v-if="isAdmin" class="ghost-btn" @click="createProduct">Nuevo</button>
            </div>

            <div v-if="isAdmin" class="inventory-box">
                <div class="inventory-title">Inventario de Alcohol</div>
                <div class="inventory-form">
                    <input v-model="inventoryForm.nombre" type="text" placeholder="Nombre del producto">
                    <input v-model="inventoryForm.precio" type="number" min="0" step="0.01" placeholder="Precio">
                    <input v-model="inventoryForm.stock" type="number" min="0" step="1" placeholder="Stock">
                    <label class="inventory-check">
                        <input v-model="inventoryForm.activo" type="checkbox">
                        Activo
                    </label>
                    <button class="ghost-btn" @click="submitInventoryForm">{{ inventoryForm.id ? 'Guardar cambios' : 'Agregar producto' }}</button>
                    <button class="ghost-btn" @click="resetInventoryForm">Limpiar</button>
                </div>
                <div v-if="inventoryMessage" class="ok-msg">{{ inventoryMessage }}</div>
            </div>

            <div class="controls-row">
                <select v-model="selectedEventId" @change="onEventChange">
                    <option :value="null" disabled>Selecciona evento</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.name }}</option>
                </select>
                <input v-model="search" type="text" placeholder="Buscar bebida...">
            </div>

            <div class="filters-row">
                <select v-model="statusFilter">
                    <option value="all">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                    <option value="out">Sin stock</option>
                </select>
                <input v-model="minPrice" type="number" min="0" step="0.01" placeholder="Precio min">
                <input v-model="maxPrice" type="number" min="0" step="0.01" placeholder="Precio max">
                <button class="ghost-btn" @click="clearFilters">Limpiar filtros</button>
            </div>

            <div v-if="loading" class="state-msg">Cargando...</div>
            <div v-else class="products-grid">
                <article v-for="product in filteredProducts" :key="product.id" class="product-card">
                    <div class="product-row">
                        <div>
                            <div class="product-name">{{ product.nombre }}</div>
                            <div class="product-price">{{ formatCurrency(product.precio) }}</div>
                        </div>
                        <div class="stock-chip">Stock: {{ product.stock }}</div>
                    </div>

                    <div class="qty-row">
                        <button @click="removeProduct(product)">-</button>
                        <div>{{ cart[product.id] || 0 }}</div>
                        <button @click="addProduct(product)">+</button>
                    </div>

                    <div v-if="isAdmin" class="stock-row">
                        <input :value="product.stock" type="number" min="0" @change="updateStock(product, $event.target.value)">
                        <span>Stock rapido</span>
                    </div>

                    <div v-if="isAdmin" class="inventory-actions">
                        <button class="ghost-btn" @click="editProduct(product)">Editar</button>
                        <button class="ghost-btn danger-btn" @click="deleteProduct(product)">Eliminar</button>
                    </div>
                </article>
            </div>
        </section>

        <section class="checkout-panel">
            <h2>Cobro rapido</h2>

            <div class="cut-box">
                <div class="cut-head">
                    <strong>Corte de caja</strong>
                    <span v-if="hasOpenCut" class="cut-state open">ABIERTO</span>
                    <span v-else class="cut-state closed">CERRADO</span>
                </div>

                <div v-if="hasOpenCut" class="cut-grid">
                    <div class="cut-metric">
                        <span>Efectivo esperado</span>
                        <strong>{{ formatCurrency(expectedCash) }}</strong>
                    </div>
                    <label>Monto real al cierre</label>
                    <input v-model="closingCash" type="number" min="0" step="0.01" placeholder="0.00">
                    <label>Notas cierre</label>
                    <input v-model="cutCloseNotes" type="text" placeholder="Observaciones de cierre">
                    <button class="ghost-btn" :disabled="processingCut" @click="closeCut">{{ processingCut ? 'CERRANDO...' : 'Cerrar corte' }}</button>
                </div>

                <div v-else class="cut-grid">
                    <label>Fondo inicial</label>
                    <input v-model="openingCash" type="number" min="0" step="0.01" placeholder="0.00">
                    <label>Notas apertura</label>
                    <input v-model="cutOpenNotes" type="text" placeholder="Turno, caja o referencia">
                    <button class="ghost-btn" :disabled="processingCut" @click="openCut">{{ processingCut ? 'ABRIENDO...' : 'Abrir corte' }}</button>
                </div>

                <div v-if="cutMessage" class="ok-msg">{{ cutMessage }}</div>
            </div>

            <div class="checkout-total">{{ formatCurrency(cartTotal) }}</div>
            <div class="checkout-count">{{ cartCount }} articulos</div>

            <label>Metodo de pago</label>
            <select v-model="paymentMethod">
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
            </select>

            <label>Notas</label>
            <input v-model="notes" type="text" placeholder="Mesa, referencia, etc.">

            <button class="pay-btn" :disabled="processing" @click="submitSale">
                {{ processing ? 'REGISTRANDO...' : 'Registrar venta' }}
            </button>

            <div v-if="successMessage" class="ok-msg">{{ successMessage }}</div>

            <h3>Detalle</h3>
            <div class="cart-list">
                <div v-for="item in cartItems" :key="item.id" class="cart-row">
                    <span>{{ item.nombre }} x{{ item.quantity }}</span>
                    <strong>{{ formatCurrency(item.quantity * item.precio) }}</strong>
                </div>
                <div v-if="cartItems.length === 0" class="state-msg">Sin productos en carrito.</div>
            </div>
        </section>

        <section class="sales-panel">
            <h2>Ventas recientes</h2>
            <div class="sales-list">
                <div v-for="sale in recentSales" :key="sale.id" class="sale-row">
                    <div>
                        <div class="sale-title">Venta #{{ sale.id }} · {{ sale.evento }}</div>
                        <div class="sale-meta">{{ sale.vendedor || 'Operador' }} · {{ paymentLabel(sale.metodo_pago) }} · {{ formatDateTime(sale.created_at) }}</div>
                    </div>
                    <strong>{{ formatCurrency(sale.total) }}</strong>
                </div>
                <div v-if="recentSales.length === 0" class="state-msg">Sin ventas registradas.</div>
            </div>

            <h2 style="margin-top: 16px;">Cortes recientes</h2>
            <div class="sales-list">
                <div v-for="cut in cutHistory" :key="`cut-${cut.id}`" class="sale-row">
                    <div>
                        <div class="sale-title">Corte #{{ cut.id }} · {{ cut.evento }}</div>
                        <div class="sale-meta">{{ cut.operador || 'Operador' }} · {{ cut.estado.toUpperCase() }} · {{ formatDateTime(cut.abierto_en) }}</div>
                    </div>
                    <strong>{{ formatCurrency(cut.monto_efectivo_esperado) }}</strong>
                </div>
                <div v-if="cutHistory.length === 0" class="state-msg">Sin cortes registrados.</div>
            </div>

            <template v-if="isAdmin && globalTotals && globalSales">
                <h2 style="margin-top: 16px;">Consolidado del Evento</h2>
                <div class="global-grid">
                    <div class="global-card">
                        <div class="global-label">Ventas Totales</div>
                        <div class="global-value">{{ formatCurrency(globalSales.sales_amount) }}</div>
                    </div>
                    <div class="global-card">
                        <div class="global-label">Ventas en Efectivo</div>
                        <div class="global-value">{{ formatCurrency(globalSales.cash_sales) }}</div>
                    </div>
                    <div class="global-card">
                        <div class="global-label">Cortes Abiertos</div>
                        <div class="global-value">{{ globalTotals.open_cuts }}</div>
                    </div>
                    <div class="global-card">
                        <div class="global-label">Diferencia Total</div>
                        <div class="global-value">{{ formatCurrency(globalTotals.difference_total) }}</div>
                    </div>
                </div>

                <div class="sales-list" style="margin-top: 10px;">
                    <div v-for="operator in globalOperators" :key="`op-${operator.operator_id}`" class="sale-row">
                        <div>
                            <div class="sale-title">{{ operator.operator_name || 'Operador' }}</div>
                            <div class="sale-meta">Cortes: {{ operator.cuts_count }} · Abiertos: {{ operator.open_cuts }} · Cerrados: {{ operator.closed_cuts }}</div>
                        </div>
                        <strong>{{ formatCurrency(operator.sales_total) }}</strong>
                    </div>
                    <div v-if="globalOperators.length === 0" class="state-msg">Sin operadores con movimiento en este evento.</div>
                </div>
            </template>
        </section>
    </main>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Libre+Baskerville:wght@400;700&family=DM+Mono:wght@400;500&display=swap');

:global(body) {
    margin: 0;
    min-height: 100vh;
    font-family: 'Libre Baskerville', serif;
    background: radial-gradient(circle at top, #4d180a, #170703 65%);
    color: #f5efe0;
}

.topbar {
    background: #8b1a1a;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.topbar-brand {
    color: #f5efe0;
    text-decoration: none;
    font-family: 'Playfair Display', serif;
    font-size: 24px;
}

.topbar-brand span { color: #f0c060; }

.topbar-nav { display: flex; gap: 14px; }
.topbar-nav a {
    color: rgba(245, 239, 224, 0.75);
    text-decoration: none;
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.topbar-nav a.active, .topbar-nav a:hover { color: #f0c060; }

.page-label {
    background: #1e0b03;
    border-bottom: 1px solid rgba(240, 192, 96, 0.2);
    padding: 8px 24px;
    font-family: 'DM Mono', monospace;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #c8922a;
}

.login-wrap {
    min-height: calc(100vh - 90px);
    display: grid;
    place-items: center;
    padding: 24px;
}

.login-card {
    width: min(420px, 100%);
    background: rgba(16, 4, 0, 0.9);
    border: 1px solid rgba(240, 192, 96, 0.3);
    padding: 28px;
    display: grid;
    gap: 12px;
}

.login-icon { font-size: 52px; text-align: center; }
.login-card h1 { margin: 0; font-family: 'Playfair Display', serif; color: #f0c060; }
.login-card p { margin: 0 0 8px; color: #b5a394; font-size: 14px; }
.login-card input, .controls-row input, .controls-row select, .checkout-panel input, .checkout-panel select {
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(240, 192, 96, 0.3);
    color: #f5efe0;
    padding: 10px 12px;
    font-family: 'DM Mono', monospace;
}
.login-card button, .pay-btn, .ghost-btn, .qty-row button {
    border: none;
    cursor: pointer;
    font-family: 'DM Mono', monospace;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.login-card button, .pay-btn {
    background: #8b1a1a;
    color: #f5efe0;
    padding: 12px;
}

.login-error { color: #ef5350; font-size: 12px; }

.layout {
    display: grid;
    grid-template-columns: 1.3fr 0.9fr 1fr;
    gap: 18px;
    padding: 18px;
}

.products-panel, .checkout-panel, .sales-panel {
    background: rgba(20, 8, 3, 0.92);
    border: 1px solid rgba(240, 192, 96, 0.22);
    padding: 16px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.panel-head h2, .checkout-panel h2, .sales-panel h2 {
    margin: 0;
    font-family: 'Playfair Display', serif;
    color: #f0c060;
    font-size: 24px;
}

.inventory-box {
    margin-bottom: 10px;
    padding: 10px;
    border: 1px solid rgba(240, 192, 96, 0.2);
    background: rgba(39, 14, 6, 0.35);
}

.inventory-title {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    letter-spacing: 1px;
    color: #f0c060;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.inventory-form {
    display: grid;
    grid-template-columns: 1.2fr 0.7fr 0.7fr auto auto auto;
    gap: 8px;
    align-items: center;
}

.inventory-check {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    color: #c9b7a8;
}

.ghost-btn {
    background: transparent;
    color: #f0c060;
    border: 1px solid rgba(240, 192, 96, 0.35);
    padding: 7px 10px;
}

.controls-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 10px;
}

.filters-row {
    display: grid;
    grid-template-columns: 1fr 0.8fr 0.8fr auto;
    gap: 8px;
    margin-bottom: 10px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
}

.product-card {
    border: 1px solid rgba(240, 192, 96, 0.2);
    padding: 10px;
    background: rgba(50, 18, 8, 0.4);
}

.product-row {
    display: flex;
    justify-content: space-between;
    gap: 6px;
}

.product-name { font-weight: 700; font-size: 14px; }
.product-price { color: #f0c060; font-family: 'DM Mono', monospace; font-size: 13px; }
.stock-chip { font-size: 11px; color: #c9b7a8; font-family: 'DM Mono', monospace; }

.qty-row {
    display: grid;
    grid-template-columns: 36px 1fr 36px;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
}

.qty-row button { background: #c8922a; color: #2a1504; height: 32px; font-weight: 700; }
.qty-row div { text-align: center; font-family: 'DM Mono', monospace; }

.stock-row {
    margin-top: 8px;
    display: grid;
    grid-template-columns: 90px 1fr;
    gap: 8px;
    align-items: center;
}

.stock-row input {
    padding: 6px 8px;
}

.stock-row span {
    font-size: 11px;
    color: #c9b7a8;
    font-family: 'DM Mono', monospace;
}

.inventory-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.danger-btn {
    color: #ef9a9a;
    border-color: rgba(239, 154, 154, 0.45);
}

.checkout-total {
    font-family: 'Playfair Display', serif;
    font-size: 36px;
    color: #f0c060;
    margin-top: 2px;
}

.cut-box {
    border: 1px solid rgba(240, 192, 96, 0.22);
    background: rgba(39, 14, 6, 0.45);
    padding: 10px;
    margin-bottom: 10px;
}

.cut-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    text-transform: uppercase;
}

.cut-state {
    padding: 2px 7px;
    border: 1px solid;
    letter-spacing: 1px;
}

.cut-state.open {
    color: #81c784;
    border-color: rgba(129, 199, 132, 0.45);
}

.cut-state.closed {
    color: #ef9a9a;
    border-color: rgba(239, 154, 154, 0.45);
}

.cut-grid {
    display: grid;
    gap: 6px;
}

.cut-metric {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: 'DM Mono', monospace;
    font-size: 12px;
    color: #c9b7a8;
}

.cut-metric strong {
    color: #f0c060;
}
.checkout-count {
    margin-bottom: 10px;
    color: #c9b7a8;
    font-family: 'DM Mono', monospace;
    font-size: 12px;
}

.checkout-panel label {
    display: block;
    margin: 10px 0 6px;
    font-family: 'DM Mono', monospace;
    color: #c9b7a8;
    font-size: 11px;
}

.pay-btn { width: 100%; margin-top: 14px; }
.ok-msg { margin-top: 10px; color: #66bb6a; font-size: 13px; }

.cart-list { margin-top: 8px; display: grid; gap: 7px; }
.cart-row {
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid rgba(240, 192, 96, 0.14);
    padding-bottom: 5px;
    font-size: 13px;
}

.sales-list { display: grid; gap: 8px; margin-top: 8px; }
.sale-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: rgba(39, 14, 6, 0.55);
    border: 1px solid rgba(240, 192, 96, 0.14);
}

.sale-title { font-size: 13px; }
.sale-meta { font-size: 11px; color: #c9b7a8; font-family: 'DM Mono', monospace; }

.global-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-top: 8px;
}

.global-card {
    border: 1px solid rgba(240, 192, 96, 0.18);
    background: rgba(39, 14, 6, 0.42);
    padding: 8px;
}

.global-label {
    font-size: 10px;
    color: #c9b7a8;
    font-family: 'DM Mono', monospace;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.global-value {
    margin-top: 4px;
    color: #f0c060;
    font-family: 'Playfair Display', serif;
    font-size: 20px;
}

.state-msg {
    color: #b5a394;
    font-family: 'DM Mono', monospace;
    font-size: 12px;
}

@media (max-width: 1200px) {
    .layout {
        grid-template-columns: 1fr;
    }

    .global-grid {
        grid-template-columns: 1fr;
    }

    .inventory-form {
        grid-template-columns: 1fr;
    }

    .filters-row,
    .controls-row {
        grid-template-columns: 1fr;
    }
}
</style>
