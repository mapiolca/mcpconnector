<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/mcpconnector/lib/mcpconnector.lib.php');

/**
 * Service helper for MCP Connector.
 */
class McpConnector
{
	public const VERSION = '1.0.0';

	/**
	 * Return V1 tool metadata.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function getTools()
	{
		$allowed = mcpconnector_get_allowed_tools(mcpconnector_powerplantpv_available());
		$catalog = mcpconnector_get_tool_catalog(mcpconnector_powerplantpv_available());
		$result = array();

		foreach ($catalog as $name => $tool) {
			$tool['name'] = $name;
			$tool['enabled'] = in_array($name, $allowed, true);
			$tool['readonly'] = true;
			$result[] = $tool;
		}

		return $result;
	}
}
