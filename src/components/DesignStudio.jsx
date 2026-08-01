import { useCallback, useEffect, useMemo, useState } from 'react';
import { fetchCategories, fetchDesignPage, fetchDesignPages, fetchProducts, saveDesignPage } from '../api.js';
import SectionContentEditor from './SectionContentEditor.jsx';

function moveItem(list, index, dir) {
  const next = list.slice();
  const target = index + dir;
  if (target < 0 || target >= next.length) return list;
  const tmp = next[index];
  next[index] = next[target];
  next[target] = tmp;
  return next;
}

const EMPTY_SECTION_CONTENT = {
  hero: { custom: false, chip_category_ids: [], title: '', subtitle: '' },
  'story-rail': { custom: false, category_ids: [], title: '', subtitle: '' },
  'pick-deck': { custom: false, product_id: 0, title: '', subtitle: '' },
  'quick-reach': { custom: false, category_ids: [], title: '', subtitle: '' },
  'heat-board': { custom: false, product_ids: [], title: '', subtitle: '' },
  showcase: { custom: false, product_ids: [], title: '', subtitle: '' },
  deal: { custom: false, product_id: 0 },
  trust: { custom: false, title: '', items: [] },
  'editor-content': { note: '' },
  cta: { custom: false, title: '', text: '', button_label: '', button_url: '' },
};

function mergeSectionContent(saved) {
  const out = {};
  Object.keys(EMPTY_SECTION_CONTENT).forEach((key) => {
    out[key] = {
      ...EMPTY_SECTION_CONTENT[key],
      ...(saved && typeof saved[key] === 'object' && saved[key] ? saved[key] : {}),
    };
  });
  return out;
}

export default function DesignStudio({ showToast }) {
  const [pages, setPages] = useState([]);
  const [loadingPages, setLoadingPages] = useState(true);
  const [activeKey, setActiveKey] = useState(null);
  const [design, setDesign] = useState(null);
  const [navItems, setNavItems] = useState([]);
  const [sections, setSections] = useState([]);
  const [sectionContent, setSectionContent] = useState(EMPTY_SECTION_CONTENT);
  const [sectionPreview, setSectionPreview] = useState({});
  const [expandedSection, setExpandedSection] = useState(null);
  const [categories, setCategories] = useState([]);
  const [productsById, setProductsById] = useState({});
  const [loadingDesign, setLoadingDesign] = useState(false);
  const [saving, setSaving] = useState(false);

  const storefrontReady = Boolean(window.storyphoneSettings?.storefrontReady);

  const loadPages = useCallback(async () => {
    setLoadingPages(true);
    try {
      const data = await fetchDesignPages();
      const list = data.pages || [];
      setPages(list);
      if (!activeKey && list.length) {
        const firstEditable = list.find((p) => !p.readonly) || list[0];
        setActiveKey(firstEditable.key);
      }
    } catch (err) {
      showToast?.(err.message || 'Could not load pages', 'error');
    } finally {
      setLoadingPages(false);
    }
  }, [activeKey, showToast]);

  useEffect(() => {
    loadPages();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const [catData, prodData] = await Promise.all([
          fetchCategories(),
          // Design pickers: only active, in-stock catalog items.
          fetchProducts({ page: 1, perPage: 250, collection: 'instock' }),
        ]);
        if (cancelled) return;
        setCategories(catData.categories || []);
        const map = {};
        (prodData.products || []).forEach((p) => {
          const enabled = p.enabled !== false;
          const inStock = (p.stock_status || 'instock') === 'instock';
          if (enabled && inStock) {
            map[p.id] = p;
          }
        });
        setProductsById(map);
      } catch {
        /* non-blocking for design shell */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!activeKey) return;
    const page = pages.find((p) => p.key === activeKey);
    if (page?.readonly) {
      setDesign(null);
      setNavItems([]);
      setSections([]);
      setSectionContent(EMPTY_SECTION_CONTENT);
      setSectionPreview({});
      return;
    }

    let cancelled = false;
    (async () => {
      setLoadingDesign(true);
      try {
        const data = await fetchDesignPage(activeKey);
        if (cancelled) return;
        setDesign(data);
        const navBlock = (data.blocks || []).find((b) => b.type === 'nav_categories');
        const secBlock = (data.blocks || []).find((b) => b.type === 'sections');
        setNavItems(navBlock?.items || []);
        setSections(secBlock?.items || []);
        setSectionContent(mergeSectionContent(data.section_content || {}));
        setSectionPreview(data.section_preview || {});
      } catch (err) {
        if (!cancelled) showToast?.(err.message || 'Could not load design', 'error');
      } finally {
        if (!cancelled) setLoadingDesign(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [activeKey, pages, showToast]);

  const categoryOptions = useMemo(
    () =>
      (categories || []).map((c) => ({
        id: c.id,
        name: c.name,
        count: c.count ?? 0,
      })),
    [categories]
  );

  async function handleSave() {
    if (!activeKey || !design || loadingDesign) return;
    const enabled = navItems.filter((i) => i.enabled);
    if (navItems.length) {
      if (enabled.length === 0) {
        showToast?.('Turn on at least one navbar category before saving', 'error');
        return;
      }
      if (enabled.length > 9) {
        showToast?.('Max 9 navbar items — turn some off before saving', 'error');
        return;
      }
    }
    setSaving(true);
    try {
      const nav_category_ids = enabled.map((i) => i.id);
      const data = await saveDesignPage(activeKey, {
        ...(nav_category_ids.length ? { nav_category_ids } : {}),
        sections,
        section_content: mergeSectionContent(sectionContent),
      });
      setDesign(data);
      const navBlock = (data.blocks || []).find((b) => b.type === 'nav_categories');
      const secBlock = (data.blocks || []).find((b) => b.type === 'sections');
      setNavItems(navBlock?.items || []);
      setSections(secBlock?.items || []);
      setSectionContent(mergeSectionContent(data.section_content || {}));
      setSectionPreview(data.section_preview || {});
      showToast?.(
        `Design saved${
          nav_category_ids.length
            ? ` — header ${nav_category_ids.length} categor${
                nav_category_ids.length === 1 ? 'y' : 'ies'
              }`
            : ''
        }. Hard-refresh homepage preview.`,
        'success'
      );
    } catch (err) {
      showToast?.(err.message || 'Save failed', 'error');
    } finally {
      setSaving(false);
    }
  }

  const activePage = pages.find((p) => p.key === activeKey);

  return (
    <div className="sp-design">
      <header className="sp-design-hero">
        <div>
          <p className="sp-design-kicker">Storefront</p>
          <h2 className="sp-design-title">Design</h2>
          <p className="sp-design-sub">
            Pick a page, then edit blocks from top to bottom. Changes apply after Save.
          </p>
          {!storefrontReady ? (
            <p className="sp-design-sub" style={{ color: '#b91c1c', marginTop: 10 }}>
              StoryPhone Pages plugin is not active on this site. Homepage Design cannot render
              without it.
            </p>
          ) : (
            <p className="sp-design-sub" style={{ marginTop: 10 }}>
              Expand a section to choose categories/products and optional titles. Empty picks keep
              automatic content.
            </p>
          )}
        </div>
      </header>

      <div className="sp-design-layout">
        <aside className="sp-design-pages" aria-label="Pages">
          <h3 className="sp-design-aside-title">Pages</h3>
          {loadingPages ? (
            <p className="sp-design-muted">Loading pages…</p>
          ) : (
            <ul className="sp-design-page-list">
              {pages.map((page) => (
                <li key={page.key}>
                  <button
                    type="button"
                    className={`sp-design-page-card ${activeKey === page.key ? 'is-active' : ''} ${
                      page.readonly ? 'is-readonly' : ''
                    }`}
                    onClick={() => setActiveKey(page.key)}
                  >
                    <span className="sp-design-page-badge">{page.badge}</span>
                    <span className="sp-design-page-name">{page.title}</span>
                    <span className="sp-design-page-desc">{page.description}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </aside>

        <section className="sp-design-editor">
          {!activePage ? (
            <div className="sp-empty">Select a page to start editing.</div>
          ) : activePage.readonly ? (
            <div className="sp-design-readonly">
              <h3>{activePage.title}</h3>
              <p>{activePage.description}</p>
              <div className="sp-design-readonly-actions">
                {activePage.url ? (
                  <a className="sp-btn sp-btn-soft" href={activePage.url} target="_blank" rel="noreferrer">
                    View page
                  </a>
                ) : null}
                {activePage.edit_url ? (
                  <a
                    className="sp-btn sp-btn-primary"
                    href={activePage.edit_url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Open in WordPress
                  </a>
                ) : null}
              </div>
            </div>
          ) : loadingDesign ? (
            <div className="sp-empty">Loading page design…</div>
          ) : (
            <>
              <div className="sp-design-editor-bar">
                <div>
                  <h3 className="sp-design-editor-title">{design?.title || activePage.title}</h3>
                  <p className="sp-design-muted">
                    Navbar first, then each section’s content down the page.
                  </p>
                </div>
                <button
                  type="button"
                  className="sp-btn sp-btn-primary sp-btn-create-cat"
                  disabled={saving}
                  onClick={handleSave}
                >
                  {saving ? 'Saving…' : 'Save design'}
                </button>
              </div>

              <div className="sp-design-block">
                <div className="sp-design-block-head">
                  <span className="sp-design-block-step">1</span>
                  <div>
                    <h4>Navbar items</h4>
                    <p>
                      Toggle on/off, reorder (top = first). Max 9. Currently on:{' '}
                      <strong>{navItems.filter((i) => i.enabled).length}</strong>
                      {design?.nav_custom ? '' : ' — not saved yet'}.
                    </p>
                  </div>
                </div>
                <ul className="sp-design-item-list">
                  {navItems.map((item, index) => (
                    <li
                      key={item.id}
                      className={`sp-design-item ${item.enabled ? 'is-on' : 'is-off'}`}
                    >
                      <label className={`sp-switch ${item.enabled ? 'is-on' : ''}`}>
                        <input
                          type="checkbox"
                          checked={!!item.enabled}
                          onChange={() =>
                            setNavItems((list) =>
                              list.map((row, i) =>
                                i === index ? { ...row, enabled: !row.enabled } : row
                              )
                            )
                          }
                        />
                        <span className="sp-switch-track" aria-hidden="true" />
                      </label>
                      <div className="sp-design-item-body">
                        <strong>{item.name}</strong>
                        <span>{item.count} products</span>
                      </div>
                      <div className="sp-design-item-move">
                        <button
                          type="button"
                          className="sp-btn sp-btn-ghost sp-btn-sm"
                          disabled={index === 0}
                          onClick={() => setNavItems((list) => moveItem(list, index, -1))}
                          aria-label="Move up"
                        >
                          ↑
                        </button>
                        <button
                          type="button"
                          className="sp-btn sp-btn-ghost sp-btn-sm"
                          disabled={index === navItems.length - 1}
                          onClick={() => setNavItems((list) => moveItem(list, index, 1))}
                          aria-label="Move down"
                        >
                          ↓
                        </button>
                      </div>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="sp-design-block">
                <div className="sp-design-block-head">
                  <span className="sp-design-block-step">2</span>
                  <div>
                    <h4>Page sections</h4>
                    <p>
                      Toggle visibility, reorder, then expand a section to choose what appears
                      inside.
                    </p>
                  </div>
                </div>
                <ul className="sp-design-item-list sp-design-section-list">
                  {sections.map((item, index) => {
                    const open = expandedSection === item.id;
                    return (
                      <li
                        key={item.id}
                        className={`sp-design-section-card ${item.enabled ? 'is-on' : 'is-off'} ${
                          open ? 'is-open' : ''
                        }`}
                      >
                        <div className="sp-design-section-card-top">
                          <label className={`sp-switch ${item.enabled ? 'is-on' : ''}`}>
                            <input
                              type="checkbox"
                              checked={!!item.enabled}
                              onChange={() =>
                                setSections((list) =>
                                  list.map((row, i) =>
                                    i === index ? { ...row, enabled: !row.enabled } : row
                                  )
                                )
                              }
                            />
                            <span className="sp-switch-track" aria-hidden="true" />
                          </label>
                          <button
                            type="button"
                            className="sp-design-section-toggle"
                            onClick={() =>
                              setExpandedSection((cur) => (cur === item.id ? null : item.id))
                            }
                          >
                            <span className="sp-design-item-body">
                              <strong>{item.label}</strong>
                              <span className="sp-design-item-id">{item.id}</span>
                            </span>
                            <span className="sp-design-section-chev" aria-hidden="true">
                              {open ? '▾' : '▸'}
                            </span>
                          </button>
                          <div className="sp-design-item-move">
                            <button
                              type="button"
                              className="sp-btn sp-btn-ghost sp-btn-sm"
                              disabled={index === 0}
                              onClick={() => setSections((list) => moveItem(list, index, -1))}
                              aria-label="Move up"
                            >
                              ↑
                            </button>
                            <button
                              type="button"
                              className="sp-btn sp-btn-ghost sp-btn-sm"
                              disabled={index === sections.length - 1}
                              onClick={() => setSections((list) => moveItem(list, index, 1))}
                              aria-label="Move down"
                            >
                              ↓
                            </button>
                          </div>
                        </div>
                        {open ? (
                          <div className="sp-design-section-body">
                            <SectionContentEditor
                              sectionId={item.id}
                              content={sectionContent[item.id] || EMPTY_SECTION_CONTENT[item.id] || {}}
                              onChange={(next) =>
                                setSectionContent((prev) => ({
                                  ...prev,
                                  [item.id]: next,
                                }))
                              }
                              categories={categoryOptions}
                              productsById={productsById}
                              livePreview={sectionPreview[item.id] || null}
                            />
                          </div>
                        ) : null}
                      </li>
                    );
                  })}
                </ul>
              </div>
            </>
          )}
        </section>
      </div>
    </div>
  );
}
