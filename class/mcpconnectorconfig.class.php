<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/mcpconnector/lib/mcpconnector.lib.php');
if (defined('DOL_DOCUMENT_ROOT') && file_exists(DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php')) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
}

/**
 * Centralized module configuration access.
 */
class McpConnectorConfig
{
	/**
	 * Return current config values.
	 *
	 * @return array<string,mixed>
	 */
	public static function getConfig()
	{
		return array(
			'enabled' => (int) getDolGlobalInt('MCP_CONNECTOR_ENABLED', 0),
			'readonly' => 1,
			'dolibarr_base_url' => mcpconnector_clean_url(getDolGlobalString('MCP_CONNECTOR_DOLIBARR_BASE_URL')),
			'dolibarr_api_url' => mcpconnector_clean_url(getDolGlobalString('MCP_CONNECTOR_DOLIBARR_API_URL')),
			'api_user_id' => (int) getDolGlobalInt('MCP_CONNECTOR_API_USER_ID', 0),
			'public_url' => mcpconnector_clean_url(getDolGlobalString('MCP_CONNECTOR_PUBLIC_URL')),
			'allowed_tools' => self::getAllowedTools(),
			'log_enabled' => (int) getDolGlobalInt('MCP_CONNECTOR_LOG_ENABLED', 1),
			'log_retention_days' => max(1, (int) getDolGlobalInt('MCP_CONNECTOR_LOG_RETENTION_DAYS', 90)),
			'entity_mode' => getDolGlobalString('MCP_CONNECTOR_ENTITY_MODE', 'current_user_entity'),
			'node_port' => max(1, (int) getDolGlobalInt('MCP_CONNECTOR_NODE_PORT', 3001)),
			'last_test_date' => getDolGlobalString('MCP_CONNECTOR_LAST_TEST_DATE'),
			'last_test_status' => getDolGlobalString('MCP_CONNECTOR_LAST_TEST_STATUS'),
		);
	}

	/**
	 * Return allowed tools, excluding unavailable conditional tools.
	 *
	 * @return string[]
	 */
	public static function getAllowedTools()
	{
		return mcpconnector_get_allowed_tools(mcpconnector_powerplantpv_available());
	}

	/**
	 * Save module setup.
	 *
	 * @param DoliDB              $db     Database handler
	 * @param array<string,mixed> $values Values
	 * @return int
	 */
	public static function save($db, array $values)
	{
		global $conf;

		$entity = (int) $conf->entity;
		$error = 0;
		$allowedTools = array_values(array_intersect((array) ($values['allowed_tools'] ?? array()), array_keys(mcpconnector_get_tool_catalog(mcpconnector_powerplantpv_available()))));

		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_ENABLED', empty($values['enabled']) ? '0' : '1', 'yesno', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_READONLY', '1', 'yesno', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_DOLIBARR_BASE_URL', mcpconnector_clean_url($values['dolibarr_base_url'] ?? ''), 'chaine', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_DOLIBARR_API_URL', mcpconnector_clean_url($values['dolibarr_api_url'] ?? ''), 'chaine', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_API_USER_ID', (string) ((int) ($values['api_user_id'] ?? 0)), 'chaine', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_PUBLIC_URL', mcpconnector_clean_url($values['public_url'] ?? ''), 'chaine', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_ALLOWED_TOOLS', json_encode($allowedTools), 'chaine', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_LOG_ENABLED', empty($values['log_enabled']) ? '0' : '1', 'yesno', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_LOG_RETENTION_DAYS', (string) max(1, (int) ($values['log_retention_days'] ?? 90)), 'chaine', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_ENTITY_MODE', in_array(($values['entity_mode'] ?? ''), array('current_user_entity', 'fixed_entity'), true) ? $values['entity_mode'] : 'current_user_entity', 'chaine', 0, '', $entity) > 0 ? 0 : 1;
		$error += dolibarr_set_const($db, 'MCP_CONNECTOR_NODE_PORT', (string) max(1, (int) ($values['node_port'] ?? 3001)), 'chaine', 0, '', $entity) > 0 ? 0 : 1;

		if (!empty($values['api_key'])) {
			$error += dolibarr_set_const($db, 'MCP_CONNECTOR_API_KEY_ENCRYPTED', self::encryptSecret($values['api_key']), 'chaine', 0, '', $entity) > 0 ? 0 : 1;
		}

		return $error ? -1 : 1;
	}

	/**
	 * Return whether an API key is stored.
	 *
	 * @return bool
	 */
	public static function hasApiKey()
	{
		return getDolGlobalString('MCP_CONNECTOR_API_KEY_ENCRYPTED') !== '';
	}

	/**
	 * Return decrypted API key.
	 *
	 * @return string
	 */
	public static function getApiKey()
	{
		$stored = getDolGlobalString('MCP_CONNECTOR_API_KEY_ENCRYPTED');
		if ($stored === '') {
			return '';
		}

		if (function_exists('dolDecrypt')) {
			return (string) dolDecrypt($stored);
		}

		return $stored;
	}

	/**
	 * Encrypt a secret when Dolibarr crypto helpers are available.
	 *
	 * @param string $secret Secret
	 * @return string
	 */
	private static function encryptSecret($secret)
	{
		if (function_exists('dolEncrypt')) {
			return (string) dolEncrypt($secret);
		}

		return (string) $secret;
	}
}
