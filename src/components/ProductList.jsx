import StockToggle from './StockToggle.jsx';

function stripHtml(value) {
  if (!value) return '';
  return String(value)
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function formatPrice(currencySymbol, price) {
  const n = Number(price);
  if (Number.isFinite(n)) {
    return `${currencySymbol}${n.toLocaleString(undefined, {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    })}`;
  }
  return `${currencySymbol}${price || '0'}`;
}

export default function ProductList({
  products,
  currencySymbol,
  selectedId,
  selectedIds,
  onSelect,
  onToggleSelect,
  onToggleSelectAll,
  onQuickUpdate,
}) {
  const allSelected =
    products.length > 0 && products.every((p) => selectedIds.has(p.id));
  const someSelected = products.some((p) => selectedIds.has(p.id));

  return (
    <div className="sp-list" role="list">
      <div className="sp-list-toolbar">
        <label className={`sp-check ${allSelected ? 'is-checked' : ''} ${someSelected && !allSelected ? 'is-indeterminate' : ''}`}>
          <input
            type="checkbox"
            checked={allSelected}
            ref={(el) => {
              if (el) el.indeterminate = someSelected && !allSelected;
            }}
            onChange={onToggleSelectAll}
            aria-label="Select all items on this page"
          />
          <span className="sp-check-box" aria-hidden="true" />
          <span className="sp-check-label">Select page</span>
        </label>
      </div>

      {products.map((product) => {
        const active = selectedId === product.id;
        const checked = selectedIds.has(product.id);
        const desc = stripHtml(product.description || product.short_description || '');
        const cats = (product.categories || []).map((c) => c.name).join(' · ');
        const isDisabled =
          product.enabled === false ||
          product.catalog_visibility === 'hidden' ||
          (product.status && product.status !== 'publish');
        return (
          <article
            key={product.id}
            className={`sp-list-row ${active ? 'is-active' : ''} ${
              product.stock_status === 'outofstock' ? 'is-out' : ''
            } ${checked ? 'is-checked' : ''} ${isDisabled ? 'is-disabled-item' : ''}`}
            role="listitem"
          >
            <label
              className={`sp-check sp-list-check ${checked ? 'is-checked' : ''}`}
              onClick={(e) => e.stopPropagation()}
            >
              <input
                type="checkbox"
                checked={checked}
                onChange={() => onToggleSelect(product.id)}
                aria-label={`Select ${product.name}`}
              />
              <span className="sp-check-box" aria-hidden="true" />
            </label>

            <button
              type="button"
              className="sp-list-main"
              onClick={() => onSelect(product.id)}
            >
              <div className="sp-list-thumb">
                {product.image ? (
                  <img src={product.image} alt="" loading="lazy" />
                ) : (
                  <span className="sp-list-thumb-empty">—</span>
                )}
              </div>
              <div className="sp-list-copy">
                <h3 className="sp-list-name">
                  {product.name}
                  {isDisabled ? <span className="sp-badge-soft">Disabled</span> : null}
                </h3>
                <p className="sp-list-meta">
                  {product.sku ? `SKU ${product.sku}` : 'No SKU'}
                  {cats ? ` · ${cats}` : ''}
                  {desc ? ` · ${desc.slice(0, 80)}${desc.length > 80 ? '…' : ''}` : ''}
                </p>
              </div>
              <div className="sp-list-price">{formatPrice(currencySymbol, product.price)}</div>
            </button>

            <div className="sp-list-controls" onClick={(e) => e.stopPropagation()}>
              <span
                className="sp-stock-infinity"
                title="Stock quantity is managed in the edit panel"
                aria-label="Unlimited by default — open item to set quantity"
              >
                ∞
              </span>
              <StockToggle
                status={product.stock_status}
                onChange={(status) => onQuickUpdate(product.id, { stock_status: status })}
              />
            </div>
          </article>
        );
      })}
    </div>
  );
}
