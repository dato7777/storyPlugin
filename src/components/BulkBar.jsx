export default function BulkBar({ count, saving, onClear, onAction }) {
  if (count < 1) return null;

  return (
    <div className="sp-bulk-bar" role="region" aria-label="Bulk actions">
      <div className="sp-bulk-bar-inner">
        <div className="sp-bulk-count">
          <strong>{count}</strong>
          <span> selected</span>
        </div>
        <div className="sp-bulk-actions">
          <button
            type="button"
            className="sp-btn sp-btn-soft"
            disabled={saving}
            onClick={() => onAction('mark_instock')}
          >
            In stock
          </button>
          <button
            type="button"
            className="sp-btn sp-btn-soft"
            disabled={saving}
            onClick={() => onAction('mark_outofstock')}
          >
            Out of stock
          </button>
          <button
            type="button"
            className="sp-btn sp-btn-soft"
            disabled={saving}
            onClick={() => onAction('enable')}
          >
            Enable
          </button>
          <button
            type="button"
            className="sp-btn sp-btn-soft"
            disabled={saving}
            onClick={() => onAction('disable')}
          >
            Disable
          </button>
          <button
            type="button"
            className="sp-btn sp-btn-danger"
            disabled={saving}
            onClick={() => onAction('trash')}
          >
            Trash
          </button>
          <button
            type="button"
            className="sp-btn sp-btn-ghost"
            disabled={saving}
            onClick={onClear}
          >
            Clear
          </button>
        </div>
      </div>
    </div>
  );
}
