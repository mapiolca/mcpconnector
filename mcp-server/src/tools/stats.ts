import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod/v4";
import type { ServerConfig } from "../config.js";
import type { DolibarrClient } from "../dolibarrClient.js";
import type { Logger } from "../logger.js";
import { okResult, runReadOnlyTool } from "./shared.js";

export function registerStatsTools(server: McpServer, client: DolibarrClient, config: ServerConfig, logger: Logger): void {
	server.registerTool(
		"get_turnover_stats",
		{
			title: "Get turnover statistics",
			description: "Get Dolibarr turnover statistics for a supported period.",
			inputSchema: {
				period: z.enum(["current_month", "previous_month", "current_year", "previous_year", "last_12_months"]).default("current_year")
			}
		},
		async ({ period }) => runReadOnlyTool(config, logger, "get_turnover_stats", async () => {
			const data = await client.get<Record<string, unknown>>("/mcpconnector/stats/turnover", { period });
			return okResult(`Turnover statistics for ${period}.`, data);
		})
	);
}
