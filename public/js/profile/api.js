const ProfileAPI = (() => {
    const BASE = '/profile';

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

    const updateProfile = async (data) => {
        const res = await fetch(BASE, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(data),
        });
        return handleResponse(res);
    };

    const changePassword = async (data) => {
        const res = await fetch(`${BASE}/password`, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(data),
        });
        return handleResponse(res);
    };

    return { updateProfile, changePassword };
})();
