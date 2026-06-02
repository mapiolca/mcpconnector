import type { ServerConfig } from "./config.js";

type LogLevel = "debug" | "info" | "warn" | "error";

const order: Record<LogLevel, number> = {
	debug: 10,
	info: 20,
	warn: 30,
	error: 40
};

export class Logger {
	private readonly minLevel: LogLevel;

	constructor(config: ServerConfig) {
		this.minLevel = config.logLevel;
	}

	debug(message: string, data?: unknown): void {
		this.write("debug", message, data);
	}

	info(message: string, data?: unknown): void {
		this.write("info", message, data);
	}

	warn(message: string, data?: unknown): void {
		this.write("warn", message, data);
	}

	error(message: string, data?: unknown): void {
		this.write("error", message, data);
	}

	private write(level: LogLevel, message: string, data?: unknown): void {
		if (order[level] < order[this.minLevel]) {
			return;
		}

		const payload = {
			time: new Date().toISOString(),
			level,
			message,
			data: redact(data)
		};

		const line = JSON.stringify(payload);
		if (level === "error") {
			console.error(line);
		} else if (level === "warn") {
			console.warn(line);
		} else {
			console.log(line);
		}
	}
}

export function redact(value: unknown): unknown {
	if (Array.isArray(value)) {
		return value.map(redact);
	}
	if (value && typeof value === "object") {
		const output: Record<string, unknown> = {};
		for (const [key, item] of Object.entries(value)) {
			if (/(api.?key|token|secret|password)/i.test(key)) {
				output[key] = "***";
			} else {
				output[key] = redact(item);
			}
		}
		return output;
	}
	if (typeof value === "string") {
		return value.replace(/(DOLIBARR_API_KEY|api[_-]?key|token|secret|password)(["'\s:=]+)[^,"'}\s]+/gi, "$1$2***");
	}
	return value;
}
