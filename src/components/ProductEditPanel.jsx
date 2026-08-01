import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { flattenCategoriesForSelect } from '../categories.js';
import { openMediaLibrary } from '../lib/mediaLibrary.js';
import StockToggle from './StockToggle.jsx';
import QuantityStepper from './QuantityStepper.jsx';
import RichTextEditor from './RichTextEditor.jsx';

const blank = {
  name: '',
  price: '',
  sku: '',
  description: '',
  stock_qty: 0,
  unlimited: true,
  stock_status: 'instock',
  enabled: true,
  category_ids: [],
  image_id: 0,
};

export default function ProductEditPanel({
  open,
  product,
  categories,
  currencySymbol,
  saving,
  onClose,
  onSave,
  onRequestDelete,
  onDeleteImage,
  onSetFeaturedImage,
}) {
  const [form, setForm] = useState(blank);
  const [pendingFiles, setPendingFiles] = useState([]);
  const [pendingLibrary, setPendingLibrary] = useState([]);
  const [mediaOpen, setMediaOpen] = useState(false);
  const [tab, setTab] = useState('basics');
  const categoryOptions = useMemo(() => flattenCategoriesForSelect(categories), [categories]);

  const images = product?.images || [];
  const featuredId = form.image_id || product?.image_id || 0;
  const blockDismiss = saving || mediaOpen;

  useEffect(() => {
    if (!product) {
      setForm(blank);
      setPendingFiles([]);
      setPendingLibrary([]);
      setMediaOpen(false);
      setTab('basics');
      return;
    }
    const isDisabled =
      product.enabled === false ||
      product.catalog_visibility === 'hidden' ||
      (product.status && product.status !== 'publish');
    const unlimited = product.manage_stock !== true;
    const rawDesc = product.edit_description || product.description || product.short_description || '';
    setForm({
      name: product.name || '',
      price: product.price || '',
      sku: product.sku || '',
      description: rawDesc,
      stock_qty: unlimited ? 0 : product.stock_qty ?? 0,
      unlimited,
      stock_status: product.stock_status || 'instock',
      enabled: !isDisabled,
      category_ids: product.category_ids || [],
      image_id: product.image_id || 0,
    });
    setPendingFiles([]);
    setPendingLibrary([]);
    setMediaOpen(false);
    setTab('basics');
  }, [product]);

  useEffect(() => {
    if (!open) return undefined;
    function onKey(e) {
      if (e.key === 'Escape' && !blockDismiss) onClose();
    }
    window.addEventListener('keydown', onKey);
    document.body.classList.add('storyphone-im-panel-open');
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.classList.remove('storyphone-im-panel-open');
    };
  }, [open, blockDismiss, onClose]);

  if (!open) return null;

  async function handleSubmit(e) {
    e.preventDefault();
    await onSave(
      {
        name: form.name,
        price: form.price,
        sku: form.sku,
        description: form.description,
        short_description: form.description,
        stock_quantity: form.unlimited ? null : Number(form.stock_qty) || 0,
        stock_status: form.stock_status,
        enabled: form.enabled,
        category_ids: form.category_ids,
        image_id: form.image_id || 0,
      },
      {
        files: pendingFiles,
        libraryIds: pendingLibrary.map((item) => item.id),
      }
    );
  }

  function queueFiles(fileList) {
    const files = Array.from(fileList || []).filter(Boolean);
    if (!files.length) return;
    setPendingFiles((prev) => [...prev, ...files]);
  }

  async function pickFromLibrary() {
    try {
      const picked = await openMediaLibrary({
        multiple: true,
        title: 'Select images from Media Library',
        buttonText: 'Add to product',
        onOpen: () => setMediaOpen(true),
        onClose: () => setMediaOpen(false),
      });
      setMediaOpen(false);
      if (!picked.length) return;
      setPendingLibrary((prev) => {
        const seen = new Set(prev.map((p) => p.id));
        const next = [...prev];
        picked.forEach((item) => {
          if (!seen.has(item.id)) {
            seen.add(item.id);
            next.push(item);
          }
        });
        return next;
      });
    } catch (err) {
      setMediaOpen(false);
      window.alert(err.message || 'Could not open Media Library');
    }
  }

  const panel = (
    <div
      className="sp-panel-backdrop is-open"
      onClick={() => {
        if (!blockDismiss) onClose();
      }}
    >
      <aside
        className="sp-panel is-open"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sp-panel-title"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="sp-panel-header">
          <div>
            <p className="sp-panel-kicker">Edit</p>
            <h2 id="sp-panel-title">{product?.name || 'Loading…'}</h2>
          </div>
          <button type="button" className="sp-icon-btn" onClick={onClose} disabled={saving} aria-label="Close">
            ×
          </button>
        </header>

        <div className="sp-tabs" role="tablist">
          <button
            type="button"
            role="tab"
            className={`sp-tab ${tab === 'basics' ? 'is-active' : ''}`}
            aria-selected={tab === 'basics'}
            onClick={() => setTab('basics')}
          >
            Basics
          </button>
          <button
            type="button"
            role="tab"
            className={`sp-tab ${tab === 'media' ? 'is-active' : ''}`}
            aria-selected={tab === 'media'}
            onClick={() => setTab('media')}
          >
            Images
          </button>
        </div>

        {!product ? (
          <div className="sp-panel-body">
            <p className="sp-muted">Loading product…</p>
          </div>
        ) : (
          <form className="sp-panel-body sp-form" onSubmit={handleSubmit}>
            {tab === 'media' ? (
              <div className="sp-card-block">
                <h3 className="sp-card-block-title">Images</h3>
                <p className="sp-help sp-image-pref">
                  Preferred size: <strong>600×600 px</strong> (square). JPG, PNG, GIF, or WebP.
                </p>

                {images.length === 0 && pendingFiles.length === 0 && pendingLibrary.length === 0 ? (
                  <div className="sp-image-preview sp-image-preview-lg">
                    <span>No image</span>
                  </div>
                ) : (
                  <div className="sp-image-grid">
                    {images.map((img) => {
                      const isDefault = Number(img.id) === Number(featuredId) || img.is_featured;
                      return (
                        <div
                          key={img.id}
                          className={`sp-image-tile ${isDefault ? 'is-featured' : ''}`}
                        >
                          <div className="sp-image-tile-preview">
                            {img.url ? <img src={img.url} alt="" /> : <span>—</span>}
                            {isDefault ? (
                              <span className="sp-image-tile-badge">Default</span>
                            ) : null}
                          </div>
                          <div className="sp-image-tile-actions">
                            {!isDefault ? (
                              <button
                                type="button"
                                className="sp-btn sp-btn-soft sp-btn-sm"
                                disabled={saving || mediaOpen}
                                onClick={() => onSetFeaturedImage(img.id)}
                              >
                                Set as default
                              </button>
                            ) : (
                              <span className="sp-image-tile-default-label">Shown on website</span>
                            )}
                            <button
                              type="button"
                              className="sp-btn sp-btn-danger-soft sp-btn-sm"
                              disabled={saving || mediaOpen}
                              onClick={() => {
                                if (window.confirm('Remove this image from the product?')) {
                                  onDeleteImage(img.id);
                                }
                              }}
                            >
                              Delete
                            </button>
                          </div>
                        </div>
                      );
                    })}
                    {pendingFiles.map((file, idx) => (
                      <div key={`pending-file-${idx}`} className="sp-image-tile is-pending">
                        <div className="sp-image-tile-preview">
                          <img src={URL.createObjectURL(file)} alt="" />
                          <span className="sp-image-tile-badge">Pending · Local</span>
                        </div>
                        <div className="sp-image-tile-actions">
                          <button
                            type="button"
                            className="sp-btn sp-btn-ghost sp-btn-sm"
                            onClick={() =>
                              setPendingFiles((prev) => prev.filter((_, i) => i !== idx))
                            }
                          >
                            Remove
                          </button>
                        </div>
                      </div>
                    ))}
                    {pendingLibrary.map((item) => (
                      <div key={`pending-lib-${item.id}`} className="sp-image-tile is-pending">
                        <div className="sp-image-tile-preview">
                          {item.url ? <img src={item.url} alt="" /> : <span>—</span>}
                          <span className="sp-image-tile-badge">Pending · Library</span>
                        </div>
                        <div className="sp-image-tile-actions">
                          <button
                            type="button"
                            className="sp-btn sp-btn-ghost sp-btn-sm"
                            onClick={() =>
                              setPendingLibrary((prev) => prev.filter((row) => row.id !== item.id))
                            }
                          >
                            Remove
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                <div className="sp-image-source-row">
                  <label className={`sp-btn sp-btn-ghost sp-file-label ${mediaOpen ? 'is-disabled' : ''}`}>
                    Local
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/gif,image/webp"
                      hidden
                      multiple
                      disabled={saving || mediaOpen}
                      onChange={(e) => {
                        queueFiles(e.target.files);
                        e.target.value = '';
                      }}
                    />
                  </label>
                  <button
                    type="button"
                    className="sp-btn sp-btn-ghost"
                    disabled={saving || mediaOpen}
                    onClick={pickFromLibrary}
                  >
                    Library
                  </button>
                </div>
                <p className="sp-help">
                  <strong>Local</strong> = from this computer. <strong>Library</strong> = WordPress
                  Media Library (opens over this panel). Pending images apply only when you press{' '}
                  <strong>Save</strong>.
                </p>
              </div>
            ) : (
              <>
                <div className="sp-card-block">
                  <h3 className="sp-card-block-title">About</h3>
                  <label>
                    Item name
                    <input
                      required
                      maxLength={300}
                      value={form.name}
                      onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                    />
                    <span className="sp-char-count">{form.name.length}/300</span>
                  </label>

                  <label>
                    SKU
                    <input
                      value={form.sku}
                      placeholder="No SKU"
                      onChange={(e) => setForm((f) => ({ ...f, sku: e.target.value }))}
                    />
                  </label>
                  <p className="sp-help">
                    {form.sku?.trim()
                      ? 'Editable product code. NewOrder imports can set this automatically later.'
                      : 'No SKU yet — you can enter one, or leave empty until import.'}
                  </p>

                  <label>
                    Price ({currencySymbol})
                    <input
                      required
                      inputMode="decimal"
                      value={form.price}
                      onChange={(e) => setForm((f) => ({ ...f, price: e.target.value }))}
                    />
                  </label>

                  <label>
                    Storefront
                    <select
                      value={form.enabled ? 'enabled' : 'disabled'}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          enabled: e.target.value === 'enabled',
                        }))
                      }
                    >
                      <option value="enabled">Enabled (shop + search)</option>
                      <option value="disabled">Disabled (off website & search)</option>
                    </select>
                  </label>

                  <label>
                    Primary category
                    <select
                      value={form.category_ids[0] || ''}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          category_ids: e.target.value ? [Number(e.target.value)] : [],
                        }))
                      }
                    >
                      <option value="">None</option>
                      {categoryOptions.map((cat) => (
                        <option key={cat.id} value={cat.id}>
                          {cat.label}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>

                <div className="sp-card-block">
                  <h3 className="sp-card-block-title">Inventory</h3>
                  <div className="sp-field-row">
                    <div>
                      <span className="sp-label">Quantity</span>
                      <div className="sp-qty-mode">
                        <button
                          type="button"
                          className={`sp-qty-mode-btn ${form.unlimited ? 'is-active' : ''}`}
                          onClick={() => setForm((f) => ({ ...f, unlimited: true }))}
                        >
                          ∞ Unlimited
                        </button>
                        <button
                          type="button"
                          className={`sp-qty-mode-btn ${!form.unlimited ? 'is-active' : ''}`}
                          onClick={() =>
                            setForm((f) => ({
                              ...f,
                              unlimited: false,
                              stock_qty: f.stock_qty > 0 ? f.stock_qty : 1,
                            }))
                          }
                        >
                          Limited
                        </button>
                      </div>
                      {!form.unlimited ? (
                        <div className="sp-qty-stepper-wrap">
                          <QuantityStepper
                            value={form.stock_qty}
                            onChange={(qty) =>
                              setForm((f) => ({
                                ...f,
                                stock_qty: qty,
                                stock_status: qty > 0 ? 'instock' : f.stock_status,
                              }))
                            }
                          />
                        </div>
                      ) : (
                        <p className="sp-help sp-qty-help">
                          Default: unlimited stock. Switch to Limited to set an exact quantity.
                        </p>
                      )}
                    </div>
                    <div>
                      <span className="sp-label">Availability</span>
                      <StockToggle
                        status={form.stock_status}
                        onChange={(status) => setForm((f) => ({ ...f, stock_status: status }))}
                      />
                    </div>
                  </div>
                </div>

                <div className="sp-card-block">
                  <h3 className="sp-card-block-title">Description</h3>
                  <RichTextEditor
                    value={form.description}
                    disabled={saving}
                    onChange={(html) => setForm((f) => ({ ...f, description: html }))}
                    placeholder="Product description shown on the website"
                  />
                  <p className="sp-help">
                    Format text with the toolbar, then use Preview to see how it will look. Press
                    Enter for new lines — you do not need &amp;nbsp;. Saved to the storefront
                    description fields on Save.
                  </p>
                </div>
              </>
            )}

            <div className="sp-panel-footer">
              <button
                type="button"
                className="sp-btn sp-btn-danger-outline"
                disabled={saving}
                onClick={() => onRequestDelete(product)}
              >
                Trash
              </button>
              <div className="sp-panel-footer-right">
                <button type="button" className="sp-btn sp-btn-ghost" disabled={saving} onClick={onClose}>
                  Close
                </button>
                <button type="submit" className="sp-btn sp-btn-primary" disabled={saving}>
                  {saving ? 'Saving…' : 'Save'}
                </button>
              </div>
            </div>
          </form>
        )}
      </aside>
    </div>
  );

  return createPortal(panel, document.body);
}
