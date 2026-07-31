import { useCallback, useEffect, useState } from 'react';
import { fetchDesignPage, fetchDesignPages, saveDesignPage } from '../api.js';

function moveItem(list, index, dir) {
  const next = list.slice();
  const target = index + dir;
  if (target < 0 || target >= next.length) return list;
  const tmp = next[index];
  next[index] = next[target];
  next[target] = tmp;
  return next;
}

export default function DesignStudio({ showToast }) {
  const [pages, setPages] = useState([]);
  const [loadingPages, setLoadingPages] = useState(true);
  const [activeKey, setActiveKey] = useState(null);
  const [design, setDesign] = useState(null);
  const [navItems, setNavItems] = useState([]);
  const [sections, setSections] = useState([]);
  const [loadingDesign, setLoadingDesign] = useState(false);
  const [saving, setSaving] = useState(false);

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
    if (!activeKey) return;
    const page = pages.find((p) => p.key === activeKey);
    if (page?.readonly) {
      setDesign(null);
      setNavItems([]);
      setSections([]);
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

  async function handleSave() {
    if (!activeKey || !design || loadingDesign) return;
    if (!navItems.length) {
      showToast?.('Navbar list not loaded yet — wait a moment and try again', 'error');
      return;
    }
    const enabled = navItems.filter((i) => i.enabled);
    if (enabled.length === 0) {
      showToast?.('Turn on at least one navbar category before saving', 'error');
      return;
    }
    if (enabled.length > 9) {
      showToast?.('Max 9 navbar items — turn some off before saving', 'error');
      return;
    }
    setSaving(true);
    try {
      const nav_category_ids = enabled.map((i) => i.id);
      const data = await saveDesignPage(activeKey, {
        nav_category_ids,
        sections,
      });
      setDesign(data);
      const navBlock = (data.blocks || []).find((b) => b.type === 'nav_categories');
      const secBlock = (data.blocks || []).find((b) => b.type === 'sections');
      setNavItems(navBlock?.items || []);
      setSections(secBlock?.items || []);
      showToast?.(
        `Design saved — header will show ${nav_category_ids.length} categor${
          nav_category_ids.length === 1 ? 'y' : 'ies'
        }. Hard-refresh the homepage preview.`,
        'success'
      );
    } catch (err) {
      showToast?.(err.message || 'Save failed', 'error');
    } finally {
      setSaving(false);
    }
  }

  const activePage = pages.find((p) => p.key === activeKey);

  const storefrontReady = Boolean(window.storyphoneSettings?.storefrontReady);

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
              After Save, hard-refresh the StoryPhone — Home preview. Logged-in admins see a dark
              badge (bottom-left) with nav mode/count — it must say <strong>custom</strong> and your
              item count (e.g. 7), not auto · 9.
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
                  <p className="sp-design-muted">Edit in order — navbar first, then sections down the page.</p>
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
                      Toggle on/off, reorder with arrows (top = first in header). Max 9 on site.
                      Currently on:{' '}
                      <strong>{navItems.filter((i) => i.enabled).length}</strong>
                      {design?.nav_custom ? '' : ' — not saved yet (header still uses auto top 9)'}.
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
                    <p>Homepage stack from top to bottom. Disable what you don’t want shown.</p>
                  </div>
                </div>
                <ul className="sp-design-item-list">
                  {sections.map((item, index) => (
                    <li
                      key={item.id}
                      className={`sp-design-item ${item.enabled ? 'is-on' : 'is-off'}`}
                    >
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
                      <div className="sp-design-item-body">
                        <strong>{item.label}</strong>
                        <span className="sp-design-item-id">{item.id}</span>
                      </div>
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
                    </li>
                  ))}
                </ul>
              </div>
            </>
          )}
        </section>
      </div>
    </div>
  );
}
