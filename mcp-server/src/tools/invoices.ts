import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod/v4";
import type { ServerConfig } from "../config.js";
import type { DolibarrClient } from "../dolibarrClient.js";
import type { Logger } from "../logger.js";
import { okResult, runReadOnlyTool } from "./shared.js";

export function registerInvoiceTools(server: McpServer, client: DolibarrClient, config: ServerConfig, logger: Logger): void {
	server.registerTool(
		"get_unpaid_invoices",
		{
			title: "Get unpaid invoices",
			description: "List unpaid Dolibarr customer invoices.",
			inputSchema: {
				limit: z.number().int().min(1).max(20).default(20)
			}
		},
		async ({ limit }) => runReadOnlyTool(config, logger, "get_unpaid_invoices", async () => {
			const data = await client.get<Record<string, unknown>>("/mcpconnector/invoices/unpaid", { limit });
			const count = Array.isArray(data.items) ? data.items.length : 0;
			return okResult(`${count} unpaid invoice record(s) found.`, data);
		})
	);
}
