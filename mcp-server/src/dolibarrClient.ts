import type { ServerConfig } from "./config.js";
import { Logger } from "./logger.js";

export class DolibarrHttpError extends Error {
	readonly status: number;
	readonly responseBody: string;

	constructor(status: number, responseBody: string) {
		super(`Dolibarr API returned HTTP ${status}`);
		this.status = status;
		this.responseBody = responseBody;
	}
}

export class DolibarrClient {
	private readonly config: ServerConfig;
	private readonly logger: Logger;

	constructor(config: ServerConfig, logger: Logger) {
		this.config = config;
		this.logger = logger;
	}

	async get<T>(path: string, params: Record<string, string | number | boolean | undefined> = {}): Promise<T> {
		const url = new URL(`${this.config.dolibarrApiUrl}${path}`);
		url.searchParams.set("entity", String(this.config.dolibarrEntity));
		for (const [key, value] of Object.entries(params)) {
			if (value !== undefined && value !== "") {
				url.searchParams.set(key, String(value));
			}
		}

		const controller = new AbortController();
		const timeout = setTimeout(() => controller.abort(), this.config.httpTimeoutMs);

		try {
			this.logger.debug("Dolibarr API GET", { url: url.toString().replace(this.config.dolibarrApiKey, "***") });
			const response = await fetch(url, {
				method: "GET",
				headers: {
					Accept: "application/json",
					DOLAPIKEY: this.config.dolibarrApiKey
				},
				signal: controller.signal
			});

			const text = await response.text();
			if (!response.ok) {
				throw new DolibarrHttpError(response.status, text);
			}

			if (!text) {
				return {} as T;
			}

			return JSON.parse(text) as T;
		} finally {
			clearTimeout(timeout);
		}
	}
}
