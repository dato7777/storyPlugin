import StockToggle from './StockToggle.jsx';
import QuantityStepper from './QuantityStepper.jsx';

export default function ProductCard({ product, currencySymbol, onSelect, onQuickUpdate }) {
  const inStock = product.stock_status === 'instock';

  return (
    <article className={`sp-card ${inStock ? 'is-in' : 'is-out'}`}>
      <button
        type="button"
        className="sp-card-main"
        onClick={() => onSelect(product.id)}
        aria-label={`Edit ${product.name}`}
      >
        <div className="sp-card-image">
          {product.image ? (
            <img src={product.image} alt="" loading="lazy" />
          ) : (
            <div className="sp-card-image-placeholder">No image</div>
          )}
        </div>
        <div className="sp-card-body">
          <h2 className="sp-card-name">{product.name}</h2>
          <div className="sp-card-meta">
            <span className="sp-card-price">
              {currencySymbol}
              {product.price || '0'}
            </span>
            {product.sku ? <span className="sp-card-sku">SKU {product.sku}</span> : null}
          </div>
        </div>
      </button>

      <div className="sp-card-controls" onClick={(e) => e.stopPropagation()}>
        <QuantityStepper
          value={product.stock_qty ?? 0}
          onChange={(qty) =>
            onQuickUpdate(product.id, {
              stock_quantity: qty,
              stock_status: qty > 0 ? 'instock' : 'outofstock',
            })
          }
        />
        <StockToggle
          status={product.stock_status}
          onChange={(status) => onQuickUpdate(product.id, { stock_status: status })}
        />
      </div>
    </article>
  );
}
