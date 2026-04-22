<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    inventory_tab.php
 * \ingroup customerinventory
 * \brief   Customer Inventory tab on third-party card.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/customerinventory/lib/customerinventory.lib.php');

$langs->loadLangs(array('companies', 'products', 'customerinventory@customerinventory'));

// Parameters
$socid = GETPOSTINT('socid');
$groupby = GETPOST('groupby', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = GETPOST('sortorder', 'aZ09');
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : (getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT') ? getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT') : 25);

if (empty($groupby) || !in_array($groupby, array('flat', 'order', 'invoice', 'product'))) {
	$groupby = 'flat';
}
if (empty($sortfield)) {
	$sortfield = 'delivery_date';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}
$offset = $limit * $page;

// Permission check
if (!$user->hasRight('customerinventory', 'inventory', 'read')) {
	accessforbidden();
}

if (empty($socid)) {
	accessforbidden('Missing socid parameter');
}

// Load third-party
$object = new Societe($db);
$result = $object->fetch($socid);
if ($result <= 0) {
	dol_print_error($db, $object->error);
	exit;
}

// Check returns module availability
$returnsEnabled = isModEnabled('customerreturn');

// Fetch data
$lines = fetchInventoryLines($db, $socid, $groupby, $sortfield, $sortorder, ($groupby === 'flat' ? $limit : 0), ($groupby === 'flat' ? $offset : 0));
$totalcount = getInventoryLineCount($db, $socid);

// Fetch return data if module is enabled
$returnData = array('by_product' => array(), 'by_expeditiondet' => array());
if ($returnsEnabled) {
	$returnData = fetchReturnData($db, $socid);
}

// Build a lookup of serials that were re-shipped after being returned.
// For each serial, track the latest shipment expedition_id so we can mark
// earlier returned rows as "Re-shipped" instead of "Returned".
$reshipIndex = array();
foreach ($lines as $ln) {
	if (empty($ln->serial_number) || empty($ln->expedition_id)) {
		continue;
	}
	$sn = $ln->serial_number;
	if (!isset($reshipIndex[$sn])) {
		$reshipIndex[$sn] = array();
	}
	$reshipIndex[$sn][] = array(
		'expedition_id' => (int) $ln->expedition_id,
		'expedition_ref' => $ln->expedition_ref,
		'delivery_date' => $ln->delivery_date,
		'expeditiondet_id' => (int) $ln->expeditiondet_id,
	);
}

// Check tooltip dismissal state
$tooltipDismissed = false;
$sql_tp = "SELECT value FROM ".MAIN_DB_PREFIX."user_param WHERE fk_user = ".((int) $user->id)." AND param = 'CUSTOMERINVENTORY_TOOLTIP_DISMISSED'";
$resql_tp = $db->query($sql_tp);
if ($resql_tp && $db->num_rows($resql_tp) > 0) {
	$obj_tp = $db->fetch_object($resql_tp);
	if ($obj_tp->value == '1') {
		$tooltipDismissed = true;
	}
}

// Page header
llxHeader('', $langs->trans('CustomerInventory').' - '.$object->name, '', '', 0, 0,
	array('/customerinventory/js/customerinventory.js'),
	array('/customerinventory/css/customerinventory.css')
);

$head = societe_prepare_head($object);
print dol_get_fiche_head($head, 'customerinventory', $langs->trans('ThirdParty'), -1, $object->picto);

// Company banner
$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_value=1">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Tooltip — show only if returns module is NOT enabled and user hasn't dismissed
if (!$returnsEnabled && !$tooltipDismissed) {
	print '<div id="ci-tooltip" class="ci-tooltip">';
	print '<span>'.img_picto('', 'info', 'class="pictofixedwidth"').$langs->trans('TooltipReturnsModule').'</span>';
	print '<a href="#" id="ci-tooltip-dismiss" class="ci-tooltip-close" title="'.$langs->trans('TooltipDismiss').'">&times;</a>';
	print '</div>';
	print '<script>var ci_csrf_token = \''.newToken().'\';</script>';
}

// Groupby toolbar
$baseurl = $_SERVER['PHP_SELF'].'?socid='.$socid;
print '<div class="ci-groupby-bar tabsAction">';
print '<span class="opacitymedium">'.$langs->trans('GroupBy').'</span> ';
$groupby_options = array(
	'flat' => 'FlatList',
	'order' => 'ByOrder',
	'invoice' => 'ByInvoice',
	'product' => 'ByProduct',
);
foreach ($groupby_options as $key => $label) {
	$cssclass = ($groupby === $key) ? 'butAction ci-groupby-active' : 'butAction';
	print '<a href="'.$baseurl.'&groupby='.$key.'" class="'.$cssclass.'">'.$langs->trans($label).'</a> ';
}
print '</div>';

// Begin data table
if (empty($lines)) {
	print '<div class="opacitymedium">'.$langs->trans('NoItemsFound').'</div>';
} else {
	if ($groupby === 'flat') {
		renderFlatTable($lines, $returnData, $returnsEnabled, $socid, $sortfield, $sortorder, $totalcount, $limit, $offset, $page, $groupby);
	} elseif ($groupby === 'order') {
		renderGroupedByOrder($lines, $returnData, $returnsEnabled);
	} elseif ($groupby === 'invoice') {
		renderGroupedByInvoice($lines, $returnData, $returnsEnabled);
	} elseif ($groupby === 'product') {
		renderGroupedByProduct($lines, $returnData, $returnsEnabled);
	}
}

print '</div>'; // fichecenter

print dol_get_fiche_end();
llxFooter();
$db->close();


// ---- Rendering functions ----

/**
 * Render flat sortable table with pagination
 *
 * @param array  $lines          Array of inventory line objects
 * @param array  $returnData     Return data indexed by product and expeditiondet
 * @param bool   $returnsEnabled Whether the returns module is active
 * @param int    $socid          Third-party ID
 * @param string $sortfield      Field used for sorting
 * @param string $sortorder      Sort direction (ASC or DESC)
 * @param int    $totalcount     Total number of inventory lines
 * @param int    $limit          Number of lines per page
 * @param int    $offset         SQL offset for pagination
 * @param int    $page           Current page number
 * @param string $groupby        Current groupby mode
 * @return void
 */
function renderFlatTable($lines, $returnData, $returnsEnabled, $socid, $sortfield, $sortorder, $totalcount, $limit, $offset, $page, $groupby)
{
	global $langs;

	$baseurl = $_SERVER['PHP_SELF'].'?socid='.$socid.'&groupby='.$groupby;

	// Pagination
	print_barre_liste('', $page, $baseurl, '&sortfield='.$sortfield.'&sortorder='.$sortorder, $sortfield, $sortorder, '', count($lines), $totalcount, '', 0, '', '', $limit);

	print '<table class="tagtable nobottomiftotal liste">';

	// Header
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans('ProductRef'), $baseurl, 'product_ref', '', '&groupby='.$groupby, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('ProductName'), $baseurl, 'product_label', '', '&groupby='.$groupby, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('ProductType'), $baseurl, 'product_type', '', '&groupby='.$groupby, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('CInvQuantity'), $baseurl, 'qty', '', '&groupby='.$groupby, 'class="right"', $sortfield, $sortorder);
	if ($returnsEnabled) {
		print '<th class="liste_titre right">'.$langs->trans('NetQuantity').'</th>';
	}
	print '<th class="liste_titre">'.$langs->trans('CInvSerialNumber').'</th>';
	print_liste_field_titre($langs->trans('ShipmentRef'), $baseurl, 'expedition_ref', '', '&groupby='.$groupby, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('OrderRef'), $baseurl, 'commande_ref', '', '&groupby='.$groupby, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('InvoiceRef'), $baseurl, 'facture_ref', '', '&groupby='.$groupby, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('DeliveryDate'), $baseurl, 'delivery_date', '', '&groupby='.$groupby, 'class="center"', $sortfield, $sortorder);
	print '<th class="liste_titre center">'.$langs->trans('InventoryStatus').'</th>';
	print '</tr>';

	foreach ($lines as $line) {
		print '<tr class="oddeven">';
		printInventoryRow($line, $returnData, $returnsEnabled);
		print '</tr>';
	}

	print '</table>';
}

/**
 * Render grouped by sales order
 *
 * @param array $lines          Array of inventory line objects
 * @param array $returnData     Return data indexed by product and expeditiondet
 * @param bool  $returnsEnabled Whether the returns module is active
 * @return void
 */
function renderGroupedByOrder($lines, $returnData, $returnsEnabled)
{
	global $langs;

	$groups = array();
	$no_order = array();
	foreach ($lines as $line) {
		if (!empty($line->commande_ref)) {
			$key = $line->commande_ref.'|'.(int) $line->commande_id;
			if (!isset($groups[$key])) {
				$groups[$key] = array();
			}
			$groups[$key][] = $line;
		} else {
			$no_order[] = $line;
		}
	}

	$colspan = $returnsEnabled ? 11 : 10;

	print '<table class="tagtable nobottomiftotal liste">';
	printGroupTableHeader($returnsEnabled, false);

	foreach ($groups as $key => $group_lines) {
		list($ref, $id) = explode('|', $key);
		$url = DOL_URL_ROOT.'/commande/card.php?id='.(int) $id;
		print '<tr class="ci-group-header liste_titre">';
		print '<td colspan="'.$colspan.'">';
		print img_picto('', 'order', 'class="pictofixedwidth"');
		print '<a href="'.$url.'">'.$ref.'</a>';
		print ' <span class="opacitymedium">('.count($group_lines).' '.$langs->trans('Lines').')</span>';
		print '</td></tr>';

		foreach ($group_lines as $line) {
			print '<tr class="oddeven ci-group-row">';
			printInventoryRow($line, $returnData, $returnsEnabled);
			print '</tr>';
		}
	}

	// Lines without an order
	if (!empty($no_order)) {
		print '<tr class="ci-group-header liste_titre">';
		print '<td colspan="'.$colspan.'">';
		print img_picto('', 'generic', 'class="pictofixedwidth"');
		print '<em>'.$langs->trans('Other').'</em>';
		print ' <span class="opacitymedium">('.count($no_order).' '.$langs->trans('Lines').')</span>';
		print '</td></tr>';

		foreach ($no_order as $line) {
			print '<tr class="oddeven ci-group-row">';
			printInventoryRow($line, $returnData, $returnsEnabled);
			print '</tr>';
		}
	}

	print '</table>';
}

/**
 * Render grouped by invoice
 *
 * @param array $lines          Array of inventory line objects
 * @param array $returnData     Return data indexed by product and expeditiondet
 * @param bool  $returnsEnabled Whether the returns module is active
 * @return void
 */
function renderGroupedByInvoice($lines, $returnData, $returnsEnabled)
{
	global $langs;

	$groups = array();
	$no_invoice = array();
	foreach ($lines as $line) {
		if (!empty($line->facture_refs)) {
			$key = $line->facture_refs.'|'.$line->facture_ids;
			if (!isset($groups[$key])) {
				$groups[$key] = array();
			}
			$groups[$key][] = $line;
		} else {
			$no_invoice[] = $line;
		}
	}

	$colspan = $returnsEnabled ? 11 : 10;

	print '<table class="tagtable nobottomiftotal liste">';
	printGroupTableHeader($returnsEnabled, false);

	foreach ($groups as $key => $group_lines) {
		list($ref, $id) = explode('|', $key);
		$url = DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $id;
		print '<tr class="ci-group-header liste_titre">';
		print '<td colspan="'.$colspan.'">';
		print img_picto('', 'bill', 'class="pictofixedwidth"');
		print '<a href="'.$url.'">'.$ref.'</a>';
		print ' <span class="opacitymedium">('.count($group_lines).' '.$langs->trans('Lines').')</span>';
		print '</td></tr>';

		foreach ($group_lines as $line) {
			print '<tr class="oddeven ci-group-row">';
			printInventoryRow($line, $returnData, $returnsEnabled);
			print '</tr>';
		}
	}

	if (!empty($no_invoice)) {
		print '<tr class="ci-group-header liste_titre">';
		print '<td colspan="'.$colspan.'">';
		print img_picto('', 'generic', 'class="pictofixedwidth"');
		print '<em>'.$langs->trans('Other').'</em>';
		print ' <span class="opacitymedium">('.count($no_invoice).' '.$langs->trans('Lines').')</span>';
		print '</td></tr>';

		foreach ($no_invoice as $line) {
			print '<tr class="oddeven ci-group-row">';
			printInventoryRow($line, $returnData, $returnsEnabled);
			print '</tr>';
		}
	}

	print '</table>';
}

/**
 * Render grouped by product
 *
 * @param array $lines          Array of inventory line objects
 * @param array $returnData     Return data indexed by product and expeditiondet
 * @param bool  $returnsEnabled Whether the returns module is active
 * @return void
 */
function renderGroupedByProduct($lines, $returnData, $returnsEnabled)
{
	global $langs;

	$groups = array();
	foreach ($lines as $line) {
		$key = ((int) $line->product_id > 0) ? (int) $line->product_id : 'unknown';
		if (!isset($groups[$key])) {
			$groups[$key] = array('ref' => $line->product_ref, 'label' => $line->product_label, 'type' => $line->product_type, 'lines' => array(), 'total_qty' => 0);
		}
		$groups[$key]['lines'][] = $line;
		$groups[$key]['total_qty'] += (float) $line->qty;
	}

	$colspan = $returnsEnabled ? 11 : 10;

	print '<table class="tagtable nobottomiftotal liste">';
	printGroupTableHeader($returnsEnabled, false);

	foreach ($groups as $product_id => $group) {
		$product_url = ($product_id !== 'unknown') ? DOL_URL_ROOT.'/product/card.php?id='.(int) $product_id : '';
		$type_label = ((int) $group['type'] === 1) ? $langs->trans('TypeService') : $langs->trans('TypeProduct');

		// Returned qty for this product
		$returned_qty = 0;
		$return_links = array();
		if ($returnsEnabled && $product_id !== 'unknown' && isset($returnData['by_product'][$product_id])) {
			$returned_qty = $returnData['by_product'][$product_id]['returned_qty'];
			$return_links = $returnData['by_product'][$product_id]['returns'];
		}
		$net_qty = $group['total_qty'] - $returned_qty;

		print '<tr class="ci-group-header liste_titre">';
		print '<td colspan="'.$colspan.'">';
		print img_picto('', ((int) $group['type'] === 1 ? 'service' : 'product'), 'class="pictofixedwidth"');
		if (!empty($product_url)) {
			print '<a href="'.$product_url.'">'.$group['ref'].'</a> - ';
		}
		print dol_escape_htmltag($group['label']);
		print ' <span class="opacitymedium">('.$type_label.')</span>';
		print ' &mdash; '.$langs->trans('CInvQuantity').': <strong>'.((float) $group['total_qty']).'</strong>';
		if ($returnsEnabled) {
			print ' &mdash; '.$langs->trans('NetQuantity').': <strong>'.max(0, $net_qty).'</strong>';
			if ($returned_qty > 0) {
				print ' <span class="opacitymedium">('.$returned_qty.' '.$langs->trans('CInvReturned').')</span>';
			}
		}
		print '</td></tr>';

		foreach ($group['lines'] as $line) {
			print '<tr class="oddeven ci-group-row">';
			printInventoryRow($line, $returnData, $returnsEnabled);
			print '</tr>';
		}
	}

	print '</table>';
}

/**
 * Print table header for grouped modes
 *
 * @param bool $returnsEnabled Whether the returns module is active
 * @param bool $sortable       Whether columns should be sortable
 * @return void
 */
function printGroupTableHeader($returnsEnabled, $sortable = false)
{
	global $langs;

	print '<tr class="liste_titre">';
	print '<th class="liste_titre">'.$langs->trans('ProductRef').'</th>';
	print '<th class="liste_titre">'.$langs->trans('ProductName').'</th>';
	print '<th class="liste_titre">'.$langs->trans('ProductType').'</th>';
	print '<th class="liste_titre right">'.$langs->trans('CInvQuantity').'</th>';
	if ($returnsEnabled) {
		print '<th class="liste_titre right">'.$langs->trans('NetQuantity').'</th>';
	}
	print '<th class="liste_titre">'.$langs->trans('CInvSerialNumber').'</th>';
	print '<th class="liste_titre">'.$langs->trans('ShipmentRef').'</th>';
	print '<th class="liste_titre">'.$langs->trans('OrderRef').'</th>';
	print '<th class="liste_titre">'.$langs->trans('InvoiceRef').'</th>';
	print '<th class="liste_titre center">'.$langs->trans('DeliveryDate').'</th>';
	print '<th class="liste_titre center">'.$langs->trans('InventoryStatus').'</th>';
	print '</tr>';
}

/**
 * Print a single inventory row (used by all rendering modes)
 *
 * @param object $line           Inventory line object
 * @param array  $returnData     Return data indexed by product and expeditiondet
 * @param bool   $returnsEnabled Whether the returns module is active
 * @return void
 */
function printInventoryRow($line, $returnData, $returnsEnabled)
{
	global $langs;

	$product_id = (int) $line->product_id;
	$qty = (float) $line->qty;
	$expeditiondet_id = isset($line->expeditiondet_id) ? (int) $line->expeditiondet_id : 0;

	// Product ref (linked)
	print '<td class="nowraponall">';
	if ($product_id > 0) {
		print '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.$product_id.'">';
		print img_picto('', ((int) $line->product_type === 1 ? 'service' : 'product'), 'class="pictofixedwidth"');
		print dol_escape_htmltag($line->product_ref).'</a>';
	}
	print '</td>';

	// Product name
	print '<td>'.dol_escape_htmltag($line->product_label).'</td>';

	// Type
	print '<td>';
	if ((int) $line->product_type === 1) {
		print $langs->trans('TypeService');
	} else {
		print $langs->trans('TypeProduct');
	}
	print '</td>';

	// Quantity
	print '<td class="right">'.$qty.'</td>';

	// Net quantity (if returns enabled)
	if ($returnsEnabled) {
		$returned_qty = 0;
		$serial_number = isset($line->serial_number) ? $line->serial_number : '';
		// Try serial+expeditiondet level first (most precise for batch products)
		if (!empty($serial_number) && isset($returnData['by_serial'][$serial_number])) {
			foreach ($returnData['by_serial'][$serial_number]['entries'] as $entry) {
				if ($entry['fk_expeditiondet'] == $expeditiondet_id || $expeditiondet_id <= 0) {
					$returned_qty += $entry['returned_qty'];
				}
			}
		} elseif (!empty($serial_number)) {
			$returned_qty = 0;
		} elseif ($expeditiondet_id > 0 && isset($returnData['by_expeditiondet'][$expeditiondet_id])) {
			$returned_qty = $returnData['by_expeditiondet'][$expeditiondet_id]['returned_qty'];
		}
		$net = max(0, $qty - $returned_qty);
		print '<td class="right">'.$net.'</td>';
	}

	// Serial number
	print '<td>';
	if (!empty($line->serial_number)) {
		print dol_escape_htmltag($line->serial_number);
	}
	print '</td>';

	// Shipment ref (linked)
	print '<td class="nowraponall">';
	if (!empty($line->expedition_ref)) {
		print '<a href="'.DOL_URL_ROOT.'/expedition/card.php?id='.((int) $line->expedition_id).'">';
		print img_picto('', 'dolly', 'class="pictofixedwidth"');
		print dol_escape_htmltag($line->expedition_ref).'</a>';
	}
	print '</td>';

	// Order ref (linked)
	print '<td class="nowraponall">';
	if (!empty($line->commande_ref)) {
		print '<a href="'.DOL_URL_ROOT.'/commande/card.php?id='.((int) $line->commande_id).'">';
		print img_picto('', 'order', 'class="pictofixedwidth"');
		print dol_escape_htmltag($line->commande_ref).'</a>';
	}
	print '</td>';

	// Invoice ref(s) (linked)
	print '<td class="nowraponall">';
	if (!empty($line->facture_refs)) {
		$ids = explode(',', $line->facture_ids);
		$refs = explode(',', $line->facture_refs);
		$links = array();
		foreach ($refs as $i => $ref) {
			$fid = isset($ids[$i]) ? (int) $ids[$i] : 0;
			$links[] = '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$fid.'">'.img_picto('', 'bill', 'class="pictofixedwidth"').dol_escape_htmltag(trim($ref)).'</a>';
		}
		print implode('<br>', $links);
	}
	print '</td>';

	// Date
	print '<td class="center nowraponall">';
	if (!empty($line->delivery_date)) {
		print dol_print_date($line->delivery_date, 'day');
	}
	print '</td>';

	// Status
	print '<td class="center nowraponall">';
	print getInventoryStatusBadge($line, $returnData, $returnsEnabled, $reshipIndex);
	print '</td>';
}

/**
 * Get status badge HTML for an inventory line
 *
 * @param object $line           Inventory line object
 * @param array  $returnData     Return data indexed by product and expeditiondet
 * @param bool   $returnsEnabled Whether the returns module is active
 * @param array  $reshipIndex    Serial-number → list of shipments lookup for Re-shipped detection
 * @return string HTML string for the status badge
 */
function getInventoryStatusBadge($line, $returnData, $returnsEnabled, $reshipIndex = array())
{
	global $langs;

	$product_id = (int) $line->product_id;
	$qty = (float) $line->qty;
	$expeditiondet_id = isset($line->expeditiondet_id) ? (int) $line->expeditiondet_id : 0;
	$expedition_id = isset($line->expedition_id) ? (int) $line->expedition_id : 0;
	$serial = isset($line->serial_number) ? $line->serial_number : '';

	if (!$returnsEnabled) {
		if ($line->source_type === 'invoiced') {
			return '<span class="badge badge-status4">'.$langs->trans('CInvInvoiced').'</span>';
		}
		return '<span class="badge badge-status4">'.$langs->trans('CInvShipped').'</span>';
	}

	// Check for returns — try serial+expeditiondet (most precise), then expeditiondet, then product
	$returned_qty = 0;
	$return_links = array();

	if (!empty($serial) && isset($returnData['by_serial'][$serial])) {
		// Serial-level: match only if the return's fk_expeditiondet belongs to this line's shipment
		foreach ($returnData['by_serial'][$serial]['entries'] as $entry) {
			if ($entry['fk_expeditiondet'] == $expeditiondet_id || $expeditiondet_id <= 0) {
				$returned_qty += $entry['returned_qty'];
				$ri = $entry['return_info'];
				$found = false;
				foreach ($return_links as $rl) {
					if ($rl['id'] == $ri['id']) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$return_links[] = $ri;
				}
			}
		}
	} elseif (!empty($serial)) {
		// Serialized line with no serial-level return match = not returned
		$returned_qty = 0;
	} elseif ($expeditiondet_id > 0 && isset($returnData['by_expeditiondet'][$expeditiondet_id])) {
		$returned_qty = $returnData['by_expeditiondet'][$expeditiondet_id]['returned_qty'];
		$return_links = $returnData['by_expeditiondet'][$expeditiondet_id]['returns'];
	} elseif (empty($serial) && $product_id > 0 && isset($returnData['by_product'][$product_id])) {
		// Product-level fallback for non-serialized lines only
		$returned_qty = $returnData['by_product'][$product_id]['returned_qty'];
		$return_links = $returnData['by_product'][$product_id]['returns'];
	}

	if ($returned_qty <= 0) {
		return '<span class="badge badge-status4">'.$langs->trans('InInventory').'</span>';
	}

	// Build return link(s)
	$return_html = '';
	if (!empty($return_links)) {
		$refs = array();
		foreach ($return_links as $r) {
			$refs[] = '<a href="'.dol_buildpath('/customerreturn/customerreturn_card.php', 1).'?id='.(int) $r['id'].'">'.dol_escape_htmltag($r['ref']).'</a>';
		}
		$return_html = ' '.implode(', ', $refs);
	}

	if ($returned_qty >= $qty) {
		// Fully returned — but check if the same serial was re-shipped in a later shipment.
		// If so, this row is superseded, not a current return.
		if (!empty($serial) && !empty($reshipIndex[$serial])) {
			$hasLaterShipment = false;
			$laterRef = '';
			foreach ($reshipIndex[$serial] as $entry) {
				if ($entry['expedition_id'] != $expedition_id) {
					// Different shipment for the same serial = re-shipped
					$hasLaterShipment = true;
					$laterRef = $entry['expedition_ref'];
					break;
				}
			}
			if ($hasLaterShipment) {
				return '<span class="badge badge-status0">'.$langs->trans('CInvReshipped').'</span>'.$return_html;
			}
		}

		return '<span class="badge badge-status8">'.$langs->trans('CInvReturned').'</span>'.$return_html;
	}

	return '<span class="badge badge-status1">'.$langs->trans('PartialReturn').'</span>'.$return_html;
}
