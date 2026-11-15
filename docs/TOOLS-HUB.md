# Modular free tools

`wp-content/mu-plugins/dttl-tools-hub.php` adds the catalog and eight independent child pages under `/cong-cu-mien-phi/`. It preserves the legacy page content and existing application downloads. The first administrator visit creates missing pages only; an existing page with a conflicting slug is not overwritten.

Registry: `dttl-tools-hub/registry.json` (VI/EN labels, fields, constraints). Calculators: individual `modules/*.js`. Shared result rendering and chart: `core.js`; common colors and responsive layout: `tools.css`. Journal, checklist and learning progress have independent templates. Progress still uses the theme shortcode and existing account/local storage synchronization; no progress records are migrated or cleared.

Old investment-tool links are repointed by `links.js`, including known anchors. The legacy page also renders the catalog for clients without JavaScript. JavaScript-enabled legacy visits navigate to the catalog or corresponding tool. Existing application downloads retain their original plugin access rules.

Validation: `node tests/tools-hub.test.cjs`, `node --check` on JS. Deployment performs PHP syntax validation before any server writes and retains checksum guards and file backups. Review the live hub, EN calculators, learning progress, search and legacy links after deployment.

Rollback: restore the preceding file release using the normal deploy backup process. The former database page content is intact. New child pages can be returned to draft through WordPress if the module is removed.

## Version 1.1.0

Adds transaction cash-flow and sell-price scenarios, up to 20 average-cost lots, recovery cash shortfalls, current-versus-target allocation, inflation/contribution growth/goal funding/scenarios for compound growth, and result CSV exports. Formula inputs are numeric-only so TranslatePress's hidden language field is excluded.

The journal records **fully closed, unleveraged, VND-denominated long trades**, with explicit total fees/taxes and after-tax other income. It supports edit, undo the last deletion, safe CSV, JSON backups and validated merge-only restore (up to 2,000 trades). Journal and ten-item checklist use browser-local keys scoped to the current WP user ID, not server synchronization. Anonymous users share the guest key in that browser. Existing theme learning progress is read, never migrated or replaced; the summary suggests the first unchecked stage.

Browser-local storage is not encryption or cross-device backup. The UI clearly describes where data lives. Failed/corrupt reads block overwrites; failed writes preserve prior persisted state. CSV exports escape quotes and guard string cells starting with spreadsheet formula characters. JSON imports are size/schema/count checked before a single storage write; duplicate IDs retain the existing row.
