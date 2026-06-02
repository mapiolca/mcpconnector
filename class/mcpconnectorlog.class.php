<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

dol_include_once('/mcpconnector/class/mcpconnectorconfig.class.php');

/**
 * MCP Connector call log storage.
 */
class McpConnectorLog
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var string[] */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Create a log entry.
	 *
	 * @param array<string,mixed> $data Log values
	 * @return int
	 */
	public function create(array $data)
	{
		global $conf;

		if (!getDolGlobalInt('MCP_CONNECTOR_LOG_ENABLED', 1)) {
			return 1;
		}
		if ($this->ensureTable() < 0) {
			return -1;
		}

		$input = $data['input_json'] ?? null;
		if (is_array($input) || is_object($input)) {
			$input = json_encode($this->redact($input));
		} elseif (is_string($input)) {
			$input = $this->redactString($input);
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'mcpconnector_log (';
		$sql .= 'entity, datec, fk_user, tool_name, endpoint, input_json, output_status, http_code, error_message, ip, user_agent, duration_ms';
		$sql .= ') VALUES (';
		$sql .= ((int) ($data['entity'] ?? $conf->entity));
		$sql .= ', '.$this->db->idate(dol_now());
		$sql .= ', '.(!empty($data['fk_user']) ? ((int) $data['fk_user']) : 'NULL');
		$sql .= ", '".$this->db->escape((string) ($data['tool_name'] ?? ''))."'";
		$endpoint = $data['endpoint'] ?? null;
		$sql .= ', '.($endpoint !== null && $endpoint !== '' ? "'".$this->db->escape((string) $endpoint)."'" : 'NULL');
		$sql .= ', '.($input !== null && $input !== '' ? "'".$this->db->escape((string) $input)."'" : 'NULL');
		$sql .= ", '".$this->db->escape((string) ($data['output_status'] ?? 'OK'))."'";
		$sql .= ', '.(isset($data['http_code']) ? ((int) $data['http_code']) : 'NULL');
		$sql .= ', '.(!empty($data['error_message']) ? "'".$this->db->escape($this->redactString((string) $data['error_message']))."'" : 'NULL');
		$sql .= ', '.(!empty($data['ip']) ? "'".$this->db->escape((string) $data['ip'])."'" : 'NULL');
		$sql .= ', '.(!empty($data['user_agent']) ? "'".$this->db->escape(substr((string) $data['user_agent'], 0, 255))."'" : 'NULL');
		$sql .= ', '.(isset($data['duration_ms']) ? ((int) $data['duration_ms']) : 'NULL');
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Fetch logs.
	 *
	 * @param array<string,mixed> $filters Filters
	 * @param int                 $limit   Limit
	 * @param int                 $offset  Offset
	 * @return array<int,object>|int
	 */
	public function fetchAll(array $filters = array(), $limit = 50, $offset = 0)
	{
		if ($this->ensureTable() < 0) {
			return -1;
		}

		$sql = 'SELECT l.rowid, l.entity, l.datec, l.fk_user, l.tool_name, l.endpoint, l.input_json, l.output_status, l.http_code, l.error_message, l.ip, l.user_agent, l.duration_ms, u.login';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'mcpconnector_log as l';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON u.rowid = l.fk_user';
		$sql .= ' WHERE 1 = 1';

		if (!empty($filters['date_start'])) {
			$sql .= " AND l.datec >= '".$this->db->escape($filters['date_start'])." 00:00:00'";
		}
		if (!empty($filters['date_end'])) {
			$sql .= " AND l.datec <= '".$this->db->escape($filters['date_end'])." 23:59:59'";
		}
		if (!empty($filters['tool_name'])) {
			$sql .= " AND l.tool_name = '".$this->db->escape($filters['tool_name'])."'";
		}
		if (!empty($filters['output_status'])) {
			$sql .= " AND l.output_status = '".$this->db->escape($filters['output_status'])."'";
		}
		if (isset($filters['entity']) && $filters['entity'] !== '') {
			$sql .= ' AND l.entity = '.((int) $filters['entity']);
		}
		if (!empty($filters['fk_user'])) {
			$sql .= ' AND l.fk_user = '.((int) $filters['fk_user']);
		}

		$sql .= ' ORDER BY l.datec DESC, l.rowid DESC';
		$sql .= $this->db->plimit($limit, $offset);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$rows = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $obj;
		}

		return $rows;
	}

	/**
	 * Purge old logs.
	 *
	 * @param int $retentionDays Retention in days
	 * @return int
	 */
	public function purgeOld($retentionDays)
	{
		if ($this->ensureTable() < 0) {
			return -1;
		}

		$retentionDays = max(1, (int) $retentionDays);
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'mcpconnector_log';
		$sql .= ' WHERE datec < DATE_SUB(NOW(), INTERVAL '.$retentionDays.' DAY)';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return $this->db->affected_rows($resql);
	}

	/**
	 * Ensure the log table exists for already-enabled installations.
	 *
	 * @return int
	 */
	public function ensureTable()
	{
		$table = MAIN_DB_PREFIX.'mcpconnector_log';
		$resql = $this->db->query("SHOW TABLES LIKE '".$this->db->escape($table)."'");
		if ($resql && $this->db->num_rows($resql) > 0) {
			return 1;
		}

		$sql = 'CREATE TABLE '.$table.' (';
		$sql .= 'rowid integer AUTO_INCREMENT PRIMARY KEY,';
		$sql .= 'entity integer NOT NULL DEFAULT 1,';
		$sql .= 'datec datetime NOT NULL,';
		$sql .= 'tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,';
		$sql .= 'fk_user integer DEFAULT NULL,';
		$sql .= 'tool_name varchar(128) NOT NULL,';
		$sql .= 'endpoint varchar(255) DEFAULT NULL,';
		$sql .= 'input_json mediumtext DEFAULT NULL,';
		$sql .= "output_status varchar(32) NOT NULL DEFAULT 'OK',";
		$sql .= 'http_code integer DEFAULT NULL,';
		$sql .= 'error_message text DEFAULT NULL,';
		$sql .= 'ip varchar(64) DEFAULT NULL,';
		$sql .= 'user_agent varchar(255) DEFAULT NULL,';
		$sql .= 'duration_ms integer DEFAULT NULL';
		$sql .= ') ENGINE=innodb';

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		foreach (array(
			'idx_mcpconnector_log_entity' => 'entity',
			'idx_mcpconnector_log_datec' => 'datec',
			'idx_mcpconnector_log_tool_name' => 'tool_name',
			'idx_mcpconnector_log_status' => 'output_status',
		) as $index => $column) {
			if (!$this->db->query('ALTER TABLE '.$table.' ADD INDEX '.$index.' ('.$column.')')) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Redact sensitive values recursively.
	 *
	 * @param mixed $value Value
	 * @return mixed
	 */
	private function redact($value)
	{
		if (is_array($value)) {
			$redacted = array();
			foreach ($value as $key => $item) {
				if (preg_match('/(api.?key|token|secret|password)/i', (string) $key)) {
					$redacted[$key] = '***';
				} else {
					$redacted[$key] = $this->redact($item);
				}
			}
			return $redacted;
		}

		return $value;
	}

	/**
	 * Redact sensitive strings.
	 *
	 * @param string $value Value
	 * @return string
	 */
	private function redactString($value)
	{
		return preg_replace('/(DOLIBARR_API_KEY|api[_-]?key|token|secret|password)(["\'\s:=]+)[^,"\'}\s]+/i', '$1$2***', $value);
	}
}
