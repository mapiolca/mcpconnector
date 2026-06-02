<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Descriptor for module MCP Connector.
 */
class modMcpConnector extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		$this->numero = 500000;
		$this->rights_class = 'mcpconnector';
		$this->family = 'technic';
		$this->module_position = 500;
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'MCPConnectorDescription';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-plug';

		$this->editor_name = 'Pierre Ardoin';
		$this->editor_url = '';

		$this->dolibarrmin = array(20, 0);
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);
		$this->langfiles = array('mcpconnector@mcpconnector');

		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->hidden = false;

		$this->config_page_url = array(
			'setup.php@mcpconnector',
		);

		$this->dirs = array('/mcpconnector/temp');

		$this->module_parts = array(
			'api' => 1,
			'triggers' => 0,
			'hooks' => array(),
			'models' => 0,
			'substitutions' => 0,
		);

		$this->initConstants();
		$this->initRights();
		$this->initMenus();
	}

	/**
	 * Module activation.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/mcpconnector/sql/');
		if ($result < 0) {
			return -1;
		}

		return $this->_init(array(), $options);
	}

	/**
	 * Module removal.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}

	/**
	 * Declare module constants.
	 *
	 * @return void
	 */
	private function initConstants()
	{
		$r = 0;
		$defaultTools = json_encode(array(
			'search_thirdparties',
			'get_thirdparty_summary',
			'search_projects',
			'get_project_summary',
			'search_proposals',
			'get_unpaid_invoices',
			'get_turnover_stats',
		));

		$this->const[$r++] = array('MCP_CONNECTOR_ENABLED', 'yesno', '0', 'Enable MCP Connector', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_READONLY', 'yesno', '1', 'Force read-only mode', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_DOLIBARR_BASE_URL', 'chaine', '', 'Dolibarr public base URL', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_DOLIBARR_API_URL', 'chaine', '', 'Dolibarr REST API URL', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_API_USER_ID', 'chaine', '', 'Technical API user ID', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_API_KEY_ENCRYPTED', 'chaine', '', 'Encrypted API key', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_PUBLIC_URL', 'chaine', '', 'Public MCP server URL', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_ALLOWED_TOOLS', 'chaine', $defaultTools, 'Allowed MCP tools', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_LOG_ENABLED', 'yesno', '1', 'Enable MCP call logs', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_LOG_RETENTION_DAYS', 'chaine', '90', 'MCP call log retention in days', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_ENTITY_MODE', 'chaine', 'current_user_entity', 'Entity mode', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_NODE_PORT', 'chaine', '3001', 'Local Node.js MCP server port', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_LAST_TEST_DATE', 'chaine', '', 'Last connection test date', 0, 'current', 1);
		$this->const[$r++] = array('MCP_CONNECTOR_LAST_TEST_STATUS', 'chaine', '', 'Last connection test status', 0, 'current', 1);
	}

	/**
	 * Declare module permissions.
	 *
	 * @return void
	 */
	private function initRights()
	{
		$r = 0;

		$this->rights[$r][0] = $this->numero + 1;
		$this->rights[$r][1] = 'Read data via MCP Connector';
		$this->rights[$r][4] = 'read';
		$this->rights[$r][5] = '';
		$this->rights[$r][6] = 0;
		$r++;

		$this->rights[$r][0] = $this->numero + 2;
		$this->rights[$r][1] = 'Configure MCP Connector';
		$this->rights[$r][4] = 'setup';
		$this->rights[$r][5] = '';
		$this->rights[$r][6] = 0;
		$r++;

		$this->rights[$r][0] = $this->numero + 3;
		$this->rights[$r][1] = 'View MCP Connector logs';
		$this->rights[$r][4] = 'logs';
		$this->rights[$r][5] = '';
		$this->rights[$r][6] = 0;
		$r++;

		$this->rights[$r][0] = $this->numero + 4;
		$this->rights[$r][1] = 'Write data via MCP Connector (reserved for V2)';
		$this->rights[$r][4] = 'write';
		$this->rights[$r][5] = '';
		$this->rights[$r][6] = 0;
		$r++;

		$this->rights[$r][0] = $this->numero + 5;
		$this->rights[$r][1] = 'Delete data via MCP Connector (reserved for V2)';
		$this->rights[$r][4] = 'delete';
		$this->rights[$r][5] = '';
		$this->rights[$r][6] = 0;
	}

	/**
	 * Declare optional navigation entries.
	 *
	 * @return void
	 */
	private function initMenus()
	{
		$r = 0;

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'MCPConnector',
			'mainmenu' => 'tools',
			'leftmenu' => 'mcpconnector',
			'url' => '/mcpconnector/admin/setup.php',
			'langs' => 'mcpconnector@mcpconnector',
			'position' => 500,
			'enabled' => 'isModEnabled("mcpconnector")',
			'perms' => '$user->hasRight("mcpconnector", "setup")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=mcpconnector',
			'type' => 'left',
			'titre' => 'MCPConnectorLogs',
			'mainmenu' => 'tools',
			'leftmenu' => 'mcpconnector_logs',
			'url' => '/mcpconnector/admin/logs.php',
			'langs' => 'mcpconnector@mcpconnector',
			'position' => 501,
			'enabled' => 'isModEnabled("mcpconnector")',
			'perms' => '$user->hasRight("mcpconnector", "logs")',
			'target' => '',
			'user' => 2,
		);
	}
}
