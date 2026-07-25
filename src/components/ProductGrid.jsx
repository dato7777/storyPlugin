import ProductCard from './ProductCard.jsx';

export default function ProductGrid({ products, currencySymbol, onSelect, onQuickUpdate }) {
  return (
    <div className="sp-grid">
      {products.map((product) => (
        <ProductCard
          key={product.id}
          product={product}
          currencySymbol={currencySymbol}
          onSelect={onSelect}
          onQuickUpdate={onQuickUpdate}
        />
      ))}
    </div>
  );
}
