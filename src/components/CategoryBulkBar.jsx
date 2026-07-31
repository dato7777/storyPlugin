export default function CategoryBulkBar({
  count,
  saving,
  onClear,
  onSetParent,
  onDelete,
}) {
  if (count < 1) return null;

  return (
    <div className="sp-bulk-bar" role="region" aria-label="Category bulk actions">
      <div className="sp-bulk-bar-inner">
        <div className="sp-bulk-count">
          <strong>{count}</strong>
          <span>
            {' '}
            categor{count === 1 ? 'y' : 'ies'} selected
          </span>
        </div>
        <div className="sp-bulk-actions">
          <button
            type="button"
            className="sp-btn sp-btn-soft"
            disabled={saving}
            onClick={onSetParent}
          >
            Set parent…
          </button>
          <button
            type="button"
            className="sp-btn sp-btn-danger"
            disabled={saving}
            onClick={onDelete}
          >
            Delete
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
