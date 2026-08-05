# Hand-authored acf-json is the sole author of ACF field groups

ACF offers three ways to define field groups: the admin UI (with JSON sync), PHP registration, and local JSON files. We author minimal JSON in `acf-json/` by hand and never use the admin field-group editor to create, edit, or sync groups — no DB copies, no sync step, no `modified` timestamps.

Chosen because field work here is code-driven (often agent-driven): hand-authored JSON gives deterministic, reviewable, site-prefixed keys (`field_lk_fp_hero_heading`, not `field_66a1b2c3d4e5f`), minimal diffs, and one source of truth that ACF loads automatically at runtime. The admin-UI workflow was rejected for its hash keys, verbose exports, and an easy-to-forget sync step whose failure mode is silent: a stale DB copy shows in the editor while the JSON quietly wins on the front end (this happened on Johnson Miller). PHP registration was rejected as more verbose with no offsetting benefit.

## Consequences

- Field edits happen only in the repo; anyone editing groups in wp-admin is working on a copy that will be overridden. If a group ever lands in the DB, delete the DB copy.
- The ACF UI remains useful read-only (browsing field values on posts), and the options *page* is still registered in code on `acf/init`.
- Reversal cost grows over time: seed scripts and content migrations address fields by key, so switching to UI-generated keys later would orphan those references.
