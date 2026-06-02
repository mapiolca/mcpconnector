[Unit]
Description=Dolibarr MCP Connector
After=network.target

[Service]
Type=simple
WorkingDirectory={{MCP_SERVER_PATH}}
ExecStart={{NODE_BIN}} dist/index.js
Restart=always
RestartSec=5
EnvironmentFile={{ENV_FILE_PATH}}

[Install]
WantedBy=multi-user.target
