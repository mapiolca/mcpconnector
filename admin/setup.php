<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/../lib/mcpconnector.lib.php';
mcpconnector_include_main();

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/mcpconnector/lib/mcpconnector_admin.lib.php');
dol_include_once('/mcpconnector/lib/mcpconnector_generator.lib.php');
dol_include_once('/mcpconnector/class/mcpconnectorconfig.class.php');

$langs->loadLangs(array('admin', 'users', 'mcpconnector@mcpconnector'));

if (!isModEnabled('mcpconnector')) {
	accessforbidden();
}
mcpconnector_restricted_area('setup');

$action = GETPOST('action', 'aZ09');
$form = new Form($db);

if (in_array($action, array('save', 'generate_env', 'generate_systemd', 'generate_nginx', 'generate_apache', 'test_api', 'test_mcp'), true)) {
	if (function_exists('checkToken') && !checkToken()) {
		accessforbidden($langs->trans('MCPConnectorInvalidToken'));
	}
}

if ($action === 'save') {
	$values = array(
		'enabled' => GETPOST('enabled', 'int'),
		'dolibarr_base_url' => GETPOST('dolibarr_base_url', 'restricthtml'),
		'dolibarr_api_url' => GETPOST('dolibarr_api_url', 'restricthtml'),
		'api_user_id' => GETPOST('api_user_id', 'int'),
		'api_key' => GETPOST('api_key', 'restricthtml'),
		'public_url' => GETPOST('public_url', 'restricthtml'),
		'node_port' => GETPOST('node_port', 'int'),
		'allowed_tools' => GETPOST('allowed_tools', 'array'),
		'log_enabled' => GETPOST('log_enabled', 'int'),
		'log_retention_days' => GETPOST('log_retention_days', 'int'),
		'entity_mode' => GETPOST('entity_mode', 'alpha'),
	);

	$result = McpConnectorConfig::save($db, $values);
	if ($result > 0) {
		setEventMessages($langs->trans('MCPConnectorSetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
} elseif ($action === 'generate_env') {
	McpConnectorGenerator::download('dolibarr-mcpconnector.env', McpConnectorGenerator::buildEnv());
} elseif ($action === 'generate_systemd') {
	McpConnectorGenerator::download('dolibarr-mcpconnector.service', McpConnectorGenerator::buildSystemd());
} elseif ($action === 'generate_nginx') {
	McpConnectorGenerator::download('dolibarr-mcpconnector.nginx.conf', McpConnectorGenerator::buildNginx());
} elseif ($action === 'generate_apache') {
	McpConnectorGenerator::download('dolibarr-mcpconnector.apache.conf', McpConnectorGenerator::buildApache());
} elseif ($action === 'test_api') {
	$result = 0;
	$config = McpConnectorConfig::getConfig();
	if ($config['dolibarr_api_url'] !== '' && McpConnectorConfig::hasApiKey() && extension_loaded('curl')) {
		$ch = curl_init($config['dolibarr_api_url'].'/mcpconnector/status');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('DOLAPIKEY: '.McpConnectorConfig::getApiKey()));
		curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$result = ($httpCode >= 200 && $httpCode < 300) ? 1 : -1;
	}
	dolibarr_set_const($db, 'MCP_CONNECTOR_LAST_TEST_DATE', dol_print_date(dol_now(), 'dayhourlog'), 'chaine', 0, '', (int) $conf->entity);
	dolibarr_set_const($db, 'MCP_CONNECTOR_LAST_TEST_STATUS', $result > 0 ? 'OK' : 'ERROR', 'chaine', 0, '', (int) $conf->entity);
	setEventMessages($result > 0 ? 'Dolibarr API OK' : 'Dolibarr API ERROR', null, $result > 0 ? 'mesgs' : 'errors');
} elseif ($action === 'test_mcp') {
	$config = McpConnectorConfig::getConfig();
	$result = 0;
	if ($config['public_url'] !== '' && extension_loaded('curl')) {
		$ch = curl_init($config['public_url']);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$result = ($httpCode >= 200 && $httpCode < 500) ? 1 : -1;
	}
	setEventMessages($result > 0 ? 'MCP URL OK' : 'MCP URL ERROR', null, $result > 0 ? 'mesgs' : 'errors');
}

$config = McpConnectorConfig::getConfig();
$toolCatalog = mcpconnector_get_tool_catalog(mcpconnector_powerplantpv_available());
$allowedTools = McpConnectorConfig::getAllowedTools();

llxHeader('', $langs->trans('MCPConnectorSetup'));
mcpconnectorPrintAdminHeader('setup', $langs->trans('MCPConnectorSetup'));

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('MCPConnectorSetup').'</td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorEnabled').'</td><td><input type="checkbox" name="enabled" value="1"'.(!empty($config['enabled']) ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorReadOnly').'</td><td><input type="checkbox" checked disabled> '.$langs->trans('MCPConnectorReadOnlyForced').'<input type="hidden" name="readonly" value="1"></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorDolibarrBaseUrl').'</td><td><input class="minwidth500" type="text" name="dolibarr_base_url" value="'.dol_escape_htmltag($config['dolibarr_base_url']).'"></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorDolibarrApiUrl').'</td><td><input class="minwidth500" type="text" name="dolibarr_api_url" value="'.dol_escape_htmltag($config['dolibarr_api_url']).'"></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorApiUser').'</td><td>'.$form->select_dolusers($config['api_user_id'], 'api_user_id', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth500').'</td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorApiKey').'</td><td><input class="minwidth300" type="password" name="api_key" value="" autocomplete="new-password"> '.(McpConnectorConfig::hasApiKey() ? $langs->trans('MCPConnectorSecretStored') : $langs->trans('MCPConnectorNoApiKeyConfigured')).'<br><span class="opacitymedium">'.$langs->trans('MCPConnectorApiKeyHelp').'</span></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorEntityMode').'</td><td><select name="entity_mode">';
print '<option value="current_user_entity"'.($config['entity_mode'] === 'current_user_entity' ? ' selected' : '').'>'.$langs->trans('MCPConnectorCurrentUserEntity').'</option>';
print '<option value="fixed_entity"'.($config['entity_mode'] === 'fixed_entity' ? ' selected' : '').'>'.$langs->trans('MCPConnectorFixedEntity').'</option>';
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorPublicUrl').'</td><td><input class="minwidth500" type="text" name="public_url" value="'.dol_escape_htmltag($config['public_url']).'"></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorNodePort').'</td><td><input class="width75" type="number" min="1" name="node_port" value="'.((int) $config['node_port']).'"></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorAllowedTools').'</td><td>';
foreach ($toolCatalog as $name => $tool) {
	print '<label class="marginrightonly">';
	print '<input type="checkbox" name="allowed_tools[]" value="'.dol_escape_htmltag($name).'"'.(in_array($name, $allowedTools, true) ? ' checked' : '').'> ';
	print dol_escape_htmltag($name);
	print '</label><br>';
}
print '</td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorLogEnabled').'</td><td><input type="checkbox" name="log_enabled" value="1"'.(!empty($config['log_enabled']) ? ' checked' : '').'></td></tr>';
print '<tr><td>'.$langs->trans('MCPConnectorLogRetentionDays').'</td><td><input class="width75" type="number" min="1" name="log_retention_days" value="'.((int) $config['log_retention_days']).'"></td></tr>';
print '</table>';
print '<div class="center">';
print '<button class="button button-save" type="submit">'.$langs->trans('MCPConnectorSave').'</button>';
print '</div>';
print '</form>';

print '<div class="tabsAction">';
foreach (array('test_api' => 'MCPConnectorTestApi', 'test_mcp' => 'MCPConnectorTestMcp', 'generate_env' => 'MCPConnectorGenerateEnv', 'generate_systemd' => 'MCPConnectorGenerateSystemd', 'generate_nginx' => 'MCPConnectorGenerateNginx', 'generate_apache' => 'MCPConnectorGenerateApache') as $act => $label) {
	print '<form class="inline-block" method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$act.'">';
	print '<button class="button" type="submit">'.$langs->trans($label).'</button>';
	print '</form> ';
}
print '<a class="button" href="'.dol_buildpath('/mcpconnector/admin/installassistant.php', 1).'">'.$langs->trans('MCPConnectorInstallAssistant').'</a>';
print '</div>';

mcpconnectorPrintAdminFooter();
llxFooter();
$db->close();
