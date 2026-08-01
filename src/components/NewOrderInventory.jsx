/**
 * New Order Inventory — categories first, product cards, Update Stock sync,
 * multi-select export into Disabled (SKU = New Order id).
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  exportNewOrderProducts,
  fetchNewOrderCatalog,
  fetchNewOrderStatus,
  saveNewOrderSettings,
  syncNewOrderStock,
} from '../api.js';
import { nameMatchesAllWords } from '../utils/searchMatch.js';
import SearchField from './SearchField.jsx';

function formatSyncedAt(iso) {
  if (!iso) return '';
  try {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString();
  } catch {
    return iso;
  }
}

function formatMoney(value, symbol) {
  if (value === null || value === undefined || value === '') return '—';
  const n = Number(value);
  if (!Number.isFinite(n)) return String(value);
  return `${symbol}${n.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
}

function humanizeKey(key) {
  return String(key || '')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/[._]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/^\w/, (c) => c.toUpperCase());
}

function formatDetailValue(value, currencySymbol) {
  if (value === null || value === undefined) return null;
  if (typeof value === 'boolean') return value ? 'Yes' : 'No';
  if (typeof value === 'number') return String(value);
  if (typeof value === 'string') {
    const trimmed = value.trim();
    return trimmed === '' ? null : trimmed;
  }
  if (Array.isArray(value)) {
    if (!value.length) return null;
    return value
      .map((item) => {
        if (item === null || item === undefined) return '';
        if (typeof item === 'object') {
          if (item.name != null) return String(item.name);
          if (item.id != null) return String(item.id);
          return JSON.stringify(item);
        }
        return String(item);
      })
      .filter(Boolean)
      .join(', ');
  }
  if (typeof value === 'object') {
    // Prefer nested name/id pairs as short text; else flatten separately.
    if (value.name != null || value.id != null) {
      const parts = [];
      if (value.name != null) parts.push(String(value.name));
      if (value.id != null) parts.push(`id ${value.id}`);
      return parts.join(' · ');
    }
    return null;
  }
  return String(value);
}

/**
 * Build labeled rows from normalized product + every raw API field.
 */
function buildDetailRows(product, currencySymbol) {
  if (!product) return [];
  const rows = [];
  const seen = new Set();

  const push = (label, value, { money = false } = {}) => {
    const key = label.toLowerCase();
    if (seen.has(key)) return;
    let display = money ? formatMoney(value, currencySymbol) : formatDetailValue(value, currencySymbol);
    if (money && (value === null || value === undefined || value === '')) return;
    if (display === null || display === '') return;
    if (money && display === '—') return;
    seen.add(key);
    rows.push({ label, value: display });
  };

  push('New Order ID', product.id);
  push('Name', product.name);
  push('SKU / barcode', product.sku || product.barcode);
  push('Additional barcodes', product.additional_barcodes);
  push('Stock', product.stock);
  push('Price', product.price, { money: true });
  push('Cost', product.cost, { money: true });
  push('Cost (no tax)', product.cost_no_tax, { money: true });
  push('Category', product.category_name);
  push('Category ID', product.category_id);
  push('Supplier', product.supplier_name);
  push('Supplier ID', product.supplier_id);
  push('Serial', product.is_serial);
  push('Tax free', product.is_tax_free);
  push('Is stock item', product.is_stock);
  push('Active', product.is_active);
  push('Description', product.description);

  const walk = (obj, prefix = '') => {
    if (!obj || typeof obj !== 'object') return;
    if (Array.isArray(obj)) {
      push(humanizeKey(prefix || 'List'), obj);
      return;
    }
    Object.keys(obj).forEach((key) => {
      const val = obj[key];
      const path = prefix ? `${prefix}.${key}` : key;
      if (val !== null && typeof val === 'object' && !Array.isArray(val)) {
        // Expand nested objects as their own rows (name/id) + children.
        const short = formatDetailValue(val, currencySymbol);
        if (short) push(humanizeKey(path), short);
        walk(val, path);
        return;
      }
      push(humanizeKey(path), val);
    });
  };

  if (product.raw && typeof product.raw === 'object') {
    walk(product.raw);
  }

  return rows;
}

function DetailRow({ label, value }) {
  return (
    <div className="sp-no-detail-row">
      <span className="sp-no-detail-label">{label}</span>
      <span className="sp-no-detail-value">{value}</span>
    </div>
  );
}

export default function NewOrderInventory({
  showToast,
  currencySymbol = '₪',
  onCountChange,
}) {
  const [configured, setConfigured] = useState(
    Boolean(window.storyphoneSettings?.neworderConfigured)
  );
  const [tokenInput, setTokenInput] = useState('');
  const [savingToken, setSavingToken] = useState(false);
  const [categories, setCategories] = useState([]);
  const [products, setProducts] = useState([]);
  const [syncedAt, setSyncedAt] = useState('');
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [activeCategoryId, setActiveCategoryId] = useState(null);
  const [selected, setSelected] = useState(null);
  const [checkedIds, setCheckedIds] = useState(() => new Set());

  const markConfigured = useCallback((value) => {
    const on = Boolean(value);
    setConfigured(on);
    if (window.storyphoneSettings) {
      window.storyphoneSettings.neworderConfigured = on;
    }
  }, []);

  const loadCatalog = useCallback(async () => {
    setLoading(true);
    try {
      const [status, data] = await Promise.all([
        fetchNewOrderStatus().catch(() => null),
        fetchNewOrderCatalog(),
      ]);
      const isOn = Boolean(status?.configured ?? data.configured);
      markConfigured(isOn);
      setCategories(data.categories || []);
      setProducts(data.products || []);
      setSyncedAt(data.synced_at || '');
      onCountChange?.(data.product_count || (data.products || []).length || 0);
    } catch (err) {
      showToast?.(err.message || 'Failed to load New Order catalog', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast, onCountChange, markConfigured]);

  useEffect(() => {
    loadCatalog();
  }, [loadCatalog]);

  useEffect(() => {
    const timer = setTimeout(() => setSearch(searchInput.trim()), 250);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const activeCategory = useMemo(
    () => categories.find((c) => String(c.id) === String(activeCategoryId)) || null,
    [categories, activeCategoryId]
  );

  const filteredCategories = useMemo(() => {
    if (!search) return categories;
    return categories.filter((c) => nameMatchesAllWords(c.name, search));
  }, [categories, search]);

  const categoryProducts = useMemo(() => {
    if (!activeCategoryId) return [];
    const catKey = String(activeCategoryId);
    return products.filter((p) => {
      const pid = p.category_id ? String(p.category_id) : '__none__';
      if (pid !== catKey) return false;
      return (
        nameMatchesAllWords(p.name, search) ||
        nameMatchesAllWords(p.sku || p.barcode, search) ||
        nameMatchesAllWords(p.id, search)
      );
    });
  }, [products, activeCategoryId, search]);

  const detailRows = useMemo(
    () => buildDetailRows(selected, currencySymbol),
    [selected, currencySymbol]
  );

  function toggleChecked(id, e) {
    e?.stopPropagation?.();
    const key = String(id);
    setCheckedIds((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  }

  async function handleSaveToken(e) {
    e.preventDefault();
    setSavingToken(true);
    try {
      const data = await saveNewOrderSettings({ token: tokenInput.trim() });
      if (!data.configured) {
        throw new Error(data.message || 'Token was not stored on the server');
      }
      markConfigured(true);
      setTokenInput('');
      showToast?.(data.message || 'New Order API token saved on server', 'success');
      await loadCatalog();
    } catch (err) {
      markConfigured(false);
      showToast?.(err.message || 'Could not save token', 'error');
    } finally {
      setSavingToken(false);
    }
  }

  async function handleUpdateStock() {
    setSyncing(true);
    try {
      const data = await syncNewOrderStock();
      markConfigured(true);
      setCategories(data.categories || []);
      setProducts(data.products || []);
      setSyncedAt(data.synced_at || '');
      setActiveCategoryId(null);
      setSelected(null);
      setCheckedIds(new Set());
      onCountChange?.(data.product_count || 0);
      showToast?.(
        `Updated stock — ${data.product_count || 0} in-stock items in ${data.category_count || 0} categories`,
        'success'
      );
    } catch (err) {
      showToast?.(err.message || 'Update Stock failed', 'error');
    } finally {
      setSyncing(false);
    }
  }

  async function handleExport() {
    const ids = [...checkedIds];
    if (!ids.length) {
      showToast?.('Select at least one product to export', 'error');
      return;
    }
    setExporting(true);
    try {
      const data = await exportNewOrderProducts(ids);
      const n = data.count || 0;
      const errN = (data.errors || []).length;
      showToast?.(
        errN
          ? `Exported ${n} to Disabled (${errN} failed)`
          : `Exported ${n} product${n === 1 ? '' : 's'} to Disabled (SKU = New Order ID)`,
        errN && !n ? 'error' : 'success'
      );
      setCheckedIds(new Set());
    } catch (err) {
      showToast?.(err.message || 'Export failed', 'error');
    } finally {
      setExporting(false);
    }
  }

  return (
    <div className="sp-no-page">
      <header className="sp-content-header">
        <div className="sp-content-heading">
          {activeCategory ? (
            <button
              type="button"
              className="sp-back-btn"
              onClick={() => {
                setActiveCategoryId(null);
                setSelected(null);
                setCheckedIds(new Set());
                setSearchInput('');
                setSearch('');
              }}
            >
              <span aria-hidden="true">←</span> Back to categories
            </button>
          ) : null}
          <div className="sp-title-row">
            <h2 className="sp-content-title">
              {activeCategory ? activeCategory.name : 'New Order Inventory'}
            </h2>
          </div>
          <p className="sp-content-subtitle">
            {activeCategory
              ? 'Select products to export into Disabled (SKU = New Order ID). Tap a card for full details.'
              : 'Live stock from New Order. Update Stock loads all in-stock products, then browse by category.'}
          </p>
          {syncedAt ? (
            <div className="sp-category-chip">
              <span className="sp-category-chip-dot" aria-hidden="true" />
              Last sync · {formatSyncedAt(syncedAt)} · {products.length} items ·{' '}
              {categories.length} categories
              {configured ? ' · API connected' : ''}
            </div>
          ) : null}
        </div>
      </header>

      {!configured ? (
        <form className="sp-no-token-card" onSubmit={handleSaveToken}>
          <h3>Connect New Order API</h3>
          <p>
            Paste your bearer token once. It is stored in the WordPress database on this server
            and works from any computer. You can also set{' '}
            <code>STORYPHONE_NEWORDER_API_TOKEN</code> in <code>wp-config.php</code>.
          </p>
          <label className="sp-label">
            API token
            <input
              type="password"
              value={tokenInput}
              onChange={(e) => setTokenInput(e.target.value)}
              placeholder="Bearer token"
              autoComplete="off"
              required
            />
          </label>
          <button type="submit" className="sp-btn sp-btn-primary" disabled={savingToken}>
            {savingToken ? 'Saving…' : 'Save token'}
          </button>
        </form>
      ) : null}

      <div className="sp-product-toolbar sp-no-toolbar">
        <SearchField
          value={searchInput}
          onChange={setSearchInput}
          onSearch={setSearch}
          placeholder="Search anything"
        />
        <div className="sp-content-meta sp-content-meta-inline">
          <span>
            {loading || syncing
              ? syncing
                ? 'Updating stock…'
                : 'Loading…'
              : activeCategory
                ? `${categoryProducts.length} item${categoryProducts.length === 1 ? '' : 's'}`
                : `${filteredCategories.length} categor${filteredCategories.length === 1 ? 'y' : 'ies'}`}
            {checkedIds.size ? ` · ${checkedIds.size} selected` : ''}
          </span>
        </div>
        <div className="sp-product-toolbar-actions">
          {activeCategory && checkedIds.size ? (
            <button
              type="button"
              className="sp-btn sp-btn-soft"
              onClick={handleExport}
              disabled={exporting}
            >
              {exporting ? 'Exporting…' : `Export to Disabled (${checkedIds.size})`}
            </button>
          ) : null}
          <button
            type="button"
            className="sp-btn sp-btn-primary sp-no-update-btn"
            onClick={handleUpdateStock}
            disabled={syncing || !configured}
          >
            {syncing ? 'Updating…' : 'Update Stock'}
          </button>
        </div>
      </div>

      <main className="sp-main sp-no-main">
        {loading && !products.length && !categories.length ? (
          <div className="sp-empty">Loading New Order catalog…</div>
        ) : !products.length && !syncing ? (
          <div className="sp-empty">
            {configured
              ? 'No New Order stock cached yet. Press Update Stock to import in-stock products.'
              : 'Save an API token, then press Update Stock.'}
          </div>
        ) : activeCategory ? (
          <div className={`sp-no-layout${selected ? ' has-detail' : ''}`}>
            <ul className="sp-no-product-grid">
              {categoryProducts.length === 0 ? (
                <li className="sp-empty">No products match this search.</li>
              ) : (
                categoryProducts.map((p) => {
                  const id = String(p.id);
                  const isChecked = checkedIds.has(id);
                  const isOpen = selected?.id === p.id;
                  return (
                    <li key={id}>
                      <div
                        className={`sp-no-product-card${isOpen ? ' is-selected' : ''}${isChecked ? ' is-checked' : ''}`}
                      >
                        <label className="sp-no-product-check" onClick={(e) => e.stopPropagation()}>
                          <input
                            type="checkbox"
                            checked={isChecked}
                            onChange={(e) => toggleChecked(id, e)}
                            aria-label={`Select ${p.name}`}
                          />
                        </label>
                        <button
                          type="button"
                          className="sp-no-product-card-main"
                          onClick={() => setSelected(p)}
                        >
                          <span className="sp-no-product-stock">
                            {p.stock === null || p.stock === undefined ? '—' : p.stock}
                          </span>
                          <strong className="sp-no-product-name">{p.name}</strong>
                          <span className="sp-no-product-meta">
                            ID {p.id}
                            {p.sku || p.barcode ? ` · barcode ${p.sku || p.barcode}` : ''}
                          </span>
                          <span className="sp-no-product-price">
                            {formatMoney(p.price, currencySymbol)}
                          </span>
                        </button>
                      </div>
                    </li>
                  );
                })
              )}
            </ul>

            {selected ? (
              <aside className="sp-no-detail" aria-label="Product details">
                <div className="sp-no-detail-head">
                  <h3>{selected.name}</h3>
                  <button
                    type="button"
                    className="sp-btn sp-btn-ghost sp-btn-sm"
                    onClick={() => setSelected(null)}
                  >
                    Close
                  </button>
                </div>
                <div className="sp-no-detail-actions">
                  <label className="sp-no-detail-select">
                    <input
                      type="checkbox"
                      checked={checkedIds.has(String(selected.id))}
                      onChange={(e) => toggleChecked(selected.id, e)}
                    />
                    Select for export
                  </label>
                  <button
                    type="button"
                    className="sp-btn sp-btn-soft sp-btn-sm"
                    disabled={exporting}
                    onClick={async () => {
                      setCheckedIds(new Set([String(selected.id)]));
                      setExporting(true);
                      try {
                        const data = await exportNewOrderProducts([String(selected.id)]);
                        showToast?.(
                          `Exported to Disabled · SKU ${selected.id}`,
                          data.count ? 'success' : 'error'
                        );
                      } catch (err) {
                        showToast?.(err.message || 'Export failed', 'error');
                      } finally {
                        setExporting(false);
                      }
                    }}
                  >
                    Export this to Disabled
                  </button>
                </div>
                {detailRows.map((row) => (
                  <DetailRow key={row.label} label={row.label} value={row.value} />
                ))}
              </aside>
            ) : null}
          </div>
        ) : (
          <ul className="sp-no-category-grid">
            {filteredCategories.length === 0 ? (
              <li className="sp-empty">No categories match this search.</li>
            ) : (
              filteredCategories.map((cat) => (
                <li key={cat.id}>
                  <button
                    type="button"
                    className="sp-no-category-card"
                    onClick={() => {
                      setActiveCategoryId(cat.id);
                      setSelected(null);
                      setCheckedIds(new Set());
                      setSearchInput('');
                      setSearch('');
                    }}
                  >
                    <span className="sp-no-category-count">{cat.count ?? 0}</span>
                    <strong className="sp-no-category-name">{cat.name}</strong>
                    <span className="sp-no-category-hint">View items →</span>
                  </button>
                </li>
              ))
            )}
          </ul>
        )}
      </main>
    </div>
  );
}
