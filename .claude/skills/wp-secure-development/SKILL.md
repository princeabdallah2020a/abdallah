---
name: wp-secure-development
description: "Use when writing or changing WordPress PHP (plugins, REST routes, AJAX, forms, custom tables, cron, uploads) to apply the mandatory security and quality checklist."
---

# WordPress secure development

## Every entry point
| Entry | Must have |
|---|---|
| Form / admin action | `wp_nonce_field` + `check_admin_referer`/`wp_verify_nonce`, `current_user_can( <specific cap> )` |
| AJAX | `check_ajax_referer`, capability check, `wp_send_json_error` on failure |
| REST route | `permission_callback` (never `__return_true` for private data), `args` with `sanitize_callback` + `validate_callback` |
| Public form | nonce, honeypot, rate limit (plugin `class-rate-limiter.php`), consent checkbox when personal data |
| Upload | `wp_check_filetype_and_ext`, size limit, allowed MIME list, store outside guessable paths when private |

## Data
- Input: `sanitize_text_field`, `sanitize_email`, `absint`, `esc_url_raw`, `wp_kses_post` — by field type.
- Output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses` — escape late, at echo time.
- SQL: `$wpdb->prepare()` for every variable; `$wpdb->esc_like()` for LIKE; table names from the plugin helper only.
- Multi-tenant: every query on dealer data filters by the current dealer id (prevent IDOR).

## Code
- `defined( 'ABSPATH' ) || exit;` at the top of every PHP file.
- Return `WP_Error`; log with context, never expose SQL, paths or secrets.
- Secrets encrypted (plugin crypto class), never in code, logs or JS.
- All strings translatable with the plugin text domain; RTL-safe markup.
- Schema changes via versioned upgrade routine (`dbDelta`), never manual.

## Before handing over
`php -l` each touched file · grep the diff for `$_POST`, `$_GET`, `$_REQUEST`, `echo $` and check each one · run `php tools/tests/run.php` on a local site if available.
