CREATE TABLE llx_mcpconnector_log (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL DEFAULT 1,
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user integer DEFAULT NULL,
	tool_name varchar(128) NOT NULL,
	endpoint varchar(255) DEFAULT NULL,
	input_json mediumtext DEFAULT NULL,
	output_status varchar(32) NOT NULL DEFAULT 'OK',
	http_code integer DEFAULT NULL,
	error_message text DEFAULT NULL,
	ip varchar(64) DEFAULT NULL,
	user_agent varchar(255) DEFAULT NULL,
	duration_ms integer DEFAULT NULL
) ENGINE=innodb;
