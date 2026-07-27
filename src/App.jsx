import { useCallback, useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import {
  bulkProducts,
  createCategory,
  createProduct,
  deleteCategory,
  fetchCategories,
  fetchProduct,
  fetchProducts,
  fetchStats,
  trashProduct,
  updateCategory,
  updateProduct,
  uploadProductImage,
  deleteProductImage,
} from './api.js';
import { flattenCategoriesForSelect } from './categories.js';
import BulkBar from './components/BulkBar.jsx';
import CategoryNav from './components/CategoryNav.jsx';
import CategoryTreeView from './components/CategoryTreeView.jsx';
import ProductList from './components/ProductList.jsx';
import ProductEditPanel from './components/ProductEditPanel.jsx';
import QuantityStepper from './components/QuantityStepper.jsx';
import RichTextEditor from './components/RichTextEditor.jsx';
import StockToggle from './components/StockToggle.jsx';
import Toast from './components/Toast.jsx';

const emptyCreateForm = {
  name: '',
  price: '',
  sku: '',
  description: '',
  stock_qty: 0,
  unlimited: true,
  stock_status: 'instock',
  enabled: true,
  category_ids: [],
  imageFile: null,
};

const COLLECTION_TITLES = {
  all: 'All items',
  categories: 'All Categories',
  instock: 'In Stock',
  outofstock: 'Out of stock',
  disabled: 'Disabled',
};

export default function App() {
  const settings = window.storyphoneSettings || {};
  const canManage = Boolean(settings.canManage);

  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [stats, setStats] = useState({ all: 0, instock: 0, outofstock: 0, disabled: 0 });
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [category, setCategory] = useState(0);
  const [collection, setCollection] = useState('all');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [selectedId, setSelectedId] = useState(null);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [selectedIds, setSelectedIds] = useState(() => new Set());
  const [panelOpen, setPanelOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState(emptyCreateForm);
  const [toast, setToast] = useState(null);
  const [confirmTrash, setConfirmTrash] = useState(null);
  const [editCategoryOpen, setEditCategoryOpen] = useState(false);
  const [editCategoryName, setEditCategoryName] = useState('');
  const [confirmDeleteCategory, setConfirmDeleteCategory] = useState(false);
  const [createCategoryOpen, setCreateCategoryOpen] = useState(false);
  const [createCategoryForm, setCreateCategoryForm] = useState({
    name: '',
    parent: 0,
  });

  const showToast = useCallback((message, type = 'success') => {
    setToast({ message, type, id: Date.now() });
  }, []);

  const categoryOptions = useMemo(() => flattenCategoriesForSelect(categories), [categories]);

  const activeCategory = useMemo(
    () => (category ? categories.find((c) => c.id === category) || null : null),
    [categories, category]
  );

  const showCategoryTree = collection === 'categories' && !category;

  const headerTitle = activeCategory?.name || COLLECTION_TITLES[collection] || 'All items';

  const headerSubtitle = useMemo(() => {
    if (activeCategory) {
      return 'Products in this category. Edit or delete the category below.';
    }
    if (showCategoryTree) {
      return 'Interactive map of your category structure.';
    }
    if (collection === 'instock') {
      return 'Enabled products that are in stock (Disabled and out of stock are excluded).';
    }
    if (collection === 'outofstock') {
      return 'Enabled products that are out of stock (Disabled items are not listed here).';
    }
    if (collection === 'disabled') {
      return 'Disabled only — off the website and search (any stock level).';
    }
    return 'Every inventory item, including Disabled / drafts.';
  }, [activeCategory, collection, showCategoryTree]);

  const loadStats = useCallback(async () => {
    try {
      const data = await fetchStats();
      setStats({
        all: data.all || 0,
        instock: data.instock || 0,
        outofstock: data.outofstock || 0,
        disabled: data.disabled || 0,
      });
    } catch {
      /* non-blocking */
    }
  }, []);

  const loadCategories = useCallback(async () => {
    const data = await fetchCategories();
    setCategories(data.categories || []);
  }, []);

  const loadProducts = useCallback(async () => {
    if (collection === 'categories' && !category) {
      setProducts([]);
      setPages(1);
      setTotal(0);
      setSelectedIds(new Set());
      setLoading(false);
      return;
    }

    setLoading(true);
    try {
      const data = await fetchProducts({
        page,
        perPage: 24,
        search,
        category,
        collection: collection === 'categories' ? 'all' : collection,
      });
      setProducts(data.products || []);
      setPages(data.pages || 1);
      setTotal(typeof data.total === 'number' ? data.total : data.products?.length || 0);
      setSelectedIds(new Set());
    } catch (err) {
      showToast(err.message || 'Failed to load products', 'error');
    } finally {
      setLoading(false);
    }
  }, [page, search, category, collection, showToast]);

  useEffect(() => {
    loadProducts();
  }, [loadProducts]);

  useEffect(() => {
    loadCategories().catch((err) =>
      showToast(err.message || 'Failed to load categories', 'error')
    );
    loadStats();
  }, [loadCategories, loadStats, showToast]);

  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1);
      setSearch(searchInput.trim());
    }, 300);
    return () => clearTimeout(timer);
  }, [searchInput]);

  function selectCategory(id) {
    setCategory(id);
    if (collection === 'all' || collection === 'categories') {
      setCollection('categories');
    }
    setPage(1);
    setSelectedIds(new Set());
  }

  function selectCollection(id) {
    setCollection(id);
    if (id === 'all' || id === 'categories') setCategory(0);
    setPage(1);
    setSelectedIds(new Set());
  }

  function backToCategoryTree() {
    setCategory(0);
    setCollection('categories');
    setPage(1);
    setSelectedIds(new Set());
  }

  const showCategoryBack = Boolean(activeCategory) && collection === 'categories';

  async function openProduct(id) {
    setSelectedId(id);
    setPanelOpen(true);
    setSelectedProduct(null);
    try {
      const data = await fetchProduct(id);
      setSelectedProduct(data);
    } catch (err) {
      showToast(err.message || 'Failed to load product', 'error');
      setPanelOpen(false);
    }
  }

  function closePanel() {
    setPanelOpen(false);
    setSelectedId(null);
    setSelectedProduct(null);
  }

  function toggleSelect(id) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function toggleSelectAll() {
    setSelectedIds((prev) => {
      const pageIds = products.map((p) => p.id);
      const allOnPage = pageIds.length > 0 && pageIds.every((id) => prev.has(id));
      if (allOnPage) return new Set();
      return new Set(pageIds);
    });
  }

  async function handleBulkAction(action) {
    const ids = Array.from(selectedIds);
    if (!ids.length) return;

    if (action === 'trash') {
      const ok = window.confirm(`Move ${ids.length} product(s) to trash?`);
      if (!ok) return;
    }

    setSaving(true);
    try {
      const result = await bulkProducts(ids, action);
      showToast(`Updated ${result.updated || ids.length} item(s)`);
      setSelectedIds(new Set());
      await loadProducts();
      await loadStats();
    } catch (err) {
      showToast(err.message || 'Bulk update failed', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function refreshSelectedProduct() {
    if (!selectedId) return;
    try {
      const data = await fetchProduct(selectedId);
      setSelectedProduct(data);
    } catch {
      /* non-blocking */
    }
  }

  async function handleSave(payload, pendingFiles = []) {
    if (!selectedId) return;
    setSaving(true);
    try {
      await updateProduct(selectedId, payload);
      const files = Array.isArray(pendingFiles)
        ? pendingFiles
        : pendingFiles
          ? [pendingFiles]
          : [];
      for (let i = 0; i < files.length; i += 1) {
        // First upload becomes featured if product had none; later ones join gallery.
        await uploadProductImage(selectedId, files[i], {
          asFeatured: i === 0 && !payload.image_id,
        });
      }
      showToast('Product saved');
      closePanel();
      await loadProducts();
      await loadStats();
    } catch (err) {
      showToast(err.message || 'Save failed', 'error');
      throw err;
    } finally {
      setSaving(false);
    }
  }

  async function handleUploadImage(file, asFeatured = true) {
    if (!selectedId) return;
    setSaving(true);
    try {
      const result = await uploadProductImage(selectedId, file, { asFeatured });
      if (result.product) setSelectedProduct(result.product);
      else await refreshSelectedProduct();
      showToast('Image uploaded');
      await loadProducts();
    } catch (err) {
      showToast(err.message || 'Image upload failed', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleDeleteImage(imageId) {
    if (!selectedId) return;
    setSaving(true);
    try {
      const result = await deleteProductImage(selectedId, imageId);
      if (result.product) setSelectedProduct(result.product);
      else await refreshSelectedProduct();
      showToast('Image removed');
      await loadProducts();
    } catch (err) {
      showToast(err.message || 'Could not delete image', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleSetFeaturedImage(imageId) {
    if (!selectedId) return;
    setSaving(true);
    try {
      const result = await updateProduct(selectedId, { image_id: imageId });
      if (result.product) setSelectedProduct(result.product);
      else await refreshSelectedProduct();
      showToast('Default image updated');
      await loadProducts();
    } catch (err) {
      showToast(err.message || 'Could not set default image', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleQuickUpdate(id, payload) {
    try {
      const result = await updateProduct(id, payload);
      setProducts((prev) =>
        prev.map((p) => (p.id === id ? { ...p, ...result.product } : p))
      );
      showToast('Updated');
      loadStats();
    } catch (err) {
      showToast(err.message || 'Update failed', 'error');
      await loadProducts();
    }
  }

  async function handleCreate(e) {
    e.preventDefault();
    if (!createForm.name || !createForm.price || !createForm.sku) {
      showToast('Name, price, and SKU are required', 'error');
      return;
    }
    setSaving(true);
    try {
      const result = await createProduct({
        name: createForm.name,
        price: createForm.price,
        sku: createForm.sku,
        description: createForm.description || '',
        short_description: createForm.description || '',
        category_ids: createForm.category_ids,
        stock_quantity: createForm.unlimited ? null : Number(createForm.stock_qty) || 0,
        stock_status: createForm.stock_status,
        enabled: createForm.enabled,
      });
      if (createForm.imageFile && result.product?.id) {
        await uploadProductImage(result.product.id, createForm.imageFile);
      }
      showToast('Product created');
      setCreateOpen(false);
      setCreateForm(emptyCreateForm);
      setPage(1);
      await loadProducts();
      await loadStats();
      await loadCategories();
    } catch (err) {
      showToast(err.message || 'Create failed', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleTrashConfirm() {
    if (!confirmTrash) return;
    const id = confirmTrash.id;
    setSaving(true);
    try {
      await trashProduct(id);
      showToast('Product moved to trash');
      setConfirmTrash(null);
      if (selectedId === id) closePanel();
      await loadProducts();
      await loadStats();
    } catch (err) {
      showToast(err.message || 'Delete failed', 'error');
    } finally {
      setSaving(false);
    }
  }

  function openEditCategory() {
    if (!activeCategory) return;
    setEditCategoryName(activeCategory.name);
    setEditCategoryOpen(true);
  }

  async function handleCategorySave(e) {
    e.preventDefault();
    if (!activeCategory) return;
    const name = editCategoryName.trim();
    if (!name) {
      showToast('Category name is required', 'error');
      return;
    }
    setSaving(true);
    try {
      await updateCategory(activeCategory.id, { name });
      showToast('Category updated');
      setEditCategoryOpen(false);
      await loadCategories();
    } catch (err) {
      showToast(err.message || 'Could not update category', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleCategoryDelete() {
    if (!activeCategory) return;
    setSaving(true);
    try {
      await deleteCategory(activeCategory.id);
      showToast('Category deleted');
      setConfirmDeleteCategory(false);
      setCategory(0);
      await loadCategories();
      await loadProducts();
    } catch (err) {
      showToast(err.message || 'Could not delete category', 'error');
    } finally {
      setSaving(false);
    }
  }

  function openCreateCategory({ parentId = 0 } = {}) {
    setCreateCategoryForm({ name: '', parent: parentId || 0 });
    setCreateCategoryOpen(true);
  }

  async function handleCreateCategory(e) {
    e.preventDefault();
    const name = createCategoryForm.name.trim();
    if (!name) {
      showToast('Category name is required', 'error');
      return;
    }
    setSaving(true);
    try {
      await createCategory({
        name,
        parent: createCategoryForm.parent || 0,
      });
      showToast(
        createCategoryForm.parent ? 'Subcategory created' : 'Category created'
      );
      setCreateCategoryOpen(false);
      setCreateCategoryForm({ name: '', parent: 0 });
      await loadCategories();
    } catch (err) {
      showToast(err.message || 'Could not create category', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleDeleteEmptyCategories(ids) {
    setSaving(true);
    let deleted = 0;
    try {
      for (const id of ids) {
        await deleteCategory(id);
        deleted += 1;
      }
      showToast(`Deleted ${deleted} categor${deleted === 1 ? 'y' : 'ies'}`);
      await loadCategories();
    } catch (err) {
      showToast(
        deleted
          ? `Deleted ${deleted}, then failed: ${err.message}`
          : err.message || 'Could not delete categories',
        'error'
      );
      await loadCategories();
    } finally {
      setSaving(false);
    }
  }

  if (!canManage) {
    return (
      <div className="sp-shell">
        <div className="sp-empty">
          You do not have permission to manage WooCommerce inventory.
        </div>
      </div>
    );
  }

  return (
    <div className="sp-shell">
      <div className="sp-layout">
        <CategoryNav
          categories={categories}
          selectedCategoryId={category}
          collection={collection}
          stats={stats}
          onSelectCategory={selectCategory}
          onSelectCollection={selectCollection}
        />

        <section className="sp-content">
          <header className="sp-content-header">
            <div className="sp-content-heading">
              {showCategoryBack ? (
                <button
                  type="button"
                  className="sp-back-btn"
                  onClick={backToCategoryTree}
                >
                  <span aria-hidden="true">←</span> Back to All Categories
                </button>
              ) : null}
              <div className="sp-title-row">
                <h2 className="sp-content-title">{headerTitle}</h2>
                {activeCategory ? (
                  <div className="sp-title-actions">
                    <button
                      type="button"
                      className="sp-btn sp-btn-soft sp-btn-sm"
                      onClick={openEditCategory}
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      className="sp-btn sp-btn-danger-soft sp-btn-sm"
                      onClick={() => setConfirmDeleteCategory(true)}
                    >
                      Delete
                    </button>
                  </div>
                ) : null}
              </div>
              <p className="sp-content-subtitle">{headerSubtitle}</p>
              {activeCategory ? (
                <div className="sp-category-chip">
                  <span className="sp-category-chip-dot" aria-hidden="true" />
                  Category · {activeCategory.count ?? 0} linked
                  {collection !== 'all' && collection !== 'categories'
                    ? ` · filtered: ${COLLECTION_TITLES[collection]}`
                    : ''}
                </div>
              ) : null}
            </div>
            {!showCategoryTree ? (
              <div className="sp-content-actions">
                <div className="sp-search-wrap">
                  <span className="sp-search-icon" aria-hidden="true">
                    ⌕
                  </span>
                  <input
                    type="search"
                    className="sp-search"
                    placeholder={`Search ${headerTitle}…`}
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                    aria-label="Search products"
                  />
                </div>
                <button
                  type="button"
                  className="sp-btn sp-btn-primary"
                  onClick={() => setCreateOpen(true)}
                >
                  + Create
                </button>
              </div>
            ) : null}
          </header>

          {!showCategoryTree ? (
            <div className="sp-content-meta">
              <span>{loading ? 'Loading…' : `${total} item${total === 1 ? '' : 's'}`}</span>
            </div>
          ) : null}

          <main className="sp-main">
            {showCategoryTree ? (
              <CategoryTreeView
                categories={categories}
                onOpenCategory={selectCategory}
                onCreateCategory={openCreateCategory}
                onDeleteCategories={handleDeleteEmptyCategories}
                saving={saving}
              />
            ) : loading ? (
              <div className="sp-empty">Loading products…</div>
            ) : products.length === 0 ? (
              <div className="sp-empty">No products found.</div>
            ) : (
              <ProductList
                products={products}
                currencySymbol={settings.currencySymbol || '₪'}
                selectedId={selectedId}
                selectedIds={selectedIds}
                onSelect={openProduct}
                onToggleSelect={toggleSelect}
                onToggleSelectAll={toggleSelectAll}
                onQuickUpdate={handleQuickUpdate}
              />
            )}

            {!showCategoryTree && pages > 1 && (
              <div className="sp-pagination">
                <button
                  type="button"
                  className="sp-btn sp-btn-ghost"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </button>
                <span className="sp-page-label">
                  Page {page} of {pages}
                </span>
                <button
                  type="button"
                  className="sp-btn sp-btn-ghost"
                  disabled={page >= pages}
                  onClick={() => setPage((p) => Math.min(pages, p + 1))}
                >
                  Next
                </button>
              </div>
            )}
          </main>
        </section>
      </div>

      {!showCategoryTree ? (
        <BulkBar
          count={selectedIds.size}
          saving={saving}
          onClear={() => setSelectedIds(new Set())}
          onAction={handleBulkAction}
        />
      ) : null}

      <ProductEditPanel
        open={panelOpen}
        product={selectedProduct}
        categories={categories}
        currencySymbol={settings.currencySymbol || '₪'}
        saving={saving}
        onClose={closePanel}
        onSave={handleSave}
        onRequestDelete={(product) => setConfirmTrash(product)}
        onUploadImage={handleUploadImage}
        onDeleteImage={handleDeleteImage}
        onSetFeaturedImage={handleSetFeaturedImage}
      />

      {createOpen &&
        createPortal(
          <div className="sp-modal-backdrop" onClick={() => !saving && setCreateOpen(false)}>
            <div
              className="sp-modal sp-modal-lg"
              role="dialog"
              aria-modal="true"
              aria-labelledby="sp-create-title"
              onClick={(e) => e.stopPropagation()}
            >
              <h2 id="sp-create-title">Create item</h2>
              <form className="sp-form sp-form-create" onSubmit={handleCreate}>
                <div className="sp-card-block">
                  <h3 className="sp-card-block-title">About</h3>
                  <label>
                    Item name
                    <input
                      required
                      maxLength={300}
                      value={createForm.name}
                      onChange={(e) => setCreateForm((f) => ({ ...f, name: e.target.value }))}
                    />
                    <span className="sp-char-count">{createForm.name.length}/300</span>
                  </label>
                  <label>
                    SKU
                    <input
                      required
                      value={createForm.sku}
                      onChange={(e) => setCreateForm((f) => ({ ...f, sku: e.target.value }))}
                    />
                  </label>
                  <label>
                    Price ({settings.currencySymbol || '₪'})
                    <input
                      required
                      inputMode="decimal"
                      value={createForm.price}
                      onChange={(e) => setCreateForm((f) => ({ ...f, price: e.target.value }))}
                    />
                  </label>
                  <label>
                    Storefront
                    <select
                      value={createForm.enabled ? 'enabled' : 'disabled'}
                      onChange={(e) =>
                        setCreateForm((f) => ({
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
                      value={createForm.category_ids[0] || ''}
                      onChange={(e) =>
                        setCreateForm((f) => ({
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
                  <label>
                    Image
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/gif,image/webp"
                      onChange={(e) =>
                        setCreateForm((f) => ({
                          ...f,
                          imageFile: e.target.files?.[0] || null,
                        }))
                      }
                    />
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
                          className={`sp-qty-mode-btn ${createForm.unlimited ? 'is-active' : ''}`}
                          onClick={() => setCreateForm((f) => ({ ...f, unlimited: true }))}
                        >
                          ∞ Unlimited
                        </button>
                        <button
                          type="button"
                          className={`sp-qty-mode-btn ${!createForm.unlimited ? 'is-active' : ''}`}
                          onClick={() =>
                            setCreateForm((f) => ({
                              ...f,
                              unlimited: false,
                              stock_qty: f.stock_qty > 0 ? f.stock_qty : 1,
                            }))
                          }
                        >
                          Limited
                        </button>
                      </div>
                      {!createForm.unlimited ? (
                        <div className="sp-qty-stepper-wrap">
                          <QuantityStepper
                            value={createForm.stock_qty}
                            onChange={(qty) =>
                              setCreateForm((f) => ({
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
                        status={createForm.stock_status}
                        onChange={(status) =>
                          setCreateForm((f) => ({ ...f, stock_status: status }))
                        }
                      />
                    </div>
                  </div>
                </div>

                <div className="sp-card-block">
                  <h3 className="sp-card-block-title">Description</h3>
                  <RichTextEditor
                    value={createForm.description}
                    disabled={saving}
                    onChange={(html) => setCreateForm((f) => ({ ...f, description: html }))}
                    placeholder="Product description shown on the website"
                  />
                  <p className="sp-help">
                    Same editor as edit — format text, preview, and insert images. Saved with the
                    new item.
                  </p>
                </div>

                <div className="sp-modal-actions">
                  <button
                    type="button"
                    className="sp-btn sp-btn-ghost"
                    disabled={saving}
                    onClick={() => setCreateOpen(false)}
                  >
                    Close
                  </button>
                  <button type="submit" className="sp-btn sp-btn-primary" disabled={saving}>
                    {saving ? 'Creating…' : 'Save'}
                  </button>
                </div>
              </form>
            </div>
          </div>,
          document.body
        )}

      {createCategoryOpen &&
        createPortal(
          <div
            className="sp-modal-backdrop"
            onClick={() => !saving && setCreateCategoryOpen(false)}
          >
            <div
              className="sp-modal sp-modal-sm"
              role="dialog"
              aria-modal="true"
              aria-labelledby="sp-create-cat-title"
              onClick={(e) => e.stopPropagation()}
            >
              <h2 id="sp-create-cat-title">
                {createCategoryForm.parent ? 'Create subcategory' : 'Create new category'}
              </h2>
              <form className="sp-form" onSubmit={handleCreateCategory}>
                <label>
                  Name
                  <input
                    required
                    value={createCategoryForm.name}
                    onChange={(e) =>
                      setCreateCategoryForm((f) => ({ ...f, name: e.target.value }))
                    }
                    placeholder="e.g. Headphones"
                    autoFocus
                  />
                </label>
                <label>
                  Parent category
                  <select
                    value={createCategoryForm.parent || ''}
                    onChange={(e) =>
                      setCreateCategoryForm((f) => ({
                        ...f,
                        parent: e.target.value ? Number(e.target.value) : 0,
                      }))
                    }
                  >
                    <option value="">None (top-level category)</option>
                    {categoryOptions.map((cat) => (
                      <option key={cat.id} value={cat.id}>
                        {cat.label}
                      </option>
                    ))}
                  </select>
                </label>
                <p className="sp-help">
                  Choose a parent to create a subcategory under an existing category.
                </p>
                <div className="sp-modal-actions">
                  <button
                    type="button"
                    className="sp-btn sp-btn-ghost"
                    disabled={saving}
                    onClick={() => setCreateCategoryOpen(false)}
                  >
                    Cancel
                  </button>
                  <button type="submit" className="sp-btn sp-btn-primary" disabled={saving}>
                    {saving ? 'Creating…' : 'Create'}
                  </button>
                </div>
              </form>
            </div>
          </div>,
          document.body
        )}

      {editCategoryOpen &&
        createPortal(
          <div
            className="sp-modal-backdrop"
            onClick={() => !saving && setEditCategoryOpen(false)}
          >
            <div
              className="sp-modal sp-modal-sm"
              role="dialog"
              aria-modal="true"
              onClick={(e) => e.stopPropagation()}
            >
              <h2>Edit category</h2>
              <form className="sp-form" onSubmit={handleCategorySave}>
                <label>
                  Name
                  <input
                    required
                    value={editCategoryName}
                    onChange={(e) => setEditCategoryName(e.target.value)}
                    autoFocus
                  />
                </label>
                <div className="sp-modal-actions">
                  <button
                    type="button"
                    className="sp-btn sp-btn-ghost"
                    disabled={saving}
                    onClick={() => setEditCategoryOpen(false)}
                  >
                    Cancel
                  </button>
                  <button type="submit" className="sp-btn sp-btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : 'Save'}
                  </button>
                </div>
              </form>
            </div>
          </div>,
          document.body
        )}

      {confirmDeleteCategory &&
        createPortal(
          <div
            className="sp-modal-backdrop"
            onClick={() => !saving && setConfirmDeleteCategory(false)}
          >
            <div
              className="sp-modal sp-modal-sm"
              role="dialog"
              aria-modal="true"
              onClick={(e) => e.stopPropagation()}
            >
              <h2>Delete category?</h2>
              <p>
                “{activeCategory?.name}” will be removed. Products stay in the store; they just
                lose this category assignment. Child categories must be deleted first.
              </p>
              <div className="sp-modal-actions">
                <button
                  type="button"
                  className="sp-btn sp-btn-ghost"
                  disabled={saving}
                  onClick={() => setConfirmDeleteCategory(false)}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  className="sp-btn sp-btn-danger"
                  disabled={saving}
                  onClick={handleCategoryDelete}
                >
                  {saving ? 'Deleting…' : 'Delete category'}
                </button>
              </div>
            </div>
          </div>,
          document.body
        )}

      {confirmTrash &&
        createPortal(
          <div className="sp-modal-backdrop" onClick={() => !saving && setConfirmTrash(null)}>
            <div
              className="sp-modal sp-modal-sm"
              role="dialog"
              aria-modal="true"
              onClick={(e) => e.stopPropagation()}
            >
              <h2>Move to trash?</h2>
              <p>
                “{confirmTrash.name}” will be moved to the trash. You can restore it later from
                WordPress.
              </p>
              <div className="sp-modal-actions">
                <button
                  type="button"
                  className="sp-btn sp-btn-ghost"
                  disabled={saving}
                  onClick={() => setConfirmTrash(null)}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  className="sp-btn sp-btn-danger"
                  disabled={saving}
                  onClick={handleTrashConfirm}
                >
                  {saving ? 'Trashing…' : 'Trash product'}
                </button>
              </div>
            </div>
          </div>,
          document.body
        )}

      {toast && (
        <Toast
          key={toast.id}
          message={toast.message}
          type={toast.type}
          onClose={() => setToast(null)}
        />
      )}
    </div>
  );
}
