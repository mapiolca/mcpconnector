import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod/v4";
import type { ServerConfig } from "../config.js";
import type { DolibarrClient } from "../dolibarrClient.js";
import type { Logger } from "../logger.js";
import { okResult, runReadOnlyTool } from "./shared.js";

export function registerProjectTools(server: McpServer, client: DolibarrClient, config: ServerConfig, logger: Logger): void {
	server.registerTool(
		"search_projects",
		{
			title: "Search projects",
			description: "Search Dolibarr projects by reference, title or thirdparty.",
			inputSchema: {
				query: z.string().default(""),
				limit: z.number().int().min(1).max(20).default(10)
			}
		},
		async ({ query, limit }) => runReadOnlyTool(config, logger, "search_projects", async () => {
			const data = await client.get<Record<string, unknown>>("/mcpconnector/projects/search", { query, limit });
			const count = Array.isArray(data.items) ? data.items.length : 0;
			return okResult(`${count} project record(s) found.`, data);
		})
	);

	server.registerTool(
		"get_project_summary",
		{
			title: "Get project summary",
			description: "Get a read-only summary for one Dolibarr project.",
			inputSchema: {
				id: z.number().int().positive()
			}
		},
		async ({ id }) => runReadOnlyTool(config, logger, "get_project_summary", async () => {
			const data = await client.get<Record<string, unknown>>(`/mcpconnector/projects/${id}/summary`);
			return okResult(`Project summary for ID ${id}.`, data);
		})
	);
}
