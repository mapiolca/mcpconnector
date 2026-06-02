<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Prepare admin tabs.
 *
 * @return array<int,array<int,string>>
 */
function mcpconnectorAdminPrepareHead()
{
	global $langs;

	$langs->loadLangs(array('mcpconnector@mcpconnector'));

	$head = array();
	$head[] = array(dol_buildpath('/mcpconnector/admin/setup.php', 1), $langs->trans('MCPConnectorSetup'), 'setup');
	$head[] = array(dol_buildpath('/mcpconnector/admin/compatibility.php', 1), $langs->trans('MCPConnectorCompatibility'), 'compatibility');
	$head[] = array(dol_buildpath('/mcpconnector/admin/installassistant.php', 1), $langs->trans('MCPConnectorInstallAssistant'), 'installassistant');
	$head[] = array(dol_buildpath('/mcpconnector/admin/logs.php', 1), $langs->trans('MCPConnectorLogs'), 'logs');

	return $head;
}

/**
 * Print common admin header.
 *
 * @param string $selected Selected tab
 * @param string $title    Page title
 * @return void
 */
function mcpconnectorPrintAdminHeader($selected, $title)
{
	$head = mcpconnectorAdminPrepareHead();

	print load_fiche_titre($title, '', 'fa-plug');
	if (function_exists('dol_get_fiche_head')) {
		print dol_get_fiche_head($head, $selected, $title, -1, 'fa-plug');
	} else {
		dol_fiche_head($head, $selected, $title, -1, 'fa-plug');
	}
}

/**
 * Print common admin footer.
 *
 * @return void
 */
function mcpconnectorPrintAdminFooter()
{
	if (function_exists('dol_get_fiche_end')) {
		print dol_get_fiche_end();
	} else {
		dol_fiche_end();
	}
}
