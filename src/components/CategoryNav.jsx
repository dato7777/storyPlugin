import { useEffect, useMemo, useState } from 'react';
import { buildCategoryTree, getRootAncestorId } from '../categories.js';

function CategoryBranch({
  nodes,
  depth,
  selectedId,
  expanded,
  onToggle,
  onSelect,
}) {
  return nodes.map((node) => {
    const hasChildren = node.children?.length > 0;
    const isOpen = expanded.has(node.id);
    const isActive = selectedId === node.id;

    return (
      <div key={node.id} className="sp-nav-branch">
        <div
          className={`sp-nav-row depth-${Math.min(depth, 3)} ${isActive ? 'is-active' : ''}`}
        >
          {hasChildren ? (
            <button
              type="button"
              className={`sp-nav-chevron ${isOpen ? 'is-open' : ''}`}
              aria-label={isOpen ? 'Collapse' : 'Expand'}
              onClick={() => onToggle(node.id)}
            >
              ›
            </button>
          ) : (
            <span className="sp-nav-chevron-spacer" />
          )}
          <button
            type="button"
            className="sp-nav-item"
            onClick={() => onSelect(node.id)}
          >
            <span className="sp-nav-item-name">{node.name}</span>
            <span className="sp-nav-item-count">{node.count ?? 0}</span>
          </button>
        </div>
        {hasChildren && isOpen ? (
          <CategoryBranch
            nodes={node.children}
            depth={depth + 1}
            selectedId={selectedId}
            expanded={expanded}
            onToggle={onToggle}
            onSelect={onSelect}
          />
        ) : null}
      </div>
    );
  });
}

const COLLECTIONS = [
  { id: 'all', label: 'All items', icon: '◈' },
  { id: 'outofstock', label: 'Out of stock', icon: '○' },
  { id: 'disabled', label: 'Disabled', icon: '⊘' },
];

export default function CategoryNav({
  categories,
  selectedCategoryId,
  collection,
  stats,
  onSelectCategory,
  onSelectCollection,
}) {
  const tree = useMemo(() => buildCategoryTree(categories), [categories]);
  const [expanded, setExpanded] = useState(() => new Set());

  useEffect(() => {
    if (!selectedCategoryId) return;
    const rootId = getRootAncestorId(categories, selectedCategoryId);
    if (!rootId) return;

    setExpanded((prev) => {
      const next = new Set(prev);
      const byId = new Map(categories.map((c) => [c.id, c]));
      let current = byId.get(selectedCategoryId);
      while (current && Number(current.parent)) {
        next.add(Number(current.parent));
        current = byId.get(Number(current.parent));
      }
      if (rootId) next.add(rootId);
      return next;
    });
  }, [selectedCategoryId, categories]);

  function toggle(id) {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function countFor(id) {
    if (id === 'all') return stats?.all ?? 0;
    if (id === 'outofstock') return stats?.outofstock ?? 0;
    if (id === 'disabled') return stats?.disabled ?? 0;
    return 0;
  }

  return (
    <aside className="sp-nav" aria-label="Listing filters">
      <div className="sp-nav-inner">
        <div className="sp-nav-brand">
          <span className="sp-nav-brand-mark" aria-hidden="true" />
          <h1 className="sp-nav-title">Inventory</h1>
        </div>

        <section className="sp-nav-section">
          <h2 className="sp-nav-section-title">Collections</h2>
          <div className="sp-nav-collections">
            {COLLECTIONS.map((item) => {
              const active =
                collection === item.id &&
                (item.id !== 'all' || !selectedCategoryId);
              return (
                <button
                  key={item.id}
                  type="button"
                  className={`sp-nav-collection ${active ? 'is-active' : ''}`}
                  onClick={() => onSelectCollection(item.id)}
                >
                  <span className="sp-nav-collection-icon" aria-hidden="true">
                    {item.icon}
                  </span>
                  <span className="sp-nav-collection-label">{item.label}</span>
                  <span className="sp-nav-item-count">{countFor(item.id)}</span>
                </button>
              );
            })}
          </div>
        </section>

        <section className="sp-nav-section">
          <h2 className="sp-nav-section-title">Categories</h2>
          {tree.length === 0 ? (
            <p className="sp-nav-empty">No categories yet</p>
          ) : (
            <CategoryBranch
              nodes={tree}
              depth={0}
              selectedId={selectedCategoryId}
              expanded={expanded}
              onToggle={toggle}
              onSelect={onSelectCategory}
            />
          )}
        </section>
      </div>
    </aside>
  );
}
