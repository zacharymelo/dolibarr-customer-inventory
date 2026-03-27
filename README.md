# Customer Inventory for Dolibarr

A Dolibarr module that adds a **Customer Inventory** tab to every third-party card, giving you a single view of every product and service a customer has purchased.

Lines are pulled from shipments, invoices, and sales orders — with serial/lot numbers, quantities, and direct links back to each source record.

## Features

- **Inventory tab** on the third-party card showing all purchased products and services
- **Grouping options** — view as a flat list, or group by Sales Order, Invoice, or Product
- **Sortable columns** — click any column header to reorder
- **Serial/lot tracking** — displays batch and serial numbers from shipment details
- **Linked records** — every row links directly to the product, shipment, order, and invoice
- **Returns integration** (optional) — when the [Customer Returns](https://github.com/zacharymelo/doli-returns) module is enabled, surfaces net quantities, return status, and replacement info
- **Debug diagnostics** — admin-only endpoint for troubleshooting data queries and module integration

## Requirements

- Dolibarr 16.0+
- PHP 7.0+
- Modules enabled: **Third Parties**, **Products/Services**
- Optional: **Customer Returns** module for return tracking accuracy

## Install

1. Download the latest `customerinventory-x.x.x.zip` from [Releases](https://github.com/zacharymelo/dolibarr-customer-inventory/releases)
2. In Dolibarr, go to **Home > Setup > Modules > Deploy external module/package**
3. Upload the zip
4. Search for "Customer Inventory" in the module list and enable it

## Usage

Open any third-party card (customer or prospect) and click the **Customer Inventory** tab. Use the group-by buttons to switch between views.

If the Customer Returns module is installed, a one-time tooltip will suggest enabling it for more accurate inventory status.

## Development

```bash
git clone https://github.com/zacharymelo/dolibarr-customer-inventory.git
cd dolibarr-customer-inventory
docker compose up -d
```

Dolibarr will be available at `http://localhost:8080` with the module mounted at `custom/customerinventory`.

## License

GPL-3.0
