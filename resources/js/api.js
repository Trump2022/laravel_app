const tokenKey = 'admin_access_token';// 适用于jwt模式
const tokenRotatedAtKey = 'admin_access_token_rotated_at';
const tokenRotationIntervalMs = 30 * 60 * 1000;//30分钟更换一个token，避免token被盗用后长期有效。刷新接口会返回新的token和过期时间，前端会保存新的token和过期时间，并设置刷新定时器。

function clearTokenIfCurrent(token) {
    if (!token || localStorage.getItem(tokenKey) !== token) {
        return;
    }

    localStorage.removeItem(tokenKey);
    localStorage.removeItem(tokenRotatedAtKey);
    localStorage.removeItem('admin_access_token_expires_at');
    window.dispatchEvent(new Event('admin-auth-expired'));
}

async function requestJson(url, options, token) {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
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

async function getTokenForRequest() {
    const rotateToken = async () => {
        const currentToken = localStorage.getItem(tokenKey);

        if (!currentToken) {
            return null;
        }

        const rotatedAt = Number(localStorage.getItem(tokenRotatedAtKey));

        if (!rotatedAt) {
            localStorage.setItem(tokenRotatedAtKey, String(Date.now()));
            return currentToken;
        }

        if (Date.now() - rotatedAt < tokenRotationIntervalMs) {
            return currentToken;
        }

        try {
            const response = await requestJson(
                '/api/admin/refresh',
                { method: 'POST' },
                currentToken,
            );

            if (!response.access_token) {
                throw new Error('刷新接口没有返回 access_token。');
            }

            localStorage.setItem(tokenKey, response.access_token);
            localStorage.setItem(tokenRotatedAtKey, String(Date.now()));

            return response.access_token;
        } catch (error) {
            if (error.status === 401) {
                clearTokenIfCurrent(currentToken);
                throw error;
            }

            return currentToken;
        }
    };

    if (navigator.locks?.request) {
        return navigator.locks.request('admin-jwt-rotation', rotateToken);
    }

    return rotateToken();
}

export async function apiRequest(url, options = {}) {
    // const token = sessionStorage.getItem(tokenKey);//默认为会按选项卡隔离

    const pathname = new URL(url, window.location.origin).pathname;
    const isLoginRequest = pathname === '/api/admin/login';
    const isLogoutRequest = pathname === '/api/admin/logout';
    const isRefreshRequest = pathname === '/api/admin/refresh';

    //登录请求不会携带上次残留的 token。Web Locks 不可用的浏览器仍没有跨标签并发保护；常见的 HTTPS/localhost 环境可用 Web Locks。
    let token = isLoginRequest ? null : localStorage.getItem(tokenKey);//

    if (token && !isLoginRequest && !isLogoutRequest && !isRefreshRequest) {
        token = await getTokenForRequest();
    }

    try {
        return await requestJson(url, options, token);
    } catch (error) {
        // // sessionStorage.removeItem(tokenKey);
        // localStorage.removeItem(tokenKey); //localStorage 会让 token 在多个标签间共享
        // localStorage.removeItem('admin_access_token_expires_at');//清除过期时间
        const latestToken = localStorage.getItem(tokenKey);

        if (
            error.status === 401 &&
            token &&
            latestToken &&
            latestToken !== token
        ) {
            try {
                return await requestJson(url, options, latestToken);
            } catch (retryError) {
                if (retryError.status === 401) {
                    clearTokenIfCurrent(latestToken);
                }

                throw retryError;
            }
        }

        if (error.status === 401) {
            clearTokenIfCurrent(token);
        }

        throw error;
    }
}

export function getCookie(name) {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith(`${name}=`));

    return cookie
        ? decodeURIComponent(cookie.substring(name.length + 1))
        : '';
}

// 适用于session模式
// export async function apiRequest(url, options = {}) {
//     const response = await fetch(url, {
//         ...options,
//         credentials: 'same-origin',
//         headers: {
//             Accept: 'application/json',
//             ...(options.body ? { 'Content-Type': 'application/json' } : {}),
//             ...(options.method && options.method !== 'GET'
//                 ? { 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') }
//                 : {}),
//             ...options.headers,
//         },
//     });
//
//     const data = await response.json().catch(() => ({}));
//
//     if (!response.ok) {
//         const error = new Error(data.message ?? `请求失败（${response.status}）`);
//         error.status = response.status;
//         throw error;
//     }
//
//     return data;
// }



