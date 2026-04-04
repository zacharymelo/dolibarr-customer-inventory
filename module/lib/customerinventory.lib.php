<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Fetch inventory lines for a given third-party (customer).
 * Pulls from shipment lines (products actually delivered) and invoice lines (services not shipped).
 *
 * @param  DoliDB $db         Database handler
 * @param  int    $socid      Third-party ID
 * @param  string $groupby    Grouping mode: flat, order, invoice, product
 * @param  string $sortfield  Sort field (flat mode)
 * @param  string $sortorder  Sort direction ASC/DESC (flat mode)
 * @param  int    $limit      Max rows (flat mode, 0 = no limit)
 * @param  int    $offset     Offset (flat mode)
 * @return array              Array of stdClass line objects, or empty array on error
 */
function fetchInventoryLines($db, $socid, $groupby = 'flat', $sortfield = 'delivery_date', $sortorder = 'DESC', $limit = 0, $offset = 0)
{
	$socid = (int) $socid;
	if ($socid <= 0) {
		return array();
	}

	// Part 1: Products shipped via expeditions
	$sql_shipped = "SELECT p.rowid AS product_id, p.ref AS product_ref, p.label AS product_label,";
	$sql_shipped .= " p.fk_product_type AS product_type,";
	$sql_shipped .= " CASE WHEN edb.batch IS NOT NULL THEN 1 ELSE ed.qty END AS qty, edb.batch AS serial_number,";
	$sql_shipped .= " e.rowid AS expedition_id, e.ref AS expedition_ref, e.date_delivery AS delivery_date,";
	$sql_shipped .= " c.rowid AS commande_id, c.ref AS commande_ref,";
	$sql_shipped .= " GROUP_CONCAT(DISTINCT f.rowid ORDER BY f.rowid SEPARATOR ',') AS facture_ids,";
	$sql_shipped .= " GROUP_CONCAT(DISTINCT f.ref ORDER BY f.rowid SEPARATOR ',') AS facture_refs,";
	$sql_shipped .= " ed.rowid AS expeditiondet_id,";
	$sql_shipped .= " 'shipped' AS source_type";
	$sql_shipped .= " FROM ".MAIN_DB_PREFIX."expeditiondet ed";
	$sql_shipped .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
	$sql_shipped .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ed.fk_product";
	$sql_shipped .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_batch edb ON edb.fk_expeditiondet = ed.rowid";
	// Shipment -> Order via element_element
	$sql_shipped .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee_co ON ee_co.fk_target = e.rowid AND ee_co.targettype = 'shipping' AND ee_co.sourcetype = 'commande'";
	$sql_shipped .= " LEFT JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid = ee_co.fk_source";
	// Order -> Invoice via element_element
	$sql_shipped .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee_cf ON ee_cf.fk_source = c.rowid AND ee_cf.sourcetype = 'commande' AND ee_cf.targettype = 'facture'";
	$sql_shipped .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = ee_cf.fk_target";
	$sql_shipped .= " WHERE e.fk_soc = ".$socid;
	$sql_shipped .= " AND e.fk_statut > 0";
	$sql_shipped .= " AND e.entity IN (".getEntity('expedition').")";
	$sql_shipped .= " GROUP BY ed.rowid, edb.batch, p.rowid, p.ref, p.label, p.fk_product_type, ed.qty, e.rowid, e.ref, e.date_delivery, c.rowid, c.ref";

	// Part 2: Services invoiced but not shipped
	$sql_services = "SELECT p.rowid AS product_id, p.ref AS product_ref, p.label AS product_label,";
	$sql_services .= " p.fk_product_type AS product_type,";
	$sql_services .= " fd.qty AS qty, NULL AS serial_number,";
	$sql_services .= " NULL AS expedition_id, NULL AS expedition_ref, f.datef AS delivery_date,";
	$sql_services .= " NULL AS commande_id, NULL AS commande_ref,";
	$sql_services .= " CAST(f.rowid AS CHAR) AS facture_ids, f.ref AS facture_refs,";
	$sql_services .= " NULL AS expeditiondet_id,";
	$sql_services .= " 'invoiced' AS source_type";
	$sql_services .= " FROM ".MAIN_DB_PREFIX."facturedet fd";
	$sql_services .= " INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture";
	$sql_services .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = fd.fk_product AND p.fk_product_type = 1";
	$sql_services .= " WHERE f.fk_soc = ".$socid;
	$sql_services .= " AND f.fk_statut > 0";
	$sql_services .= " AND f.entity IN (".getEntity('facture').")";
	// Exclude services that were also shipped to avoid double-counting
	$sql_services .= " AND NOT EXISTS (";
	$sql_services .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."expeditiondet ed2";
	$sql_services .= "   INNER JOIN ".MAIN_DB_PREFIX."expedition e2 ON e2.rowid = ed2.fk_expedition";
	$sql_services .= "   WHERE ed2.fk_product = p.rowid AND e2.fk_soc = ".$socid;
	$sql_services .= "   AND e2.fk_statut > 0";
	$sql_services .= " )";

	// Combine — list all columns explicitly (never SELECT *)
	$sql = "SELECT product_id, product_ref, product_label, product_type,";
	$sql .= " qty, serial_number,";
	$sql .= " expedition_id, expedition_ref, delivery_date,";
	$sql .= " commande_id, commande_ref,";
	$sql .= " facture_ids, facture_refs,";
	$sql .= " expeditiondet_id, source_type";
	$sql .= " FROM (".$sql_shipped." UNION ALL ".$sql_services.") AS inventory";

	// Sorting
	$allowed_sort_fields = array(
		'product_ref' => 'product_ref',
		'product_label' => 'product_label',
		'product_type' => 'product_type',
		'qty' => 'qty',
		'serial_number' => 'serial_number',
		'delivery_date' => 'delivery_date',
		'commande_ref' => 'commande_ref',
		'facture_ref' => 'facture_refs',
		'expedition_ref' => 'expedition_ref',
	);

	switch ($groupby) {
		case 'order':
			$sql .= " ORDER BY commande_ref ASC, delivery_date DESC";
			break;
		case 'invoice':
			$sql .= " ORDER BY facture_refs ASC, delivery_date DESC";
			break;
		case 'product':
			$sql .= " ORDER BY product_ref ASC, delivery_date DESC";
			break;
		case 'flat':
		default:
			$safe_sortfield = isset($allowed_sort_fields[$sortfield]) ? $allowed_sort_fields[$sortfield] : 'delivery_date';
			$safe_sortorder = strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC';
			$sql .= " ORDER BY ".$safe_sortfield." ".$safe_sortorder;
			break;
	}

	// Pagination only in flat mode
	if ($groupby === 'flat' && $limit > 0) {
		$sql .= " LIMIT ".((int) $limit);
		if ($offset > 0) {
			$sql .= " OFFSET ".((int) $offset);
		}
	}

	$lines = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$lines[] = $obj;
		}
		$db->free($resql);
	}
	return $lines;
}

/**
 * Count total inventory lines for a given third-party.
 *
 * @param  DoliDB $db    Database handler
 * @param  int    $socid Third-party ID
 * @return int           Total count, or 0 on error
 */
function getInventoryLineCount($db, $socid)
{
	$socid = (int) $socid;
	if ($socid <= 0) {
		return 0;
	}

	$sql_shipped = "SELECT ed.rowid";
	$sql_shipped .= " FROM ".MAIN_DB_PREFIX."expeditiondet ed";
	$sql_shipped .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
	$sql_shipped .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_batch edb ON edb.fk_expeditiondet = ed.rowid";
	$sql_shipped .= " WHERE e.fk_soc = ".$socid;
	$sql_shipped .= " AND e.fk_statut > 0";
	$sql_shipped .= " AND e.entity IN (".getEntity('expedition').")";

	$sql_services = "SELECT fd.rowid";
	$sql_services .= " FROM ".MAIN_DB_PREFIX."facturedet fd";
	$sql_services .= " INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture";
	$sql_services .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = fd.fk_product AND p.fk_product_type = 1";
	$sql_services .= " WHERE f.fk_soc = ".$socid;
	$sql_services .= " AND f.fk_statut > 0";
	$sql_services .= " AND f.entity IN (".getEntity('facture').")";
	$sql_services .= " AND NOT EXISTS (";
	$sql_services .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."expeditiondet ed2";
	$sql_services .= "   INNER JOIN ".MAIN_DB_PREFIX."expedition e2 ON e2.rowid = ed2.fk_expedition";
	$sql_services .= "   WHERE ed2.fk_product = p.rowid AND e2.fk_soc = ".$socid;
	$sql_services .= "   AND e2.fk_statut > 0";
	$sql_services .= " )";

	$sql = "SELECT COUNT(*) AS total FROM (".$sql_shipped." UNION ALL ".$sql_services.") AS inventory";

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return (int) $obj->total;
	}
	return 0;
}

/**
 * Fetch return data for a given third-party from the customerreturn module.
 * Only call this when isModEnabled('customerreturn') is true.
 *
 * @param  DoliDB $db    Database handler
 * @param  int    $socid Third-party ID
 * @return array         Associative array keyed by product_id:
 *                       [product_id => ['returned_qty' => X, 'returns' => [['ref' => ..., 'id' => ...], ...]]]
 *                       Also keyed by expeditiondet_id for serial-level accuracy:
 *                       ['by_expeditiondet'][expeditiondet_id] => ['returned_qty' => X, 'returns' => [...]]
 */
function fetchReturnData($db, $socid)
{
	$socid = (int) $socid;
	$result = array('by_product' => array(), 'by_expeditiondet' => array());

	if ($socid <= 0) {
		return $result;
	}

	$sql = "SELECT crl.fk_product, crl.fk_expeditiondet, crl.qty,";
	$sql .= " cr.ref AS return_ref, cr.rowid AS return_id";
	$sql .= " FROM ".MAIN_DB_PREFIX."customer_return_line crl";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."customer_return cr ON cr.rowid = crl.fk_customer_return";
	$sql .= " WHERE cr.fk_soc = ".$socid;
	$sql .= " AND cr.status >= 1"; // Validated or closed, not draft

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$product_id = (int) $obj->fk_product;
			$expeditiondet_id = (int) $obj->fk_expeditiondet;
			$qty = (float) $obj->qty;
			$return_info = array('ref' => $obj->return_ref, 'id' => (int) $obj->return_id);

			// By product
			if ($product_id > 0) {
				if (!isset($result['by_product'][$product_id])) {
					$result['by_product'][$product_id] = array('returned_qty' => 0, 'returns' => array());
				}
				$result['by_product'][$product_id]['returned_qty'] += $qty;
				// Avoid duplicate return refs
				$found = false;
				foreach ($result['by_product'][$product_id]['returns'] as $r) {
					if ($r['id'] == $return_info['id']) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$result['by_product'][$product_id]['returns'][] = $return_info;
				}
			}

			// By expeditiondet (for serial-level matching)
			if ($expeditiondet_id > 0) {
				if (!isset($result['by_expeditiondet'][$expeditiondet_id])) {
					$result['by_expeditiondet'][$expeditiondet_id] = array('returned_qty' => 0, 'returns' => array());
				}
				$result['by_expeditiondet'][$expeditiondet_id]['returned_qty'] += $qty;
				$found = false;
				foreach ($result['by_expeditiondet'][$expeditiondet_id]['returns'] as $r) {
					if ($r['id'] == $return_info['id']) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$result['by_expeditiondet'][$expeditiondet_id]['returns'][] = $return_info;
				}
			}
		}
		$db->free($resql);
	}

	return $result;
}
