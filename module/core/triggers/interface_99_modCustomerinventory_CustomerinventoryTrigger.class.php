<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Trigger class for customerinventory module.
 *
 * This module is read-only (no own business objects), so this trigger
 * is a placeholder for reacting to native module events that may affect
 * the customer inventory view (e.g., shipment validation, return creation).
 */
class InterfaceCustomerinventoryTrigger extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'crm';
		$this->description = 'Triggers for customerinventory module';
		$this->version = '1.0.0';
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'CustomerinventoryTrigger';
	}

	/**
	 * @return string
	 */
	public function getDesc()
	{
		return 'Triggers for customerinventory module';
	}

	/**
	 * @return string
	 */
	public function getVersion()
	{
		return '1.0.0';
	}

	/**
	 * Run trigger
	 *
	 * @param  string    $action   Event action code
	 * @param  object    $object   Object
	 * @param  User      $user     User
	 * @param  Translate $langs    Langs
	 * @param  Conf      $conf     Conf
	 * @return int                 0=OK, <0=KO
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('customerinventory')) {
			return 0;
		}

		// React to native module events that affect customer inventory
		// These are placeholders for future cache-invalidation or notification logic
		switch ($action) {
			case 'SHIPPING_VALIDATE':
				// A shipment was validated — new items delivered to customer
				break;

			case 'CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE':
				// A return was validated — items removed from customer inventory
				break;

			case 'BILL_VALIDATE':
				// An invoice was validated — services may now appear in inventory
				break;
		}

		return 0;
	}
}
