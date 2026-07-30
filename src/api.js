/**
 * API wrapper for StoryPhone Inventory Manager.
 * Uses window.storyphoneSettings from wp_localize_script — never hardcodes URLs or keys.
 *
 * Supports both pretty permalinks (/wp-json/...) and plain permalinks
 * (index.php?rest_route=/storyphone/v1/...), which WP Staging Free often uses.
 */

function getSettings() {
  const settings = window.storyphoneSettings;
  if (!settings || !settings.root || !settings.nonce) {
    throw new Error('storyphoneSettings is missing. Is the plugin admin page loaded correctly?');
  }
  return settings;
}

/**
 * Join REST root + path, handling rest_route query-style bases correctly.
 *
 * Pretty:  https://site/wp-json/storyphone/v1/ + products?page=1
 * Plain:   https://site/index.php?rest_route=/storyphone/v1/ + products?page=1
 *       -> https://site/index.php?rest_route=/storyphone/v1/products&page=1
 *
 * @param {string} root
 * @param {string} path  e.g. "products", "products/12", "products?page=1&per_page=24"
 * @returns {string}
 */
function buildRestUrl(root, path) {
  const cleanPath = String(path || '').replace(/^\//, '');
  const qIndex = cleanPath.indexOf('?');
  const pathname = (qIndex === -1 ? cleanPath : cleanPath.slice(0, qIndex)).replace(/^\/+|\/+$/g, '');
  const query = qIndex === -1 ? '' : cleanPath.slice(qIndex + 1);

  // Plain permalinks / staging: root already contains ?rest_route=
  if (/[?&]rest_route=/.test(root)) {
    const url = new URL(root, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
    const currentRoute = (url.searchParams.get('rest_route') || '/').replace(/\/+$/, '') || '';
    const nextRoute = pathname ? `${currentRoute}/${pathname}` : currentRoute;
    url.searchParams.set('rest_route', nextRoute.startsWith('/') ? nextRoute : `/${nextRoute}`);

    if (query) {
      const extra = new URLSearchParams(query);
      extra.forEach((value, key) => {
        url.searchParams.set(key, value);
      });
    }

    return url.toString();
  }

  // Pretty permalinks
  const base = root.replace(/\/?$/, '/');
  return query ? `${base}${pathname}?${query}` : `${base}${pathname}`;
}

function friendlyErrorMessage(data, status) {
  const raw =
    (data && (data.message || data.code)) ||
    `Request failed (${status})`;

  if (typeof raw !== 'string') {
    return 'Request failed';
  }

  // Avoid dumping full HTML error pages into the UI toast.
  const trimmed = raw.trim();
  if (trimmed.startsWith('<') || /<!DOCTYPE/i.test(trimmed) || /<html[\s>]/i.test(trimmed)) {
    return `Server returned an error page instead of JSON (${status}). Check permalinks / REST URL.`;
  }

  if (trimmed.length > 280) {
    return `${trimmed.slice(0, 280)}…`;
  }

  return trimmed;
}

async function request(path, options = {}) {
  const { root, nonce } = getSettings();
  const url = buildRestUrl(root, path);

  const headers = new Headers(options.headers || {});
  headers.set('X-WP-Nonce', nonce);

  if (options.body && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(url, {
    ...options,
    credentials: 'same-origin',
    headers,
  });

  let data = null;
  const text = await response.text();
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = { message: text };
    }
  }

  if (!response.ok) {
    const message = friendlyErrorMessage(data, response.status);
    const error = new Error(message);
    error.status = response.status;
    error.data = data;
    throw error;
  }

  return data;
}

export function fetchProducts({
  page = 1,
  perPage = 24,
  search = '',
  category = 0,
  collection = 'all',
} = {}) {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
    collection: collection || 'all',
  });
  if (search) params.set('search', search);
  if (category) params.set('category', String(category));
  return request(`products?${params.toString()}`);
}

export function fetchStats() {
  return request('stats');
}

export function fetchProduct(id) {
  return request(`products/${absInt(id)}`);
}

export function updateProduct(id, payload) {
  return request(`products/${absInt(id)}`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function createProduct(payload) {
  return request('products', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function trashProduct(id) {
  return request(`products/${absInt(id)}`, {
    method: 'DELETE',
  });
}

export function bulkProducts(ids, action) {
  return request('products/bulk', {
    method: 'POST',
    body: JSON.stringify({ ids, action }),
  });
}

export function uploadProductImage(id, file, { asFeatured = true } = {}) {
  const form = new FormData();
  form.append('image', file);
  form.append('as_featured', asFeatured ? '1' : '0');
  return request(`products/${absInt(id)}/image`, {
    method: 'POST',
    body: form,
  });
}

export function deleteProductImage(id, imageId) {
  const q = imageId ? `?image_id=${absInt(imageId)}` : '';
  return request(`products/${absInt(id)}/image${q}`, {
    method: 'DELETE',
  });
}

export function uploadMedia(file) {
  const form = new FormData();
  form.append('image', file);
  return request('media', {
    method: 'POST',
    body: form,
  });
}

export function fetchCategories() {
  return request('categories');
}

export function createCategory(payload) {
  return request('categories', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function updateCategory(id, payload) {
  return request(`categories/${absInt(id)}`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function bulkCategories(ids, action, extra = {}) {
  return request('categories/bulk', {
    method: 'POST',
    body: JSON.stringify({
      ids,
      action,
      ...extra,
    }),
  });
}

export function deleteCategory(id) {
  return request(`categories/${absInt(id)}`, {
    method: 'DELETE',
  });
}

function absInt(value) {
  const n = parseInt(value, 10);
  return Number.isFinite(n) && n > 0 ? n : 0;
}
