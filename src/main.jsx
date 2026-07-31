import { Component } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './styles.css';

class RootErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    // Keep a breadcrumb in the console for staging debugging.
    // eslint-disable-next-line no-console
    console.error('[StoryPhone Inventory] render failed', error, info);
  }

  render() {
    if (this.state.error) {
      return (
        <div className="sp-shell" style={{ padding: 24 }}>
          <div className="sp-empty">
            <strong>Inventory failed to load.</strong>
            <p style={{ marginTop: 8 }}>{String(this.state.error?.message || this.state.error)}</p>
            <p style={{ marginTop: 8, opacity: 0.7 }}>
              Hard-refresh the page. If it persists, redeploy the latest plugin build.
            </p>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}

function showMountFailure(rootEl, error) {
  const message = error?.message || String(error || 'Unknown error');
  // eslint-disable-next-line no-console
  console.error('[StoryPhone Inventory] mount failed', error);
  rootEl.innerHTML =
    '<div class="sp-shell" style="padding:24px">' +
    '<div class="sp-empty">' +
    '<strong>Inventory failed to start.</strong>' +
    '<p style="margin-top:8px"></p>' +
    '</div></div>';
  const p = rootEl.querySelector('p');
  if (p) p.textContent = message;
}

function mountApp() {
  const rootEl = document.getElementById('storyphone-inventory-root');
  if (!rootEl) return;

  try {
    createRoot(rootEl).render(
      <RootErrorBoundary>
        <App />
      </RootErrorBoundary>
    );
  } catch (error) {
    showMountFailure(rootEl, error);
  }
}

// Mount immediately when this IIFE runs (footer script). Avoid waiting on
// DOMContentLoaded — other admin scripts can race the global scope before then.
mountApp();

