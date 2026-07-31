/**
 * Per-section content editors for Design studio.
 */

function IdChecklist({ options, selected, onChange, emptyLabel = 'Nothing available yet' }) {
  if (!options.length) {
    return <p className="sp-design-muted">{emptyLabel}</p>;
  }
  const selectedSet = new Set(selected.map(Number));
  return (
    <ul className="sp-design-check-list">
      {options.map((opt) => {
        const on = selectedSet.has(Number(opt.id));
        return (
          <li key={opt.id}>
            <label className={`sp-design-check-row ${on ? 'is-on' : ''}`}>
              <input
                type="checkbox"
                checked={on}
                onChange={() => {
                  const id = Number(opt.id);
                  if (on) onChange(selected.filter((x) => Number(x) !== id));
                  else onChange([...selected, id]);
                }}
              />
              <span className="sp-design-check-name">{opt.name}</span>
              {typeof opt.count === 'number' ? (
                <span className="sp-design-check-meta">{opt.count}</span>
              ) : null}
            </label>
          </li>
        );
      })}
    </ul>
  );
}

function ProductIdList({ ids, onChange, productsById }) {
  return (
    <div className="sp-design-id-stack">
      {(ids || []).map((id, index) => (
        <div key={`${id}-${index}`} className="sp-design-id-row">
          <select
            value={id || ''}
            onChange={(e) => {
              const next = [...ids];
              next[index] = Number(e.target.value) || 0;
              onChange(next.filter(Boolean));
            }}
          >
            <option value="">Select product…</option>
            {Object.values(productsById).map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
          <button
            type="button"
            className="sp-btn sp-btn-ghost sp-btn-sm"
            onClick={() => onChange(ids.filter((_, i) => i !== index))}
          >
            Remove
          </button>
        </div>
      ))}
      <button
        type="button"
        className="sp-btn sp-btn-soft sp-btn-sm"
        onClick={() => onChange([...(ids || []), 0])}
      >
        + Add product
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

export default function SectionContentEditor({
  sectionId,
  content,
  onChange,
  categories = [],
  productsById = {},
}) {
  const patch = (partial) => onChange({ ...content, ...partial });

  if (sectionId === 'editor-content') {
    return (
      <p className="sp-design-muted">
        This block shows the WordPress page body. Edit that page in the WP editor (classic /
        Elementor), not here.
      </p>
    );
  }

  if (sectionId === 'hero') {
    return (
      <div className="sp-design-section-editor">
        <TitleFields content={content} onPatch={patch} />
        <p className="sp-design-editor-label">Popular chips under search (categories)</p>
        <IdChecklist
          options={categories}
          selected={content.chip_category_ids || []}
          onChange={(chip_category_ids) => patch({ chip_category_ids })}
        />
        <p className="sp-design-muted">Empty = automatic popular categories.</p>
      </div>
    );
  }

  if (sectionId === 'story-rail' || sectionId === 'quick-reach') {
    return (
      <div className="sp-design-section-editor">
        <TitleFields content={content} onPatch={patch} />
        <p className="sp-design-editor-label">Categories to show</p>
        <IdChecklist
          options={categories}
          selected={content.category_ids || []}
          onChange={(category_ids) => patch({ category_ids })}
        />
        <p className="sp-design-muted">Empty = automatic selection.</p>
      </div>
    );
  }

  if (sectionId === 'pick-deck') {
    return (
      <div className="sp-design-section-editor">
        <TitleFields content={content} onPatch={patch} />
        <label className="sp-label">
          Featured product
          <select
            value={content.product_id || ''}
            onChange={(e) => patch({ product_id: Number(e.target.value) || 0 })}
          >
            <option value="">Automatic (hottest / showcase)</option>
            {Object.values(productsById).map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </label>
      </div>
    );
  }

  if (sectionId === 'heat-board' || sectionId === 'showcase') {
    return (
      <div className="sp-design-section-editor">
        <TitleFields content={content} onPatch={patch} />
        <p className="sp-design-editor-label">Products (order = display order)</p>
        <ProductIdList
          ids={content.product_ids || []}
          onChange={(product_ids) => patch({ product_ids })}
          productsById={productsById}
        />
        <p className="sp-design-muted">Empty = automatic bestsellers / showcase.</p>
      </div>
    );
  }

  if (sectionId === 'deal') {
    return (
      <div className="sp-design-section-editor">
        <label className="sp-label">
          Deal product
          <select
            value={content.product_id || ''}
            onChange={(e) => patch({ product_id: Number(e.target.value) || 0 })}
          >
            <option value="">Automatic deal pick</option>
            {Object.values(productsById).map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </label>
      </div>
    );
  }

  if (sectionId === 'trust') {
    const items = content.items?.length
      ? content.items
      : [
          { title: '', text: '' },
          { title: '', text: '' },
        ];
    return (
      <div className="sp-design-section-editor">
        <p className="sp-design-muted">
          Leave items empty to keep the default trust marquee. Or set custom title + text pairs.
        </p>
        {items.map((item, index) => (
          <div key={index} className="sp-design-field-grid">
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
        ))}
        <button
          type="button"
          className="sp-btn sp-btn-soft sp-btn-sm"
          onClick={() => patch({ items: [...items, { title: '', text: '' }] })}
        >
          + Add trust item
        </button>
      </div>
    );
  }

  if (sectionId === 'cta') {
    return (
      <div className="sp-design-section-editor">
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
    );
  }

  return <p className="sp-design-muted">No extra content options for this section yet.</p>;
}
