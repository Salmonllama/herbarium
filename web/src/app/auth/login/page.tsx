"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Leaf } from "lucide-react";
import { apiFetch, ApiError } from "@/lib/api/client";
import type { InstanceConfig } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [instance, setInstance] = useState<InstanceConfig | null>(null);

  useEffect(() => {
    apiFetch<InstanceConfig>("/api/v1/instance")
      .then(setInstance)
      .catch(() => {});
  }, []);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await apiFetch("/auth/login", {
        method: "POST",
        json: { email, password, remember: true },
      });
      router.push("/app");
      router.refresh();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to sign in.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center archival-paper px-4 py-12">
      <div className="w-full max-w-md rounded-xl border border-border bg-surface p-7 shadow-sm space-y-6">
        <div className="text-center space-y-2">
          <Link href="/" className="inline-flex items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-primary-foreground">
              <Leaf className="h-4 w-4" />
            </span>
            <span className="font-serif text-2xl font-semibold text-ink">
              {instance?.app_name || "Herbarium"}
            </span>
          </Link>
          <h1 className="font-serif text-xl font-medium text-ink">Sign in to your workspace</h1>
        </div>

        {error && (
          <div className="rounded-md border border-danger/30 bg-danger-soft px-3.5 py-2.5 text-xs text-danger">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <label htmlFor="email" className="block text-xs font-medium text-ink">
              Email address
            </label>
            <Input
              id="email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="curator@university.edu"
            />
          </div>

          <div className="space-y-1.5">
            <label htmlFor="password" className="block text-xs font-medium text-ink">
              Password
            </label>
            <Input
              id="password"
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>

          <Button type="submit" className="w-full" disabled={submitting}>
            {submitting ? "Signing in…" : "Sign in"}
          </Button>
        </form>

        {instance?.auth_providers && instance.auth_providers.length > 0 && (
          <div className="space-y-3 pt-2 border-t border-border">
            <p className="text-center text-xs text-ink-muted">Or continue with institutional identity</p>
            <div className="grid gap-2">
              {instance.auth_providers.map((p) => (
                <a
                  key={p.id}
                  href={p.redirect_url}
                  className="inline-flex h-9 w-full items-center justify-center rounded-md border border-border bg-surface-muted px-4 text-sm font-medium text-ink hover:bg-border/40 transition-colors"
                >
                  Continue with {p.label}
                </a>
              ))}
            </div>
          </div>
        )}

        <p className="text-center text-xs text-ink-muted">
          New to Herbarium?{" "}
          <Link href="/auth/register" className="font-medium text-primary hover:underline">
            Create an account
          </Link>
        </p>
      </div>
    </div>
  );
}
