# StockPilot User Guide

Practical guidance for day-to-day use. For installation and technical detail, see the [project README](../README.md).

## Getting started

1. Open the application (typically `http://127.0.0.1:8000`).
2. Register an account, or sign in if you already have one.
3. Start from the **Dashboard** for inventory totals, low-stock counts, and outstanding purchase orders.

All authenticated users share the same capabilities. There are no separate admin or staff roles in the current build.

## Catalog

### Categories

Use **Categories** to create labels you can assign to products. Categories help filter and organize the catalog; they do not affect stock quantities.

### Products

Use **Products** to manage SKUs:

- Create a product with name, SKU, prices, reorder level, active flag, optional description, optional categories, and optional image.
- Opening quantity is set only when the product is first created. After that, change quantity through stock operations or purchase-order receiving.
- Edit details and replace or remove the product image from the product editor.
- JPEG, PNG, and WebP images up to 2 MB are supported. Products without an image show a placeholder.

## Stock movements

Open **Stock Movements** to review history, or use **Adjust Stock** from the products page:

| Type | Meaning |
| --- | --- |
| Stock In | Add a positive quantity |
| Stock Out | Remove a positive quantity (blocked if stock would go negative) |
| Adjustment | Apply a signed non-zero delta (for cycle counts and corrections) |

Each movement stores before/after quantities, type, reason, and the user who applied it. Opening stock for a new product is recorded as its own movement when the opening quantity is greater than zero.

## Suppliers and purchasing

### Suppliers

Maintain supplier contact details under **Suppliers**. Inactive suppliers remain in history but should not be used for new orders if your process treats them as closed.

### Purchase orders

Typical flow:

1. Create a **Draft** order with a supplier and line items (product, ordered quantity, unit cost).
2. Mark the order **Ordered** when you place it with the supplier.
3. **Receive** against ordered or partially received orders. You may receive less than the ordered quantity across multiple receipts.
4. When all ordered lines are fully received, status becomes **Received**.
5. **Cancel** is available for draft or ordered (not yet received) orders, subject to the application rules.

Receiving increases on-hand stock through stock-in movements. Receipts use an idempotency key so a repeated submit of the same receipt does not double-count inventory.

## Reports

From **Reports**:

- **Low Stock** — products at or below reorder level, including out of stock, with a suggested replenishment gap to the reorder level.
- **Stock Movements** — filterable movement history.
- **Purchasing** — purchase-order status and supplier purchasing summaries.

Each report supports CSV export. Exports are for analysis; they do not change inventory.

## Audit logs

**Audit Logs** show create, update, and delete history for catalog, purchasing, and settings changes. Stock quantity changes appear in stock movement history rather than as product “quantity edited” audits. Image changes are recorded as path values only; image binaries are never stored in the audit trail.

## Settings

**Settings** stores:

- Business name and contact fields used in the UI
- Default reorder level and default pagination size for new work
- Display date format and currency symbol

Display preferences format money and dates in the interface. They do not rewrite stored costs, historical timestamps, or past purchase-order amounts.

## Tips

- Prefer Stock In / Stock Out / Adjustment over trying to edit on-hand quantity on an existing product.
- Keep SKUs unique and stable; they appear across stock and purchasing screens.
- Use reorder levels deliberately so the low-stock report stays useful.
- After uploading product images, confirm `php artisan storage:link` was run so thumbnails resolve under `/storage/...`.
