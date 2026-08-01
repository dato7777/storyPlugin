/**
 * Per-section content editors for Design studio.
 * Shows only what currently exists as editable cards; pickers open on demand.
 */

import { useEffect, useMemo, useRef, useState } from 'react';
import SearchField from './SearchField.jsx';
import { nameMatchesAllWords } from '../utils/searchMatch.js';

function LiveMeta({ preview, isCustom }) {
  if (!preview) return null;
  return (
    <div className="sp-design-live-head">
      <p className="sp-design-editor-label">On homepage</p>
      <span className={`sp-design-live-badge ${isCustom ? 'is-custom' : 'is-auto'}`}>
        {isCustom ? 'Your selection' : 'Automatic'}
      </span>
    </div>
  );
}

/**
 * Top-of-section picker with card grid.
 * multi=true → select several, then Apply; multi=false → pick one immediately.
 */
function PickerSheet({
  open,
  title,
  options,
  excludeIds,
  onPick,
  onPickMany,
  multi = false,
  onClose,
  emptyLabel = 'Nothing to choose',
  showImages = false,
}) {
  const [q, setQ] = useState('');
  const [selected, setSelected] = useState(() => new Set());
  const rootRef = useRef(null);

  useEffect(() => {
    if (!open) return;
    setQ('');
    setSelected(new Set());
    // Keep the picker visible at the top of the open section.
    requestAnimationFrame(() => {
      rootRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  }, [open]);

  const filtered = useMemo(() => {
    if (!open) return [];
    const excluded = new Set((excludeIds || []).map(Number));
    return (options || []).filter((opt) => {
      if (excluded.has(Number(opt.id))) return false;
      return nameMatchesAllWords(opt.name, q);
    });
  }, [open, options, excludeIds, q]);

  if (!open) return null;

  const toggle = (id) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const applyMany = () => {
    const ids = [...selected];
    if (!ids.length) return;
    onPickMany?.(ids);
    onClose();
  };

  return (
    <div ref={rootRef} className="sp-design-picker" role="dialog" aria-label={title}>
      <div className="sp-design-picker-head">
        <strong>{title}</strong>
        <button type="button" className="sp-btn sp-btn-ghost sp-btn-sm" onClick={onClose}>
          Close
        </button>
      </div>
      <SearchField
        value={q}
        onChange={setQ}
        onSearch={setQ}
        placeholder="Search anything"
        className="sp-design-picker-search-wrap"
      />
      {multi ? (
        <div className="sp-design-picker-actions">
          <span className="sp-design-muted">
            {selected.size
              ? `${selected.size} selected`
              : 'Tap cards to select several, then Apply'}
          </span>
          <button
            type="button"
            className="sp-btn sp-btn-primary sp-btn-sm"
            disabled={!selected.size}
            onClick={applyMany}
          >
            Apply
          </button>
        </div>
      ) : null}
      {filtered.length === 0 ? (
        <p className="sp-design-muted">{emptyLabel}</p>
      ) : (
        <ul className={`sp-design-picker-grid${showImages ? ' has-images' : ''}`}>
          {filtered.map((opt) => {
            const id = Number(opt.id);
            const isOn = selected.has(id);
            return (
              <li key={id}>
                <button
                  type="button"
                  className={`sp-design-picker-card${isOn ? ' is-selected' : ''}`}
                  onClick={() => {
                    if (multi) {
                      toggle(id);
                      return;
                    }
                    onPick?.(id);
                    onClose();
                  }}
                >
                  {showImages ? (
                    <span className="sp-design-picker-card-media" aria-hidden="true">
                      {opt.image ? (
                        <img src={opt.image} alt="" />
                      ) : (
                        <span className="sp-design-picker-card-ph" />
                      )}
                    </span>
                  ) : null}
                  <span className="sp-design-picker-card-body">
                    <span className="sp-design-picker-card-name">{opt.name}</span>
                    {typeof opt.count === 'number' ? (
                      <span className="sp-design-check-meta">{opt.count}</span>
                    ) : null}
                  </span>
                  {multi && isOn ? (
                    <span className="sp-design-picker-card-check" aria-hidden="true">
                      ✓
                    </span>
                  ) : null}
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}

function ItemReel({
  items,
  onRemove,
  onReplace,
  onAdd,
  addLabel = 'Add',
  emptyLabel = 'Nothing selected yet',
  typeLabel = 'item',
}) {
  return (
    <div className="sp-design-reel">
      {items.length === 0 ? (
        <p className="sp-design-muted">{emptyLabel}</p>
      ) : (
        <ul className="sp-design-reel-track">
          {items.map((item) => (
            <li key={`${item.id}-${item.name}`} className="sp-design-reel-card">
              <button
                type="button"
                className="sp-design-reel-main"
                onClick={() => onReplace(item.id)}
                title={`Replace this ${typeLabel}`}
              >
                <span className="sp-design-reel-name">{item.name}</span>
                <span className="sp-design-reel-hint">Replace</span>
              </button>
              <button
                type="button"
                className="sp-design-reel-remove"
                aria-label={`Remove ${item.name}`}
                onClick={() => onRemove(item.id)}
              >
                ×
              </button>
            </li>
          ))}
        </ul>
      )}
      <button type="button" className="sp-btn sp-btn-soft sp-btn-sm" onClick={onAdd}>
        + {addLabel}
      </button>
    </div>
  );
}

function TitleFields({ content, onPatch }) {
  return (
    <div className="sp-design-field-grid">
      <label className="sp-label">
        Title (optional)
        <input
          type="text"
          value={content.title || ''}
          placeholder="Leave empty for default Hebrew title"
          onChange={(e) => onPatch({ title: e.target.value })}
        />
      </label>
      <label className="sp-label">
        Subtitle (optional)
        <input
          type="text"
          value={content.subtitle || ''}
          placeholder="Leave empty for default subtitle"
          onChange={(e) => onPatch({ subtitle: e.target.value })}
        />
      </label>
    </div>
  );
}

function resolveCategoryItems(ids, categories, liveItems, isCustom) {
  const locked = (ids || []).map(Number).filter(Boolean);
  if (isCustom) {
    return locked.map((id) => {
      const hit = categories.find((c) => Number(c.id) === id);
      const live = (liveItems || []).find((c) => Number(c.id) === id);
      return { id, name: hit?.name || live?.name || `Category #${id}` };
    });
  }
  return (liveItems || []).map((row) => ({
    id: Number(row.id),
    name: row.name,
  }));
}

function resolveProductItems(ids, productsById, liveItems, isCustom) {
  const locked = (ids || []).map(Number).filter(Boolean);
  if (isCustom) {
    return locked.map((id) => {
      const hit = productsById[id];
      const live = (liveItems || []).find((p) => Number(p.id) === id);
      return { id, name: hit?.name || live?.name || `Product #${id}` };
    });
  }
  return (liveItems || []).map((row) => ({
    id: Number(row.id),
    name: row.name,
  }));
}

function CategorySectionEditor({
  content,
  onChange,
  categories,
  livePreview,
  idsKey,
  addLabel,
}) {
  const patch = (partial) => onChange({ ...content, ...partial });
  const customIds = content[idsKey] || [];
  const isCustom = Boolean(content.custom) || customIds.length > 0;
  const items = useMemo(
    () => resolveCategoryItems(customIds, categories, livePreview?.items, isCustom),
    [customIds, categories, livePreview, isCustom]
  );
  const [picker, setPicker] = useState(null); // null | { mode: 'add'|'replace', replaceId }

  const commitIds = (nextIds, custom = true) =>
    patch({ custom, [idsKey]: nextIds });

  const ensureEditableIds = () => {
    if (isCustom) return [...customIds].map(Number).filter(Boolean);
    return items.map((i) => i.id).filter(Boolean);
  };

  const isAdd = picker?.mode === 'add';

  return (
    <div className="sp-design-section-editor">
      <PickerSheet
        open={Boolean(picker)}
        title={picker?.mode === 'replace' ? 'Replace category' : 'Add categories'}
        options={categories}
        excludeIds={isAdd ? items.map((i) => i.id) : []}
        multi={isAdd}
        onClose={() => setPicker(null)}
        onPick={(id) => {
          const base = ensureEditableIds();
          if (picker?.mode === 'replace') {
            commitIds(
              base.map((x) => (Number(x) === Number(picker.replaceId) ? id : x)),
              true
            );
          }
        }}
        onPickMany={(ids) => {
          const base = ensureEditableIds();
          const have = new Set(base.map(Number));
          const extra = ids.map(Number).filter((id) => id && !have.has(id));
          if (extra.length) commitIds([...base, ...extra], true);
        }}
      />
      <div className="sp-design-live">
        <LiveMeta preview={livePreview} isCustom={isCustom} />
        {livePreview?.note ? <p className="sp-design-muted">{livePreview.note}</p> : null}
        <TitleFields content={content} onPatch={patch} />
        <ItemReel
          items={items}
          typeLabel="category"
          addLabel={addLabel}
          emptyLabel="No categories on this section yet"
          onAdd={() => setPicker({ mode: 'add' })}
          onReplace={(id) => setPicker({ mode: 'replace', replaceId: id })}
          onRemove={(id) => {
            const base = ensureEditableIds().filter((x) => Number(x) !== Number(id));
            commitIds(base, true);
          }}
        />
        {!isCustom && items.length ? (
          <p className="sp-design-muted">
            Showing automatic picks. Add, replace, or remove to lock your own list.
          </p>
        ) : null}
        {isCustom ? (
          <button
            type="button"
            className="sp-btn sp-btn-ghost sp-btn-sm"
            onClick={() => commitIds([], false)}
          >
            Reset to automatic
          </button>
        ) : null}
      </div>
    </div>
  );
}

function selectableProductOptions(productsById) {
  return Object.values(productsById || {})
    .filter((p) => p && p.enabled !== false && (p.stock_status || 'instock') === 'instock')
    .map((p) => ({
      id: p.id,
      name: p.name,
      image: p.image || '',
    }))
    .sort((a, b) => String(a.name).localeCompare(String(b.name)));
}

function ProductListSectionEditor({ content, onChange, productsById, livePreview, addLabel }) {
  const patch = (partial) => onChange({ ...content, ...partial });
  const customIds = content.product_ids || [];
  const isCustom = Boolean(content.custom) || customIds.length > 0;
  const productOptions = useMemo(() => selectableProductOptions(productsById), [productsById]);
  const items = useMemo(
    () => resolveProductItems(customIds, productsById, livePreview?.items, isCustom),
    [customIds, productsById, livePreview, isCustom]
  );
  const [picker, setPicker] = useState(null);

  const commitIds = (nextIds, custom = true) => patch({ custom, product_ids: nextIds });
  const ensureEditableIds = () => {
    if (isCustom) return [...customIds].map(Number).filter(Boolean);
    return items.map((i) => i.id).filter(Boolean);
  };

  const isAdd = picker?.mode === 'add';

  return (
    <div className="sp-design-section-editor">
      <PickerSheet
        open={Boolean(picker)}
        title={picker?.mode === 'replace' ? 'Replace product' : 'Add products'}
        options={productOptions}
        excludeIds={isAdd ? items.map((i) => i.id) : []}
        multi={isAdd}
        showImages
        onClose={() => setPicker(null)}
        onPick={(id) => {
          const base = ensureEditableIds();
          if (picker?.mode === 'replace') {
            commitIds(
              base.map((x) => (Number(x) === Number(picker.replaceId) ? id : x)),
              true
            );
          }
        }}
        onPickMany={(ids) => {
          const base = ensureEditableIds();
          const have = new Set(base.map(Number));
          const extra = ids.map(Number).filter((id) => id && !have.has(id));
          if (extra.length) commitIds([...base, ...extra], true);
        }}
      />
      <div className="sp-design-live">
        <LiveMeta preview={livePreview} isCustom={isCustom} />
        {livePreview?.note ? <p className="sp-design-muted">{livePreview.note}</p> : null}
        <TitleFields content={content} onPatch={patch} />
        <ItemReel
          items={items}
          typeLabel="product"
          addLabel={addLabel}
          emptyLabel="No products on this section yet"
          onAdd={() => setPicker({ mode: 'add' })}
          onReplace={(id) => setPicker({ mode: 'replace', replaceId: id })}
          onRemove={(id) => {
            commitIds(
              ensureEditableIds().filter((x) => Number(x) !== Number(id)),
              true
            );
          }}
        />
        {!isCustom && items.length ? (
          <p className="sp-design-muted">
            Showing automatic picks. Add, replace, or remove to lock your own list.
          </p>
        ) : null}
        {isCustom ? (
          <button
            type="button"
            className="sp-btn sp-btn-ghost sp-btn-sm"
            onClick={() => commitIds([], false)}
          >
            Reset to automatic
          </button>
        ) : null}
      </div>
    </div>
  );
}

function SingleProductEditor({ content, onChange, productsById, livePreview, label }) {
  const patch = (partial) => onChange({ ...content, ...partial });
  const productId = Number(content.product_id) || 0;
  const isCustom = Boolean(content.custom) || productId > 0;
  const live = livePreview?.items?.[0];
  const current = useMemo(() => {
    if (isCustom) {
      if (!productId) return null;
      return {
        id: productId,
        name:
          productsById[productId]?.name ||
          live?.name ||
          (livePreview?.items || []).find((row) => Number(row.id) === productId)?.name ||
          `Product #${productId}`,
      };
    }
    // Automatic: always prefer live homepage preview payload.
    if (live?.name) {
      return { id: Number(live.id) || 0, name: live.name };
    }
    if (livePreview?.items?.length) {
      const first = livePreview.items[0];
      return { id: Number(first.id) || 0, name: first.name };
    }
    return null;
  }, [isCustom, productId, productsById, live, livePreview]);
  const [picker, setPicker] = useState(null);
  const productOptions = useMemo(() => selectableProductOptions(productsById), [productsById]);

  return (
    <div className="sp-design-section-editor">
      <PickerSheet
        open={Boolean(picker)}
        title={`Choose ${label}`}
        options={productOptions}
        excludeIds={[]}
        showImages
        onClose={() => setPicker(null)}
        onPick={(id) => patch({ custom: true, product_id: id })}
      />
      <div className="sp-design-live">
        <LiveMeta preview={livePreview} isCustom={isCustom} />
        {livePreview?.note ? <p className="sp-design-muted">{livePreview.note}</p> : null}
        {content.title !== undefined ? <TitleFields content={content} onPatch={patch} /> : null}
        <ItemReel
          items={current ? [current] : []}
          typeLabel="product"
          addLabel={current ? `Change ${label}` : `Choose ${label}`}
          emptyLabel={
            isCustom
              ? `No ${label} selected`
              : `Automatic ${label} will show here once the homepage preview loads`
          }
          onAdd={() => setPicker({ mode: current ? 'replace' : 'add', replaceId: current?.id })}
          onReplace={(id) => setPicker({ mode: 'replace', replaceId: id })}
          onRemove={() => patch({ custom: true, product_id: 0 })}
        />
        {!isCustom && current ? (
          <p className="sp-design-muted">Automatic pick (live on homepage). Replace to customize.</p>
        ) : null}
        {isCustom ? (
          <button
            type="button"
            className="sp-btn sp-btn-ghost sp-btn-sm"
            onClick={() => patch({ custom: false, product_id: 0 })}
          >
            Reset to automatic
          </button>
        ) : null}
      </div>
    </div>
  );
}

export default function SectionContentEditor({
  sectionId,
  content,
  onChange,
  categories = [],
  productsById = {},
  livePreview = null,
}) {
  const patch = (partial) => onChange({ ...content, ...partial });

  if (sectionId === 'editor-content') {
    return (
      <div className="sp-design-section-editor">
        <div className="sp-design-live">
          <LiveMeta preview={livePreview} isCustom={false} />
          <p className="sp-design-muted">
            This block shows the WordPress page body. Edit that page in the WP editor, not here.
          </p>
        </div>
      </div>
    );
  }

  if (sectionId === 'hero') {
    return (
      <CategorySectionEditor
        content={content}
        onChange={onChange}
        categories={categories}
        livePreview={livePreview}
        idsKey="chip_category_ids"
        addLabel="Add chip"
      />
    );
  }

  if (sectionId === 'story-rail') {
    return (
      <CategorySectionEditor
        content={content}
        onChange={onChange}
        categories={categories}
        livePreview={livePreview}
        idsKey="category_ids"
        addLabel="Add story"
      />
    );
  }

  if (sectionId === 'quick-reach') {
    return (
      <CategorySectionEditor
        content={content}
        onChange={onChange}
        categories={categories}
        livePreview={livePreview}
        idsKey="category_ids"
        addLabel="Add tile"
      />
    );
  }

  if (sectionId === 'pick-deck') {
    return (
      <SingleProductEditor
        content={content}
        onChange={onChange}
        productsById={productsById}
        livePreview={livePreview}
        label="featured product"
      />
    );
  }

  if (sectionId === 'heat-board') {
    return (
      <ProductListSectionEditor
        content={content}
        onChange={onChange}
        productsById={productsById}
        livePreview={livePreview}
        addLabel="Add product"
      />
    );
  }

  if (sectionId === 'showcase') {
    return (
      <ProductListSectionEditor
        content={content}
        onChange={onChange}
        productsById={productsById}
        livePreview={livePreview}
        addLabel="Add product"
      />
    );
  }

  if (sectionId === 'deal') {
    return (
      <SingleProductEditor
        content={content}
        onChange={onChange}
        productsById={productsById}
        livePreview={livePreview}
        label="deal product"
      />
    );
  }

  if (sectionId === 'trust') {
    const items = content.items?.length
      ? content.items
      : [
          { title: '', text: '' },
          { title: '', text: '' },
        ];
    const isCustom = (content.items || []).some((row) => row.title || row.text);
    return (
      <div className="sp-design-section-editor">
        <div className="sp-design-live">
          <LiveMeta preview={livePreview} isCustom={isCustom} />
          <p className="sp-design-muted">
            Leave items empty for the default trust marquee, or edit the pairs below.
          </p>
          {items.map((item, index) => (
            <div key={index} className="sp-design-reel-card sp-design-trust-card">
              <div className="sp-design-field-grid">
                <label className="sp-label">
                  Item {index + 1} title
                  <input
                    type="text"
                    value={item.title || ''}
                    onChange={(e) => {
                      const next = items.map((row, i) =>
                        i === index ? { ...row, title: e.target.value } : row
                      );
                      patch({ items: next });
                    }}
                  />
                </label>
                <label className="sp-label">
                  Text
                  <input
                    type="text"
                    value={item.text || ''}
                    onChange={(e) => {
                      const next = items.map((row, i) =>
                        i === index ? { ...row, text: e.target.value } : row
                      );
                      patch({ items: next });
                    }}
                  />
                </label>
              </div>
              <button
                type="button"
                className="sp-design-reel-remove"
                aria-label="Remove trust item"
                onClick={() => patch({ items: items.filter((_, i) => i !== index) })}
              >
                ×
              </button>
            </div>
          ))}
          <button
            type="button"
            className="sp-btn sp-btn-soft sp-btn-sm"
            onClick={() => patch({ items: [...items, { title: '', text: '' }] })}
          >
            + Add trust item
          </button>
        </div>
      </div>
    );
  }

  if (sectionId === 'cta') {
    const isCustom =
      Boolean(content.title) ||
      Boolean(content.text) ||
      Boolean(content.button_label) ||
      Boolean(content.button_url);
    return (
      <div className="sp-design-section-editor">
        <div className="sp-design-live">
          <LiveMeta preview={livePreview} isCustom={isCustom} />
          <div className="sp-design-field-grid">
            <label className="sp-label">
              Title
              <input
                type="text"
                value={content.title || ''}
                onChange={(e) => patch({ title: e.target.value })}
              />
            </label>
            <label className="sp-label">
              Button label
              <input
                type="text"
                value={content.button_label || ''}
                onChange={(e) => patch({ button_label: e.target.value })}
              />
            </label>
          </div>
          <label className="sp-label">
            Text
            <textarea
              rows={3}
              value={content.text || ''}
              onChange={(e) => patch({ text: e.target.value })}
            />
          </label>
          <label className="sp-label">
            Button URL
            <input
              type="url"
              value={content.button_url || ''}
              placeholder="https://…"
              onChange={(e) => patch({ button_url: e.target.value })}
            />
          </label>
          <p className="sp-design-muted">Empty fields keep the default CTA copy / shop link.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="sp-design-section-editor">
      <p className="sp-design-muted">No extra content options for this section yet.</p>
    </div>
  );
}
