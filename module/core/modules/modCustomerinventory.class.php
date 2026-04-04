<?php
/* Copyright (C) 2026 Zachary Melo
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modCustomerinventory
 *
 * Module descriptor for Customer Inventory
 */
class modCustomerinventory extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 510200;
		$this->family = 'crm';
		$this->module_position = '91';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Shows all products and services purchased by a customer on their third-party card';
		$this->version = '1.0.2';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'product';

		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array(
				'data' => array('elementproperties', 'thirdpartycard'),
				'entity' => '0',
			),
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@customerinventory');

		$this->depends = array('modSociete', 'modProduct');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array('customerinventory@customerinventory');

		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);

		$this->const = array();

		// Tabs
		$this->tabs = array();
		$this->tabs[] = 'thirdparty:+customerinventory:CustomerInventory:customerinventory@customerinventory:$user->hasRight(\'customerinventory\',\'inventory\',\'read\'):/customerinventory/inventory_tab.php?socid=__ID__';

		// Permissions
		$this->rights = array();
		$this->rights_class = 'customerinventory';
		$r = 0;

		$r++;
		$this->rights[$r][0] = 510201;
		$this->rights[$r][1] = 'Read customer inventory';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'inventory';
		$this->rights[$r][5] = 'read';

		// No menus — tab-only module
		$this->menu = array();
	}

	/**
	 * Enable module
	 *
	 * @param  string $options Options when enabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		// No SQL tables to load
		$this->delete_menus();
		return $this->_init(array(), $options);
	}

	/**
	 * Disable module
	 *
	 * @param  string $options Options when disabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
