<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../lib/mcpconnector.lib.php';
mcpconnector_include_main();

dol_include_once('/mcpconnector/lib/mcpconnector_admin.lib.php');
dol_include_once('/mcpconnector/class/mcpconnectorconfig.class.php');

$langs->loadLangs(array('admin', 'mcpconnector@mcpconnector'));

if (!isModEnabled('mcpconnector')) {
	accessforbidden();
}
mcpconnector_restricted_area('setup');

$mode = GETPOST('mode', 'alpha');
if (!in_array($mode, array('same', 'remote', 'shared', 'generate'), true)) {
	$mode = 'same';
}

$config = McpConnectorConfig::getConfig();
$serverPath = dol_buildpath('/mcpconnector/mcp-server', 0);

llxHeader('', $langs->trans('MCPConnectorInstallAssistant'));
mcpconnectorPrintAdminHeader('installassistant', $langs->trans('MCPConnectorInstallAssistant'));

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<label>'.$langs->trans('MCPConnectorInstallMode').' </label>';
print '<select name="mode" onchange="this.form.submit()">';
print '<option value="same"'.($mode === 'same' ? ' selected' : '').'>'.$langs->trans('MCPConnectorSameServer').'</option>';
print '<option value="remote"'.($mode === 'remote' ? ' selected' : '').'>'.$langs->trans('MCPConnectorRemoteServer').'</option>';
print '<option value="shared"'.($mode === 'shared' ? ' selected' : '').'>'.$langs->trans('MCPConnectorSharedHosting').'</option>';
print '<option value="generate"'.($mode === 'generate' ? ' selected' : '').'>'.$langs->trans('MCPConnectorGenerateOnly').'</option>';
print '</select>';
print '</form>';

print '<br>';
print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<h3>'.$langs->trans('MCPConnectorServerInstructions').'</h3>';

if ($mode === 'same') {
	print '<pre>';
	print 'cd '.dol_escape_htmltag($serverPath)."\n";
	print "npm ci\n";
	print "npm run build\n";
	print "node dist/index.js\n";
	print '</pre>';
	print '<p>Use the generated systemd service only after reviewing paths and permissions.</p>';
} elseif ($mode === 'remote') {
	print '<pre>';
	print "scp -r mcp-server user@server:/opt/dolibarr-mcpconnector\n";
	print "scp dolibarr-mcpconnector.env user@server:/etc/dolibarr-mcpconnector.env\n";
	print "cd /opt/dolibarr-mcpconnector\n";
	print "npm ci --omit=dev\n";
	print "npm run build\n";
	print "node dist/index.js\n";
	print '</pre>';
} elseif ($mode === 'shared') {
	print '<div class="warning">';
	print 'Your hosting provider must allow a permanent Node.js application. If it only provides PHP/MySQL hosting, the MCP server cannot run there.';
	print '</div>';
	print '<pre>';
	print "npm ci\n";
	print "npm run build\n";
	print "node dist/index.js\n";
	print '</pre>';
} else {
	print '<p>Generate the deployment files from the setup page and transfer them to the server that will run Node.js.</p>';
}

print '<p><strong>Public MCP URL:</strong> '.dol_escape_htmltag($config['public_url'] ?: 'https://mcp.example.com/mcp').'</p>';
print '<p><strong>Local port:</strong> '.((int) $config['node_port']).'</p>';
print '</div>';

mcpconnectorPrintAdminFooter();
llxFooter();
$db->close();
