import { useEffect, useMemo, useState } from 'react';
import { buildCategoryTree } from '../categories.js';

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
    const containsSelected =
      hasChildren && selectedId ? categoryContainsId(node, selectedId) : false;

    return (
      <div
        key={node.id}
        className={[
          'sp-nav-branch',
          hasChildren ? 'has-children' : 'is-leaf',
          isOpen ? 'is-open' : '',
          isActive ? 'is-selected' : '',
          containsSelected ? 'is-ancestor' : '',
        ]
          .filter(Boolean)
          .join(' ')}
      >
        <div
          className={`sp-nav-row depth-${Math.min(depth, 3)} ${isActive ? 'is-active' : ''}`}
        >
          {hasChildren ? (
            <button
              type="button"
              className={`sp-nav-expand ${isOpen ? 'is-open' : ''}`}
              aria-label={isOpen ? 'Collapse subcategories' : 'Show subcategories'}
              aria-expanded={isOpen}
              onClick={() => onToggle(node.id)}
            >
              <span className="sp-nav-expand-icon" aria-hidden="true">
                {isOpen ? '▼' : '▶'}
              </span>
            </button>
          ) : (
            <span className="sp-nav-expand-spacer" aria-hidden="true" />
          )}

          <button
            type="button"
            className={`sp-nav-item ${hasChildren ? 'has-kids' : ''}`}
            onClick={() => {
              if (hasChildren) onToggle(node.id);
              onSelect(node.id);
            }}
          >
            {hasChildren ? (
              <span className="sp-nav-folder" aria-hidden="true" title="Has subcategories">
                ▣
              </span>
            ) : (
              <span className="sp-nav-dot" aria-hidden="true" />
            )}
            <span className="sp-nav-item-name">{node.name}</span>
            <span className="sp-nav-item-count">{node.count ?? 0}</span>
          </button>
        </div>

        {hasChildren && isOpen ? (
          <div className="sp-nav-children" role="group" aria-label={`Subcategories of ${node.name}`}>
            <div className="sp-nav-children-label">
              <span className="sp-nav-children-arrow" aria-hidden="true">
                ↓
              </span>
              Under {node.name}
            </div>
            <CategoryBranch
              nodes={node.children}
              depth={depth + 1}
              selectedId={selectedId}
              expanded={expanded}
              onToggle={onToggle}
              onSelect={onSelect}
            />
          </div>
        ) : null}
      </div>
    );
  });
}

function categoryContainsId(node, id) {
  if (!node?.children?.length) return false;
  for (const child of node.children) {
    if (child.id === id) return true;
    if (categoryContainsId(child, id)) return true;
  }
  return false;
}

const COLLECTIONS = [
  { id: 'all', label: 'All items', icon: '◈' },
  { id: 'categories', label: 'All Categories', icon: '▤' },
  { id: 'instock', label: 'In Stock', icon: '●' },
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

    // Keep ancestor path open so the selected category stays visible,
    // but do not force-expand the selected node itself (click toggles that).
    setExpanded((prev) => {
      const next = new Set(prev);
      const byId = new Map(categories.map((c) => [c.id, c]));
      let current = byId.get(selectedCategoryId);
      while (current && Number(current.parent)) {
        next.add(Number(current.parent));
        current = byId.get(Number(current.parent));
      }
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
    // All Categories badge = every category, including empty.
    if (id === 'categories') return categories?.length ?? 0;
    if (id === 'instock') return stats?.instock ?? 0;
    if (id === 'outofstock') return stats?.outofstock ?? 0;
    if (id === 'disabled') return stats?.disabled ?? 0;
    return 0;
  }

  function isActive(itemId) {
    if (itemId === 'all') {
      return collection === 'all' && !selectedCategoryId;
    }
    if (itemId === 'categories') {
      return collection === 'categories' && !selectedCategoryId;
    }
    return collection === itemId;
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
            {COLLECTIONS.map((item) => (
              <button
                key={item.id}
                type="button"
                className={`sp-nav-collection ${isActive(item.id) ? 'is-active' : ''}`}
                onClick={() => onSelectCollection(item.id)}
              >
                <span className="sp-nav-collection-icon" aria-hidden="true">
                  {item.icon}
                </span>
                <span className="sp-nav-collection-label">{item.label}</span>
                <span className="sp-nav-item-count">{countFor(item.id)}</span>
              </button>
            ))}
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
