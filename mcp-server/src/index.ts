import type { Request, Response } from "express";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { createMcpExpressApp } from "@modelcontextprotocol/sdk/server/express.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { loadConfig, type ServerConfig } from "./config.js";
import { DolibarrClient } from "./dolibarrClient.js";
import { Logger } from "./logger.js";
import { registerThirdpartyTools } from "./tools/thirdparties.js";
import { registerProjectTools } from "./tools/projects.js";
import { registerProposalTools } from "./tools/proposals.js";
import { registerInvoiceTools } from "./tools/invoices.js";
import { registerStatsTools } from "./tools/stats.js";
import { registerPowerplantPvTools } from "./tools/powerplantpv.js";

const config = loadConfig();
const logger = new Logger(config);

function createServer(config: ServerConfig, logger: Logger): McpServer {
	const server = new McpServer(
		{
			name: "dolibarr-mcpconnector",
			version: "1.0.0"
		},
		{
			capabilities: {
				logging: {}
			}
		}
	);

	const client = new DolibarrClient(config, logger);
	registerThirdpartyTools(server, client, config, logger);
	registerProjectTools(server, client, config, logger);
	registerProposalTools(server, client, config, logger);
	registerInvoiceTools(server, client, config, logger);
	registerStatsTools(server, client, config, logger);
	registerPowerplantPvTools(server, client, config, logger);

	return server;
}

const app = createMcpExpressApp({ host: config.host });

app.get("/health", (_req: Request, res: Response) => {
	res.json({
		status: "ok",
		readonly: true,
		name: "dolibarr-mcpconnector",
		version: "1.0.0"
	});
});

app.post("/mcp", async (req: Request, res: Response) => {
	const server = createServer(config, logger);
	const transport = new StreamableHTTPServerTransport({
		sessionIdGenerator: undefined
	});

	res.on("close", () => {
		transport.close();
		server.close();
	});

	try {
		await server.connect(transport);
		await transport.handleRequest(req, res, req.body);
	} catch (error) {
		logger.error("MCP request failed", error instanceof Error ? error.message : error);
		if (!res.headersSent) {
			res.status(500).json({
				jsonrpc: "2.0",
				error: {
					code: -32603,
					message: "Internal server error"
				},
				id: null
			});
		}
	}
});

function methodNotAllowed(_req: Request, res: Response): void {
	res.status(405).json({
		jsonrpc: "2.0",
		error: {
			code: -32000,
			message: "Method not allowed."
		},
		id: null
	});
}

app.get("/mcp", methodNotAllowed);
app.put("/mcp", methodNotAllowed);
app.patch("/mcp", methodNotAllowed);
app.delete("/mcp", methodNotAllowed);

app.listen(config.port, config.host, () => {
	logger.info("Dolibarr MCP Connector server started", {
		host: config.host,
		port: config.port,
		publicUrl: config.mcpPublicUrl || null,
		allowedTools: [...config.allowedTools],
		readonly: true
	});
});
