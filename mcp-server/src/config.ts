import dotenv from "dotenv";
import { z } from "zod/v4";

dotenv.config({ path: process.env.MCP_ENV_FILE || ".env" });

const envSchema = z.object({
	DOLIBARR_BASE_URL: z.string().url(),
	DOLIBARR_API_URL: z.string().url(),
	DOLIBARR_API_KEY: z.string().min(1),
	DOLIBARR_ENTITY: z.coerce.number().int().positive().default(1),
	MCP_PUBLIC_URL: z.string().url().optional(),
	MCP_PORT: z.coerce.number().int().positive().default(3001),
	MCP_HOST: z.string().default("127.0.0.1"),
	MCP_READ_ONLY: z.string().default("true"),
	MCP_LOG_LEVEL: z.enum(["debug", "info", "warn", "error"]).default("info"),
	MCP_ALLOWED_TOOLS: z.string().default(""),
	MCP_HTTP_TIMEOUT_MS: z.coerce.number().int().positive().default(10000)
});

export type ServerConfig = {
	dolibarrBaseUrl: string;
	dolibarrApiUrl: string;
	dolibarrApiKey: string;
	dolibarrEntity: number;
	mcpPublicUrl: string;
	port: number;
	host: string;
	readOnly: true;
	logLevel: "debug" | "info" | "warn" | "error";
	allowedTools: Set<string>;
	httpTimeoutMs: number;
};

export function loadConfig(): ServerConfig {
	const parsed = envSchema.parse(process.env);
	const requestedReadOnly = parsed.MCP_READ_ONLY.toLowerCase() !== "false";

	if (!requestedReadOnly) {
		console.warn("MCP_READ_ONLY=false ignored: V1 is always read-only.");
	}

	return {
		dolibarrBaseUrl: trimTrailingSlash(parsed.DOLIBARR_BASE_URL),
		dolibarrApiUrl: trimTrailingSlash(parsed.DOLIBARR_API_URL),
		dolibarrApiKey: parsed.DOLIBARR_API_KEY,
		dolibarrEntity: parsed.DOLIBARR_ENTITY,
		mcpPublicUrl: trimTrailingSlash(parsed.MCP_PUBLIC_URL || ""),
		port: parsed.MCP_PORT,
		host: parsed.MCP_HOST,
		readOnly: true,
		logLevel: parsed.MCP_LOG_LEVEL,
		allowedTools: new Set(parseAllowedTools(parsed.MCP_ALLOWED_TOOLS)),
		httpTimeoutMs: parsed.MCP_HTTP_TIMEOUT_MS
	};
}

function parseAllowedTools(raw: string): string[] {
	const value = raw.trim();
	if (!value) {
		return [];
	}

	if (value.startsWith("[")) {
		const decoded = JSON.parse(value);
		if (Array.isArray(decoded)) {
			return decoded.map(String).map((tool) => tool.trim()).filter(Boolean);
		}
	}

	return value.split(",").map((tool) => tool.trim()).filter(Boolean);
}

function trimTrailingSlash(value: string): string {
	return value.replace(/\/+$/, "");
}
