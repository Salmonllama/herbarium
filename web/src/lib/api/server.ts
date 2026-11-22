import { cookies, headers } from "next/headers";

const INTERNAL_API = (process.env.INTERNAL_API_URL || "http://127.0.0.1:8000").replace(/\/$/, "");

export async function serverApiFetch<T>(path: string): Promise<T | null> {
  try {
    const cookieStore = await cookies();
    const hdrs = await headers();
    const cookieHeader = cookieStore
      .getAll()
      .map((c) => `${c.name}=${c.value}`)
      .join("; ");

    const forwardHost = hdrs.get("x-forwarded-host") || hdrs.get("host") || "";

    const res = await fetch(`${INTERNAL_API}${path}`, {
      method: "GET",
      headers: {
        Accept: "application/json",
        ...(cookieHeader ? { Cookie: cookieHeader } : {}),
        ...(forwardHost ? { "X-Forwarded-Host": forwardHost } : {}),
      },
      cache: "no-store",
    });

    if (!res.ok) {
      return null;
    }

    return (await res.json()) as T;
  } catch {
    return null;
  }
}
