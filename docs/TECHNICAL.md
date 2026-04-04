# Customer Inventory -- Technical Reference

Module number: **510200**
Family: `crm`
Version: `1.1.1`
Dependencies: `modSociete`, `modProduct`
Minimum PHP: 7.0
Minimum Dolibarr: 16.0
Config page: `setup.php@customerinventory`

This module adds a **Customer Inventory** tab to the third-party card. It is read-only -- it owns no database tables and instead queries native Dolibarr tables to show every product and service a customer has received (via shipments) or been invoiced for (services).

---

## Pages & URL Parameters

### `inventory_tab.php` -- Customer Inventory Tab

Main page rendered inside the third-party card tab system.

| Parameter   | Method | Type   | Description                                                      |
|-------------|--------|--------|------------------------------------------------------------------|
| `socid`     | GET    | int    | **Required.** Third-party ID.                                    |
| `groupby`   | GET    | string | Grouping mode. One of `flat`, `order`, `invoice`, `product`. Default `flat`. |
| `sortfield` | GET    | string | Sort column for flat mode. Allowed values: `product_ref`, `product_label`, `product_type`, `qty`, `serial_number`, `delivery_date`, `commande_ref`, `facture_ref`, `expedition_ref`. Default `delivery_date`. |
| `sortorder` | GET    | string | `ASC` or `DESC`. Default `DESC`.                                 |
| `page`      | GET    | int    | Page number for pagination (flat mode only). Default `0`.        |
| `limit`     | GET    | int    | Rows per page. Falls back to `MAIN_SIZE_LISTE_LIMIT`, then `25`.|

Permission gate: `$user->hasRight('customerinventory', 'inventory', 'read')`.

Behavior:
- Loads the third-party via `Societe::fetch()`.
- Calls `fetchInventoryLines()` and `getInventoryLineCount()` from the library.
- If the `customerreturn` module is enabled, also calls `fetchReturnData()` and shows return-aware columns (Net Qty, return status badges).
- Reads the per-user tooltip dismissal flag from `llx_user_param` (`CUSTOMERINVENTORY_TOOLTIP_DISMISSED`).
- Includes `js/customerinventory.js` and `css/customerinventory.css`.

Rendering is delegated to local functions (not part of the library):

| Function                 | Description                                                              |
|--------------------------|--------------------------------------------------------------------------|
| `renderFlatTable()`      | Sortable, paginated table. Uses `print_barre_liste` and `print_liste_field_titre`. |
| `renderGroupedByOrder()` | Groups lines under their sales-order ref. Unlinked lines go to "Other". |
| `renderGroupedByInvoice()` | Groups lines under their invoice ref. Unlinked lines go to "Other".   |
| `renderGroupedByProduct()` | Groups lines under product ref/label, with aggregate qty and net qty. |
| `printGroupTableHeader()` | Prints the `<tr class="liste_titre">` header row for grouped modes.   |
| `printInventoryRow()`    | Prints a single `<td>` row used by all four rendering modes.            |
| `getInventoryStatusBadge()` | Returns HTML for the status badge (`InInventory`, `CInvReturned`, `PartialReturn`, `CInvShipped`, `CInvInvoiced`). |

### `admin/setup.php` -- Module Setup Page

Admin-only configuration page.

| Parameter | Method | Type | Description |
|-----------|--------|------|-------------|
| *(none)*  | --     | --   | No GET/POST parameters. |

Permission gate: `$user->admin`.

Displays:
- Whether the `customerreturn` companion module is enabled or disabled.
- A toggle for the `CUSTOMERINVENTORY_DEBUG_MODE` constant (via `ajax_constantonoff`).

### `ajax/debug.php` -- Debug Diagnostic Endpoint

Plain-text diagnostic dump.

| Parameter | Method | Type | Description                     |
|-----------|--------|------|---------------------------------|
| `socid`   | GET    | int  | Optional. Third-party to diagnose. |

Permission gate: `$user->admin` **AND** `CUSTOMERINVENTORY_DEBUG_MODE` must be enabled.

Output sections (when `socid` is provided):
1. Module status -- lists enabled/disabled state of `customerinventory`, `customerreturn`, `societe`, `product`, `stock`, `expedition`, `commande`, `facture`.
2. Hook registration -- confirms registered contexts.
3. Element properties resolution -- tests `getElementProperties` hook.
4. Third-party diagnosis -- counts shipments, shipment lines, batch entries, invoices, orders for the given `socid`.
5. Inventory query test -- runs `fetchInventoryLines()` with limit 10 and `getInventoryLineCount()`.
6. Returns integration test -- runs `fetchReturnData()` if `customerreturn` is enabled.
7. Element links -- queries `llx_element_element` for shipment links.

### `ajax/dismiss_tooltip.php` -- Tooltip Dismissal Endpoint

JSON AJAX endpoint called from the front-end JavaScript.

| Parameter | Method | Type   | Description                            |
|-----------|--------|--------|----------------------------------------|
| `token`   | GET    | string | CSRF token (Dolibarr `newToken()`).    |
| `action`  | POST   | string | Must be `dismiss`.                     |

Permission gate: authenticated user (`$user->id > 0`).

Writes `CUSTOMERINVENTORY_TOOLTIP_DISMISSED = 1` into `llx_user_param` for the current user (inserts or updates).

---

## Classes & Methods

### `ActionsCustomerinventory` -- Hook & Badge Class

**File:** `class/actions_customerinventory.class.php`

| Property      | Type   | Description       |
|---------------|--------|-------------------|
| `$db`         | DoliDB | Database handler  |
| `$error`      | string | Last error message|
| `$errors`     | array  | Error stack       |
| `$results`    | array  | Hook result array |
| `$resprints`  | string | Hook HTML output  |

| Method                         | Signature                                                        | Description |
|--------------------------------|------------------------------------------------------------------|-------------|
| `__construct($db)`             | `DoliDB $db`                                                     | Constructor.|
| `getElementProperties($parameters, &$object, &$action, $hookmanager)` | Returns `int 0` | Hook for `elementproperties` context. When `$parameters['elementType']` is `'customerinventory'`, populates `$this->results` with module/element/table/class metadata. |
| `countForThirdparty($socid, $obj = null)` | `int $socid, object|null $obj` -> `int` | **Tab badge callback.** Counts shipped product lines for the given third-party. Counts 1 per serial/batch when batch-tracked, 1 per `expeditiondet` otherwise. Queries `llx_expeditiondet`, `llx_expedition`, `llx_product`, `llx_expeditiondet_batch`. |
| `doActions($parameters, &$object, &$action, $hookmanager)` | Returns `int 0` | Placeholder hook for `thirdpartycard` context. Currently a no-op. |
| `formObjectOptions($parameters, &$object, &$action, $hookmanager)` | Returns `int 0` | Placeholder hook for injecting HTML on the third-party card. Currently a no-op (returns early if module disabled). |

### `InterfaceCustomerinventoryTrigger` -- Trigger Class

**File:** `core/triggers/interface_99_modCustomerinventory_CustomerinventoryTrigger.class.php`

Extends `DolibarrTriggers`. Priority: 99.

| Method                                                            | Description |
|-------------------------------------------------------------------|-------------|
| `__construct($db)`                                                | Sets name, family (`crm`), description, version `1.0.0`. |
| `getName()` -> `string`                                           | Returns `'CustomerinventoryTrigger'`. |
| `getDesc()` -> `string`                                           | Returns description string. |
| `getVersion()` -> `string`                                        | Returns `'1.0.0'`. |
| `runTrigger($action, $object, User $user, Translate $langs, Conf $conf)` -> `int` | Listens for three events (currently placeholder/no-op for each): |

Monitored trigger actions:
- `SHIPPING_VALIDATE` -- a shipment was validated (new items delivered).
- `CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE` -- a customer return was validated (items removed).
- `BILL_VALIDATE` -- an invoice was validated (services may appear in inventory).

All three cases are currently empty placeholders reserved for future cache-invalidation or notification logic.

### `modCustomerinventory` -- Module Descriptor

**File:** `core/modules/modCustomerinventory.class.php`

Extends `DolibarrModules`.

| Property / Setting          | Value |
|-----------------------------|-------|
| `numero`                    | `510200` |
| `family`                    | `crm` |
| `module_position`           | `91` |
| `version`                   | `1.1.1` |
| `picto`                     | `product` |
| `depends`                   | `modSociete`, `modProduct` |
| `phpmin`                    | `7.0` |
| `need_dolibarr_version`     | `16.0` |
| `triggers`                  | `1` (enabled) |
| `hooks.data`                | `['elementproperties', 'thirdpartycard']` |
| `config_page_url`           | `setup.php@customerinventory` |

**Tab registration string:**

```
thirdparty:+customerinventory:CustomerInventory,ActionsCustomerinventory,/customerinventory/class/actions_customerinventory.class.php,countForThirdparty:customerinventory@customerinventory:$user->hasRight('customerinventory','inventory','read'):/customerinventory/inventory_tab.php?socid=__ID__
```

Breakdown:
- Object type: `thirdparty`
- Operation: `+customerinventory` (add tab)
- Label lang key: `CustomerInventory`
- Badge callback: class `ActionsCustomerinventory`, file path, method `countForThirdparty`
- Lang file: `customerinventory@customerinventory`
- Permission: `$user->hasRight('customerinventory', 'inventory', 'read')`
- URL: `/customerinventory/inventory_tab.php?socid=__ID__`

Methods:
- `init($options)` -- no SQL tables to load; calls `delete_menus()` then `_init()`.
- `remove($options)` -- calls `_remove()`.

---

## Library Functions

**File:** `lib/customerinventory.lib.php`

### `fetchInventoryLines($db, $socid, $groupby, $sortfield, $sortorder, $limit, $offset)` -> `array`

Core data-retrieval function. Returns an array of `stdClass` objects representing every product/service the customer has received.

**Parameters:**

| Name        | Type   | Default          | Description |
|-------------|--------|------------------|-------------|
| `$db`       | DoliDB | --               | Database handler |
| `$socid`    | int    | --               | Third-party ID |
| `$groupby`  | string | `'flat'`         | `flat`, `order`, `invoice`, or `product` |
| `$sortfield`| string | `'delivery_date'`| Sort column (flat mode only) |
| `$sortorder`| string | `'DESC'`         | `ASC` or `DESC` |
| `$limit`    | int    | `0`              | Max rows, 0 = unlimited (flat mode only) |
| `$offset`   | int    | `0`              | Row offset (flat mode only) |

**Returned columns per row:**

| Column             | Type    | Source                     | Description |
|--------------------|---------|----------------------------|-------------|
| `product_id`       | int     | `llx_product.rowid`        | Product ID |
| `product_ref`      | string  | `llx_product.ref`          | Product reference |
| `product_label`    | string  | `llx_product.label`        | Product name |
| `product_type`     | int     | `llx_product.fk_product_type` | 0 = product, 1 = service |
| `qty`              | float   | `llx_expeditiondet.qty` or `llx_facturedet.qty` | Quantity (1 when batch) |
| `serial_number`    | string  | `llx_expeditiondet_batch.batch` | Serial/lot number or NULL |
| `expedition_id`    | int     | `llx_expedition.rowid`     | Shipment ID (NULL for invoiced services) |
| `expedition_ref`   | string  | `llx_expedition.ref`       | Shipment reference |
| `delivery_date`    | date    | `llx_expedition.date_delivery` or `llx_facture.datef` | Delivery or invoice date |
| `commande_id`      | int     | `llx_commande.rowid`       | Order ID (NULL if unlinked) |
| `commande_ref`     | string  | `llx_commande.ref`         | Order reference |
| `facture_ids`      | string  | comma-separated            | Invoice ID(s) |
| `facture_refs`     | string  | comma-separated            | Invoice reference(s) |
| `expeditiondet_id` | int     | `llx_expeditiondet.rowid`  | Shipment detail line ID |
| `source_type`      | string  | literal                    | `'shipped'` or `'invoiced'` |

**Allowed sort fields (whitelist):** `product_ref`, `product_label`, `product_type`, `qty`, `serial_number`, `delivery_date`, `commande_ref`, `facture_ref` (maps to `facture_refs`), `expedition_ref`.

### `getInventoryLineCount($db, $socid)` -> `int`

Returns the total count of inventory lines for a third-party. Uses the same UNION ALL logic as `fetchInventoryLines` but wrapped in `SELECT COUNT(*)`. Used for pagination in flat mode.

### `fetchReturnData($db, $socid)` -> `array`

Queries the `customerreturn` module tables to get return quantities. Only meaningful when `isModEnabled('customerreturn')` is true.

**Return structure:**
```php
[
    'by_product' => [
        (int) product_id => [
            'returned_qty' => (float),
            'returns' => [
                ['ref' => (string), 'id' => (int)],
                ...
            ]
        ],
        ...
    ],
    'by_expeditiondet' => [
        (int) expeditiondet_id => [
            'returned_qty' => (float),
            'returns' => [
                ['ref' => (string), 'id' => (int)],
                ...
            ]
        ],
        ...
    ]
]
```

Tables queried: `llx_customer_return_line` (joined to `llx_customer_return`).
Filters: `cr.fk_soc = $socid AND cr.status >= 1` (validated or closed, not draft).

---

## Hooks

Registered in `module_parts` via the module descriptor:

| Hook Context         | Class                    | Methods Implemented                          |
|----------------------|--------------------------|----------------------------------------------|
| `elementproperties`  | `ActionsCustomerinventory` | `getElementProperties()` -- resolves element metadata when `elementType === 'customerinventory'`. |
| `thirdpartycard`     | `ActionsCustomerinventory` | `doActions()` (no-op placeholder), `formObjectOptions()` (no-op placeholder). |

---

## Triggers

**File:** `core/triggers/interface_99_modCustomerinventory_CustomerinventoryTrigger.class.php`
**Class:** `InterfaceCustomerinventoryTrigger`
**Priority:** 99

| Action Code                                 | When Fired               | Current Behavior |
|---------------------------------------------|--------------------------|------------------|
| `SHIPPING_VALIDATE`                         | Shipment validated       | No-op (placeholder for cache invalidation) |
| `CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE`    | Customer return validated| No-op (placeholder for cache invalidation) |
| `BILL_VALIDATE`                             | Invoice validated        | No-op (placeholder for cache invalidation) |

---

## Data Sources (Native Tables)

This module creates **no custom tables**. All data is read from native Dolibarr tables.

### Part 1: Shipped Products (UNION leg 1)

The `fetchInventoryLines` function builds a query for products delivered via shipments:

| Table                       | Alias | Role |
|-----------------------------|-------|------|
| `llx_expeditiondet`         | `ed`  | Shipment detail lines -- provides product FK, quantity, and line ID. |
| `llx_expedition`            | `e`   | Shipment header -- provides ref, delivery date, fk_soc. Filtered by `fk_statut > 0` and entity. |
| `llx_product`               | `p`   | Product master -- provides ref, label, type. |
| `llx_expeditiondet_batch`   | `edb` | Batch/serial tracking per shipment line. LEFT JOIN; when present, qty is forced to 1 and `batch` becomes `serial_number`. |
| `llx_element_element`       | `ee_co` | Links shipment to order (`targettype = 'shipping'`, `sourcetype = 'commande'`). LEFT JOIN. |
| `llx_commande`              | `c`   | Sales order header -- provides order ref/ID. LEFT JOIN. |
| `llx_element_element`       | `ee_cf` | Links order to invoice (`sourcetype = 'commande'`, `targettype = 'facture'`). LEFT JOIN. |
| `llx_facture`               | `f`   | Invoice header -- provides invoice ref/ID. LEFT JOIN. Uses `GROUP_CONCAT` to aggregate multiple invoices. |

### Part 2: Invoiced Services (UNION leg 2)

Services that appear on invoices but were never shipped:

| Table                       | Alias | Role |
|-----------------------------|-------|------|
| `llx_facturedet`            | `fd`  | Invoice detail lines -- provides product FK and quantity. |
| `llx_facture`               | `f`   | Invoice header -- provides ref, date, fk_soc. Filtered by `fk_statut > 0` and entity. |
| `llx_product`               | `p`   | Product master -- filtered to `fk_product_type = 1` (services only). |

A `NOT EXISTS` subquery excludes services that also appear in shipments (preventing double-counting), checking `llx_expeditiondet` joined to `llx_expedition`.

### Part 3: Return Data (optional, separate query)

When the `customerreturn` module is enabled, `fetchReturnData()` queries:

| Table                       | Alias | Role |
|-----------------------------|-------|------|
| `llx_customer_return_line`  | `crl` | Return line -- provides product FK, expeditiondet FK, quantity. |
| `llx_customer_return`       | `cr`  | Return header -- provides ref, ID, fk_soc. Filtered by `status >= 1`. |

### Part 4: User Preferences

| Table               | Usage |
|----------------------|-------|
| `llx_user_param`     | Stores per-user `CUSTOMERINVENTORY_TOOLTIP_DISMISSED` flag. Read in `inventory_tab.php`, written by `ajax/dismiss_tooltip.php`. |

### Part 5: Badge Count (countForThirdparty)

The `countForThirdparty()` method queries:

| Table                       | Role |
|-----------------------------|------|
| `llx_expeditiondet`         | Shipment lines |
| `llx_expedition`            | Shipment header (status > 0, entity filter) |
| `llx_product`               | Product master |
| `llx_expeditiondet_batch`   | Batch/serial entries |

Groups by `ed.rowid, edb.batch` and wraps in `SELECT COUNT(*)` to produce the badge number.

---

## AJAX Endpoints

### `ajax/debug.php`

| Attribute     | Value |
|---------------|-------|
| URL           | `/custom/customerinventory/ajax/debug.php` |
| Method        | GET |
| Content-Type  | `text/plain; charset=utf-8` |
| Auth          | Admin only + `CUSTOMERINVENTORY_DEBUG_MODE` constant must be truthy |
| Parameters    | `socid` (optional int) |

Returns a plain-text diagnostic dump covering module status, hook registration, element properties, and (when `socid` is given) detailed counts and sample inventory lines for a specific third-party.

### `ajax/dismiss_tooltip.php`

| Attribute     | Value |
|---------------|-------|
| URL           | `/custom/customerinventory/ajax/dismiss_tooltip.php` |
| Method        | POST |
| Content-Type  | `application/json` (response) |
| Auth          | Any authenticated user |
| Parameters    | `token` (GET, CSRF), `action` (POST, must be `dismiss`) |

Persists the tooltip dismissal by writing `CUSTOMERINVENTORY_TOOLTIP_DISMISSED = 1` to `llx_user_param`. Returns `{"success": true}` or an error JSON with appropriate HTTP status.

---

## Permissions

| ID       | Right String                                      | Type | Default | Object     | Action |
|----------|---------------------------------------------------|------|---------|------------|--------|
| `510201` | Read customer inventory tab on third-party cards  | `r`  | `0` (not granted by default) | `inventory` | `read` |

Rights class: `customerinventory`

Usage in code: `$user->hasRight('customerinventory', 'inventory', 'read')`

---

## Language Keys

**File:** `langs/en_US/customerinventory.lang`

| Key                      | Value |
|--------------------------|-------|
| `Module510200Name`       | Customer Inventory |
| `Module510200Desc`       | Shows all products and services purchased by a customer on their third-party card |
| `CustomerInventory`      | Customer Inventory |
| `GroupBy`                | Group by: |
| `FlatList`               | Flat List |
| `ByOrder`                | By Sales Order |
| `ByInvoice`              | By Invoice |
| `ByProduct`              | By Product |
| `ProductRef`             | Product Ref |
| `ProductName`            | Product |
| `ProductType`            | Type |
| `CInvQuantity`           | Qty |
| `NetQuantity`            | Net Qty |
| `CInvSerialNumber`       | Serial/Lot |
| `SourceDocument`         | Source |
| `ShipmentRef`            | Shipment |
| `OrderRef`               | Order |
| `InvoiceRef`             | Invoice |
| `DeliveryDate`           | Date |
| `InventoryStatus`        | Status |
| `InInventory`            | In Inventory |
| `CInvReturned`           | Returned |
| `PartialReturn`          | Partial Return |
| `CInvShipped`            | Shipped |
| `CInvInvoiced`           | Invoiced |
| `TypeProduct`            | Product |
| `TypeService`            | Service |
| `TooltipReturnsModule`   | Enable the Customer Returns module for return tracking and more accurate net quantity calculations. |
| `TooltipDismiss`         | Dismiss |
| `NoItemsFound`           | No products or services found for this customer. |
| `CustomerInventorySetup` | Customer Inventory Setup |
| `CustomerInventoryAbout` | This module adds a Customer Inventory tab to the third-party card showing all products and services purchased by the customer. |
| `DebugMode`              | Debug Mode |
| `DebugModeDesc`          | When enabled, exposes a diagnostic endpoint at /custom/customerinventory/ajax/debug.php for troubleshooting inventory data, linked objects, and module configuration. |
| `Permission510201a`      | View customer inventory tab on third-party cards |

---

## Configuration Constants

| Constant                              | Type    | Scope   | Description |
|---------------------------------------|---------|---------|-------------|
| `CUSTOMERINVENTORY_DEBUG_MODE`        | boolean | Global  | When enabled, the `ajax/debug.php` diagnostic endpoint becomes accessible to admins. Toggled via the admin setup page. |
| `CUSTOMERINVENTORY_TOOLTIP_DISMISSED` | string  | Per-user (`llx_user_param`) | Set to `'1'` when a user dismisses the returns-module tooltip. Not a `llx_const` entry -- stored in `llx_user_param`. |

---

## Front-End Assets

### `js/customerinventory.js`

Attaches a click handler to `#ci-tooltip-dismiss`. On click, hides the `#ci-tooltip` element and fires a POST to `ajax/dismiss_tooltip.php` with the CSRF token from the global `ci_csrf_token` variable.

### `css/customerinventory.css`

Styles for:
- `.ci-tooltip` / `.ci-tooltip-close` -- info banner with dismiss button.
- `.ci-groupby-bar` / `.ci-groupby-active` -- groupby toolbar; active button gets purple background.
- `.ci-group-header` -- grey background header rows in grouped table modes.
- `.ci-group-row` -- indented child rows (25px left padding on first cell).
