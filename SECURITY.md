# Security Model — StoryPhone Inventory Manager

## Permission model

- Every REST route under `/wp-json/storyphone/v1/` uses a `permission_callback` that requires:
  1. `current_user_can( 'manage_woocommerce' )`
  2. A valid WordPress REST nonce (`X-WP-Nonce` / `wp_rest`) verified with `wp_verify_nonce`
- The admin page is registered with the same `manage_woocommerce` capability.
- Frontend settings (`root`, `nonce`, `canManage`) are passed only via `wp_localize_script` on this plugin’s admin screen — never hardcoded.

## Product data access

- All product reads/writes go through WooCommerce CRUD (`wc_get_product()`, `WC_Product::set_*()` / `save()`, `WC_Product_Query`).
- No raw SQL against product tables.
- Deletes call `wp_trash_post()` only (soft delete / trash). Permanent deletion is not exposed.
- Inputs are sanitized before product mutation (`sanitize_text_field`, `absint`, `wc_clean`, `wp_kses_post`, allowlists for stock status).

## Write audit log + rate limit

- Every successful write (`create`, `update`, `upload_image`, `trash`) appends a row to `{prefix}storyphone_im_audit` with: user ID, product ID, action, sanitized field summary, IP, UTC timestamp.
- Soft rate limit: max 60 write requests per user per 60 seconds (transient counter). Exceeding returns HTTP 429.
- Table is created on plugin activation (`dbDelta`) and verified on bootstrap if the schema version option is missing.

## Media uploads

- Featured image replacement uses WordPress media helpers (`wp_handle_upload`, `wp_insert_attachment`, metadata generation).
- MIME types are restricted to JPEG, PNG, GIF, and WebP via `wp_check_filetype_and_ext` and upload overrides.
- Uploads are always attached to an existing product the caller is authorized to manage.
- No raw file paths or shell-like input are accepted from the frontend — only multipart file upload via WP helpers.

## Deliberately NOT exposed

- No direct database access endpoints
- No arbitrary filesystem write/read APIs
- No ability to bypass WooCommerce hooks/filters (mutations go through WC product objects)
- No external telemetry, analytics beacons, or hardcoded credentials
- No site-wide script injection — assets load only on the plugin’s admin page (`get_current_screen` / hook check)
- No permanent product deletion endpoint
- No unauthenticated or nonce-less write path

## Deployment

- Test on staging first.
- Deploy to production only as a zip install (Plugins → Add New → Upload), never by live-editing files over FTP on production.
