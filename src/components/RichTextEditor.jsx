import { useEffect, useRef, useState } from 'react';
import { uploadMedia } from '../api.js';

const FONT_FAMILIES = [
  { label: 'Arial', value: 'Arial, Helvetica, sans-serif' },
  { label: 'Helvetica', value: 'Helvetica, Arial, sans-serif' },
  { label: 'Georgia', value: 'Georgia, serif' },
  { label: 'Times New Roman', value: '"Times New Roman", Times, serif' },
  { label: 'Verdana', value: 'Verdana, Geneva, sans-serif' },
  { label: 'Tahoma', value: 'Tahoma, Geneva, sans-serif' },
  { label: 'Trebuchet MS', value: '"Trebuchet MS", sans-serif' },
  { label: 'Courier New', value: '"Courier New", Courier, monospace' },
  { label: 'Palatino', value: '"Palatino Linotype", Palatino, serif' },
  { label: 'Garamond', value: 'Garamond, serif' },
  { label: 'Segoe UI', value: '"Segoe UI", Tahoma, sans-serif' },
  { label: 'David (Hebrew)', value: 'David, "Times New Roman", serif' },
  { label: 'Arial Hebrew', value: '"Arial Hebrew", Arial, sans-serif' },
];

const FONT_SIZES = [10, 11, 12, 14, 16, 18, 20, 22, 24, 28, 32, 36, 48, 72];

function normalizeIncomingHtml(value) {
  if (!value) return '';
  const raw = String(value).trim();
  if (!raw) return '';
  if (!/<[a-z][\s\S]*>/i.test(raw)) {
    return raw
      .split(/\n{2,}/)
      .map((block) => `<p>${block.replace(/\n/g, '<br>')}</p>`)
      .join('');
  }
  return raw;
}

function exec(command, value = null) {
  document.execCommand(command, false, value);
}

function applyInlineStyle(styleMap) {
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return false;
  const range = sel.getRangeAt(0);

  if (range.collapsed) {
    const span = document.createElement('span');
    Object.assign(span.style, styleMap);
    span.appendChild(document.createTextNode('\u200B'));
    range.insertNode(span);
    range.setStart(span.firstChild, 1);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
    return true;
  }

  const span = document.createElement('span');
  Object.assign(span.style, styleMap);
  try {
    range.surroundContents(span);
  } catch {
    const frag = range.extractContents();
    span.appendChild(frag);
    range.insertNode(span);
  }
  sel.removeAllRanges();
  const next = document.createRange();
  next.selectNodeContents(span);
  next.collapse(false);
  sel.addRange(next);
  return true;
}

function insertHtmlAtCursor(html) {
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  const range = sel.getRangeAt(0);
  range.deleteContents();
  const temp = document.createElement('div');
  temp.innerHTML = html;
  const frag = document.createDocumentFragment();
  let node;
  let last = null;
  while ((node = temp.firstChild)) {
    last = frag.appendChild(node);
  }
  range.insertNode(frag);
  if (last) {
    range.setStartAfter(last);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  }
}

export default function RichTextEditor({
  value,
  onChange,
  placeholder = 'Write your product description…',
  disabled = false,
}) {
  const editorRef = useRef(null);
  const fileInputRef = useRef(null);
  const savedRange = useRef(null);
  const [mode, setMode] = useState('edit');
  const [fontFamily, setFontFamily] = useState(FONT_FAMILIES[0].value);
  const [fontSize, setFontSize] = useState(16);
  const [uploading, setUploading] = useState(false);
  const lastHtml = useRef('');

  useEffect(() => {
    const html = normalizeIncomingHtml(value);
    lastHtml.current = html;
    if (editorRef.current && mode === 'edit' && editorRef.current.innerHTML !== html) {
      editorRef.current.innerHTML = html || '';
    }
  }, [value, mode]);

  function emitChange() {
    if (!editorRef.current) return;
    const html = editorRef.current.innerHTML;
    if (html === lastHtml.current) return;
    lastHtml.current = html;
    onChange(html === '<br>' ? '' : html);
  }

  function rememberSelection() {
    const sel = window.getSelection();
    if (sel && sel.rangeCount) {
      savedRange.current = sel.getRangeAt(0).cloneRange();
    }
  }

  function restoreSelection() {
    const sel = window.getSelection();
    if (!sel || !savedRange.current) return;
    sel.removeAllRanges();
    sel.addRange(savedRange.current);
  }

  function focusEditor() {
    editorRef.current?.focus();
    restoreSelection();
  }

  function run(command, commandValue = null) {
    if (disabled || mode !== 'edit') return;
    focusEditor();
    exec(command, commandValue);
    emitChange();
  }

  function applyFontFamily(family) {
    setFontFamily(family);
    focusEditor();
    applyInlineStyle({ fontFamily: family });
    emitChange();
  }

  function applyFontSize(px) {
    const size = Number(px) || 16;
    setFontSize(size);
    focusEditor();
    applyInlineStyle({ fontSize: `${size}px` });
    emitChange();
  }

  function applyColor(color) {
    focusEditor();
    exec('foreColor', color);
    emitChange();
  }

  function applyHighlight(color) {
    focusEditor();
    if (!document.execCommand('hiliteColor', false, color)) {
      exec('backColor', color);
    }
    emitChange();
  }

  function applyLink() {
    const url = window.prompt('Link URL', 'https://');
    if (!url) return;
    run('createLink', url);
  }

  async function handleImageFile(file) {
    if (!file || disabled) return;
    setUploading(true);
    try {
      focusEditor();
      const result = await uploadMedia(file);
      const url = result.url;
      if (!url) throw new Error('Upload returned no URL');
      insertHtmlAtCursor(
        `<p><img src="${url}" alt="" style="max-width:100%;height:auto;border-radius:8px;" /></p><p></p>`
      );
      emitChange();
    } catch (err) {
      window.alert(err.message || 'Image upload failed');
    } finally {
      setUploading(false);
    }
  }

  const previewHtml = normalizeIncomingHtml(value);
  const busy = disabled || uploading;

  return (
    <div className={`sp-rte ${busy && uploading ? 'is-uploading' : ''} ${disabled ? 'is-disabled' : ''}`}>
      <div className="sp-rte-chrome">
        <div className="sp-rte-chrome-left">
          <span className="sp-rte-title">Description editor</span>
          <span className="sp-rte-hint">Enter = new line · insert images anywhere in the text</span>
        </div>
        <div className="sp-seg sp-rte-mode" role="group" aria-label="Description mode">
          <button
            type="button"
            className={`sp-seg-btn ${mode === 'edit' ? 'is-active' : ''}`}
            onClick={() => setMode('edit')}
            disabled={busy}
          >
            Edit
          </button>
          <button
            type="button"
            className={`sp-seg-btn ${mode === 'preview' ? 'is-active' : ''}`}
            onClick={() => {
              emitChange();
              setMode('preview');
            }}
            disabled={busy}
          >
            Preview
          </button>
        </div>
      </div>

      {mode === 'edit' ? (
        <>
          <div className="sp-rte-toolbar" role="toolbar" aria-label="Text formatting">
            <div className="sp-rte-group">
              <select
                className="sp-rte-select sp-rte-font"
                title="Font"
                value={fontFamily}
                disabled={busy}
                onMouseDown={rememberSelection}
                onChange={(e) => applyFontFamily(e.target.value)}
                style={{ fontFamily }}
              >
                {FONT_FAMILIES.map((f) => (
                  <option key={f.value} value={f.value} style={{ fontFamily: f.value }}>
                    {f.label}
                  </option>
                ))}
              </select>

              <select
                className="sp-rte-select sp-rte-size"
                title="Font size (px)"
                value={fontSize}
                disabled={busy}
                onMouseDown={rememberSelection}
                onChange={(e) => applyFontSize(e.target.value)}
              >
                {FONT_SIZES.map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
            </div>

            <div className="sp-rte-group">
              <button
                type="button"
                className="sp-rte-btn"
                title="Bold"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('bold')}
              >
                <strong>B</strong>
              </button>
              <button
                type="button"
                className="sp-rte-btn"
                title="Italic"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('italic')}
              >
                <em>I</em>
              </button>
              <button
                type="button"
                className="sp-rte-btn"
                title="Underline"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('underline')}
              >
                <span className="sp-rte-u">U</span>
              </button>
            </div>

            <div className="sp-rte-group">
              <label className="sp-rte-color" title="Text color" onMouseDown={rememberSelection}>
                <span className="sp-rte-color-letter">A</span>
                <span className="sp-rte-color-bar" />
                <input
                  type="color"
                  defaultValue="#0f172a"
                  disabled={busy}
                  onChange={(e) => applyColor(e.target.value)}
                />
              </label>
              <label className="sp-rte-color" title="Highlight" onMouseDown={rememberSelection}>
                <span className="sp-rte-color-letter">ab</span>
                <span className="sp-rte-color-bar is-highlight" />
                <input
                  type="color"
                  defaultValue="#fde68a"
                  disabled={busy}
                  onChange={(e) => applyHighlight(e.target.value)}
                />
              </label>
            </div>

            <div className="sp-rte-group">
              <button
                type="button"
                className="sp-rte-btn"
                title="Bulleted list"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('insertUnorderedList')}
              >
                •••
              </button>
              <button
                type="button"
                className="sp-rte-btn"
                title="Numbered list"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('insertOrderedList')}
              >
                1.
              </button>
            </div>

            <div className="sp-rte-group">
              <button
                type="button"
                className="sp-rte-btn"
                title="Align left"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('justifyLeft')}
              >
                ⬅
              </button>
              <button
                type="button"
                className="sp-rte-btn"
                title="Align center"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('justifyCenter')}
              >
                ↔
              </button>
              <button
                type="button"
                className="sp-rte-btn"
                title="Align right"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('justifyRight')}
              >
                ➡
              </button>
            </div>

            <div className="sp-rte-group">
              <button
                type="button"
                className="sp-rte-btn"
                title="Insert link"
                onMouseDown={(e) => e.preventDefault()}
                onClick={applyLink}
              >
                Link
              </button>
              <button
                type="button"
                className="sp-rte-btn sp-rte-btn-accent"
                title="Insert image"
                disabled={busy}
                onMouseDown={(e) => {
                  e.preventDefault();
                  rememberSelection();
                }}
                onClick={() => fileInputRef.current?.click()}
              >
                {uploading ? '…' : 'Image'}
              </button>
              <button
                type="button"
                className="sp-rte-btn"
                title="Clear formatting"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => run('removeFormat')}
              >
                Clear
              </button>
            </div>

            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png,image/gif,image/webp"
              hidden
              onChange={(e) => {
                const file = e.target.files?.[0];
                e.target.value = '';
                if (file) handleImageFile(file);
              }}
            />
          </div>

          <div
            ref={editorRef}
            className="sp-rte-editor"
            contentEditable={!busy}
            role="textbox"
            aria-multiline="true"
            data-placeholder={placeholder}
            suppressContentEditableWarning
            onMouseUp={rememberSelection}
            onKeyUp={rememberSelection}
            onInput={emitChange}
            onBlur={emitChange}
          />
        </>
      ) : (
        <div className="sp-rte-preview">
          <p className="sp-rte-preview-label">Website preview</p>
          {previewHtml ? (
            <div className="sp-rte-preview-body" dangerouslySetInnerHTML={{ __html: previewHtml }} />
          ) : (
            <p className="sp-muted">Nothing to preview yet.</p>
          )}
        </div>
      )}
    </div>
  );
}
