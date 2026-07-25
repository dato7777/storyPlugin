# StoryPhone Inventory Manager

Custom WooCommerce inventory dashboard for [storyphone.co.il](https://storyphone.co.il).  
Gives shop admins a simplified, mobile-first product management UI (inspired by merchant admin patterns) instead of the default wp-admin product screens.

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- Node.js 18+ (only to build the React UI)

## Install (staging first)

1. **Activate on staging before production.** Copy the plugin folder to your staging site’s `wp-content/plugins/`.
2. Ensure **WooCommerce is already active**.
3. Build the frontend assets (see below) if `build/main.js` is missing.
4. In wp-admin → Plugins, activate **StoryPhone Inventory Manager**.
5. Open **StoryPhone Inventory** in the left admin menu.
6. Smoke-test: list products, edit price/qty, toggle stock, create a product, trash one (confirm it lands in Trash, not permanently deleted).
7. Only then deploy the same build to production.

If WooCommerce is not active, the plugin shows an admin notice and deactivates itself — it will not fatal-error the site.

## Build the React app

From the plugin directory:

```bash
cd storyphone-inventory-manager
npm install
npm run build
```

This writes `build/main.js` and `build/main.css`. PHP enqueues those files **only** on the plugin’s own admin page.

For local UI iteration (optional):

```bash
npm run dev
```

Dev mode is for component work only; WordPress still needs the production `build/` output.

## REST API (for manual verification)

Base: `/wp-json/storyphone/v1/`

All routes require a user with `manage_woocommerce` and a valid `X-WP-Nonce` (`wp_rest`).

Example (while logged into wp-admin, copy the nonce from the page’s `storyphoneSettings` or create one via the REST cookie flow):

```bash
# List products
curl -sS -b 'wordpress_logged_in_COOKIE=...' \
  -H "X-WP-Nonce: YOUR_NONCE" \
  "https://staging.example/wp-json/storyphone/v1/products?page=1&per_page=10"

# Get one product
curl -sS -b '...' -H "X-WP-Nonce: YOUR_NONCE" \
  "https://staging.example/wp-json/storyphone/v1/products/123"

# Update stock
curl -sS -b '...' -H "X-WP-Nonce: YOUR_NONCE" -H "Content-Type: application/json" \
  -d '{"stock_quantity":12,"stock_status":"instock","price":"99.00"}' \
  "https://staging.example/wp-json/storyphone/v1/products/123"

# Create
curl -sS -b '...' -H "X-WP-Nonce: YOUR_NONCE" -H "Content-Type: application/json" \
  -d '{"name":"Test","price":"10","sku":"TEST-001"}' \
  "https://staging.example/wp-json/storyphone/v1/products"

# Categories
curl -sS -b '...' -H "X-WP-Nonce: YOUR_NONCE" \
  "https://staging.example/wp-json/storyphone/v1/categories"

# Trash (not permanent delete)
curl -sS -b '...' -H "X-WP-Nonce: YOUR_NONCE" -X DELETE \
  "https://staging.example/wp-json/storyphone/v1/products/123"
```

Easiest verification path: use the admin UI while logged in; the React app sends the localized nonce automatically.

## Plugin layout

```
storyphone-inventory-manager/
  storyphone-inventory-manager.php
  includes/
    class-admin-page.php
    class-rest-controller.php
    class-audit-log.php
  src/                 # React source
  build/               # Vite output (main.js + main.css)
  package.json
  vite.config.js
  SECURITY.md
  readme.txt
```

## Security notes

See [SECURITY.md](./SECURITY.md). Write actions are logged to `{prefix}storyphone_im_audit` and soft rate-limited (60 writes / user / minute).