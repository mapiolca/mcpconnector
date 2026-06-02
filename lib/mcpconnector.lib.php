<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Generic helpers for MCP Connector.
 */

/**
 * Include Dolibarr main.inc.php from module pages.
 *
 * @return int
 */
function mcpconnector_include_main()
{
	$res = 0;
	if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
		$res = @include __DIR__.'/../../../main.inc.php';
	}
	if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
		$res = @include __DIR__.'/../../main.inc.php';
	}
	if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) {
		$res = @include __DIR__.'/../../../../main.inc.php';
	}
	return $res;
}

/**
 * Check a flat module right like mcpconnector->setup.
 *
 * @param User   $user  User object
 * @param string $right Right key
 * @return bool
 */
function mcpconnector_user_has_right($user, $right)
{
	if (!empty($user->admin)) {
		return true;
	}
	if (method_exists($user, 'hasRight')) {
		return (bool) $user->hasRight('mcpconnector', $right);
	}
	return !empty($user->rights->mcpconnector->{$right});
}

/**
 * Check a core Dolibarr right using legacy and modern patterns.
 *
 * @param User   $user   User object
 * @param string $module Rights module
 * @param string $right  Right key
 * @return bool
 */
function mcpconnector_user_has_core_right($user, $module, $right)
{
	if (!empty($user->admin)) {
		return true;
	}
	if (method_exists($user, 'hasRight')) {
		return (bool) $user->hasRight($module, $right);
	}
	return !empty($user->rights->{$module}->{$right});
}

/**
 * Abort when a module right is missing.
 *
 * @param string $right Required right
 * @return void
 */
function mcpconnector_restricted_area($right)
{
	global $user;

	if (!mcpconnector_user_has_right($user, $right)) {
		accessforbidden();
	}
}

/**
 * Return the V1 MCP tool catalog.
 *
 * @param bool $includePowerPlant Include conditional Powerplant PV tools
 * @return array<string,array<string,mixed>>
 */
function mcpconnector_get_tool_catalog($includePowerPlant = false)
{
	$tools = array(
		'search_thirdparties' => array(
			'label' => 'Search thirdparties',
			'description' => 'Search Dolibarr thirdparties',
			'endpoint' => '/mcpconnector/thirdparties/search',
			'readonly' => true,
			'conditional' => false,
		),
		'get_thirdparty_summary' => array(
			'label' => 'Get thirdparty summary',
			'description' => 'Get a Dolibarr thirdparty summary',
			'endpoint' => '/mcpconnector/thirdparties/{id}/summary',
			'readonly' => true,
			'conditional' => false,
		),
		'search_projects' => array(
			'label' => 'Search projects',
			'description' => 'Search Dolibarr projects',
			'endpoint' => '/mcpconnector/projects/search',
			'readonly' => true,
			'conditional' => false,
		),
		'get_project_summary' => array(
			'label' => 'Get project summary',
			'description' => 'Get a Dolibarr project summary',
			'endpoint' => '/mcpconnector/projects/{id}/summary',
			'readonly' => true,
			'conditional' => false,
		),
		'search_proposals' => array(
			'label' => 'Search proposals',
			'description' => 'Search Dolibarr proposals',
			'endpoint' => '/mcpconnector/proposals/search',
			'readonly' => true,
			'conditional' => false,
		),
		'get_unpaid_invoices' => array(
			'label' => 'Get unpaid invoices',
			'description' => 'List unpaid Dolibarr invoices',
			'endpoint' => '/mcpconnector/invoices/unpaid',
			'readonly' => true,
			'conditional' => false,
		),
		'get_turnover_stats' => array(
			'label' => 'Get turnover statistics',
			'description' => 'Get turnover statistics',
			'endpoint' => '/mcpconnector/stats/turnover',
			'readonly' => true,
			'conditional' => false,
		),
	);

	if ($includePowerPlant) {
		$tools['search_powerplants'] = array(
			'label' => 'Search powerplants',
			'description' => 'Search Powerplant PV records',
			'endpoint' => '/mcpconnector/powerplants/search',
			'readonly' => true,
			'conditional' => true,
		);
		$tools['get_powerplantpv_summary'] = array(
			'label' => 'Get Powerplant PV summary',
			'description' => 'Get Powerplant PV summary',
			'endpoint' => '/mcpconnector/powerplants/{id}/summary',
			'readonly' => true,
			'conditional' => true,
		);
	}

	return $tools;
}

/**
 * Return configured allowed tools.
 *
 * @param bool $includePowerPlant Include conditional Powerplant PV tools
 * @return string[]
 */
function mcpconnector_get_allowed_tools($includePowerPlant = false)
{
	global $conf;

	$catalog = mcpconnector_get_tool_catalog($includePowerPlant);
	$raw = getDolGlobalString('MCP_CONNECTOR_ALLOWED_TOOLS');
	$tools = array();

	if ($raw !== '') {
		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			$tools = $decoded;
		} else {
			$tools = array_map('trim', explode(',', $raw));
		}
	} else {
		$tools = array_keys(mcpconnector_get_tool_catalog(false));
	}

	$tools = array_values(array_intersect($tools, array_keys($catalog)));

	if (!$includePowerPlant) {
		$tools = array_values(array_diff($tools, array('search_powerplants', 'get_powerplantpv_summary')));
	}

	return $tools;
}

/**
 * Detect whether Powerplant PV can be exposed.
 *
 * @return bool
 */
function mcpconnector_powerplantpv_available()
{
	if (function_exists('isModEnabled') && isModEnabled('powerplantpv')) {
		return true;
	}
	global $conf;
	return !empty($conf->powerplantpv->enabled);
}

/**
 * Return the entity filter string for SQL.
 *
 * @param DoliDB $db      Database handler
 * @param string $element Dolibarr element/table element
 * @param bool   $strict  Force current entity only
 * @return string
 */
function mcpconnector_get_entity_filter($db, $element, $strict = false)
{
	global $conf;

	if (!$strict && function_exists('getEntity')) {
		return $db->sanitize(getEntity($element));
	}

	return (string) ((int) $conf->entity);
}

/**
 * Normalize a URL by trimming trailing slashes.
 *
 * @param string $url URL
 * @return string
 */
function mcpconnector_clean_url($url)
{
	return rtrim(trim((string) $url), '/');
}
