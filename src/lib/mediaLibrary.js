/**
 * Open the WordPress media modal above Inventory Manager panels/modals.
 *
 * @param {{
 *   multiple?: boolean,
 *   title?: string,
 *   buttonText?: string,
 *   onOpen?: () => void,
 *   onClose?: () => void,
 * }} options
 * @returns {Promise<Array<{ id: number, url: string, title: string }>>}
 */
export function openMediaLibrary(options = {}) {
  return new Promise((resolve, reject) => {
    const wp = window.wp;
    if (!wp?.media) {
      reject(
        new Error(
          'WordPress Media Library is not available. Reload the Inventory Manager page and try again.'
        )
      );
      return;
    }

    const frame = wp.media({
      title: options.title || 'Select from Media Library',
      button: { text: options.buttonText || 'Use selected' },
      multiple: Boolean(options.multiple),
      library: { type: 'image' },
    });

    const bumpZ = () => {
      document.querySelectorAll('.media-modal-backdrop').forEach((el) => {
        el.style.zIndex = '200000';
      });
      document.querySelectorAll('.media-modal').forEach((el) => {
        el.style.zIndex = '200010';
      });
    };

    let settled = false;
    const finish = (items) => {
      if (settled) return;
      settled = true;
      try {
        options.onClose?.();
      } catch {
        /* ignore */
      }
      resolve(items);
    };

    frame.on('open', () => {
      bumpZ();
      // WP sometimes paints modal after open; bump again next frames.
      requestAnimationFrame(bumpZ);
      setTimeout(bumpZ, 50);
      try {
        options.onOpen?.();
      } catch {
        /* ignore */
      }
    });

    frame.on('select', () => {
      const selection = frame.state().get('selection');
      if (!selection) {
        finish([]);
        return;
      }
      const items = selection.toJSON().map((att) => ({
        id: Number(att.id) || 0,
        url: String(att.sizes?.medium?.url || att.url || ''),
        title: String(att.title || att.filename || ''),
      }));
      finish(items.filter((i) => i.id > 0));
    });

    frame.on('escape', () => finish([]));
    frame.on('close', () => finish([]));

    frame.open();
  });
}
