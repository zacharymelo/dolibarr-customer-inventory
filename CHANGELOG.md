# Changelog

## [1.1.0] - 2026-04-04

### Added
- Badge count on Customer Inventory tab showing total inventory items for the third party

## [1.0.4] - 2026-04-04

### Fixed
- Correct per-row qty when serial/batch tracked: show 1 per serial instead of full line qty

## [1.0.3] - 2026-04-04

### Fixed
- Deduplicate inventory rows when an order has multiple invoices (GROUP_CONCAT aggregation)
- Filter out blank product rows from shipment lines with no product linked
- Show all linked invoices per row instead of duplicating the row

## [1.0.2] - 2026-04-03

### Fixed
- Fix phpcs violations — add missing docblocks for class and functions

## [1.0.1] - 2026-04-02

### Fixed
- Prefix 5 generic lang keys with CInv
- Fix phpcs docblock violations
- Add debug diagnostics and fix UI inconsistencies
- Remove zip from tracking, add .gitignore
