import { useEffect, useMemo, useState } from 'react';
import { buildPopulatedCategoryTree } from '../categories.js';

function TreeNode({
  node,
  depth,
  expanded,
  onToggle,
  onOpen,
}) {
  const hasChildren = node.children?.length > 0;
  const isOpen = expanded.has(node.id);
  const direct = node.itemCount || 0;
  const total = node.totalItems || 0;

  return (
    <div
      className={`sp-ctree-node depth-${Math.min(depth, 4)}`}
      style={{ '--sp-ctree-depth': depth }}
    >
      <div className="sp-ctree-row">
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
          className="sp-ctree-card"
          onClick={() => onOpen(node.id)}
        >
          <span className="sp-ctree-card-mark" aria-hidden="true" />
          <span className="sp-ctree-card-body">
            <span className="sp-ctree-card-name">{node.name}</span>
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
      </div>

      {hasChildren && isOpen ? (
        <div className="sp-ctree-children">
          {node.children.map((child) => (
            <TreeNode
              key={child.id}
              node={child}
              depth={depth + 1}
              expanded={expanded}
              onToggle={onToggle}
              onOpen={onOpen}
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

export default function CategoryTreeView({ categories, onOpenCategory }) {
  const tree = useMemo(() => buildPopulatedCategoryTree(categories), [categories]);
  const expandableIds = useMemo(() => collectExpandableIds(tree), [tree]);
  const [expanded, setExpanded] = useState(() => new Set());

  useEffect(() => {
    // Default: expand first level for a clear overview.
    setExpanded(new Set(tree.map((n) => n.id)));
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
  }

  function collapseAll() {
    setExpanded(new Set());
  }

  if (tree.length === 0) {
    return (
      <div className="sp-empty">
        No categories with products yet. Assign products to categories to see the tree.
      </div>
    );
  }

  const populatedCount = categories.filter((c) => (Number(c.count) || 0) > 0).length;

  return (
    <div className="sp-ctree">
      <div className="sp-ctree-toolbar">
        <p className="sp-ctree-hint">
          {populatedCount} categor{populatedCount === 1 ? 'y' : 'ies'} with items · click a
          category to open its products
        </p>
        <div className="sp-ctree-toolbar-actions">
          <button type="button" className="sp-btn sp-btn-soft sp-btn-sm" onClick={expandAll}>
            Expand all
          </button>
          <button type="button" className="sp-btn sp-btn-ghost sp-btn-sm" onClick={collapseAll}>
            Collapse all
          </button>
        </div>
      </div>

      <div className="sp-ctree-canvas" role="tree" aria-label="Category structure">
        {tree.map((node) => (
          <TreeNode
            key={node.id}
            node={node}
            depth={0}
            expanded={expanded}
            onToggle={toggle}
            onOpen={onOpenCategory}
          />
        ))}
      </div>
    </div>
  );
}
