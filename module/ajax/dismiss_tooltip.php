<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/dismiss_tooltip.php
 * \ingroup customerinventory
 * \brief   AJAX endpoint to save tooltip dismissal state per-user.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

header('Content-Type: application/json');

if (!$user->id) {
	http_response_code(403);
	print json_encode(array('error' => 'Not authenticated'));
	exit;
}

$action = GETPOST('action', 'aZ09');

if ($action === 'dismiss') {
	// Check if user_param row already exists
	$sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."user_param";
	$sql_check .= " WHERE fk_user = ".((int) $user->id);
	$sql_check .= " AND param = 'CUSTOMERINVENTORY_TOOLTIP_DISMISSED'";

	$resql = $db->query($sql_check);
	if ($resql && $db->num_rows($resql) > 0) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."user_param SET value = '1'";
		$sql .= " WHERE fk_user = ".((int) $user->id);
		$sql .= " AND param = 'CUSTOMERINVENTORY_TOOLTIP_DISMISSED'";
	} else {
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."user_param (fk_user, param, value)";
		$sql .= " VALUES (".((int) $user->id).", 'CUSTOMERINVENTORY_TOOLTIP_DISMISSED', '1')";
	}

	$result = $db->query($sql);
	if ($result) {
		print json_encode(array('success' => true));
	} else {
		http_response_code(500);
		print json_encode(array('error' => 'Database error'));
	}
} else {
	http_response_code(400);
	print json_encode(array('error' => 'Invalid action'));
}
