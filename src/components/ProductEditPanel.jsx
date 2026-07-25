import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { flattenCategoriesForSelect } from '../categories.js';
import StockToggle from './StockToggle.jsx';
import QuantityStepper from './QuantityStepper.jsx';

const blank = {
  name: '',
  price: '',
  description: '',
  stock_qty: 0,
  stock_status: 'instock',
  category_ids: [],
  image: '',
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
}) {
  const [form, setForm] = useState(blank);
  const [imageFile, setImageFile] = useState(null);
  const [preview, setPreview] = useState('');
  const [tab, setTab] = useState('basics');
  const categoryOptions = useMemo(() => flattenCategoriesForSelect(categories), [categories]);

  useEffect(() => {
    if (!product) {
      setForm(blank);
      setImageFile(null);
      setPreview('');
      setTab('basics');
      return;
    }
    setForm({
      name: product.name || '',
      price: product.price || '',
      description: product.description || '',
      stock_qty: product.stock_qty ?? 0,
      stock_status: product.stock_status || 'instock',
      category_ids: product.category_ids || [],
      image: product.image || '',
    });
    setImageFile(null);
    setPreview(product.image || '');
    setTab('basics');
  }, [product]);

  useEffect(() => {
    if (!open) return undefined;
    function onKey(e) {
      if (e.key === 'Escape' && !saving) onClose();
    }
    window.addEventListener('keydown', onKey);
    document.body.classList.add('storyphone-im-panel-open');
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.classList.remove('storyphone-im-panel-open');
    };
  }, [open, saving, onClose]);

  if (!open) return null;

  async function handleSubmit(e) {
    e.preventDefault();
    await onSave(
      {
        name: form.name,
        price: form.price,
        description: form.description,
        stock_quantity: Number(form.stock_qty) || 0,
        stock_status: form.stock_status,
        category_ids: form.category_ids,
      },
      imageFile
    );
  }

  const panel = (
    <div className="sp-panel-backdrop is-open" onClick={() => !saving && onClose()}>
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
                <div className="sp-image-preview sp-image-preview-lg">
                  {preview ? <img src={preview} alt="" /> : <span>No image</span>}
                </div>
                <label className="sp-btn sp-btn-ghost sp-file-label">
                  Upload image
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    hidden
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (!file) return;
                      setImageFile(file);
                      setPreview(URL.createObjectURL(file));
                    }}
                  />
                </label>
                <p className="sp-help">Accepts JPG, PNG, GIF, WebP. Prefer sharp, uncropped photos.</p>
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
                    Price ({currencySymbol})
                    <input
                      required
                      inputMode="decimal"
                      value={form.price}
                      onChange={(e) => setForm((f) => ({ ...f, price: e.target.value }))}
                    />
                  </label>

                  <label>
                    Status
                    <select
                      value={form.stock_status === 'instock' ? 'enabled' : 'disabled'}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          stock_status: e.target.value === 'enabled' ? 'instock' : 'outofstock',
                        }))
                      }
                    >
                      <option value="enabled">Enabled (in stock)</option>
                      <option value="disabled">Disabled (out of stock)</option>
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
                  <label>
                    <textarea
                      rows={5}
                      value={form.description}
                      onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                    />
                  </label>
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

  // Render on document.body so the drawer sits above the WP admin menu (RTL right side).
  return createPortal(panel, document.body);
}
