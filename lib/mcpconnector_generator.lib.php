<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/mcpconnector/class/mcpconnectorconfig.class.php');

/**
 * Generate deployment files from templates.
 */
class McpConnectorGenerator
{
	/**
	 * Render a template file.
	 *
	 * @param string              $template Template path
	 * @param array<string,mixed> $values   Values
	 * @return string
	 */
	public static function renderTemplate($template, array $values)
	{
		$content = file_get_contents($template);
		if ($content === false) {
			return '';
		}

		foreach ($values as $key => $value) {
			$content = str_replace('{{'.$key.'}}', (string) $value, $content);
		}

		return $content;
	}

	/**
	 * Build the dotenv file content.
	 *
	 * @return string
	 */
	public static function buildEnv()
	{
		global $conf;

		$template = dol_buildpath('/mcpconnector/scripts/env.tpl', 0);
		$config = McpConnectorConfig::getConfig();

		return self::renderTemplate($template, array(
			'DOLIBARR_BASE_URL' => $config['dolibarr_base_url'],
			'DOLIBARR_API_URL' => $config['dolibarr_api_url'],
			'DOLIBARR_API_KEY' => McpConnectorConfig::getApiKey(),
			'DOLIBARR_ENTITY' => (int) $conf->entity,
			'MCP_PUBLIC_URL' => $config['public_url'],
			'MCP_PORT' => (int) $config['node_port'],
			'MCP_ALLOWED_TOOLS' => implode(',', McpConnectorConfig::getAllowedTools()),
		));
	}

	/**
	 * Build a systemd service example.
	 *
	 * @return string
	 */
	public static function buildSystemd()
	{
		$template = dol_buildpath('/mcpconnector/scripts/dolibarr-mcpconnector.service.tpl', 0);
		$modulePath = dol_buildpath('/mcpconnector/mcp-server', 0);

		return self::renderTemplate($template, array(
			'MCP_SERVER_PATH' => $modulePath,
			'NODE_BIN' => '/usr/bin/node',
			'ENV_FILE_PATH' => '/etc/dolibarr-mcpconnector.env',
		));
	}

	/**
	 * Build an Nginx reverse proxy example.
	 *
	 * @return string
	 */
	public static function buildNginx()
	{
		$template = dol_buildpath('/mcpconnector/scripts/nginx.conf.tpl', 0);
		$config = McpConnectorConfig::getConfig();
		$domain = parse_url($config['public_url'], PHP_URL_HOST);

		return self::renderTemplate($template, array(
			'MCP_DOMAIN' => $domain ?: 'mcp.example.com',
			'MCP_PORT' => (int) $config['node_port'],
			'SSL_CERTIFICATE' => '/etc/letsencrypt/live/'.($domain ?: 'mcp.example.com').'/fullchain.pem',
			'SSL_CERTIFICATE_KEY' => '/etc/letsencrypt/live/'.($domain ?: 'mcp.example.com').'/privkey.pem',
		));
	}

	/**
	 * Build an Apache reverse proxy example.
	 *
	 * @return string
	 */
	public static function buildApache()
	{
		$template = dol_buildpath('/mcpconnector/scripts/apache.conf.tpl', 0);
		$config = McpConnectorConfig::getConfig();
		$domain = parse_url($config['public_url'], PHP_URL_HOST);

		return self::renderTemplate($template, array(
			'MCP_DOMAIN' => $domain ?: 'mcp.example.com',
			'MCP_PORT' => (int) $config['node_port'],
			'SSL_CERTIFICATE' => '/etc/letsencrypt/live/'.($domain ?: 'mcp.example.com').'/fullchain.pem',
			'SSL_CERTIFICATE_KEY' => '/etc/letsencrypt/live/'.($domain ?: 'mcp.example.com').'/privkey.pem',
		));
	}

	/**
	 * Send a generated file as a browser download.
	 *
	 * @param string $filename Filename
	 * @param string $content  File content
	 * @return void
	 */
	public static function download($filename, $content)
	{
		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Content-Length: '.strlen($content));
		print $content;
		exit;
	}
}
