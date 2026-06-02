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
dol_include_once('/mcpconnector/lib/mcpconnector_compatibility.lib.php');

$langs->loadLangs(array('admin', 'mcpconnector@mcpconnector'));

if (!isModEnabled('mcpconnector')) {
	accessforbidden();
}
mcpconnector_restricted_area('setup');

llxHeader('', $langs->trans('MCPConnectorCompatibility'));
mcpconnectorPrintAdminHeader('compatibility', $langs->trans('MCPConnectorCompatibility'));

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Test').'</td><td>'.$langs->trans('MCPConnectorStatus').'</td><td>'.$langs->trans('MCPConnectorDetail').'</td></tr>';
foreach (McpConnectorCompatibility::runChecks() as $check) {
	$statusClass = strtolower($check['status']);
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($check['label']).'</td>';
	print '<td><span class="badge badge-status'.$statusClass.'">'.dol_escape_htmltag($check['status']).'</span></td>';
	print '<td>'.dol_escape_htmltag($check['detail']).'</td>';
	print '</tr>';
}
print '</table>';

print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Feature</td><td>Description</td><td>Status</td><td>Reason</td></tr>';
foreach (McpConnectorCompatibility::getFeatures() as $code => $feature) {
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($code).'</td>';
	print '<td>'.dol_escape_htmltag($feature['description']).'</td>';
	print '<td>'.(!empty($feature['available']) ? 'OK' : 'Unavailable').'</td>';
	print '<td>'.dol_escape_htmltag((string) $feature['reason']).'</td>';
	print '</tr>';
}
print '</table>';

mcpconnectorPrintAdminFooter();
llxFooter();
$db->close();
