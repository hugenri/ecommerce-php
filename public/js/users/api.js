const UserAPI = (() => {
    const BASE = '/users';
    const DATA = '/users/data';


    const headers = () => ({
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': window.CSRF_TOKEN || '',
    });

    const handleResponse = async (res) => {
        let json = {};
        try {
            json = await res.json();
        } catch (e) {
            json = {};
        }
        return { ok: res.ok, status: res.status, json };
    };

    const get = async (params = {}) => {
        const qs = new URLSearchParams(params).toString();
         const url = qs ? `${DATA}?${qs}` : DATA;
        const res = await fetch(url, { headers: headers(), credentials: 'same-origin' });
        return handleResponse(res);
    };

    const post = async (data) => {
        const res = await fetch(BASE, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(data),
        });
        return handleResponse(res);
    };

    const put = async (id, data) => {
        const res = await fetch(`${BASE}/${id}`, {
            method: 'PUT',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(data),
        });
        return handleResponse(res);
    };

    const del = async (id) => {
        const res = await fetch(`${BASE}/${id}`, {
            method: 'DELETE',
            headers: headers(),
            credentials: 'same-origin',
        });
        return handleResponse(res);
    };

    const patch = async (id, data) => {
        const res = await fetch(`${BASE}/${id}`, {
            method: 'PATCH',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(data),
        });
        return handleResponse(res);
    };

    const show = async (id) => {
        const res = await fetch(`${BASE}/${id}?_ajax=1`, {
            headers: headers(),
            credentials: 'same-origin',
        });
        return handleResponse(res);
    };

    const resetPassword = async (id, data) => {
        const res = await fetch(`${BASE}/${id}/reset-password`, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(data),
        });
        return handleResponse(res);
    };

    return { get, post, put, del, patch, show, resetPassword };
})();
