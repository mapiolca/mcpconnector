# Dolibarr MCP Connector Server

This Node.js server exposes MCP tools for the Dolibarr `mcpconnector` module. It does not access the database directly; every tool calls the Dolibarr REST API.

## Requirements

- Node.js 20 LTS or newer.
- A `.env` file generated from the Dolibarr module setup page.

## Commands

```bash
npm ci
npm run build
npm run start
```

For development:

```bash
npm run dev
```

## Endpoints

- `GET /health`: local health check.
- `POST /mcp`: MCP Streamable HTTP endpoint.

The server is intended to run behind Apache or Nginx with HTTPS.
