# MCP Connector for Dolibarr

MCP Connector is a Dolibarr external module that exposes a read-only MCP bridge for ChatGPT through a bundled Node.js/TypeScript MCP server.

The repository root is the module root and must be installed as:

```text
htdocs/custom/mcpconnector/
```

## V1 Scope

- Read-only MCP access to Dolibarr data.
- Dolibarr REST endpoints under `/api/index.php/mcpconnector`.
- Bundled MCP server in `mcp-server/`.
- Admin pages for setup, compatibility checks, installation guidance and call logs.
- Downloadable `.env`, systemd, Apache and Nginx examples.
- Multicompany-aware logs and data filters.

V1 does not provide write/delete operations, Docker support, embedded ChatGPT UI, OAuth flow, direct SQL access from the MCP server, or automatic system service startup from PHP.

## Installation

1. Copy this repository to `htdocs/custom/mcpconnector/`.
2. In Dolibarr, open **Home > Setup > Modules/Applications** and enable **MCP Connector**.
3. Grant the required rights to the technical API user:
   - Read data via MCP Connector.
   - Configure MCP Connector, for administrators only.
   - View MCP logs, for administrators only.
4. Open the module setup page and configure:
   - Dolibarr public URL.
   - Dolibarr API URL.
   - Technical API user and API key.
   - Public MCP URL.
   - Node.js port.
   - Enabled read-only tools.

The API key is not displayed again after saving. Generated deployment files containing the key are downloaded by the browser and are not stored in `htdocs`.

## MCP Server

Node.js 20 LTS or newer is recommended on the machine that runs the MCP server.

```bash
cd htdocs/custom/mcpconnector/mcp-server
npm ci
npm run build
node dist/index.js
```

The server reads its configuration from `.env`, generated from Dolibarr as `dolibarr-mcpconnector.env`. Place it next to the server or pass it with `MCP_ENV_FILE`.

## Reverse Proxy

Use the generated Apache or Nginx example as a starting point. The public URL declared to ChatGPT should usually target:

```text
https://mcp.example.com/mcp
```

The local Node.js server exposes:

- `GET /health` for a simple health check.
- `/mcp` for MCP Streamable HTTP traffic.

## Security Notes

- Keep `MCP_CONNECTOR_READONLY` enabled. V1 also enforces read-only behavior in code.
- Never expose the `.env` file publicly.
- Use HTTPS for both Dolibarr and the public MCP URL.
- Use a dedicated Dolibarr API user with only the required read permissions.
- Review logs regularly and configure a retention period appropriate for your organization.
