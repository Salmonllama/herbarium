"use client";

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie
    .split("; ")
    .find((row) => row.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.split("=")[1]) : null;
}

let csrfFetched = false;

export async function ensureCsrfCookie(): Promise<void> {
  if (csrfFetched && getCookie("XSRF-TOKEN")) return;
  await fetch("/sanctum/csrf-cookie", {
    method: "GET",
    credentials: "include",
  });
  csrfFetched = true;
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit & { json?: unknown } = {},
): Promise<T> {
  const method = (options.method || "GET").toUpperCase();
  const isMutating = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (isMutating) {
    await ensureCsrfCookie();
  }

  const headers = new Headers(options.headers || {});
  headers.set("Accept", "application/json");

  if (isMutating) {
    const xsrf = getCookie("XSRF-TOKEN");
    if (xsrf) {
      headers.set("X-XSRF-TOKEN", xsrf);
    }
  }

  let body = options.body;
  if (options.json !== undefined) {
    headers.set("Content-Type", "application/json");
    body = JSON.stringify(options.json);
  }

  const res = await fetch(path, {
    ...options,
    method,
    headers,
    body,
    credentials: "include",
  });

  if (!res.ok) {
    let message = `Request failed (${res.status})`;
    let errors: Record<string, string[]> | undefined;
    try {
      const payload = await res.json();
      if (payload?.message) message = payload.message;
      if (payload?.errors) errors = payload.errors;
    } catch {
      // ignore non-json error body
    }
    throw new ApiError(message, res.status, errors);
  }

  if (res.status === 204) {
    return undefined as T;
  }

  return res.json() as Promise<T>;
}

export async function apiDownloadBlob(
  path: string,
  options: RequestInit & { json?: unknown } = {},
  fallbackFilename = "download",
): Promise<void> {
  const method = (options.method || "GET").toUpperCase();
  const isMutating = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (isMutating) {
    await ensureCsrfCookie();
  }

  const headers = new Headers(options.headers || {});
  if (isMutating) {
    const xsrf = getCookie("XSRF-TOKEN");
    if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);
  }

  let body = options.body;
  if (options.json !== undefined) {
    headers.set("Content-Type", "application/json");
    body = JSON.stringify(options.json);
  }

  const res = await fetch(path, {
    ...options,
    method,
    headers,
    body,
    credentials: "include",
  });

  if (!res.ok) {
    let message = `Download failed (${res.status})`;
    try {
      const payload = await res.json();
      if (payload?.message) message = payload.message;
    } catch {
      // ignore
    }
    throw new ApiError(message, res.status);
  }

  const blob = await res.blob();
  const disposition = res.headers.get("Content-Disposition") || "";
  const match = disposition.match(/filename="?([^"]+)"?/i);
  const filename = match?.[1] || fallbackFilename;

  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}
