# CLAUDE.md

Reference notes for Claude Code when working in this repo (`jlex-api`, Laravel 11 API for J-Lex Pharmacy; frontend in `../jlex-web`).

## POS offline sync support (2026-10-08)

Backend half of the POS offline mode (see `../jlex-web/CLAUDE.md`).

- Migration `2026_10_08_000001_add_offline_sync_fields_to_sales_table` adds three columns to `sales`:
  - `client_uuid` (nullable, unique)
  - `offline_receipt_number` (the temporary number printed on the offline receipt)
  - `synced_at` (set when a sale arrives with an `offline_receipt_number`)
- `SaleController@store` is **idempotent on `client_uuid`**. If a sale with that uuid already exists (including soft-deleted ones), it returns `200 { status: 'Sale already recorded', sale }` without creating anything. The `catch` block does the same lookup, so a race between two concurrent retries hits the unique index and still returns the first sale instead of a 400.
- The `sale_date` sent from the device is an ISO string, usually UTC. The controller converts it to `config('app.timezone')` (Asia/Manila) and clamps future dates to `now()`. Without the conversion, Eloquent stores the UTC wall-clock time.
- Offline sales get a normal invoice number when they sync, so the `YYMMDD` prefix reflects the sync date, not the sale date. `sale_date` holds the real time.
- `GET /api/products/pos-snapshot` (auth) returns a slim copy of the whole catalog for the device cache: about 2.3k products, roughly 500 KB.
- Stock can go negative after a sync, because offline sales aren't checked against live stock. This is intentional and matches the existing "Add anyway" behaviour in the POS.
