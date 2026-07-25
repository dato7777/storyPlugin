import { useEffect } from 'react';
import { createPortal } from 'react-dom';

export default function Toast({ message, type = 'success', onClose }) {
  useEffect(() => {
    const timer = setTimeout(onClose, 3200);
    return () => clearTimeout(timer);
  }, [onClose]);

  return createPortal(
    <div className={`sp-toast sp-toast-${type}`} role="status">
      <span>{message}</span>
      <button type="button" className="sp-toast-close" onClick={onClose} aria-label="Dismiss">
        ×
      </button>
    </div>,
    document.body
  );
}
