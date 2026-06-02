import type { CallToolResult } from "@modelcontextprotocol/sdk/types.js";
import type { ServerConfig } from "../config.js";
import { DolibarrHttpError } from "../dolibarrClient.js";
import { Logger, redact } from "../logger.js";

export type ToolHandler = () => Promise<CallToolResult>;

export async function runReadOnlyTool(config: ServerConfig, logger: Logger, name: string, handler: ToolHandler): Promise<CallToolResult> {
	if (!config.allowedTools.has(name)) {
		return errorResult(`Tool '${name}' is disabled by MCP_ALLOWED_TOOLS.`);
	}

	try {
		return await handler();
	} catch (error) {
		if (error instanceof DolibarrHttpError) {
			logger.warn("Dolibarr API error", {
				tool: name,
				status: error.status,
				body: error.responseBody.slice(0, 500)
			});
			return errorResult(`Dolibarr API error ${error.status}.`);
		}

		logger.error("Tool execution failed", { tool: name, error: redact(error instanceof Error ? error.message : error) });
		return errorResult("The tool failed with an internal error.");
	}
}

export function okResult(text: string, structuredContent: Record<string, unknown>): CallToolResult {
	return {
		content: [{ type: "text", text }],
		structuredContent
	};
}

export function errorResult(text: string): CallToolResult {
	return {
		isError: true,
		content: [{ type: "text", text }]
	};
}
