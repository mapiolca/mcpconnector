<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/mcpconnector/lib/mcpconnector.lib.php');
dol_include_once('/mcpconnector/class/mcpconnectorconfig.class.php');

/**
 * Centralized compatibility checks.
 */
class McpConnectorCompatibility
{
	/**
	 * Check Dolibarr version.
	 *
	 * @param string $version Minimum version
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Check PHP version.
	 *
	 * @param string $version Minimum version
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Return feature list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getFeatures()
	{
		$powerplantAvailable = mcpconnector_powerplantpv_available();

		return array(
			'base_readonly_api' => array(
				'label' => 'Read-only REST API',
				'description' => 'Dolibarr read-only MCP Connector API endpoints',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => self::isDolibarrVersionAtLeast('20.0.0') && self::isPhpVersionAtLeast('8.0.0'),
				'reason' => '',
			),
			'mcp_server_bundle' => array(
				'label' => 'Bundled MCP server',
				'description' => 'Node.js/TypeScript MCP server folder',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => is_dir(dol_buildpath('/mcpconnector/mcp-server', 0)),
				'reason' => 'Missing mcp-server folder',
			),
			'powerplantpv_tools' => array(
				'label' => 'Powerplant PV tools',
				'description' => 'Conditional tools for module powerplantpv',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $powerplantAvailable,
				'reason' => $powerplantAvailable ? '' : 'Powerplant PV module not detected',
			),
		);
	}

	/**
	 * Check whether a feature is available.
	 *
	 * @param string $code Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($code)
	{
		$features = self::getFeatures();
		return !empty($features[$code]['available']);
	}

	/**
	 * Return unavailable features.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getUnavailableFeatures()
	{
		return array_filter(self::getFeatures(), function ($feature) {
			return empty($feature['available']);
		});
	}

	/**
	 * Run admin compatibility checks.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function runChecks()
	{
		global $db;

		$config = McpConnectorConfig::getConfig();
		$modulePath = dol_buildpath('/mcpconnector', 0);
		$mcpPath = $modulePath.'/mcp-server';
		$apiUser = self::fetchApiUser($db, (int) $config['api_user_id']);

		$checks = array();
		$checks[] = self::row('MCPConnectorCompatibilityDolibarr', self::isDolibarrVersionAtLeast('20.0.0') ? 'OK' : 'ERROR', defined('DOL_VERSION') ? DOL_VERSION : 'Unknown');
		$checks[] = self::row('MCPConnectorCompatibilityPhp', self::isPhpVersionAtLeast('8.0.0') ? 'OK' : 'ERROR', PHP_VERSION);
		$checks[] = self::row('MCPConnectorCompatibilityCurl', extension_loaded('curl') ? 'OK' : 'ERROR', extension_loaded('curl') ? 'Loaded' : 'Missing');
		$checks[] = self::row('MCPConnectorCompatibilityRest', $config['dolibarr_api_url'] !== '' ? 'OK' : 'ERROR', $config['dolibarr_api_url'] !== '' ? $config['dolibarr_api_url'] : 'Not configured');
		$checks[] = self::row('MCPConnectorCompatibilityHttps', strpos($config['dolibarr_base_url'], 'https://') === 0 ? 'OK' : 'WARNING', $config['dolibarr_base_url'] ?: 'Not configured');
		$checks[] = self::row('MCPConnectorDolibarrBaseUrl', $config['dolibarr_base_url'] !== '' ? 'OK' : 'ERROR', $config['dolibarr_base_url'] ?: 'Not configured');
		$checks[] = self::row('MCPConnectorApiKey', McpConnectorConfig::hasApiKey() ? 'OK' : 'ERROR', McpConnectorConfig::hasApiKey() ? 'Stored' : 'Missing');
		$checks[] = self::row('MCPConnectorApiUser', $apiUser ? 'OK' : 'ERROR', $apiUser ? $apiUser->login : 'Missing');
		$checks[] = self::row('MCPConnectorApiUserPermissions', self::apiUserHasMinimumRights($apiUser) ? 'OK' : 'WARNING', $apiUser ? 'Check MCP Connector and object read rights' : 'No user');
		$checks[] = self::row('MCPConnectorCompatibilityMcpServer', is_dir($mcpPath) ? 'OK' : 'ERROR', $mcpPath);
		$checks[] = self::row('package.json', is_file($mcpPath.'/package.json') ? 'OK' : 'ERROR', $mcpPath.'/package.json');
		$checks[] = self::row('MCPConnectorCompatibilityTemplates', self::templatesPresent() ? 'OK' : 'ERROR', dol_buildpath('/mcpconnector/scripts', 0));
		$checks[] = self::row('MCPConnectorPublicUrl', $config['public_url'] !== '' ? 'OK' : 'WARNING', $config['public_url'] ?: 'Not configured');
		$checks[] = self::row('MCPConnectorCompatibilityMcpReachable', self::isMcpReachable($config['public_url']) ? 'OK' : 'WARNING', $config['public_url'] ?: 'Not tested');
		$checks[] = self::row('Module Centrale PV', mcpconnector_powerplantpv_available() ? 'OK' : 'INFO', mcpconnector_powerplantpv_available() ? 'Detected' : 'Not detected');

		return $checks;
	}

	/**
	 * Fetch configured API user.
	 *
	 * @param DoliDB $db Database handler
	 * @param int    $id User ID
	 * @return User|null
	 */
	private static function fetchApiUser($db, $id)
	{
		if ($id <= 0) {
			return null;
		}
		if (file_exists(DOL_DOCUMENT_ROOT.'/user/class/user.class.php')) {
			require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		}
		if (!class_exists('User')) {
			return null;
		}
		$user = new User($db);
		return $user->fetch($id) > 0 ? $user : null;
	}

	/**
	 * Check minimum expected rights for API user.
	 *
	 * @param User|null $apiUser User
	 * @return bool
	 */
	private static function apiUserHasMinimumRights($apiUser)
	{
		if (!$apiUser) {
			return false;
		}
		return mcpconnector_user_has_right($apiUser, 'read')
			&& mcpconnector_user_has_core_right($apiUser, 'societe', 'lire')
			&& mcpconnector_user_has_core_right($apiUser, 'projet', 'lire')
			&& mcpconnector_user_has_core_right($apiUser, 'propal', 'lire')
			&& mcpconnector_user_has_core_right($apiUser, 'facture', 'lire');
	}

	/**
	 * Build one check row.
	 *
	 * @param string $label  Label translation key or text
	 * @param string $status Status
	 * @param string $detail Detail
	 * @return array<string,string>
	 */
	private static function row($label, $status, $detail)
	{
		return array('label' => $label, 'status' => $status, 'detail' => $detail);
	}

	/**
	 * Check template presence.
	 *
	 * @return bool
	 */
	private static function templatesPresent()
	{
		$base = dol_buildpath('/mcpconnector/scripts', 0);
		foreach (array('env.tpl', 'dolibarr-mcpconnector.service.tpl', 'nginx.conf.tpl', 'apache.conf.tpl') as $file) {
			if (!is_file($base.'/'.$file)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Lightweight MCP URL reachability check.
	 *
	 * @param string $url URL
	 * @return bool
	 */
	private static function isMcpReachable($url)
	{
		if ($url === '' || !extension_loaded('curl')) {
			return false;
		}

		$ch = curl_init($url);
		if ($ch === false) {
			return false;
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $code >= 200 && $code < 500;
	}
}
