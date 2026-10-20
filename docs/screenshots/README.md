# Application screenshots

These PNG files are genuine captures of a local StockPilot session (Laravel + Livewire UI). They are not AI-generated and do not invent metrics or records. The project [README](../../README.md) embeds a curated subset; this folder holds the full set.

## Gallery

| File | What it shows | Notes |
| --- | --- | --- |
| `dashboard.png` | Dashboard metrics, low-stock attention, recent movements | Live inventory snapshot |
| `products.png` | Products listing with search and stock badge | One catalog product present |
| `product-editor.png` | Edit product form (opened, not submitted) | Form cancelled after capture |
| `categories.png` | Categories listing | Live category data |
| `stock-movements.png` | Movement history with type badges | Live ledger rows |
| `suppliers.png` | Suppliers index | **Empty state** (no suppliers) |
| `purchase-orders.png` | Purchase order index | **Empty state** (no orders) |
| `reports.png` | Reports & Analytics hub | Overview metrics + report cards |
| `low-stock-report.png` | Low stock report filters and results | Empty match set (no low-stock items) |
| `stock-movement-report.png` | Filterable movement report table | Live movement rows |
| `purchasing-report.png` | Purchasing report metrics and lists | Zero PO activity (honest empty aggregates) |
| `audit-logs.png` | Audit log filters and history table | Catalog update row; entity IDs masked |
| `settings.png` | Business profile and display preferences | Optional contact fields empty |

## Unavailable workflow screens

| Screen | Reason |
| --- | --- |
| Purchase order detail / receiving | No existing purchase order. Demo data was not created. |

Do not add fabricated `purchase-order-detail.png` until a real order exists in a publishable environment.

## Privacy

Before capture, the authenticated session header email was hidden with temporary browser CSS. On audit logs, entity ID text was hidden so internal database identifiers are not published. Display names such as “Test” and sample product SKUs from the local workspace remain visible.

Do not commit screenshots that show personal email addresses, credentials, session tokens, or private business contacts.

## Capture conventions

- Viewport target: about **1440 × 900** desktop framing (captures may be saved at the browser’s rendered pixel size).
- Close dialogs, drawers, and overlays unless the shot is intentionally documenting them.
- Do not open browser developer tools in frame.
- Do not modify PostgreSQL business records or invent demo catalog/purchasing data solely for screenshots.
- Prefer PNG; keep files reasonably small for GitHub.

## Refreshing screenshots later

1. Sign in to a local StockPilot instance with publishable sample data only.
2. Set the browser viewport near 1440 × 900.
3. Open each route, scroll to the top, close overlays, mask any PII in the chrome if needed.
4. Capture PNG files using the filenames in the gallery table above.
5. Replace the files in this directory and spot-check the README image embeds.
6. Re-read this document and update the Notes column if empty states or data volume change.
