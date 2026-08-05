/**
 * Open the WordPress media modal above Inventory Manager panels/modals.
 *
 * Important: wp.media fires `close` after a successful `select`. Resolving
 * empty on `close` synchronously races and drops the selection — defer the
 * cancel path so `select` always wins when the user confirmed.
 *
 * @param {{
 *   multiple?: boolean,
 *   title?: string,
 *   buttonText?: string,
 *   libraryType?: string|string[],
 *   onOpen?: () => void,
 *   onClose?: () => void,
 * }} options
 * @returns {Promise<Array<{ id: number, url: string, title: string, mime: string, type: string }>>}
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

    const libraryType = options.libraryType || 'image';

    const frame = wp.media({
      title: options.title || 'Select from Media Library',
      button: { text: options.buttonText || 'Use selected' },
      multiple: Boolean(options.multiple),
      library: { type: libraryType },
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
    let selected = false;

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
      requestAnimationFrame(bumpZ);
      setTimeout(bumpZ, 50);
      try {
        options.onOpen?.();
      } catch {
        /* ignore */
      }
    });

    frame.on('select', () => {
      selected = true;
      const selection = frame.state().get('selection');
      if (!selection) {
        finish([]);
        return;
      }
      const items = selection.toJSON().map((att) => {
        const mime = String(att.mime || '');
        const isVideo = mime.startsWith('video/') || att.type === 'video';
        // Prefer full/original URL; size variants are sometimes missing for new uploads.
        const url = String(
          (isVideo
            ? att.url
            : att.sizes?.large?.url || att.sizes?.full?.url || att.sizes?.medium?.url || att.url) ||
            ''
        );
        return {
          id: Number(att.id) || 0,
          url,
          title: String(att.title || att.filename || ''),
          mime,
          type: isVideo ? 'video' : 'image',
        };
      });
      // Keep rows with a valid attachment id even if URL mapping failed.
      finish(items.filter((i) => i.id > 0));
    });

    // Cancel / X: close fires for success too — wait a tick so select can win.
    frame.on('escape', () => {
      setTimeout(() => {
        if (!selected) finish([]);
      }, 0);
    });
    frame.on('close', () => {
      setTimeout(() => {
        if (!selected) finish([]);
      }, 50);
    });

    frame.open();
  });
}
