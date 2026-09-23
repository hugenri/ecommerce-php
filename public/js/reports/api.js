const ReportsAPI = (() => {
    const SALES = '/reports/sales';
    const TOP_PRODUCTS = '/reports/top-products';
    const LOW_STOCK = '/reports/low-stock';
    const INVENTORY = '/reports/inventory';
    const DELIVERIES = '/reports/deliveries';

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

    const getSales = (params = {}) => get(SALES, params);

    const getTopProducts = (params = {}) => get(TOP_PRODUCTS, params);

    const getLowStock = (params = {}) => get(LOW_STOCK, params);

    const getMovements = (params = {}) => get(INVENTORY, params);

    const getDeliveries = (params = {}) => get(DELIVERIES, params);

    return {
        getSales,
        getTopProducts,
        getLowStock,
        getMovements,
        getDeliveries,
    };
})();
