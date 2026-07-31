import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { buildCategoryTreeForExplorer, getParentOptionsForCategory } from '../categories.js';
import CategoryBulkBar from './CategoryBulkBar.jsx';

function TreeNode({
  node,
  depth,
  expanded,
  selectedIds,
  onToggle,
  onOpen,
  onToggleSelect,
  onEditCategory,
  onAddSubcategory,
}) {
  const hasChildren = node.children?.length > 0;
  const isOpen = expanded.has(node.id);
  const direct = node.itemCount || 0;
  const total = node.totalItems || 0;
  const isEmpty = direct < 1;
  const checked = selectedIds.has(node.id);
  const thumb = node.thumbnail_url || '';

  return (
    <div
      className={`sp-ctree-node depth-${Math.min(depth, 4)} ${isEmpty ? 'is-empty' : ''}`}
      style={{ '--sp-ctree-depth': depth }}
    >
      <div className="sp-ctree-row">
        <label
          className={`sp-check sp-ctree-check ${checked ? 'is-checked' : ''}`}
          onClick={(e) => e.stopPropagation()}
        >
          <input
            type="checkbox"
            checked={checked}
            onChange={() => onToggleSelect(node.id)}
            aria-label={`Select category ${node.name}`}
          />
          <span className="sp-check-box" aria-hidden="true" />
        </label>

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
          {thumb ? (
            <img className="sp-ctree-card-thumb" src={thumb} alt="" />
          ) : (
            <span className="sp-ctree-card-mark" aria-hidden="true" />
          )}
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
          className="sp-btn sp-btn-soft sp-btn-sm"
          title={`Edit ${node.name}`}
          onClick={(e) => {
            e.stopPropagation();
            onEditCategory(node);
          }}
        >
          Edit
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
              onEditCategory={onEditCategory}
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

function collectAllIds(nodes, into = []) {
  nodes.forEach((node) => {
    into.push(node.id);
    if (node.children?.length) collectAllIds(node.children, into);
  });
  return into;
}

export default function CategoryTreeView({
  categories,
  onOpenCategory,
  onCreateCategory,
  onEditCategory,
  onBulkSetParent,
  onBulkDelete,
  saving = false,
}) {
  const [scope, setScope] = useState('all'); // 'active' | 'all'
  const includeEmpty = scope === 'all';
  const [expandMode, setExpandMode] = useState('expand');
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [bulkParentOpen, setBulkParentOpen] = useState(false);
  const [bulkParentId, setBulkParentId] = useState(0);
  const [confirmBulkDelete, setConfirmBulkDelete] = useState(false);

  const tree = useMemo(
    () => buildCategoryTreeForExplorer(categories, includeEmpty),
    [categories, includeEmpty]
  );
  const expandableIds = useMemo(() => collectExpandableIds(tree), [tree]);
  const allVisibleIds = useMemo(() => collectAllIds(tree), [tree]);
  const [expanded, setExpanded] = useState(() => new Set());

  const parentOptions = useMemo(
    () => getParentOptionsForCategory(categories, 0, Array.from(selectedIds)),
    [categories, selectedIds]
  );

  useEffect(() => {
    setExpanded(new Set(tree.map((n) => n.id)));
    setExpandMode('expand');
    setSelectedIds(new Set());
    setBulkParentOpen(false);
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

  async function handleBulkSetParent(e) {
    e.preventDefault();
    const ids = Array.from(selectedIds);
    if (!ids.length) return;
    await onBulkSetParent(ids, bulkParentId || 0);
    setSelectedIds(new Set());
    setBulkParentOpen(false);
    setBulkParentId(0);
  }

  async function handleBulkDeleteConfirm() {
    const ids = Array.from(selectedIds);
    if (!ids.length) return;
    await onBulkDelete?.(ids);
    setSelectedIds(new Set());
    setConfirmBulkDelete(false);
    setBulkParentOpen(false);
  }

  const withItems = categories.filter((c) => (Number(c.count) || 0) > 0).length;
  const totalCategories = categories.length;

  return (
    <div className="sp-ctree">
      <div className="sp-ctree-toolbar">
        <div className="sp-ctree-toolbar-left">
          <div
            className={`sp-scope-toggle scope-${scope}`}
            role="group"
            aria-label="Category scope"
          >
            <span className="sp-scope-toggle-thumb" aria-hidden="true" />
            <button
              type="button"
              className={`sp-scope-toggle-btn ${scope === 'active' ? 'is-active' : ''}`}
              aria-pressed={scope === 'active'}
              onClick={() => setScope('active')}
            >
              <span className="sp-scope-toggle-text">Active Categories</span>
              <span className="sp-scope-toggle-count">{withItems}</span>
            </button>
            <button
              type="button"
              className={`sp-scope-toggle-btn ${scope === 'all' ? 'is-active' : ''}`}
              aria-pressed={scope === 'all'}
              onClick={() => setScope('all')}
            >
              <span className="sp-scope-toggle-text">All Categories</span>
              <span className="sp-scope-toggle-count">{totalCategories}</span>
            </button>
          </div>
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

      {bulkParentOpen && selectedIds.size > 0
        ? createPortal(
            <div
              className="sp-modal-backdrop"
              onClick={() => !saving && setBulkParentOpen(false)}
            >
              <form
                className="sp-modal sp-modal-sm"
                role="dialog"
                aria-modal="true"
                onClick={(e) => e.stopPropagation()}
                onSubmit={handleBulkSetParent}
              >
                <h2>Set parent category</h2>
                <p>
                  Applies to {selectedIds.size} selected categor
                  {selectedIds.size === 1 ? 'y' : 'ies'}.
                </p>
                <label className="sp-label">
                  Parent
                  <select
                    value={bulkParentId || ''}
                    onChange={(e) =>
                      setBulkParentId(e.target.value ? Number(e.target.value) : 0)
                    }
                  >
                    <option value="">None (make top-level)</option>
                    {parentOptions.map((cat) => (
                      <option key={cat.id} value={cat.id}>
                        {cat.label}
                      </option>
                    ))}
                  </select>
                </label>
                <div className="sp-modal-actions">
                  <button
                    type="button"
                    className="sp-btn sp-btn-ghost"
                    disabled={saving}
                    onClick={() => setBulkParentOpen(false)}
                  >
                    Cancel
                  </button>
                  <button type="submit" className="sp-btn sp-btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : 'Apply parent'}
                  </button>
                </div>
              </form>
            </div>,
            document.body
          )
        : null}

      {confirmBulkDelete && selectedIds.size > 0
        ? createPortal(
            <div
              className="sp-modal-backdrop"
              onClick={() => !saving && setConfirmBulkDelete(false)}
            >
              <div
                className="sp-modal sp-modal-sm"
                role="dialog"
                aria-modal="true"
                onClick={(e) => e.stopPropagation()}
              >
                <h2>Delete {selectedIds.size} categor{selectedIds.size === 1 ? 'y' : 'ies'}?</h2>
                <p>
                  Categories with subcategories will be skipped. Products stay in the shop but lose
                  this category link. This cannot be undone.
                </p>
                <div className="sp-modal-actions">
                  <button
                    type="button"
                    className="sp-btn sp-btn-ghost"
                    disabled={saving}
                    onClick={() => setConfirmBulkDelete(false)}
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    className="sp-btn sp-btn-danger"
                    disabled={saving}
                    onClick={handleBulkDeleteConfirm}
                  >
                    {saving ? 'Deleting…' : 'Delete'}
                  </button>
                </div>
              </div>
            </div>,
            document.body
          )
        : null}

      {tree.length === 0 ? (
        <div className="sp-empty">
          {includeEmpty
            ? 'No categories yet. Create your first category to start building the tree.'
            : 'No active categories yet. Switch to “All Categories”, or create a category and add products.'}
        </div>
      ) : (
        <div className="sp-ctree-canvas" role="tree" aria-label="Category structure">
          <p className="sp-ctree-select-hint">
            Select categories for bulk Delete or Set parent. Use Edit for name, parent, and icon.
            {allVisibleIds.length > 0 ? ` · ${allVisibleIds.length} shown` : ''}
          </p>
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
              onEditCategory={onEditCategory}
              onAddSubcategory={(parent) =>
                onCreateCategory({ parentId: parent.id, parentName: parent.name })
              }
            />
          ))}
        </div>
      )}

      <CategoryBulkBar
        count={selectedIds.size}
        saving={saving}
        onClear={() => {
          setSelectedIds(new Set());
          setBulkParentOpen(false);
          setConfirmBulkDelete(false);
        }}
        onSetParent={() => {
          setBulkParentId(0);
          setConfirmBulkDelete(false);
          setBulkParentOpen(true);
        }}
        onDelete={() => {
          setBulkParentOpen(false);
          setConfirmBulkDelete(true);
        }}
      />
    </div>
  );
}
