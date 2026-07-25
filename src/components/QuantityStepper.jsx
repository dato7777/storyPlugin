export default function QuantityStepper({ value, onChange, disabled = false, min = 0 }) {
  const qty = Number.isFinite(Number(value)) ? Math.max(min, Number(value)) : min;

  function bump(delta) {
    const next = Math.max(min, qty + delta);
    if (next !== qty) onChange(next);
  }

  return (
    <div className="sp-stepper">
      <button
        type="button"
        className="sp-stepper-btn"
        disabled={disabled || qty <= min}
        aria-label="Decrease quantity"
        onClick={() => bump(-1)}
      >
        −
      </button>
      <input
        className="sp-stepper-input"
        type="number"
        min={min}
        value={qty}
        disabled={disabled}
        aria-label="Stock quantity"
        onChange={(e) => {
          const next = Math.max(min, parseInt(e.target.value, 10) || min);
          onChange(next);
        }}
      />
      <button
        type="button"
        className="sp-stepper-btn"
        disabled={disabled}
        aria-label="Increase quantity"
        onClick={() => bump(1)}
      >
        +
      </button>
    </div>
  );
}
