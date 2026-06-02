import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod/v4";
import type { ServerConfig } from "../config.js";
import type { DolibarrClient } from "../dolibarrClient.js";
import type { Logger } from "../logger.js";
import { okResult, runReadOnlyTool } from "./shared.js";

export function registerThirdpartyTools(server: McpServer, client: DolibarrClient, config: ServerConfig, logger: Logger): void {
	server.registerTool(
		"search_thirdparties",
		{
			title: "Search thirdparties",
			description: "Search Dolibarr thirdparties by name, customer code or email.",
			inputSchema: {
				query: z.string().default(""),
				limit: z.number().int().min(1).max(20).default(10)
			}
		},
		async ({ query, limit }) => runReadOnlyTool(config, logger, "search_thirdparties", async () => {
			const data = await client.get<Record<string, unknown>>("/mcpconnector/thirdparties/search", { query, limit });
			const count = Array.isArray(data.items) ? data.items.length : 0;
			return okResult(`${count} thirdparty record(s) found.`, data);
		})
	);

	server.registerTool(
		"get_thirdparty_summary",
		{
			title: "Get thirdparty summary",
			description: "Get a read-only summary for one Dolibarr thirdparty.",
			inputSchema: {
				id: z.number().int().positive()
			}
		},
		async ({ id }) => runReadOnlyTool(config, logger, "get_thirdparty_summary", async () => {
			const data = await client.get<Record<string, unknown>>(`/mcpconnector/thirdparties/${id}/summary`);
			return okResult(`Thirdparty summary for ID ${id}.`, data);
		})
	);
}
