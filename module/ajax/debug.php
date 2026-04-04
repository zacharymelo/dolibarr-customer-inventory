<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/debug.php
 * \ingroup customerinventory
 * \brief   Debug diagnostic endpoint for customerinventory module.
 *
 * Usage: /custom/customerinventory/ajax/debug.php?socid=123
 *
 * Gated by: admin permission + CUSTOMERINVENTORY_DEBUG_MODE setting.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

// Double gate: admin + debug mode
if (!$user->admin) {
	http_response_code(403);
	print "Access denied: admin only\n";
	exit;
}
if (!getDolGlobalString('CUSTOMERINVENTORY_DEBUG_MODE')) {
	http_response_code(403);
	print "Debug mode is disabled. Enable it in module setup.\n";
	exit;
}

header('Content-Type: text/plain; charset=utf-8');

print "=== Customer Inventory Debug Diagnostic ===\n";
print "Timestamp: ".date('Y-m-d H:i:s')."\n";
print "Dolibarr version: ".DOL_VERSION."\n";
print "Entity: ".$conf->entity."\n\n";

// Section 1: Module status
print "--- Module Status ---\n";
print "customerinventory: ".(isModEnabled('customerinventory') ? 'ENABLED' : 'DISABLED')."\n";
print "customerreturn: ".(isModEnabled('customerreturn') ? 'ENABLED' : 'DISABLED')."\n";
print "societe: ".(isModEnabled('societe') ? 'ENABLED' : 'DISABLED')."\n";
print "product: ".(isModEnabled('product') ? 'ENABLED' : 'DISABLED')."\n";
print "stock: ".(isModEnabled('stock') ? 'ENABLED' : 'DISABLED')."\n";
print "expedition (shipments): ".(isModEnabled('expedition') ? 'ENABLED' : 'DISABLED')."\n";
print "commande (orders): ".(isModEnabled('commande') ? 'ENABLED' : 'DISABLED')."\n";
print "facture (invoices): ".(isModEnabled('facture') ? 'ENABLED' : 'DISABLED')."\n\n";

// Section 2: Hook registration check
print "--- Hook Registration ---\n";
$hookContexts = array('thirdpartycard', 'elementproperties');
foreach ($hookContexts as $ctx) {
	print "Context '$ctx': registered in module_parts\n";
}
print "\n";

// Section 3: Element properties resolution
print "--- Element Properties Resolution ---\n";
$elementTypes = array('customerinventory');
foreach ($elementTypes as $elType) {
	print "Testing getElementProperties for '$elType'... ";
	// Simulate what hookmanager does
	$hookmanager2 = new HookManager($db);
	$hookmanager2->initHooks(array('elementproperties'));
	$params = array('elementType' => $elType);
	$dummyObj = new stdClass();
	$dummyAction = '';
	$hookmanager2->executeHooks('getElementProperties', $params, $dummyObj, $dummyAction);
	if (!empty($hookmanager2->resArray)) {
		print "RESOLVED\n";
		foreach ($hookmanager2->resArray as $k => $v) {
			print "  $k = $v\n";
		}
	} else {
		print "NOT FOUND (expected for read-only tab module)\n";
	}
}
print "\n";

// Section 4: Diagnose specific third-party data
$socid = GETPOSTINT('socid');
if ($socid > 0) {
	print "--- Third-Party Diagnosis (socid=$socid) ---\n";

	// Load thirdparty
	require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
	$soc = new Societe($db);
	$result = $soc->fetch($socid);
	if ($result > 0) {
		print "Company: ".$soc->name." (ID: ".$soc->id.")\n";
		print "Client type: ".$soc->client."\n\n";
	} else {
		print "ERROR: Could not fetch societe with id=$socid\n\n";
	}

	// Count shipments
	$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."expedition WHERE fk_soc = ".((int) $socid)." AND entity IN (".getEntity('expedition').")";
	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	print "Shipments for this customer: ".$obj->cnt."\n";

	// Count shipment lines
	$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."expeditiondet ed";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
	$sql .= " WHERE e.fk_soc = ".((int) $socid)." AND e.entity IN (".getEntity('expedition').")";
	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	print "Shipment lines: ".$obj->cnt."\n";

	// Count serial/batch entries
	$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."expeditiondet_batch edb";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edb.fk_expeditiondet";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
	$sql .= " WHERE e.fk_soc = ".((int) $socid)." AND e.entity IN (".getEntity('expedition').")";
	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	print "Serial/batch entries: ".$obj->cnt."\n";

	// Count invoices
	$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."facture WHERE fk_soc = ".((int) $socid)." AND entity IN (".getEntity('facture').")";
	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	print "Invoices for this customer: ".$obj->cnt."\n";

	// Count orders
	$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."commande WHERE fk_soc = ".((int) $socid)." AND entity IN (".getEntity('commande').")";
	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	print "Orders for this customer: ".$obj->cnt."\n\n";

	// Test inventory query
	dol_include_once('/customerinventory/lib/customerinventory.lib.php');
	print "--- Inventory Query Test ---\n";
	$lines = fetchInventoryLines($db, $socid, 'flat', 'delivery_date', 'DESC', 10, 0);
	$total = getInventoryLineCount($db, $socid);
	print "Total inventory lines: ".$total."\n";
	print "First 10 lines fetched: ".count($lines)."\n";

	if (!empty($lines)) {
		print "\nSample lines:\n";
		$i = 0;
		foreach ($lines as $line) {
			$i++;
			print "  ".$i.". ";
			print "Product: ".($line->product_ref ?: '(none)')." - ".($line->product_label ?: '(none)');
			print " | Qty: ".$line->qty;
			print " | Serial: ".($line->serial_number ?: '-');
			print " | Shipment: ".($line->expedition_ref ?: '-');
			print " | Order: ".($line->commande_ref ?: '-');
			print " | Invoice: ".($line->facture_ref ?: '-');
			print " | Source: ".$line->source_type;
			print "\n";
		}
	}

	// Test returns integration
	if (isModEnabled('customerreturn')) {
		print "\n--- Returns Integration Test ---\n";
		$returnData = fetchReturnData($db, $socid);
		$productCount = count($returnData['by_product']);
		$detCount = count($returnData['by_expeditiondet']);
		print "Products with returns: ".$productCount."\n";
		print "Shipment lines with returns: ".$detCount."\n";

		if ($productCount > 0) {
			print "\nReturn data by product:\n";
			foreach ($returnData['by_product'] as $pid => $rdata) {
				print "  Product ID $pid: returned_qty=".$rdata['returned_qty'];
				$refs = array();
				foreach ($rdata['returns'] as $r) {
					$refs[] = $r['ref'].' (ID:'.$r['id'].')';
				}
				print " | Returns: ".implode(', ', $refs)."\n";
			}
		}
	} else {
		print "\n--- Returns Integration ---\n";
		print "customerreturn module is not enabled. Return data not available.\n";
	}

	// Check element_element links
	print "\n--- Element Links (element_element) ---\n";
	$sql = "SELECT ee.fk_source, ee.sourcetype, ee.fk_target, ee.targettype";
	$sql .= " FROM ".MAIN_DB_PREFIX."element_element ee";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON (ee.fk_target = e.rowid AND ee.targettype = 'shipping')";
	$sql .= " WHERE e.fk_soc = ".((int) $socid);
	$sql .= " AND e.entity IN (".getEntity('expedition').")";
	$sql .= " LIMIT 20";
	$resql = $db->query($sql);
	if ($resql) {
		$count = $db->num_rows($resql);
		print "Shipment links found: ".$count."\n";
		while ($obj = $db->fetch_object($resql)) {
			print "  source=".$obj->fk_source." (".$obj->sourcetype.") -> target=".$obj->fk_target." (".$obj->targettype.")\n";
		}
	}
} else {
	print "--- No socid provided ---\n";
	print "Pass ?socid=123 to diagnose a specific third-party's inventory data.\n";
}

print "\n=== End of Diagnostic ===\n";
