# Application screenshots

These PNG files are genuine captures of a local StockPilot session (Laravel + Livewire UI). They are not AI-generated and do not invent metrics or records. The project [README](../../README.md) embeds a curated subset; this folder holds the full set.

Current publishable sample data includes the original Wireless Mouse (`MOUSE-001`) plus 100 `DEMO-` catalog products across eight categories. Suppliers and purchase orders remain empty.

## Gallery

| File | What it shows | Notes |
| --- | --- | --- |
| `dashboard.png` | Dashboard metrics, attention list, recent movements | Populated demo inventory; full-page |
| `products.png` | Products listing with search and stock badges | Paginated DEMO catalog rows |
| `product-editor.png` | Edit product form (opened, not submitted) | Form cancelled after capture; full-page |
| `categories.png` | Categories listing with product counts | Eight categories |
| `stock-movements.png` | Movement history with type badges | Includes DEMO opening stock-ins |
| `suppliers.png` | Suppliers index | **Empty state** (no suppliers) |
| `purchase-orders.png` | Purchase order index | **Empty state** (no orders) |
| `reports.png` | Reports & Analytics hub | Overview metrics + report cards |
| `low-stock-report.png` | Low stock report with out-of-stock rows | Populated attention list |
| `stock-movement-report.png` | Filterable movement report table | Live movement rows |
| `purchasing-report.png` | Purchasing report metrics and lists | Zero PO activity (honest empty aggregates) |
| `audit-logs.png` | Audit log filters and history table | Catalog create/update rows; entity IDs masked |
| `settings.png` | Business profile and display preferences | Optional contact fields empty |

## Unavailable workflow screens

| Screen | Reason |
| --- | --- |
| Purchase order detail / receiving | No existing purchase order. Purchasing demo data was not created. |

Do not add fabricated `purchase-order-detail.png` until a real order exists in a publishable environment.

## Privacy

Before capture, the authenticated session header email was hidden with temporary browser CSS. On audit logs, entity ID text was hidden so internal database identifiers are not published. Display names such as “Test” and `DEMO-` product SKUs remain visible.

Do not commit screenshots that show personal email addresses, credentials, session tokens, or private business contacts.

## Capture conventions

- Viewport: **1440 × 900** CSS pixels at `deviceScaleFactor` 1 (verify with `window.innerWidth` / `innerHeight`).
- Prefer system Chrome (or equivalent) when the IDE browser panel crops the layout.
- Close dialogs, drawers, and overlays unless the shot is intentionally documenting them.
- Do not open browser developer tools in frame.
- Do not modify PostgreSQL business records solely for screenshots.
- Prefer PNG; keep files reasonably small for GitHub.

## Refreshing screenshots later

1. Sign in to a local StockPilot instance with publishable sample data only.
2. Set the browser viewport to 1440 × 900 and confirm dimensions in the page.
3. Open each route, scroll to the top, close overlays, mask any PII in the chrome if needed.
4. Capture PNG files using the filenames in the gallery table above.
5. Replace the files in this directory and spot-check the README image embeds.
6. Re-read this document and update the Notes column if empty states or data volume change.
