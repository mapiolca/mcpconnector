import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod/v4";
import type { ServerConfig } from "../config.js";
import type { DolibarrClient } from "../dolibarrClient.js";
import type { Logger } from "../logger.js";
import { okResult, runReadOnlyTool } from "./shared.js";

export function registerPowerplantPvTools(server: McpServer, client: DolibarrClient, config: ServerConfig, logger: Logger): void {
	server.registerTool(
		"search_powerplants",
		{
			title: "Search powerplants",
			description: "Search Powerplant PV records when the Dolibarr module is available.",
			inputSchema: {
				query: z.string().default(""),
				limit: z.number().int().min(1).max(20).default(10)
			}
		},
		async ({ query, limit }) => runReadOnlyTool(config, logger, "search_powerplants", async () => {
			const data = await client.get<Record<string, unknown>>("/mcpconnector/powerplants/search", { query, limit });
			const count = Array.isArray(data.items) ? data.items.length : 0;
			return okResult(`${count} powerplant record(s) found.`, data);
		})
	);

	server.registerTool(
		"get_powerplantpv_summary",
		{
			title: "Get Powerplant PV summary",
			description: "Get a Powerplant PV summary when the Dolibarr module is available.",
			inputSchema: {
				id: z.number().int().positive()
			}
		},
		async ({ id }) => runReadOnlyTool(config, logger, "get_powerplantpv_summary", async () => {
			const data = await client.get<Record<string, unknown>>(`/mcpconnector/powerplants/${id}/summary`);
			return okResult(`Powerplant PV summary for ID ${id}.`, data);
		})
	);
}
