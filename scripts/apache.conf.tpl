<VirtualHost *:443>
	ServerName {{MCP_DOMAIN}}

	SSLEngine on
	SSLCertificateFile {{SSL_CERTIFICATE}}
	SSLCertificateKeyFile {{SSL_CERTIFICATE_KEY}}

	ProxyPreserveHost On
	ProxyPass / http://127.0.0.1:{{MCP_PORT}}/
	ProxyPassReverse / http://127.0.0.1:{{MCP_PORT}}/
</VirtualHost>
