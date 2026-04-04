# Customer Inventory for Dolibarr

**See everything you have shipped to a customer, all in one place.**

Customer Inventory adds a tab to every third-party card in Dolibarr that lists all products delivered to that customer. No configuration needed -- install the module, activate it, and the tab appears automatically.

**Version:** 1.1.1
**License:** GPL-3.0

---

## Features

- **Customer Inventory tab** on every third-party (customer) card
- **Badge count** on the tab showing the total number of inventory items at a glance
- **Multiple view modes** -- see your data as a flat list, or group by Sales Order, by Invoice, or by Product
- **Sortable columns** -- click any column header to sort the table
- **Serial and lot number display** for each shipment line
- **Per-serial quantities** -- batch-tracked items show 1 per serial number for accurate counting
- **Multiple invoice links** -- when a single order spans multiple invoices, all invoice links appear on the same row
- **Return status badges** -- when the optional Customer Returns module is active, each line shows its status: In Inventory, Returned, or Partial Return
- **Net quantity calculation** -- automatically subtracts returned quantities so you always see the real number on hand

---

## What You Will See

The Customer Inventory tab appears on each customer's third-party card, showing all products they have received.

### The Tab

When you open a third-party card, you will see a **Customer Inventory** tab in the row of tabs at the top. A badge on the tab displays the total count of inventory items for that customer, so you can tell at a glance whether they have received anything.

### Group-By Buttons

At the top of the inventory list, a row of buttons lets you choose how the data is organized:

- **Flat List** -- every shipment line on its own row, no grouping
- **By Sales Order** -- lines grouped under the sales order they belong to
- **By Invoice** -- lines grouped under the invoice they were billed on
- **By Product** -- all shipment lines for the same product collected together

### The Table

The table displays columns for:

- Product reference and label
- Quantity shipped
- Serial or lot number (if the product uses batch tracking)
- Shipment reference (linked to the shipment record)
- Sales order reference (linked to the order)
- Invoice reference(s) (linked to each invoice -- multiple links when an order spans invoices)
- Return status badge (only visible when the Customer Returns module is active)
- Net quantity (quantity shipped minus quantity returned)

All columns are sortable. Click a column header to sort ascending; click again to sort descending.

---

## Requirements

| Requirement | Minimum Version |
|---|---|
| Dolibarr | 16.0 or higher |
| PHP | 7.0 or higher |

### Required Dolibarr Modules

These modules must be enabled in your Dolibarr installation before activating Customer Inventory:

- **Third Parties** (Tiers)
- **Products/Services** (Produits/Services)
- **Shipments** (Expeditions)

### Optional

- **Customer Returns** module ([doli-returns](https://github.com/zacharymelo/doli-returns)) -- enables return status badges and net quantity calculations

---

## Installation

1. Download the latest `customerinventory-x.x.x.zip` from the [Releases page](https://github.com/zacharymelo/dolibarr-customer-inventory/releases).
2. In Dolibarr, go to **Home > Setup > Modules/Applications > Deploy external module**.
3. Upload the zip file.
4. In the module list, search for **Customer Inventory** and click the toggle to enable it.

That is all. There is no setup page and no configuration required. The Customer Inventory tab will appear on all third-party cards immediately.

---

## Usage Guide

### Opening the Customer Inventory Tab

1. Navigate to **Third Parties** in the Dolibarr main menu.
2. Open any customer or prospect card.
3. Click the **Customer Inventory** tab. The badge on the tab shows how many inventory items this customer has.

### Switching Views

Use the group-by buttons at the top of the inventory list to change how data is displayed:

- Click **Flat List** to see every shipment line individually.
- Click **By Sales Order** to see lines grouped under their originating sales order.
- Click **By Invoice** to see lines grouped under the invoice they appear on.
- Click **By Product** to see all deliveries of the same product collected together.

The view you select takes effect immediately. Column sorting works within any view.

### Understanding the Table

Each row represents a product line from a shipment to the customer. Clickable links in the Shipment, Order, and Invoice columns take you directly to those records in Dolibarr.

If a product uses batch or serial tracking, the serial/lot number column will show the specific serial or lot for that line. Batch-tracked items display a quantity of 1 per serial number.

When a single sales order is billed across multiple invoices, the Invoice column will show links to each invoice separated so you can follow the billing trail.

---

## Optional Integrations

### Customer Returns Module

If you install and activate the [Customer Returns](https://github.com/zacharymelo/doli-returns) module alongside Customer Inventory, you gain additional visibility into return activity:

- **Status badges** appear on each inventory line:
  - **In Inventory** -- the product has not been returned
  - **Returned** -- the full quantity has been returned
  - **Partial Return** -- some but not all of the quantity has been returned
- **Net quantity** is calculated automatically by subtracting returned quantities from shipped quantities.

The Customer Returns module is entirely optional. Without it, the Customer Inventory tab works normally but does not display return status or net quantity columns.

---

## License

This module is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.en.html).
