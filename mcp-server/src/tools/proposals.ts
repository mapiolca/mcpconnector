import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod/v4";
import type { ServerConfig } from "../config.js";
import type { DolibarrClient } from "../dolibarrClient.js";
import type { Logger } from "../logger.js";
import { okResult, runReadOnlyTool } from "./shared.js";

export function registerProposalTools(server: McpServer, client: DolibarrClient, config: ServerConfig, logger: Logger): void {
	server.registerTool(
		"search_proposals",
		{
			title: "Search proposals",
			description: "Search Dolibarr commercial proposals.",
			inputSchema: {
				query: z.string().default(""),
				status: z.enum(["", "draft", "validated", "signed", "not_signed", "billed"]).default(""),
				limit: z.number().int().min(1).max(20).default(10)
			}
		},
		async ({ query, status, limit }) => runReadOnlyTool(config, logger, "search_proposals", async () => {
			const data = await client.get<Record<string, unknown>>("/mcpconnector/proposals/search", { query, status, limit });
			const count = Array.isArray(data.items) ? data.items.length : 0;
			return okResult(`${count} proposal record(s) found.`, data);
		})
	);
}
