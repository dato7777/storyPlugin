/**
 * Design editor for the homepage cinema orbit.
 * Items: uploaded image, uploaded video, or catalog product — each with optional caption.
 */

import { useMemo, useState } from 'react';
import { openMediaLibrary } from '../lib/mediaLibrary.js';
import SearchField from './SearchField.jsx';
import { nameMatchesAllWords } from '../utils/searchMatch.js';

const MAX_ITEMS = 8;
const HUES = ['lime', 'mint', 'amber', 'cyan', 'coral'];

function newKey() {
  return `c${Date.now().toString(36)}${Math.random().toString(36).slice(2, 7)}`;
}

function selectableProducts(productsById) {
  return Object.values(productsById || {})
    .filter((p) => p && p.enabled !== false && (p.stock_status || 'instock') === 'instock')
    .map((p) => ({
      id: p.id,
      name: p.name,
      image: p.image || '',
    }))
    .sort((a, b) => String(a.name).localeCompare(String(b.name)));
}

function labelFor(item, productsById) {
  if (item.type === 'product') {
    const p = productsById?.[item.product_id];
    return p?.name || item.label || `Product #${item.product_id}`;
  }
  if (item.type === 'video') return item.label || 'Video';
  return item.label || 'Image';
}

function thumbFor(item, productsById) {
  if (item.type === 'product') {
    return productsById?.[item.product_id]?.image || item.url || '';
  }
  if (item.type === 'video') return '';
  return item.url || '';
}

export default function CinemaBannerEditor({ content, onChange, productsById, livePreview }) {
  const items = Array.isArray(content.items) ? content.items : [];
  const isCustom = Boolean(content.custom) || items.length > 0;
  const [productPicker, setProductPicker] = useState(false);
  const [q, setQ] = useState('');
  const [busy, setBusy] = useState('');
  const [editKey, setEditKey] = useState(null);

  const productOptions = useMemo(() => selectableProducts(productsById), [productsById]);
  const filteredProducts = useMemo(() => {
    return productOptions.filter((opt) => nameMatchesAllWords(opt.name, q));
  }, [productOptions, q]);

  const commit = (nextItems, custom = true) => {
    onChange({
      ...content,
      custom,
      items: nextItems.slice(0, MAX_ITEMS),
      product_ids: [], // drop legacy field
    });
  };

  const patchItem = (key, partial) => {
    commit(
      items.map((row) => (row.key === key ? { ...row, ...partial } : row)),
      true
    );
  };

  const moveItem = (index, dir) => {
    const next = items.slice();
    const target = index + dir;
    if (target < 0 || target >= next.length) return;
    const tmp = next[index];
    next[index] = next[target];
    next[target] = tmp;
    commit(next, true);
  };

  const addMedia = async (libraryType) => {
    if (items.length >= MAX_ITEMS) return;
    setBusy(libraryType);
    try {
      const picked = await openMediaLibrary({
        multiple: true,
        libraryType,
        title: libraryType === 'video' ? 'Select videos' : 'Select images',
        buttonText: 'Add to cinema',
      });
      if (!picked.length) return;
      const room = MAX_ITEMS - items.length;
      const extra = picked.slice(0, room).map((att) => ({
        key: newKey(),
        type: att.type === 'video' || libraryType === 'video' ? 'video' : 'image',
        attachment_id: att.id,
        product_id: 0,
        url: att.url || '',
        label: att.title || '',
        text: '',
      }));
      if (!extra.length) return;
      commit([...items, ...extra], true);
    } catch (err) {
      window.alert(err?.message || 'Media library unavailable');
    } finally {
      setBusy('');
    }
  };

  const addProduct = (id) => {
    if (items.length >= MAX_ITEMS) return;
    const p = productsById?.[id];
    commit(
      [
        ...items,
        {
          key: newKey(),
          type: 'product',
          attachment_id: 0,
          product_id: Number(id) || 0,
          url: p?.image || '',
          label: p?.name || '',
          text: '',
        },
      ],
      true
    );
    setProductPicker(false);
    setQ('');
  };

  const replaceMedia = async (key, libraryType) => {
    setBusy(`replace-${key}`);
    try {
      const picked = await openMediaLibrary({
        multiple: false,
        libraryType,
        title: libraryType === 'video' ? 'Replace video' : 'Replace image',
        buttonText: 'Use selected',
      });
      const att = picked[0];
      if (!att) return;
      patchItem(key, {
        type: libraryType === 'video' ? 'video' : 'image',
        attachment_id: att.id,
        product_id: 0,
        url: att.url,
        label: att.title,
      });
    } catch (err) {
      window.alert(err?.message || 'Media library unavailable');
    } finally {
      setBusy('');
    }
  };

  const autoItems = !isCustom && Array.isArray(livePreview?.items) ? livePreview.items : [];

  return (
    <div className="sp-design-section-editor sp-cinema-editor">
      <div className="sp-design-live">
        <div className="sp-design-live-head">
          <p className="sp-design-editor-label">On homepage cinema</p>
          <span className={`sp-design-live-badge ${isCustom ? 'is-custom' : 'is-auto'}`}>
            {isCustom ? 'Your selection' : 'Automatic'}
          </span>
        </div>
        <p className="sp-design-muted">
          Add images, videos, or products (max {MAX_ITEMS}). Caption text under each is optional —
          leave blank for no text.
        </p>

        {!isCustom && autoItems.length ? (
          <ul className="sp-cinema-editor-list is-auto">
            {autoItems.map((row, i) => (
              <li key={`auto-${row.id || i}`} className="sp-cinema-editor-card">
                <div className="sp-cinema-editor-thumb" data-hue={HUES[i % HUES.length]}>
                  {row.image || row.url ? (
                    <img src={row.image || row.url} alt="" />
                  ) : (
                    <span className="sp-cinema-editor-type">Auto</span>
                  )}
                </div>
                <div className="sp-cinema-editor-meta">
                  <strong>{row.name || 'Automatic product'}</strong>
                  <span className="sp-design-muted">From catalog search defaults</span>
                </div>
              </li>
            ))}
          </ul>
        ) : null}

        {isCustom ? (
          <ul className="sp-cinema-editor-list">
            {items.length === 0 ? (
              <li className="sp-design-muted">No orbit items — add media below, or reset to automatic.</li>
            ) : null}
            {items.map((item, index) => {
              const thumb = thumbFor(item, productsById);
              const open = editKey === item.key;
              return (
                <li key={item.key} className="sp-cinema-editor-card">
                  <div className="sp-cinema-editor-thumb" data-hue={HUES[index % HUES.length]}>
                    {item.type === 'video' ? (
                      <span className="sp-cinema-editor-type">Video</span>
                    ) : thumb ? (
                      <img src={thumb} alt="" />
                    ) : (
                      <span className="sp-cinema-editor-type">{item.type}</span>
                    )}
                  </div>
                  <div className="sp-cinema-editor-meta">
                    <strong>{labelFor(item, productsById)}</strong>
                    <span className="sp-design-muted">{item.type}</span>
                    {open ? (
                      <label className="sp-label">
                        Caption (optional)
                        <input
                          type="text"
                          value={item.text || ''}
                          placeholder="No text if empty"
                          onChange={(e) => patchItem(item.key, { text: e.target.value })}
                        />
                      </label>
                    ) : item.text ? (
                      <span className="sp-cinema-editor-caption">“{item.text}”</span>
                    ) : (
                      <span className="sp-design-muted">No caption</span>
                    )}
                  </div>
                  <div className="sp-cinema-editor-actions">
                    <button
                      type="button"
                      className="sp-btn sp-btn-ghost sp-btn-sm"
                      onClick={() => setEditKey((k) => (k === item.key ? null : item.key))}
                    >
                      {open ? 'Done' : 'Edit'}
                    </button>
                    {item.type === 'product' ? (
                      <button
                        type="button"
                        className="sp-btn sp-btn-ghost sp-btn-sm"
                        onClick={() => {
                          setEditKey(item.key);
                          setProductPicker(true);
                          setQ('');
                        }}
                      >
                        Replace
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="sp-btn sp-btn-ghost sp-btn-sm"
                        disabled={Boolean(busy)}
                        onClick={() => replaceMedia(item.key, item.type === 'video' ? 'video' : 'image')}
                      >
                        Replace
                      </button>
                    )}
                    <button
                      type="button"
                      className="sp-btn sp-btn-ghost sp-btn-sm"
                      disabled={index === 0}
                      onClick={() => moveItem(index, -1)}
                      aria-label="Move up"
                    >
                      ↑
                    </button>
                    <button
                      type="button"
                      className="sp-btn sp-btn-ghost sp-btn-sm"
                      disabled={index === items.length - 1}
                      onClick={() => moveItem(index, 1)}
                      aria-label="Move down"
                    >
                      ↓
                    </button>
                    <button
                      type="button"
                      className="sp-btn sp-btn-ghost sp-btn-sm"
                      onClick={() => commit(items.filter((row) => row.key !== item.key), true)}
                      aria-label="Remove"
                    >
                      ×
                    </button>
                  </div>
                </li>
              );
            })}
          </ul>
        ) : null}

        <div className="sp-cinema-editor-add">
          <button
            type="button"
            className="sp-btn sp-btn-soft sp-btn-sm"
            disabled={items.length >= MAX_ITEMS || Boolean(busy)}
            onClick={() => addMedia('image')}
          >
            {busy === 'image' ? 'Opening…' : '+ Image'}
          </button>
          <button
            type="button"
            className="sp-btn sp-btn-soft sp-btn-sm"
            disabled={items.length >= MAX_ITEMS || Boolean(busy)}
            onClick={() => addMedia('video')}
          >
            {busy === 'video' ? 'Opening…' : '+ Video'}
          </button>
          <button
            type="button"
            className="sp-btn sp-btn-soft sp-btn-sm"
            disabled={items.length >= MAX_ITEMS}
            onClick={() => {
              setEditKey(null);
              setProductPicker(true);
            }}
          >
            + Product
          </button>
          {isCustom ? (
            <button
              type="button"
              className="sp-btn sp-btn-ghost sp-btn-sm"
              onClick={() => commit([], false)}
            >
              Reset to automatic
            </button>
          ) : null}
        </div>

        {productPicker ? (
          <div className="sp-design-picker" role="dialog" aria-label="Add product">
            <div className="sp-design-picker-head">
              <strong>{editKey ? 'Replace with product' : 'Add product'}</strong>
              <button
                type="button"
                className="sp-btn sp-btn-ghost sp-btn-sm"
                onClick={() => {
                  setProductPicker(false);
                  setQ('');
                }}
              >
                Close
              </button>
            </div>
            <SearchField value={q} onChange={setQ} placeholder="Search products…" />
            <div className="sp-design-picker-grid has-images">
              {filteredProducts.map((opt) => (
                <button
                  key={opt.id}
                  type="button"
                  className="sp-design-picker-card"
                  onClick={() => {
                    if (editKey) {
                      patchItem(editKey, {
                        type: 'product',
                        attachment_id: 0,
                        product_id: opt.id,
                        url: opt.image || '',
                        label: opt.name,
                      });
                      setEditKey(null);
                      setProductPicker(false);
                      setQ('');
                      return;
                    }
                    addProduct(opt.id);
                  }}
                >
                  {opt.image ? <img src={opt.image} alt="" /> : null}
                  <span>{opt.name}</span>
                </button>
              ))}
              {!filteredProducts.length ? (
                <p className="sp-design-muted">No matching in-stock products.</p>
              ) : null}
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
