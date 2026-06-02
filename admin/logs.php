<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = require __DIR__.'/../../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = require __DIR__.'/../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php fails');
}

require_once __DIR__.'/../lib/mcpconnector.lib.php';

dol_include_once('/mcpconnector/lib/mcpconnector_admin.lib.php');
dol_include_once('/mcpconnector/class/mcpconnectorlog.class.php');
dol_include_once('/mcpconnector/class/mcpconnectorconfig.class.php');

$langs->loadLangs(array('admin', 'mcpconnector@mcpconnector'));

if (!isModEnabled('mcpconnector')) {
	accessforbidden();
}
mcpconnector_restricted_area('logs');

$action = GETPOST('action', 'aZ09');
$page = max(0, GETPOST('page', 'int'));
$limit = getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 50);
$offset = $limit * $page;

if ($action === 'purge') {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden($langs->trans('MCPConnectorInvalidToken'));
	}
	$config = McpConnectorConfig::getConfig();
	$log = new McpConnectorLog($db);
	$result = $log->purgeOld($config['log_retention_days']);
	if ($result >= 0) {
		setEventMessages($result.' logs purged', null, 'mesgs');
	} else {
		setEventMessages($log->error, null, 'errors');
	}
}

$filters = array(
	'date_start' => GETPOST('date_start', 'alpha'),
	'date_end' => GETPOST('date_end', 'alpha'),
	'tool_name' => GETPOST('tool_name', 'alpha'),
	'output_status' => GETPOST('output_status', 'alpha'),
	'entity' => GETPOST('entity', 'alpha'),
	'fk_user' => GETPOST('fk_user', 'alpha'),
);

$log = new McpConnectorLog($db);
$rows = $log->fetchAll($filters, $limit, $offset);

llxHeader('', $langs->trans('MCPConnectorLogs'));
mcpconnectorPrintAdminHeader('logs', $langs->trans('MCPConnectorLogs'));

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input type="date" name="date_start" value="'.dol_escape_htmltag($filters['date_start']).'"></td>';
print '<td><input type="date" name="date_end" value="'.dol_escape_htmltag($filters['date_end']).'"></td>';
print '<td><input type="text" name="tool_name" value="'.dol_escape_htmltag($filters['tool_name']).'" placeholder="'.$langs->trans('MCPConnectorTool').'"></td>';
print '<td><select name="output_status"><option value=""></option><option value="OK"'.($filters['output_status'] === 'OK' ? ' selected' : '').'>OK</option><option value="ERROR"'.($filters['output_status'] === 'ERROR' ? ' selected' : '').'>ERROR</option></select></td>';
print '<td><input class="width50" type="number" name="entity" value="'.dol_escape_htmltag($filters['entity']).'" placeholder="'.$langs->trans('MCPConnectorEntity').'"></td>';
print '<td><input class="width50" type="number" name="fk_user" value="'.dol_escape_htmltag($filters['fk_user']).'" placeholder="'.$langs->trans('MCPConnectorUser').'"></td>';
print '<td><input class="button" type="submit" value="'.$langs->trans('Search').'"></td>';
print '</tr>';
print '</table>';
print '</form>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('MCPConnectorDate').'</td>';
print '<td>'.$langs->trans('MCPConnectorTool').'</td>';
print '<td>'.$langs->trans('MCPConnectorEndpoint').'</td>';
print '<td>'.$langs->trans('MCPConnectorUser').'</td>';
print '<td>'.$langs->trans('MCPConnectorEntity').'</td>';
print '<td>'.$langs->trans('MCPConnectorStatus').'</td>';
print '<td>'.$langs->trans('MCPConnectorHttpCode').'</td>';
print '<td>'.$langs->trans('MCPConnectorDuration').'</td>';
print '<td>'.$langs->trans('MCPConnectorIp').'</td>';
print '<td>'.$langs->trans('MCPConnectorDetail').'</td>';
print '</tr>';

if (is_array($rows)) {
	foreach ($rows as $row) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($row->datec).'</td>';
		print '<td>'.dol_escape_htmltag($row->tool_name).'</td>';
		print '<td>'.dol_escape_htmltag($row->endpoint).'</td>';
		print '<td>'.dol_escape_htmltag($row->login ?: $row->fk_user).'</td>';
		print '<td>'.((int) $row->entity).'</td>';
		print '<td>'.dol_escape_htmltag($row->output_status).'</td>';
		print '<td>'.dol_escape_htmltag($row->http_code).'</td>';
		print '<td>'.dol_escape_htmltag($row->duration_ms).' ms</td>';
		print '<td>'.dol_escape_htmltag($row->ip).'</td>';
		print '<td>'.dol_escape_htmltag($row->error_message ?: $row->input_json).'</td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="10">'.$log->error.'</td></tr>';
}
print '</table>';

print '<div class="tabsAction">';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="purge">';
print '<button class="button" type="submit">'.$langs->trans('MCPConnectorPurgeOldLogs').'</button>';
print '</form>';
print '</div>';

mcpconnectorPrintAdminFooter();
llxFooter();
$db->close();
