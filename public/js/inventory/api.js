const InventoryAPI = (() => {
    const BASE = '/inventory';
    const DATA = '/inventory/data';
    const MOVEMENTS = '/inventory/movements';
    const LOW_STOCK = '/inventory/low-stock';
    const ADD_STOCK = '/inventory/add-stock';
    const ADJUST = '/inventory/adjust';
    const PRODUCTS_SEARCH = '/products/search';

    const headers = () => ({
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': window.CSRF_TOKEN || '',
    });

    const handleResponse = async (res) => {
        const json = await res.json();
        return { ok: res.ok, status: res.status, json };
    };

    const get = async (url, params = {}) => {
        const qs = new URLSearchParams(params).toString();
        const target = qs ? `${url}?${qs}` : url;
        const res = await fetch(target, { headers: headers(), credentials: 'same-origin' });
        return handleResponse(res);
    };

    const getInventory = (params = {}) => get(DATA, params);

    const getMovements = (params = {}) => get(MOVEMENTS, params);

    const getLowStock = (params = {}) => get(LOW_STOCK, params);

    const post = async (url, data) => {
        const res = await fetch(url, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(data),
        });
        return handleResponse(res);
    };

    const addStock = (data) => post(ADD_STOCK, data);

    const adjustStock = (data) => post(ADJUST, data);

    const searchProducts = async (q) => {
        const res = await fetch(`${PRODUCTS_SEARCH}?q=${encodeURIComponent(q)}`, {
            headers: headers(),
            credentials: 'same-origin',
        });
        return handleResponse(res);
    };

    return {
        getInventory,
        getMovements,
        getLowStock,
        addStock,
        adjustStock,
        searchProducts,
    };
})();
