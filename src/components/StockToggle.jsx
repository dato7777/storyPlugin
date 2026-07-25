export default function StockToggle({ status, onChange, disabled = false }) {
  const inStock = status === 'instock';

  return (
    <button
      type="button"
      className={`sp-stock-toggle ${inStock ? 'is-on' : 'is-off'}`}
      disabled={disabled}
      aria-pressed={inStock}
      aria-label={inStock ? 'In stock' : 'Out of stock'}
      onClick={() => onChange(inStock ? 'outofstock' : 'instock')}
    >
      <span className="sp-stock-toggle-knob" aria-hidden="true" />
      <span className="sp-stock-toggle-label" data-state={inStock ? 'in' : 'out'}>
        <span className="sp-stock-toggle-label-in">In stock</span>
        <span className="sp-stock-toggle-label-out">Out of stock</span>
      </span>
    </button>
  );
}
