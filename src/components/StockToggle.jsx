export default function StockToggle({ status, onChange, disabled = false }) {
  const inStock = status === 'instock';

  return (
    <button
      type="button"
      className={`sp-stock-toggle ${inStock ? 'is-on' : 'is-off'}`}
      disabled={disabled}
      aria-pressed={inStock}
      onClick={() => onChange(inStock ? 'outofstock' : 'instock')}
    >
      <span className="sp-stock-toggle-knob" />
      <span className="sp-stock-toggle-label">{inStock ? 'In stock' : 'Out of stock'}</span>
    </button>
  );
}
