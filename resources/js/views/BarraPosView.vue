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

const promotions = ref([]);
const promoMessage = ref('');
const promoForm = ref({
    id: null,
    nombre: '',
    tipo: 'porcentaje',
    valor: '',
    id_producto: null,
    fecha_inicio: '',
    fecha_fin: '',
    activo: true,
});

const dashboard = ref(null);
const dashboardLoading = ref(false);
const showDashboard = ref(false);
let dashboardInterval = null;

const stockMovements = ref([]);
const stockAlerts = ref(null);
const showMovements = ref(false);
const movementProductFilter = ref(null);
const movementForm = ref({ tipo: 'entrada', cantidad: '', motivo: '' });
const movementProductId = ref(null);
const movementMessage = ref('');

const refundModal = ref(null);
const refundReason = ref('');
const refundProcessing = ref(false);
const refundMessage = ref('');
const refundHistory = ref([]);

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

const getPromoDiscount = (productId, price) => {
    let bestDiscount = 0;
    const today = new Date().toISOString().slice(0, 10);
    for (const promo of promotions.value) {
        if (!promo.activo) continue;
        if (promo.id_producto !== null && promo.id_producto !== undefined && Number(promo.id_producto) !== Number(productId)) continue;
        if (promo.fecha_inicio && promo.fecha_inicio > today) continue;
        if (promo.fecha_fin && promo.fecha_fin < today) continue;
        const discount = promo.tipo === 'porcentaje'
            ? Math.round(price * (Number(promo.valor) / 100) * 100) / 100
            : Math.min(Number(promo.valor), price);
        if (discount > bestDiscount) bestDiscount = discount;
    }
    return bestDiscount;
};

const cartItems = computed(() => {
    return products.value
        .map((product) => {
            const precio = Number(product.precio);
            const descuento = getPromoDiscount(product.id, precio);
            return {
                ...product,
                quantity: cart.value[product.id] || 0,
                precioOriginal: precio,
                descuento,
                precioFinal: Math.round((precio - descuento) * 100) / 100,
            };
        })
        .filter((product) => product.quantity > 0);
});

const cartTotal = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + item.precioFinal * item.quantity, 0);
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

const openRefundModal = (sale) => {
    refundModal.value = sale;
    refundReason.value = '';
    refundMessage.value = '';
};

const closeRefundModal = () => {
    refundModal.value = null;
    refundReason.value = '';
    refundMessage.value = '';
};

const submitRefund = async () => {
    if (!refundModal.value || !refundReason.value.trim()) return;
    refundProcessing.value = true;
    refundMessage.value = '';
    try {
        await ax().post(`/api/admin/bar/sales/${refundModal.value.id}/refund`, {
            motivo: refundReason.value.trim(),
        });
        refundMessage.value = 'Venta cancelada y stock devuelto.';
        closeRefundModal();
        await Promise.all([loadRecentSales(), loadProducts(), loadRefundHistory()]);
    } catch (error) {
        refundMessage.value = error.response?.data?.message || 'Error al procesar el reembolso.';
    } finally {
        refundProcessing.value = false;
    }
};

const loadRefundHistory = async () => {
    if (!isAdmin.value) return;
    try {
        const { data } = await ax().get('/api/admin/bar/refunds', {
            params: { event_id: selectedEventId.value || undefined },
        });
        refundHistory.value = data;
    } catch {
        refundHistory.value = [];
    }
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
    if (showDashboard.value) await loadLiveDashboard();
};

// ── Promociones ───────────────────────────────────────────

const resetPromoForm = () => {
    promoForm.value = {
        id: null,
        nombre: '',
        tipo: 'porcentaje',
        valor: '',
        id_producto: null,
        fecha_inicio: '',
        fecha_fin: '',
        activo: true,
    };
};

const loadPromotions = async () => {
    if (isAdmin.value) {
        const { data } = await ax().get(`${apiPrefix.value}/bar/promotions`);
        promotions.value = data;
    } else {
        const { data } = await ax().get(`${apiPrefix.value}/bar/promotions/active`);
        promotions.value = data;
    }
};

const editPromotion = (promo) => {
    promoForm.value = {
        id: promo.id,
        nombre: promo.nombre,
        tipo: promo.tipo,
        valor: String(promo.valor ?? ''),
        id_producto: promo.id_producto,
        fecha_inicio: promo.fecha_inicio || '',
        fecha_fin: promo.fecha_fin || '',
        activo: Boolean(promo.activo),
    };
};

const submitPromoForm = async () => {
    if (!isAdmin.value) return;
    promoMessage.value = '';

    const payload = {
        nombre: promoForm.value.nombre?.trim(),
        tipo: promoForm.value.tipo,
        valor: Number(promoForm.value.valor),
        id_producto: promoForm.value.id_producto || null,
        fecha_inicio: promoForm.value.fecha_inicio || null,
        fecha_fin: promoForm.value.fecha_fin || null,
        activo: Boolean(promoForm.value.activo),
    };

    if (!payload.nombre || Number.isNaN(payload.valor) || payload.valor <= 0) {
        alert('Completa nombre y valor con datos válidos.');
        return;
    }

    try {
        if (promoForm.value.id) {
            await ax().put(`${apiPrefix.value}/bar/promotions/${promoForm.value.id}`, payload);
            promoMessage.value = 'Promoción actualizada.';
        } else {
            await ax().post(`${apiPrefix.value}/bar/promotions`, payload);
            promoMessage.value = 'Promoción creada.';
        }

        resetPromoForm();
        await loadPromotions();
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo guardar la promoción.');
    }
};

const deletePromotion = async (promo) => {
    if (!isAdmin.value) return;
    if (!window.confirm(`¿Eliminar promoción "${promo.nombre}"?`)) return;

    try {
        await ax().delete(`${apiPrefix.value}/bar/promotions/${promo.id}`);
        promoMessage.value = 'Promoción eliminada.';
        if (promoForm.value.id === promo.id) resetPromoForm();
        await loadPromotions();
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo eliminar la promoción.');
    }
};

const promoTypeLabel = (tipo) => ({ porcentaje: 'Porcentaje', monto_fijo: 'Monto Fijo' }[tipo] || tipo);

// ── Movimientos de inventario ─────────────────────────────

const movementTypeLabel = (tipo) => ({
    entrada: 'Entrada',
    salida_venta: 'Venta',
    merma: 'Merma',
    ajuste: 'Ajuste',
}[tipo] || tipo);

const movementTypeClass = (tipo) => ({
    entrada: 'mov-entrada',
    salida_venta: 'mov-salida',
    merma: 'mov-merma',
    ajuste: 'mov-ajuste',
}[tipo] || '');

const loadStockMovements = async () => {
    const params = {};
    if (movementProductFilter.value) params.product_id = movementProductFilter.value;
    const { data } = await ax().get(`${apiPrefix.value}/bar/stock-movements`, { params });
    stockMovements.value = data;
};

const loadStockAlerts = async () => {
    const { data } = await ax().get(`${apiPrefix.value}/bar/stock-alerts`);
    stockAlerts.value = data;
};

const toggleMovements = async () => {
    showMovements.value = !showMovements.value;
    if (showMovements.value) await loadStockMovements();
};

const submitMovement = async () => {
    if (!movementProductId.value) {
        alert('Selecciona un producto para registrar movimiento.');
        return;
    }
    const cantidad = Number(movementForm.value.cantidad);
    if (!cantidad || cantidad < 1) {
        alert('Ingresa una cantidad válida.');
        return;
    }

    movementMessage.value = '';
    try {
        const { data } = await ax().post(`${apiPrefix.value}/bar/products/${movementProductId.value}/movement`, {
            tipo: movementForm.value.tipo,
            cantidad,
            motivo: movementForm.value.motivo || null,
        });
        movementMessage.value = `${movementTypeLabel(movementForm.value.tipo)}: ${data.stock_anterior} → ${data.stock_nuevo}`;
        movementForm.value = { tipo: 'entrada', cantidad: '', motivo: '' };
        movementProductId.value = null;
        await Promise.all([loadProducts(), loadStockMovements(), loadStockAlerts()]);
    } catch (error) {
        alert(error.response?.data?.message || 'No se pudo registrar el movimiento.');
    }
};

// ── Dashboard en tiempo real ──────────────────────────────

const loadLiveDashboard = async () => {
    if (!selectedEventId.value) {
        dashboard.value = null;
        return;
    }

    dashboardLoading.value = true;
    try {
        const { data } = await ax().get(`${apiPrefix.value}/bar/dashboard`, {
            params: { event_id: selectedEventId.value },
        });
        dashboard.value = data;
    } catch {
        // silently ignore polling errors
    } finally {
        dashboardLoading.value = false;
    }
};

const toggleDashboard = () => {
    showDashboard.value = !showDashboard.value;
    if (showDashboard.value) {
        loadLiveDashboard();
        dashboardInterval = setInterval(loadLiveDashboard, 30000);
    } else {
        clearInterval(dashboardInterval);
        dashboardInterval = null;
    }
};

const loadDashboard = async () => {
    loading.value = true;
    try {
        await loadEvents();
        await Promise.all([loadProducts(), loadCurrentCut(), loadRecentSales(), loadCutHistory(), loadGlobalSummary(), loadPromotions(), loadStockAlerts(), loadRefundHistory()]);
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
        <!-- Alertas de stock (visible para todos) -->
        <div v-if="stockAlerts && (stockAlerts.out_of_stock?.length || stockAlerts.low_stock?.length)" class="alerts-banner">
            <div v-if="stockAlerts.out_of_stock?.length" class="alert-group alert-danger">
                <div class="alert-icon">🚫</div>
                <div>
                    <strong>Sin stock:</strong>
                    <span v-for="(p, i) in stockAlerts.out_of_stock" :key="'out-'+p.id">
                        {{ p.nombre }}<span v-if="i < stockAlerts.out_of_stock.length - 1">, </span>
                    </span>
                </div>
            </div>
            <div v-if="stockAlerts.low_stock?.length" class="alert-group alert-warning">
                <div class="alert-icon">⚠️</div>
                <div>
                    <strong>Stock bajo (≤{{ stockAlerts.threshold }}):</strong>
                    <span v-for="(p, i) in stockAlerts.low_stock" :key="'low-'+p.id">
                        {{ p.nombre }} ({{ p.stock }})<span v-if="i < stockAlerts.low_stock.length - 1">, </span>
                    </span>
                </div>
            </div>
        </div>

        <section class="products-panel">
            <div class="panel-head">
                <h2>Catalogo de Bebidas</h2>
                <div style="display:flex;gap:8px;">
                    <button v-if="isAdmin" class="ghost-btn" @click="toggleMovements">{{ showMovements ? 'Ocultar Movimientos' : 'Movimientos' }}</button>
                    <button v-if="isAdmin" class="ghost-btn" @click="toggleDashboard">{{ showDashboard ? 'Ocultar Dashboard' : 'Dashboard' }}</button>
                    <button v-if="isAdmin" class="ghost-btn" @click="createProduct">Nuevo</button>
                </div>
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

            <!-- Promociones CRUD -->
            <div v-if="isAdmin" class="inventory-box">
                <div class="inventory-title">Promociones de Barra</div>
                <div class="promo-form">
                    <input v-model="promoForm.nombre" type="text" placeholder="Nombre de promoción">
                    <select v-model="promoForm.tipo">
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="monto_fijo">Monto Fijo ($)</option>
                    </select>
                    <input v-model="promoForm.valor" type="number" min="0.01" step="0.01" :placeholder="promoForm.tipo === 'porcentaje' ? 'Ej: 15' : 'Ej: 20.00'">
                    <select v-model="promoForm.id_producto">
                        <option :value="null">Todos los productos</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                    </select>
                    <input v-model="promoForm.fecha_inicio" type="date" title="Fecha inicio">
                    <input v-model="promoForm.fecha_fin" type="date" title="Fecha fin">
                    <label class="inventory-check">
                        <input v-model="promoForm.activo" type="checkbox"> Activa
                    </label>
                    <button class="ghost-btn" @click="submitPromoForm">{{ promoForm.id ? 'Guardar' : 'Crear' }}</button>
                    <button class="ghost-btn" @click="resetPromoForm">Limpiar</button>
                </div>
                <div v-if="promoMessage" class="ok-msg">{{ promoMessage }}</div>
                <div v-if="promotions.length > 0" class="promo-list">
                    <div v-for="promo in promotions" :key="promo.id" class="promo-row">
                        <div>
                            <strong>{{ promo.nombre }}</strong>
                            <span class="promo-badge">{{ promo.tipo === 'porcentaje' ? promo.valor + '%' : formatCurrency(promo.valor) }}</span>
                            <span v-if="promo.producto_nombre" class="promo-product">{{ promo.producto_nombre }}</span>
                            <span v-else class="promo-product">Todos</span>
                            <span v-if="!promo.activo" class="promo-inactive">Inactiva</span>
                        </div>
                        <div class="promo-actions">
                            <span v-if="promo.fecha_inicio || promo.fecha_fin" class="promo-dates">{{ promo.fecha_inicio || '...' }} → {{ promo.fecha_fin || '...' }}</span>
                            <button class="ghost-btn" @click="editPromotion(promo)">Editar</button>
                            <button class="ghost-btn danger-btn" @click="deletePromotion(promo)">Eliminar</button>
                        </div>
                    </div>
                </div>
                <div v-else class="state-msg" style="margin-top:8px;">Sin promociones registradas.</div>
            </div>

            <!-- Promociones activas (vista seller) -->
            <div v-if="!isAdmin && promotions.length > 0" class="inventory-box">
                <div class="inventory-title">Promociones Activas</div>
                <div class="promo-list">
                    <div v-for="promo in promotions" :key="promo.id" class="promo-row">
                        <div>
                            <strong>{{ promo.nombre }}</strong>
                            <span class="promo-badge">{{ promo.tipo === 'porcentaje' ? promo.valor + '%' : formatCurrency(promo.valor) }}</span>
                            <span v-if="promo.producto_nombre" class="promo-product">{{ promo.producto_nombre }}</span>
                            <span v-else class="promo-product">Todos</span>
                        </div>
                        <div class="promo-actions">
                            <span v-if="promo.fecha_inicio || promo.fecha_fin" class="promo-dates">{{ promo.fecha_inicio || '...' }} → {{ promo.fecha_fin || '...' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard en tiempo real -->
            <div v-if="isAdmin && showDashboard" class="dashboard-panel">
                <div class="inventory-title">Dashboard en Tiempo Real</div>
                <div v-if="dashboardLoading && !dashboard" class="state-msg">Cargando dashboard...</div>
                <template v-if="dashboard">
                    <div class="dash-grid">
                        <div class="dash-card accent">
                            <div class="dash-label">Ingreso Total</div>
                            <div class="dash-value">{{ formatCurrency(dashboard.revenue?.total) }}</div>
                        </div>
                        <div class="dash-card">
                            <div class="dash-label">Ingreso Boletos</div>
                            <div class="dash-value">{{ formatCurrency(dashboard.revenue?.boletos) }}</div>
                        </div>
                        <div class="dash-card">
                            <div class="dash-label">Ingreso Barra</div>
                            <div class="dash-value">{{ formatCurrency(dashboard.revenue?.barra) }}</div>
                        </div>
                        <div class="dash-card">
                            <div class="dash-label">Tickets Vendidos</div>
                            <div class="dash-value">{{ dashboard.tickets?.vendidos || 0 }}</div>
                        </div>
                        <div class="dash-card">
                            <div class="dash-label">Tickets Escaneados</div>
                            <div class="dash-value">{{ dashboard.tickets?.escaneados || 0 }}</div>
                        </div>
                        <div class="dash-card">
                            <div class="dash-label">Asistencia</div>
                            <div class="dash-value">{{ dashboard.tickets?.vendidos > 0 ? ((dashboard.tickets.escaneados / dashboard.tickets.vendidos) * 100).toFixed(1) : '0' }}%</div>
                        </div>
                    </div>

                    <div class="dash-section">
                        <div class="dash-section-title">Ventas de Boletos por Hora</div>
                        <div v-if="dashboard.ticket_sales_per_hour?.length" class="bar-chart">
                            <div v-for="h in dashboard.ticket_sales_per_hour" :key="'th-'+h.hora" class="bar-item">
                                <div class="bar-label">{{ h.hora }}</div>
                                <div class="bar-track"><div class="bar-fill" :style="{ width: Math.min(100, (h.monto / Math.max(...dashboard.ticket_sales_per_hour.map(x => x.monto), 1)) * 100) + '%' }"></div></div>
                                <div class="bar-amount">{{ formatCurrency(h.monto) }} ({{ h.ordenes }})</div>
                            </div>
                        </div>
                        <div v-else class="state-msg">Sin ventas de boletos en las últimas 12h.</div>
                    </div>

                    <div class="dash-section">
                        <div class="dash-section-title">Ventas de Barra por Hora</div>
                        <div v-if="dashboard.bar_sales_per_hour?.length" class="bar-chart">
                            <div v-for="h in dashboard.bar_sales_per_hour" :key="'bh-'+h.hora" class="bar-item">
                                <div class="bar-label">{{ h.hora }}</div>
                                <div class="bar-track"><div class="bar-fill gold" :style="{ width: Math.min(100, (h.monto / Math.max(...dashboard.bar_sales_per_hour.map(x => x.monto), 1)) * 100) + '%' }"></div></div>
                                <div class="bar-amount">{{ formatCurrency(h.monto) }} ({{ h.ventas }})</div>
                            </div>
                        </div>
                        <div v-else class="state-msg">Sin ventas de barra en las últimas 12h.</div>
                    </div>

                    <div class="dash-section">
                        <div class="dash-section-title">Barra: Resumen por Método de Pago</div>
                        <div class="dash-grid" style="grid-template-columns: repeat(3, 1fr);">
                            <div class="dash-card"><div class="dash-label">Efectivo</div><div class="dash-value">{{ formatCurrency(dashboard.bar_totals?.efectivo) }}</div></div>
                            <div class="dash-card"><div class="dash-label">Tarjeta</div><div class="dash-value">{{ formatCurrency(dashboard.bar_totals?.tarjeta) }}</div></div>
                            <div class="dash-card"><div class="dash-label">Transferencia</div><div class="dash-value">{{ formatCurrency(dashboard.bar_totals?.transferencia) }}</div></div>
                        </div>
                    </div>

                    <div class="dash-section">
                        <div class="dash-section-title">Top 5 Productos de Barra</div>
                        <div v-if="dashboard.top_bar_products?.length" class="promo-list">
                            <div v-for="(tp, i) in dashboard.top_bar_products" :key="'tp-'+i" class="promo-row">
                                <div><strong>{{ tp.nombre }}</strong> <span class="promo-badge">{{ tp.cantidad }} uds</span></div>
                                <strong>{{ formatCurrency(tp.ingreso) }}</strong>
                            </div>
                        </div>
                        <div v-else class="state-msg">Sin datos de productos.</div>
                    </div>

                    <div class="dash-section">
                        <div class="dash-section-title">Estado de Cortes de Caja</div>
                        <div v-if="dashboard.bar_cuts?.length" class="promo-list">
                            <div v-for="c in dashboard.bar_cuts" :key="'dc-'+c.id" class="promo-row">
                                <div>
                                    <strong>Corte #{{ c.id }}</strong> · {{ c.operador || 'Operador' }}
                                    <span :class="c.estado === 'abierto' ? 'promo-badge' : 'promo-inactive'">{{ c.estado.toUpperCase() }}</span>
                                </div>
                                <div style="text-align:right;">
                                    <div>Esperado: {{ formatCurrency(c.monto_efectivo_esperado) }}</div>
                                    <div v-if="c.estado === 'cerrado'" style="font-size:11px;">Dif: {{ formatCurrency(c.diferencia) }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="state-msg">Sin cortes registrados.</div>
                    </div>

                    <div style="text-align:center;margin-top:8px;">
                        <button class="ghost-btn" @click="loadLiveDashboard">Actualizar ahora</button>
                        <span class="state-msg" style="margin-left:8px;">Auto-refresh: cada 30s</span>
                    </div>
                </template>
            </div>

            <!-- Movimientos de inventario -->
            <div v-if="isAdmin && showMovements" class="dashboard-panel">
                <div class="inventory-title">Movimientos de Inventario</div>

                <div class="movement-form">
                    <select v-model="movementProductId">
                        <option :value="null" disabled>Producto</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.nombre }} (stock: {{ p.stock }})</option>
                    </select>
                    <select v-model="movementForm.tipo">
                        <option value="entrada">Entrada</option>
                        <option value="merma">Merma</option>
                        <option value="ajuste">Ajuste (-)</option>
                    </select>
                    <input v-model="movementForm.cantidad" type="number" min="1" step="1" placeholder="Cantidad">
                    <input v-model="movementForm.motivo" type="text" placeholder="Motivo (opcional)">
                    <button class="ghost-btn" @click="submitMovement">Registrar</button>
                </div>
                <div v-if="movementMessage" class="ok-msg">{{ movementMessage }}</div>

                <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
                    <span class="state-msg">Filtrar por producto:</span>
                    <select v-model="movementProductFilter" @change="loadStockMovements" style="flex:1;">
                        <option :value="null">Todos</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                    </select>
                </div>

                <div class="movements-list">
                    <div v-for="mov in stockMovements" :key="mov.id" class="movement-row">
                        <div>
                            <span :class="'mov-badge ' + movementTypeClass(mov.tipo)">{{ movementTypeLabel(mov.tipo) }}</span>
                            <strong>{{ mov.producto_nombre }}</strong>
                            <span class="state-msg" style="margin-left:6px;">{{ mov.stock_anterior }} → {{ mov.stock_nuevo }}</span>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:12px;">{{ mov.cantidad > 0 ? (mov.tipo === 'entrada' ? '+' : '-') : '' }}{{ mov.cantidad }} uds</div>
                            <div class="state-msg">{{ mov.usuario_nombre || 'Sistema' }} · {{ formatDateTime(mov.created_at) }}</div>
                            <div v-if="mov.motivo" class="state-msg" style="font-style:italic;">{{ mov.motivo }}</div>
                        </div>
                    </div>
                    <div v-if="stockMovements.length === 0" class="state-msg">Sin movimientos registrados.</div>
                </div>
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
                    <span>{{ item.nombre }} x{{ item.quantity }}<template v-if="item.descuento > 0"> <small style="color:#4CAF50;">-{{ formatCurrency(item.descuento) }}/u</small></template></span>
                    <strong>
                        <template v-if="item.descuento > 0"><s style="color:var(--gris);font-size:11px;font-weight:normal;">{{ formatCurrency(item.quantity * item.precioOriginal) }}</s> </template>{{ formatCurrency(item.quantity * item.precioFinal) }}
                    </strong>
                </div>
                <div v-if="cartItems.length === 0" class="state-msg">Sin productos en carrito.</div>
            </div>
        </section>

        <section class="sales-panel">
            <h2>Ventas recientes</h2>
            <div v-if="refundMessage" class="field-ok" style="margin-bottom:8px;">{{ refundMessage }}</div>
            <div class="sales-list">
                <div v-for="sale in recentSales" :key="sale.id" class="sale-row">
                    <div style="flex:1;">
                        <div class="sale-title">
                            Venta #{{ sale.id }} · {{ sale.evento }}
                            <span v-if="sale.estado === 'cancelada'" style="color:#e53935;font-size:11px;margin-left:6px;">CANCELADA</span>
                            <span v-else-if="sale.estado === 'reembolsada'" style="color:#FF9800;font-size:11px;margin-left:6px;">REEMBOLSADA</span>
                        </div>
                        <div class="sale-meta">{{ sale.vendedor || 'Operador' }} · {{ paymentLabel(sale.metodo_pago) }} · {{ formatDateTime(sale.created_at) }}</div>
                    </div>
                    <strong :style="sale.estado !== 'activa' ? 'text-decoration:line-through;color:var(--gris);' : ''">{{ formatCurrency(sale.total) }}</strong>
                    <button v-if="isAdmin && (!sale.estado || sale.estado === 'activa')" class="btn-small btn-danger" style="margin-left:8px;" @click="openRefundModal(sale)">Cancelar</button>
                </div>
                <div v-if="recentSales.length === 0" class="state-msg">Sin ventas registradas.</div>
            </div>

            <template v-if="isAdmin && refundHistory.length > 0">
                <h2 style="margin-top: 16px;">Reembolsos recientes</h2>
                <div class="sales-list">
                    <div v-for="r in refundHistory" :key="`ref-${r.id}`" class="sale-row" style="border-left: 3px solid #e53935;">
                        <div style="flex:1;">
                            <div class="sale-title">Venta #{{ r.id_venta }} · {{ r.evento }}</div>
                            <div class="sale-meta">{{ r.operador || 'Operador' }} · {{ r.tipo.toUpperCase() }} · {{ formatDateTime(r.created_at) }}</div>
                            <div class="sale-meta" style="color:#e53935;">Motivo: {{ r.motivo }}</div>
                        </div>
                        <strong style="color:#e53935;">-{{ formatCurrency(r.monto) }}</strong>
                    </div>
                </div>
            </template>

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

    <!-- Modal Reembolso -->
    <div v-if="refundModal" class="modal-overlay" @click.self="closeRefundModal">
        <div class="modal-box">
            <h3>Cancelar Venta #{{ refundModal.id }}</h3>
            <p style="color:var(--gris);font-size:13px;">Total: {{ formatCurrency(refundModal.total) }} · {{ paymentLabel(refundModal.metodo_pago) }}</p>
            <label class="field-label">Motivo de cancelación</label>
            <textarea v-model="refundReason" class="field-input" rows="3" placeholder="Escribe el motivo del reembolso..." style="resize:vertical;"></textarea>
            <div v-if="refundMessage" class="field-error">{{ refundMessage }}</div>
            <div style="display:flex;gap:8px;margin-top:12px;">
                <button class="btn-primary btn-danger" :disabled="refundProcessing || !refundReason.trim()" @click="submitRefund">
                    {{ refundProcessing ? 'Procesando...' : 'Confirmar cancelación' }}
                </button>
                <button class="btn-secondary" @click="closeRefundModal">Cerrar</button>
            </div>
        </div>
    </div>
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

/* ── Alertas de stock ─────────────────────────── */

.alerts-banner {
    grid-column: 1 / -1;
    display: grid;
    gap: 8px;
    margin-bottom: 4px;
}

.alert-group {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    font-size: 13px;
    font-family: 'DM Mono', monospace;
}

.alert-danger {
    background: rgba(239, 83, 80, 0.12);
    border: 1px solid rgba(239, 83, 80, 0.35);
    color: #ef9a9a;
}

.alert-warning {
    background: rgba(255, 183, 77, 0.1);
    border: 1px solid rgba(255, 183, 77, 0.35);
    color: #ffe0b2;
}

.alert-icon {
    font-size: 20px;
    flex-shrink: 0;
}

/* ── Movimientos ──────────────────────────────── */

.movement-form {
    display: grid;
    grid-template-columns: 1.5fr 0.8fr 0.6fr 1.2fr auto;
    gap: 8px;
    align-items: center;
}

.movements-list {
    margin-top: 10px;
    display: grid;
    gap: 6px;
    max-height: 350px;
    overflow-y: auto;
}

.movement-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px;
    background: rgba(39, 14, 6, 0.45);
    border: 1px solid rgba(240, 192, 96, 0.14);
    gap: 8px;
    font-size: 13px;
}

.mov-badge {
    display: inline-block;
    padding: 2px 8px;
    font-family: 'DM Mono', monospace;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-right: 6px;
    border: 1px solid;
}

.mov-entrada {
    color: #81c784;
    border-color: rgba(129, 199, 132, 0.45);
    background: rgba(129, 199, 132, 0.08);
}

.mov-salida {
    color: #ef9a9a;
    border-color: rgba(239, 154, 154, 0.45);
    background: rgba(239, 154, 154, 0.08);
}

.mov-merma {
    color: #ffcc80;
    border-color: rgba(255, 204, 128, 0.45);
    background: rgba(255, 204, 128, 0.08);
}

.mov-ajuste {
    color: #90caf9;
    border-color: rgba(144, 202, 249, 0.45);
    background: rgba(144, 202, 249, 0.08);
}

/* ── Promociones ─────────────────────────────── */

.promo-form {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr 0.7fr 1fr 0.8fr 0.8fr auto auto auto;
    gap: 8px;
    align-items: center;
}

.promo-list {
    margin-top: 10px;
    display: grid;
    gap: 6px;
}

.promo-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px;
    background: rgba(39, 14, 6, 0.45);
    border: 1px solid rgba(240, 192, 96, 0.14);
    gap: 8px;
    font-size: 13px;
}

.promo-badge {
    display: inline-block;
    background: rgba(200, 146, 42, 0.2);
    color: #f0c060;
    padding: 2px 8px;
    border: 1px solid rgba(240, 192, 96, 0.3);
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    margin-left: 6px;
}

.promo-product {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    color: #c9b7a8;
    margin-left: 6px;
}

.promo-inactive {
    font-family: 'DM Mono', monospace;
    font-size: 10px;
    color: #ef9a9a;
    border: 1px solid rgba(239, 154, 154, 0.3);
    padding: 1px 6px;
    margin-left: 6px;
}

.promo-dates {
    font-family: 'DM Mono', monospace;
    font-size: 10px;
    color: #c9b7a8;
    margin-right: 8px;
}

.promo-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ── Dashboard ───────────────────────────────── */

.dashboard-panel {
    margin-bottom: 10px;
    padding: 14px;
    border: 1px solid rgba(240, 192, 96, 0.25);
    background: rgba(20, 8, 3, 0.9);
}

.dash-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-top: 8px;
}

.dash-card {
    border: 1px solid rgba(240, 192, 96, 0.18);
    background: rgba(39, 14, 6, 0.42);
    padding: 10px;
}

.dash-card.accent {
    border-color: rgba(240, 192, 96, 0.5);
    background: rgba(200, 146, 42, 0.12);
}

.dash-label {
    font-size: 10px;
    color: #c9b7a8;
    font-family: 'DM Mono', monospace;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.dash-value {
    margin-top: 4px;
    color: #f0c060;
    font-family: 'Playfair Display', serif;
    font-size: 18px;
}

.dash-section {
    margin-top: 14px;
}

.dash-section-title {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    letter-spacing: 1px;
    color: #f0c060;
    text-transform: uppercase;
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(240, 192, 96, 0.15);
    padding-bottom: 4px;
}

.bar-chart {
    display: grid;
    gap: 4px;
}

.bar-item {
    display: grid;
    grid-template-columns: 50px 1fr 100px;
    gap: 8px;
    align-items: center;
    font-family: 'DM Mono', monospace;
    font-size: 11px;
}

.bar-label {
    color: #c9b7a8;
    text-align: right;
}

.bar-track {
    height: 14px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(240, 192, 96, 0.12);
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: #8b1a1a;
    transition: width 0.3s ease;
}

.bar-fill.gold {
    background: rgba(200, 146, 42, 0.7);
}

.bar-amount {
    color: #c9b7a8;
    text-align: right;
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

    .promo-form {
        grid-template-columns: 1fr;
    }

    .movement-form {
        grid-template-columns: 1fr;
    }

    .dash-grid {
        grid-template-columns: 1fr 1fr;
    }

    .filters-row,
    .controls-row {
        grid-template-columns: 1fr;
    }

    .promo-row {
        flex-direction: column;
        align-items: flex-start;
    }
}

.btn-small {
    padding: 4px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
}

.btn-danger {
    background: #e53935;
    color: #fff;
}

.btn-danger:hover {
    background: #c62828;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-box {
    background: #1e1e1e;
    border: 1px solid var(--borde);
    border-radius: 10px;
    padding: 24px;
    min-width: 360px;
    max-width: 480px;
}

.modal-box h3 {
    margin: 0 0 8px;
    color: var(--dorado);
}

.btn-primary {
    padding: 8px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    background: var(--dorado);
    color: #000;
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-secondary {
    padding: 8px 18px;
    border: 1px solid var(--borde);
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    background: transparent;
    color: var(--texto);
}
</style>
