/**
 * Pill search — magnifying glass left, coral "Search" button right (ref style 3).
 */
export default function SearchField({
  value,
  onChange,
  onSearch,
  placeholder = 'Search anything',
  ariaLabel = 'Search anything',
  className = '',
  buttonLabel = 'Search',
}) {
  function handleSubmit(e) {
    e.preventDefault();
    onSearch?.(String(value || '').trim());
  }

  return (
    <form
      className={`sp-search-field ${className}`.trim()}
      onSubmit={handleSubmit}
      role="search"
    >
      <span className="sp-search-field-icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <circle cx="11" cy="11" r="6.25" stroke="currentColor" strokeWidth="1.75" />
          <path
            d="M16.2 16.2L20 20"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
          />
        </svg>
      </span>
      <input
        type="search"
        className="sp-search-field-input"
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        aria-label={ariaLabel}
        autoComplete="off"
        autoCorrect="off"
        autoCapitalize="off"
        spellCheck={false}
        inputMode="search"
      />
      <button type="submit" className="sp-search-field-btn">
        {buttonLabel}
      </button>
    </form>
  );
}
