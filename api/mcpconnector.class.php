<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once DOL_DOCUMENT_ROOT.'/api/class/ApiAccess.class.php';
dol_include_once('/mcpconnector/lib/mcpconnector.lib.php');
dol_include_once('/mcpconnector/class/mcpconnector.class.php');
dol_include_once('/mcpconnector/class/mcpconnectorlog.class.php');

/**
 * MCP Connector REST API.
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class McpConnectorApi extends DolibarrApi
{
	/** @var DoliDB */
	public $db;

	/** @var User */
	public $user;

	/** @var array<string,mixed>|null */
	private $currentContext = null;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $db, $user;

		$this->db = $db;
		$this->user = $user;
	}

	/**
	 * Connector status.
	 *
	 * @url GET /status
	 *
	 * @return array<string,mixed>
	 */
	public function status()
	{
		$ctx = $this->enter('status');
		$data = array(
			'enabled' => true,
			'readonly' => true,
			'entity' => $this->getCurrentEntity(),
			'version' => McpConnector::VERSION,
			'dolibarr_version' => defined('DOL_VERSION') ? DOL_VERSION : '',
			'php_version' => PHP_VERSION,
			'available_tools' => mcpconnector_get_allowed_tools(mcpconnector_powerplantpv_available()),
		);
		$this->leave($ctx, array());
		return $data;
	}

	/**
	 * Tool catalog.
	 *
	 * @url GET /tools
	 *
	 * @return array<string,mixed>
	 */
	public function tools()
	{
		$ctx = $this->enter('tools');
		$data = array('tools' => McpConnector::getTools());
		$this->leave($ctx, array());
		return $data;
	}

	/**
	 * Search thirdparties.
	 *
	 * @url GET /thirdparties/search
	 *
	 * @param string $query Search string
	 * @param int    $limit Limit
	 * @return array<string,mixed>
	 */
	public function searchThirdparties($query = '', $limit = 10)
	{
		$ctx = $this->enter('search_thirdparties', 'societe', 'lire');
		$limit = $this->cleanLimit($limit, 20);
		$query = trim((string) $query);

		$sql = 'SELECT t.rowid, t.nom as name, t.code_client, t.email, t.status';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'societe as t';
		$sql .= ' WHERE t.entity IN ('.mcpconnector_get_entity_filter($this->db, 'societe').')';
		if ($query !== '') {
			$like = $this->db->escape('%'.$query.'%');
			$sql .= " AND (t.nom LIKE '".$like."' OR t.code_client LIKE '".$like."' OR t.email LIKE '".$like."')";
		}
		if (!empty($this->user->socid)) {
			$sql .= ' AND t.rowid = '.((int) $this->user->socid);
		}
		$sql .= ' ORDER BY t.nom ASC';
		$sql .= $this->db->plimit($limit);

		$items = $this->fetchRows($sql, function ($obj) {
			return array(
				'id' => (int) $obj->rowid,
				'name' => $obj->name,
				'code_client' => $obj->code_client,
				'email' => $obj->email,
				'status' => ((int) $obj->status === 1) ? 'active' : 'inactive',
			);
		});

		$this->leave($ctx, array('query' => $query, 'limit' => $limit));
		return array('items' => $items);
	}

	/**
	 * Get thirdparty summary.
	 *
	 * @url GET /thirdparties/{id}/summary
	 *
	 * @param int $id Thirdparty ID
	 * @return array<string,mixed>
	 */
	public function getThirdpartySummary($id)
	{
		$ctx = $this->enter('get_thirdparty_summary', 'societe', 'lire');
		$id = (int) $id;

		$sql = 'SELECT t.rowid, t.nom as name, t.code_client, t.email, t.phone, t.address';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'societe as t';
		$sql .= ' WHERE t.rowid = '.$id;
		$sql .= ' AND t.entity IN ('.mcpconnector_get_entity_filter($this->db, 'societe').')';
		if (!empty($this->user->socid)) {
			$sql .= ' AND t.rowid = '.((int) $this->user->socid);
		}

		$obj = $this->fetchOne($sql);
		if (!$obj) {
			$this->deny($ctx, 404, 'THIRDPARTY_NOT_FOUND');
		}

		$data = array(
			'id' => (int) $obj->rowid,
			'name' => $obj->name,
			'code_client' => $obj->code_client,
			'email' => $obj->email,
			'phone' => $obj->phone,
			'address' => $obj->address,
			'projects_count' => $this->countRows('projet', 'fk_soc = '.$id, 'project'),
			'proposals_count' => $this->countRows('propal', 'fk_soc = '.$id, 'propal'),
			'invoices_unpaid_count' => $this->countUnpaidInvoices('fk_soc = '.$id),
			'last_activity_date' => $this->lastActivityDate($id, null),
		);

		$this->leave($ctx, array('id' => $id));
		return $data;
	}

	/**
	 * Search projects.
	 *
	 * @url GET /projects/search
	 *
	 * @param string $query Search string
	 * @param int    $limit Limit
	 * @return array<string,mixed>
	 */
	public function searchProjects($query = '', $limit = 10)
	{
		$ctx = $this->enter('search_projects', 'projet', 'lire');
		$limit = $this->cleanLimit($limit, 20);
		$query = trim((string) $query);

		$sql = 'SELECT p.rowid, p.ref, p.title, p.fk_statut, s.nom as thirdparty_name';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'projet as p';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON s.rowid = p.fk_soc';
		$sql .= ' WHERE p.entity IN ('.mcpconnector_get_entity_filter($this->db, 'project').')';
		if ($query !== '') {
			$like = $this->db->escape('%'.$query.'%');
			$sql .= " AND (p.ref LIKE '".$like."' OR p.title LIKE '".$like."' OR s.nom LIKE '".$like."')";
		}
		if (!empty($this->user->socid)) {
			$sql .= ' AND p.fk_soc = '.((int) $this->user->socid);
		}
		$sql .= ' ORDER BY p.ref DESC';
		$sql .= $this->db->plimit($limit);

		$items = $this->fetchRows($sql, function ($obj) {
			return array(
				'id' => (int) $obj->rowid,
				'ref' => $obj->ref,
				'title' => $obj->title,
				'thirdparty_name' => $obj->thirdparty_name,
				'status' => ((int) $obj->fk_statut === 1) ? 'open' : 'draft_or_closed',
			);
		});

		$this->leave($ctx, array('query' => $query, 'limit' => $limit));
		return array('items' => $items);
	}

	/**
	 * Get project summary.
	 *
	 * @url GET /projects/{id}/summary
	 *
	 * @param int $id Project ID
	 * @return array<string,mixed>
	 */
	public function getProjectSummary($id)
	{
		$ctx = $this->enter('get_project_summary', 'projet', 'lire');
		$id = (int) $id;

		$sql = 'SELECT p.rowid, p.ref, p.title, p.fk_statut, p.fk_soc, s.nom as thirdparty_name';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'projet as p';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON s.rowid = p.fk_soc';
		$sql .= ' WHERE p.rowid = '.$id;
		$sql .= ' AND p.entity IN ('.mcpconnector_get_entity_filter($this->db, 'project').')';
		if (!empty($this->user->socid)) {
			$sql .= ' AND p.fk_soc = '.((int) $this->user->socid);
		}

		$obj = $this->fetchOne($sql);
		if (!$obj) {
			$this->deny($ctx, 404, 'PROJECT_NOT_FOUND');
		}

		$data = array(
			'id' => (int) $obj->rowid,
			'ref' => $obj->ref,
			'title' => $obj->title,
			'thirdparty' => array(
				'id' => (int) $obj->fk_soc,
				'name' => $obj->thirdparty_name,
			),
			'status' => ((int) $obj->fk_statut === 1) ? 'open' : 'draft_or_closed',
			'proposals' => $this->projectProposalStats($id),
			'invoices' => $this->projectInvoiceStats($id),
			'last_activity_date' => $this->lastActivityDate((int) $obj->fk_soc, $id),
		);

		$this->leave($ctx, array('id' => $id));
		return $data;
	}

	/**
	 * Search proposals.
	 *
	 * @url GET /proposals/search
	 *
	 * @param string $query  Search string
	 * @param string $status Status
	 * @param int    $limit  Limit
	 * @return array<string,mixed>
	 */
	public function searchProposals($query = '', $status = '', $limit = 10)
	{
		$ctx = $this->enter('search_proposals', 'propal', 'lire');
		$limit = $this->cleanLimit($limit, 20);
		$query = trim((string) $query);
		$status = trim((string) $status);

		$sql = 'SELECT pr.rowid, pr.ref, pr.fk_statut, pr.total_ht, pr.datep, s.nom as thirdparty_name, p.ref as project_ref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'propal as pr';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON s.rowid = pr.fk_soc';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'projet as p ON p.rowid = pr.fk_project';
		$sql .= ' WHERE pr.entity IN ('.mcpconnector_get_entity_filter($this->db, 'propal').')';
		if ($query !== '') {
			$like = $this->db->escape('%'.$query.'%');
			$sql .= " AND (pr.ref LIKE '".$like."' OR s.nom LIKE '".$like."' OR p.ref LIKE '".$like."')";
		}
		if ($status !== '') {
			$statusMap = array('draft' => 0, 'validated' => 1, 'signed' => 2, 'not_signed' => 3, 'billed' => 4);
			if (isset($statusMap[$status])) {
				$sql .= ' AND pr.fk_statut = '.$statusMap[$status];
			}
		}
		if (!empty($this->user->socid)) {
			$sql .= ' AND pr.fk_soc = '.((int) $this->user->socid);
		}
		$sql .= ' ORDER BY pr.datep DESC, pr.ref DESC';
		$sql .= $this->db->plimit($limit);

		$items = $this->fetchRows($sql, function ($obj) {
			return array(
				'id' => (int) $obj->rowid,
				'ref' => $obj->ref,
				'thirdparty_name' => $obj->thirdparty_name,
				'project_ref' => $obj->project_ref,
				'status' => $this->proposalStatus((int) $obj->fk_statut),
				'total_ht' => (float) $obj->total_ht,
				'date' => $this->formatDate($obj->datep),
			);
		});

		$this->leave($ctx, array('query' => $query, 'status' => $status, 'limit' => $limit));
		return array('items' => $items);
	}

	/**
	 * List unpaid invoices.
	 *
	 * @url GET /invoices/unpaid
	 *
	 * @param int $limit Limit
	 * @return array<string,mixed>
	 */
	public function getUnpaidInvoices($limit = 20)
	{
		$ctx = $this->enter('get_unpaid_invoices', 'facture', 'lire');
		$limit = $this->cleanLimit($limit, 20);

		$paidSubquery = 'SELECT pf.fk_facture, SUM(pf.amount) as amount FROM '.MAIN_DB_PREFIX.'paiement_facture as pf GROUP BY pf.fk_facture';
		$sql = 'SELECT f.rowid, f.ref, f.total_ttc, f.date_lim_reglement, s.nom as thirdparty_name, COALESCE(pay.amount, 0) as paid_amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'facture as f';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON s.rowid = f.fk_soc';
		$sql .= ' LEFT JOIN ('.$paidSubquery.') as pay ON pay.fk_facture = f.rowid';
		$sql .= ' WHERE f.entity IN ('.mcpconnector_get_entity_filter($this->db, 'invoice').')';
		$sql .= ' AND f.fk_statut > 0 AND f.paye = 0';
		if (!empty($this->user->socid)) {
			$sql .= ' AND f.fk_soc = '.((int) $this->user->socid);
		}
		$sql .= ' ORDER BY f.date_lim_reglement ASC, f.ref ASC';
		$sql .= $this->db->plimit($limit);

		$total = 0.0;
		$items = $this->fetchRows($sql, function ($obj) use (&$total) {
			$remain = max(0, (float) $obj->total_ttc - (float) $obj->paid_amount);
			$total += $remain;
			return array(
				'id' => (int) $obj->rowid,
				'ref' => $obj->ref,
				'thirdparty_name' => $obj->thirdparty_name,
				'total_ttc' => (float) $obj->total_ttc,
				'remain_to_pay' => $remain,
				'due_date' => $this->formatDate($obj->date_lim_reglement),
				'late_days' => $this->lateDays($obj->date_lim_reglement),
			);
		});

		$this->leave($ctx, array('limit' => $limit));
		return array('items' => $items, 'total_ttc' => $total);
	}

	/**
	 * Turnover stats.
	 *
	 * @url GET /stats/turnover
	 *
	 * @param string $period Period code
	 * @return array<string,mixed>
	 */
	public function getTurnoverStats($period = 'current_year')
	{
		$ctx = $this->enter('get_turnover_stats', 'facture', 'lire');
		$period = in_array($period, array('current_month', 'previous_month', 'current_year', 'previous_year', 'last_12_months'), true) ? $period : 'current_year';
		$range = $this->periodRange($period);

		$data = array(
			'period' => $period,
			'total_signed_proposals_ht' => $this->sumProposals($range['start'], $range['end']),
			'total_invoiced_ht' => $this->sumInvoices($range['start'], $range['end'], false),
			'total_paid_ttc' => $this->sumInvoices($range['start'], $range['end'], true),
			'currency' => getDolGlobalString('MAIN_MONNAIE', 'EUR'),
		);

		$this->leave($ctx, array('period' => $period));
		return $data;
	}

	/**
	 * Search Powerplant PV records.
	 *
	 * @url GET /powerplants/search
	 *
	 * @param string $query Search string
	 * @param int    $limit Limit
	 * @return array<string,mixed>
	 */
	public function searchPowerplants($query = '', $limit = 10)
	{
		$ctx = $this->enter('search_powerplants');
		$this->requirePowerplantPv($ctx);
		$limit = $this->cleanLimit($limit, 20);
		$table = $this->detectPowerplantTable();
		if ($table === '') {
			$this->deny($ctx, 503, 'POWERPLANTPV_TABLE_NOT_FOUND');
		}

		$columns = $this->describeTable($table);
		$nameColumn = $this->firstAvailableColumn($columns, array('ref', 'label', 'title', 'name'));
		$sql = 'SELECT rowid'.($nameColumn ? ', '.$nameColumn.' as label' : '');
		$sql .= ' FROM '.MAIN_DB_PREFIX.$table.' WHERE 1 = 1';
		if (in_array('entity', $columns, true)) {
			$sql .= ' AND entity IN ('.mcpconnector_get_entity_filter($this->db, $table).')';
		}
		if ($query !== '' && $nameColumn) {
			$sql .= " AND ".$nameColumn." LIKE '".$this->db->escape('%'.$query.'%')."'";
		}
		$sql .= ' ORDER BY rowid DESC';
		$sql .= $this->db->plimit($limit);

		$items = $this->fetchRows($sql, function ($obj) {
			return array(
				'id' => (int) $obj->rowid,
				'label' => property_exists($obj, 'label') ? $obj->label : '',
			);
		});

		$this->leave($ctx, array('query' => $query, 'limit' => $limit));
		return array('items' => $items);
	}

	/**
	 * Get Powerplant PV summary.
	 *
	 * @url GET /powerplants/{id}/summary
	 *
	 * @param int $id Record ID
	 * @return array<string,mixed>
	 */
	public function getPowerplantPvSummary($id)
	{
		$ctx = $this->enter('get_powerplantpv_summary');
		$this->requirePowerplantPv($ctx);
		$table = $this->detectPowerplantTable();
		if ($table === '') {
			$this->deny($ctx, 503, 'POWERPLANTPV_TABLE_NOT_FOUND');
		}
		$columns = $this->describeTable($table);
		$nameColumn = $this->firstAvailableColumn($columns, array('ref', 'label', 'title', 'name'));

		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.$table.' WHERE rowid = '.((int) $id);
		if (in_array('entity', $columns, true)) {
			$sql .= ' AND entity IN ('.mcpconnector_get_entity_filter($this->db, $table).')';
		}
		$obj = $this->fetchOne($sql);
		if (!$obj) {
			$this->deny($ctx, 404, 'POWERPLANTPV_NOT_FOUND');
		}

		$data = array(
			'id' => (int) $obj->rowid,
			'label' => $nameColumn ? $obj->{$nameColumn} : '',
			'raw_summary' => $this->objectToSafeArray($obj, array('api_key', 'token', 'secret', 'password')),
		);

		$this->leave($ctx, array('id' => (int) $id));
		return $data;
	}

	/**
	 * Common endpoint guard.
	 *
	 * @param string $tool       Tool name
	 * @param string $coreModule Core module right name
	 * @param string $coreRight  Core right key
	 * @return array<string,mixed>
	 */
	private function enter($tool, $coreModule = '', $coreRight = '')
	{
		global $conf;

		$ctx = array(
			'tool' => $tool,
			'start' => microtime(true),
			'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
			'entity' => (int) $conf->entity,
		);

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
			$this->deny($ctx, 405, 'METHOD_NOT_ALLOWED_READONLY');
		}
		if (!isModEnabled('mcpconnector') || !getDolGlobalInt('MCP_CONNECTOR_ENABLED', 0)) {
			$this->deny($ctx, 403, 'MCP_CONNECTOR_DISABLED');
		}
		if (!mcpconnector_user_has_right($this->user, 'read')) {
			$this->deny($ctx, 403, 'MCP_CONNECTOR_READ_PERMISSION_REQUIRED');
		}
		if ($coreModule !== '' && $coreRight !== '' && !mcpconnector_user_has_core_right($this->user, $coreModule, $coreRight)) {
			$this->deny($ctx, 403, strtoupper($coreModule).'_READ_PERMISSION_REQUIRED');
		}

		$this->currentContext = $ctx;

		return $ctx;
	}

	/**
	 * Log successful endpoint completion.
	 *
	 * @param array<string,mixed> $ctx   Context
	 * @param array<string,mixed> $input Input values
	 * @return void
	 */
	private function leave(array $ctx, array $input)
	{
		$this->writeLog($ctx, $input, 'OK', 200, '');
		$this->currentContext = null;
	}

	/**
	 * Log and throw a REST error.
	 *
	 * @param array<string,mixed> $ctx     Context
	 * @param int                 $code    HTTP code
	 * @param string              $message Error message
	 * @return void
	 *
	 * @throws RestException
	 */
	private function deny(array $ctx, $code, $message)
	{
		$this->writeLog($ctx, array(), 'ERROR', $code, $message);
		$this->currentContext = null;
		throw new RestException($code, $message);
	}

	/**
	 * Write API log.
	 *
	 * @param array<string,mixed> $ctx     Context
	 * @param array<string,mixed> $input   Input values
	 * @param string              $status  Status
	 * @param int                 $code    HTTP code
	 * @param string              $message Error message
	 * @return void
	 */
	private function writeLog(array $ctx, array $input, $status, $code, $message)
	{
		$log = new McpConnectorLog($this->db);
		$log->create(array(
			'entity' => $ctx['entity'],
			'fk_user' => !empty($this->user->id) ? (int) $this->user->id : null,
			'tool_name' => $ctx['tool'],
			'endpoint' => $ctx['endpoint'],
			'input_json' => $input,
			'output_status' => $status,
			'http_code' => $code,
			'error_message' => $message,
			'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
			'duration_ms' => (int) round((microtime(true) - (float) $ctx['start']) * 1000),
		));
	}

	/**
	 * Clean and cap limit.
	 *
	 * @param int $limit Limit
	 * @param int $max   Max
	 * @return int
	 */
	private function cleanLimit($limit, $max)
	{
		return max(1, min((int) $limit, $max));
	}

	/**
	 * Fetch rows and map them.
	 *
	 * @param string   $sql    SQL query
	 * @param callable $mapper Mapper
	 * @return array<int,mixed>
	 */
	private function fetchRows($sql, callable $mapper)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			if ($this->currentContext !== null) {
				$this->writeLog($this->currentContext, array(), 'ERROR', 500, 'SQL_ERROR');
				$this->currentContext = null;
			}
			throw new RestException(500, 'SQL_ERROR');
		}

		$rows = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $mapper($obj);
		}

		return $rows;
	}

	/**
	 * Fetch one row.
	 *
	 * @param string $sql SQL
	 * @return object|null
	 */
	private function fetchOne($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) {
			if ($this->currentContext !== null) {
				$this->writeLog($this->currentContext, array(), 'ERROR', 500, 'SQL_ERROR');
				$this->currentContext = null;
			}
			throw new RestException(500, 'SQL_ERROR');
		}

		return $this->db->fetch_object($resql) ?: null;
	}

	/**
	 * Count rows with entity filter.
	 *
	 * @param string $table   Table without prefix
	 * @param string $where   Additional where clause
	 * @param string $element Entity element
	 * @return int
	 */
	private function countRows($table, $where, $element)
	{
		$sql = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX.$table.' WHERE entity IN ('.mcpconnector_get_entity_filter($this->db, $element).') AND '.$where;
		$obj = $this->fetchOne($sql);
		return $obj ? (int) $obj->nb : 0;
	}

	/**
	 * Count unpaid invoices.
	 *
	 * @param string $where Additional where clause
	 * @return int
	 */
	private function countUnpaidInvoices($where)
	{
		$sql = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX.'facture WHERE entity IN ('.mcpconnector_get_entity_filter($this->db, 'invoice').') AND fk_statut > 0 AND paye = 0 AND '.$where;
		$obj = $this->fetchOne($sql);
		return $obj ? (int) $obj->nb : 0;
	}

	/**
	 * Last activity date.
	 *
	 * @param int      $socid     Thirdparty ID
	 * @param int|null $projectId Project ID
	 * @return string|null
	 */
	private function lastActivityDate($socid, $projectId)
	{
		$conditions = array();
		if ($socid > 0) {
			$conditions[] = 'fk_soc = '.$socid;
		}
		if ($projectId !== null) {
			$conditions[] = 'fk_project = '.$projectId;
		}
		$where = implode(' AND ', $conditions);
		if ($where === '') {
			return null;
		}

		$dates = array();
		$entityElements = array('projet' => 'project', 'propal' => 'propal', 'facture' => 'invoice');
		foreach (array('projet' => 'datec', 'propal' => 'datec', 'facture' => 'datec') as $table => $dateColumn) {
			$sql = 'SELECT MAX('.$dateColumn.') as lastdate FROM '.MAIN_DB_PREFIX.$table.' WHERE '.$where;
			if ($table === 'projet' && $projectId !== null) {
				$sql = 'SELECT MAX(datec) as lastdate FROM '.MAIN_DB_PREFIX.'projet WHERE rowid = '.$projectId;
			}
			$sql .= ' AND entity IN ('.mcpconnector_get_entity_filter($this->db, $entityElements[$table]).')';
			$obj = $this->fetchOne($sql);
			if ($obj && $obj->lastdate) {
				$dates[] = $obj->lastdate;
			}
		}

		if (empty($dates)) {
			return null;
		}

		rsort($dates);
		return $this->formatDate($dates[0]);
	}

	/**
	 * Project proposal stats.
	 *
	 * @param int $projectId Project ID
	 * @return array<string,mixed>
	 */
	private function projectProposalStats($projectId)
	{
		$sql = 'SELECT COUNT(*) as nb, SUM(CASE WHEN fk_statut = 2 THEN 1 ELSE 0 END) as signed_nb, SUM(CASE WHEN fk_statut = 2 THEN total_ht ELSE 0 END) as signed_total';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'propal WHERE entity IN ('.mcpconnector_get_entity_filter($this->db, 'propal').') AND fk_project = '.((int) $projectId);
		$obj = $this->fetchOne($sql);
		return array(
			'count' => $obj ? (int) $obj->nb : 0,
			'signed_count' => $obj ? (int) $obj->signed_nb : 0,
			'total_signed_ht' => $obj ? (float) $obj->signed_total : 0.0,
		);
	}

	/**
	 * Project invoice stats.
	 *
	 * @param int $projectId Project ID
	 * @return array<string,mixed>
	 */
	private function projectInvoiceStats($projectId)
	{
		$sql = 'SELECT COUNT(*) as nb, SUM(CASE WHEN paye = 0 AND fk_statut > 0 THEN 1 ELSE 0 END) as unpaid_nb, SUM(CASE WHEN paye = 0 AND fk_statut > 0 THEN total_ttc ELSE 0 END) as unpaid_total';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'facture WHERE entity IN ('.mcpconnector_get_entity_filter($this->db, 'invoice').') AND fk_project = '.((int) $projectId);
		$obj = $this->fetchOne($sql);
		return array(
			'count' => $obj ? (int) $obj->nb : 0,
			'unpaid_count' => $obj ? (int) $obj->unpaid_nb : 0,
			'unpaid_total_ttc' => $obj ? (float) $obj->unpaid_total : 0.0,
		);
	}

	/**
	 * Proposal status label.
	 *
	 * @param int $status Status ID
	 * @return string
	 */
	private function proposalStatus($status)
	{
		$map = array(0 => 'draft', 1 => 'validated', 2 => 'signed', 3 => 'not_signed', 4 => 'billed');
		return $map[$status] ?? 'unknown';
	}

	/**
	 * Format a SQL date-like value.
	 *
	 * @param mixed $date Date
	 * @return string|null
	 */
	private function formatDate($date)
	{
		if (empty($date)) {
			return null;
		}
		return substr((string) $date, 0, 10);
	}

	/**
	 * Compute late days.
	 *
	 * @param mixed $date Due date
	 * @return int
	 */
	private function lateDays($date)
	{
		if (empty($date)) {
			return 0;
		}
		$due = strtotime((string) $date);
		if ($due === false) {
			return 0;
		}
		$days = floor((time() - $due) / 86400);
		return max(0, (int) $days);
	}

	/**
	 * Period date range.
	 *
	 * @param string $period Period
	 * @return array<string,string>
	 */
	private function periodRange($period)
	{
		$now = new DateTimeImmutable('now');
		if ($period === 'current_month') {
			$start = $now->modify('first day of this month')->format('Y-m-d');
			$end = $now->modify('last day of this month')->format('Y-m-d');
		} elseif ($period === 'previous_month') {
			$start = $now->modify('first day of previous month')->format('Y-m-d');
			$end = $now->modify('last day of previous month')->format('Y-m-d');
		} elseif ($period === 'previous_year') {
			$start = $now->modify('first day of january previous year')->format('Y-m-d');
			$end = $now->modify('last day of december previous year')->format('Y-m-d');
		} elseif ($period === 'last_12_months') {
			$start = $now->modify('-12 months')->format('Y-m-d');
			$end = $now->format('Y-m-d');
		} else {
			$start = $now->modify('first day of january this year')->format('Y-m-d');
			$end = $now->modify('last day of december this year')->format('Y-m-d');
		}

		return array('start' => $start, 'end' => $end);
	}

	/**
	 * Sum signed proposals.
	 *
	 * @param string $start Start date
	 * @param string $end   End date
	 * @return float
	 */
	private function sumProposals($start, $end)
	{
		$sql = 'SELECT SUM(total_ht) as total FROM '.MAIN_DB_PREFIX.'propal';
		$sql .= ' WHERE entity IN ('.mcpconnector_get_entity_filter($this->db, 'propal').') AND fk_statut = 2';
		$sql .= " AND datep >= '".$this->db->escape($start)."' AND datep <= '".$this->db->escape($end)."'";
		$obj = $this->fetchOne($sql);
		return $obj ? (float) $obj->total : 0.0;
	}

	/**
	 * Sum invoices.
	 *
	 * @param string $start    Start date
	 * @param string $end      End date
	 * @param bool   $paidOnly Only paid invoices
	 * @return float
	 */
	private function sumInvoices($start, $end, $paidOnly)
	{
		$field = $paidOnly ? 'total_ttc' : 'total_ht';
		$sql = 'SELECT SUM('.$field.') as total FROM '.MAIN_DB_PREFIX.'facture';
		$sql .= ' WHERE entity IN ('.mcpconnector_get_entity_filter($this->db, 'invoice').') AND fk_statut > 0';
		if ($paidOnly) {
			$sql .= ' AND paye = 1';
		}
		$sql .= " AND datef >= '".$this->db->escape($start)."' AND datef <= '".$this->db->escape($end)."'";
		$obj = $this->fetchOne($sql);
		return $obj ? (float) $obj->total : 0.0;
	}

	/**
	 * Current entity.
	 *
	 * @return int
	 */
	private function getCurrentEntity()
	{
		global $conf;
		return (int) $conf->entity;
	}

	/**
	 * Require Powerplant PV module.
	 *
	 * @param array<string,mixed> $ctx Context
	 * @return void
	 */
	private function requirePowerplantPv(array $ctx)
	{
		if (!mcpconnector_powerplantpv_available()) {
			$this->deny($ctx, 503, 'POWERPLANTPV_MODULE_NOT_AVAILABLE');
		}
	}

	/**
	 * Detect a plausible Powerplant PV main table.
	 *
	 * @return string
	 */
	private function detectPowerplantTable()
	{
		foreach (array('powerplantpv_powerplant', 'powerplantpv_plant', 'powerplantpv_centrale') as $table) {
			$resql = $this->db->query("SHOW TABLES LIKE '".$this->db->escape(MAIN_DB_PREFIX.$table)."'");
			if ($resql && $this->db->num_rows($resql) > 0) {
				return $table;
			}
		}
		return '';
	}

	/**
	 * Describe a table and return column names.
	 *
	 * @param string $table Table without prefix
	 * @return string[]
	 */
	private function describeTable($table)
	{
		$columns = array();
		$resql = $this->db->query('DESCRIBE '.MAIN_DB_PREFIX.$table);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$columns[] = $obj->Field;
			}
		}
		return $columns;
	}

	/**
	 * Return first available column.
	 *
	 * @param string[] $columns    Columns
	 * @param string[] $candidates Candidates
	 * @return string
	 */
	private function firstAvailableColumn(array $columns, array $candidates)
	{
		foreach ($candidates as $candidate) {
			if (in_array($candidate, $columns, true)) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * Convert object to array while redacting sensitive fields.
	 *
	 * @param object   $obj       Object
	 * @param string[] $denyWords Denied field words
	 * @return array<string,mixed>
	 */
	private function objectToSafeArray($obj, array $denyWords)
	{
		$result = array();
		foreach (get_object_vars($obj) as $key => $value) {
			foreach ($denyWords as $word) {
				if (stripos($key, $word) !== false) {
					continue 2;
				}
			}
			$result[$key] = $value;
		}
		return $result;
	}
}
