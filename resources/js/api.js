
export function getCookie(name) {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith(`${name}=`));

    return cookie
        ? decodeURIComponent(cookie.substring(name.length + 1))
        : '';
}

export async function apiRequest(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.method && options.method !== 'GET'
                ? { 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') }
                : {}),
            ...options.headers,
        },
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message ?? `请求失败（${response.status}）`);
        error.status = response.status;
        throw error;
    }

    return data;
}
