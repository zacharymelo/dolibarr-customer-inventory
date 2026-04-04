<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/actions_customerinventory.class.php
 * \ingroup customerinventory
 * \brief   Hook class for customerinventory module.
 */
class ActionsCustomerinventory
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array */
	public $errors = array();

	/** @var array */
	public $results = array();

	/** @var string */
	public $resprints = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Resolve element properties for this module.
	 * Required for elementproperties hook context.
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Current object
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=OK
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		$elementType = isset($parameters['elementType']) ? $parameters['elementType'] : '';

		if ($elementType === 'customerinventory') {
			$this->results = array(
				'module'        => 'customerinventory',
				'element'       => 'customerinventory',
				'table_element' => 'customerinventory',
				'classpath'     => 'customerinventory/class',
				'classfile'     => 'customerinventory',
				'classname'     => 'Customerinventory',
			);
		}

		return 0;
	}

	/**
	 * Count inventory lines for a given third party (used for tab badge).
	 *
	 * @param  int         $socid  Third party ID
	 * @param  object|null $obj    Optional object context (unused)
	 * @return int                 Count of distinct shipped products
	 */
	public function countForThirdparty($socid, $obj = null)
	{
		$socid = (int) $socid;
		if ($socid <= 0) {
			return 0;
		}

		// Count shipped product lines (1 per serial when batch-tracked, otherwise 1 per expeditiondet)
		$sql = "SELECT COUNT(*) AS nb FROM (";
		$sql .= "SELECT ed.rowid, edb.batch";
		$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet ed";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ed.fk_product";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_batch edb ON edb.fk_expeditiondet = ed.rowid";
		$sql .= " WHERE e.fk_soc = ".$socid;
		$sql .= " AND e.fk_statut > 0";
		$sql .= " AND e.entity IN (".getEntity('expedition').")";
		$sql .= " GROUP BY ed.rowid, edb.batch";
		$sql .= ") AS inventory";

		$resql = $this->db->query($sql);
		if ($resql) {
			$row = $this->db->fetch_object($resql);
			return (int) $row->nb;
		}
		return 0;
	}

	/**
	 * Hook for additional actions on thirdparty card.
	 * Placeholder for future expansion.
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Current object
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=OK
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		return 0;
	}

	/**
	 * Hook for injecting HTML on thirdparty card.
	 * Placeholder for future expansion.
	 *
	 * @param  array  $parameters Hook parameters
	 * @param  object $object     Current object
	 * @param  string $action     Current action
	 * @param  object $hookmanager Hook manager
	 * @return int                0=OK
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		if (!isModEnabled('customerinventory')) {
			return 0;
		}

		return 0;
	}
}
