ALTER TABLE llx_mcpconnector_log ADD INDEX idx_mcpconnector_log_entity (entity);
ALTER TABLE llx_mcpconnector_log ADD INDEX idx_mcpconnector_log_datec (datec);
ALTER TABLE llx_mcpconnector_log ADD INDEX idx_mcpconnector_log_tool_name (tool_name);
ALTER TABLE llx_mcpconnector_log ADD INDEX idx_mcpconnector_log_status (output_status);
