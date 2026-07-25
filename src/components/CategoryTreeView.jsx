import { useEffect, useMemo, useState } from 'react';
import { buildCategoryTreeForExplorer } from '../categories.js';

function TreeNode({
  node,
  depth,
  expanded,
  selectedIds,
  onToggle,
  onOpen,
  onToggleSelect,
  onAddSubcategory,
}) {
  const hasChildren = node.children?.length > 0;
  const isOpen = expanded.has(node.id);
  const direct = node.itemCount || 0;
  const total = node.totalItems || 0;
  const isEmpty = direct < 1;
  const canSelect = isEmpty && !hasChildren;
  const checked = selectedIds.has(node.id);

  return (
    <div
      className={`sp-ctree-node depth-${Math.min(depth, 4)} ${isEmpty ? 'is-empty' : ''}`}
      style={{ '--sp-ctree-depth': depth }}
    >
      <div className="sp-ctree-row">
        {canSelect ? (
          <label
            className={`sp-check sp-ctree-check ${checked ? 'is-checked' : ''}`}
            onClick={(e) => e.stopPropagation()}
          >
            <input
              type="checkbox"
              checked={checked}
              onChange={() => onToggleSelect(node.id)}
              aria-label={`Select empty category ${node.name}`}
            />
            <span className="sp-check-box" aria-hidden="true" />
          </label>
        ) : (
          <span className="sp-ctree-check-spacer" aria-hidden="true" />
        )}

        {hasChildren ? (
          <button
            type="button"
            className={`sp-ctree-chevron ${isOpen ? 'is-open' : ''}`}
            aria-expanded={isOpen}
            aria-label={isOpen ? 'Collapse' : 'Expand'}
            onClick={() => onToggle(node.id)}
          >
            ›
          </button>
        ) : (
          <span className="sp-ctree-chevron-spacer" aria-hidden="true" />
        )}

        <button
          type="button"
          className={`sp-ctree-card ${isEmpty ? 'is-empty-card' : ''}`}
          onClick={() => onOpen(node.id)}
        >
          <span className="sp-ctree-card-mark" aria-hidden="true" />
          <span className="sp-ctree-card-body">
            <span className="sp-ctree-card-name">
              {node.name}
              {isEmpty ? <span className="sp-badge-soft">Empty</span> : null}
            </span>
            <span className="sp-ctree-card-meta">
              {hasChildren
                ? `${direct} direct · ${total} in branch`
                : `${direct} item${direct === 1 ? '' : 's'}`}
            </span>
          </span>
          <span className="sp-ctree-card-count">{total}</span>
          <span className="sp-ctree-card-go" aria-hidden="true">
            View
          </span>
        </button>

        <button
          type="button"
          className="sp-btn sp-btn-soft sp-btn-sm sp-ctree-add-sub"
          title={`Add subcategory under ${node.name}`}
          onClick={(e) => {
            e.stopPropagation();
            onAddSubcategory(node);
          }}
        >
          + Sub
        </button>
      </div>

      {hasChildren && isOpen ? (
        <div className="sp-ctree-children">
          {node.children.map((child) => (
            <TreeNode
              key={child.id}
              node={child}
              depth={depth + 1}
              expanded={expanded}
              selectedIds={selectedIds}
              onToggle={onToggle}
              onOpen={onOpen}
              onToggleSelect={onToggleSelect}
              onAddSubcategory={onAddSubcategory}
            />
          ))}
        </div>
      ) : null}
    </div>
  );
}

function collectExpandableIds(nodes, into = []) {
  nodes.forEach((node) => {
    if (node.children?.length) {
      into.push(node.id);
      collectExpandableIds(node.children, into);
    }
  });
  return into;
}

function collectSelectableIds(nodes, into = []) {
  nodes.forEach((node) => {
    const hasChildren = node.children?.length > 0;
    if ((node.itemCount || 0) < 1 && !hasChildren) {
      into.push(node.id);
    }
    if (hasChildren) collectSelectableIds(node.children, into);
  });
  return into;
}

export default function CategoryTreeView({
  categories,
  onOpenCategory,
  onCreateCategory,
  onDeleteCategories,
  saving = false,
}) {
  const [includeEmpty, setIncludeEmpty] = useState(false);
  const [expandMode, setExpandMode] = useState('expand');
  const [selectedIds, setSelectedIds] = useState(() => new Set());

  const tree = useMemo(
    () => buildCategoryTreeForExplorer(categories, includeEmpty),
    [categories, includeEmpty]
  );
  const expandableIds = useMemo(() => collectExpandableIds(tree), [tree]);
  const selectableIds = useMemo(() => collectSelectableIds(tree), [tree]);
  const [expanded, setExpanded] = useState(() => new Set());

  useEffect(() => {
    setExpanded(new Set(tree.map((n) => n.id)));
    setExpandMode('expand');
    setSelectedIds(new Set());
  }, [tree]);

  function toggle(id) {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function expandAll() {
    setExpanded(new Set(expandableIds));
    setExpandMode('expand');
  }

  function collapseAll() {
    setExpanded(new Set());
    setExpandMode('collapse');
  }

  function toggleSelect(id) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function handleBulkDelete() {
    const ids = Array.from(selectedIds);
    if (!ids.length) return;
    const ok = window.confirm(
      `Delete ${ids.length} empty categor${ids.length === 1 ? 'y' : 'ies'}? This cannot be undone.`
    );
    if (!ok) return;
    await onDeleteCategories(ids);
    setSelectedIds(new Set());
  }

  const emptyCount = categories.filter((c) => (Number(c.count) || 0) < 1).length;
  const withItems = categories.filter((c) => (Number(c.count) || 0) > 0).length;

  return (
    <div className="sp-ctree">
      <div className="sp-ctree-toolbar">
        <div className="sp-ctree-toolbar-left">
          <p className="sp-ctree-hint">
            {includeEmpty
              ? `${categories.length} categories · ${withItems} with items · ${emptyCount} empty`
              : `${withItems} categor${withItems === 1 ? 'y' : 'ies'} with items`}
          </p>
          <label className={`sp-check sp-ctree-include ${includeEmpty ? 'is-checked' : ''}`}>
            <input
              type="checkbox"
              checked={includeEmpty}
              onChange={(e) => setIncludeEmpty(e.target.checked)}
            />
            <span className="sp-check-box" aria-hidden="true" />
            <span className="sp-check-label">Include categories without items</span>
          </label>
        </div>

        <div className="sp-ctree-toolbar-actions">
          <div className="sp-seg" role="group" aria-label="Tree expand mode">
            <button
              type="button"
              className={`sp-seg-btn ${expandMode === 'expand' ? 'is-active' : ''}`}
              onClick={expandAll}
            >
              Expand
            </button>
            <button
              type="button"
              className={`sp-seg-btn ${expandMode === 'collapse' ? 'is-active' : ''}`}
              onClick={collapseAll}
            >
              Collapse
            </button>
          </div>
          <button
            type="button"
            className="sp-btn sp-btn-primary sp-btn-create-cat"
            onClick={() => onCreateCategory({ parentId: 0 })}
          >
            <span className="sp-btn-create-cat-plus" aria-hidden="true">
              +
            </span>
            Create New Category
          </button>
        </div>
      </div>

      {selectedIds.size > 0 ? (
        <div className="sp-ctree-bulk">
          <span>
            <strong>{selectedIds.size}</strong> empty categor
            {selectedIds.size === 1 ? 'y' : 'ies'} selected
          </span>
          <div className="sp-ctree-bulk-actions">
            <button
              type="button"
              className="sp-btn sp-btn-ghost sp-btn-sm"
              disabled={saving}
              onClick={() => setSelectedIds(new Set())}
            >
              Clear
            </button>
            <button
              type="button"
              className="sp-btn sp-btn-danger sp-btn-sm"
              disabled={saving}
              onClick={handleBulkDelete}
            >
              {saving ? 'Deleting…' : 'Delete selected'}
            </button>
          </div>
        </div>
      ) : null}

      {tree.length === 0 ? (
        <div className="sp-empty">
          {includeEmpty
            ? 'No categories yet. Create your first category to start building the tree.'
            : 'No categories with products yet. Enable “Include categories without items”, or create a new category.'}
        </div>
      ) : (
        <div className="sp-ctree-canvas" role="tree" aria-label="Category structure">
          {includeEmpty && selectableIds.length > 0 ? (
            <p className="sp-ctree-select-hint">
              Empty categories show a checkbox so you can delete them quickly.
            </p>
          ) : null}
          {tree.map((node) => (
            <TreeNode
              key={node.id}
              node={node}
              depth={0}
              expanded={expanded}
              selectedIds={selectedIds}
              onToggle={toggle}
              onOpen={onOpenCategory}
              onToggleSelect={toggleSelect}
              onAddSubcategory={(parent) =>
                onCreateCategory({ parentId: parent.id, parentName: parent.name })
              }
            />
          ))}
        </div>
      )}
    </div>
  );
}
