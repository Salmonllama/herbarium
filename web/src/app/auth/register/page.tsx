"use client";

import { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Leaf } from "lucide-react";
import { apiFetch, ApiError } from "@/lib/api/client";
import type { InstanceConfig } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

function RegisterForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const initialInvite = searchParams.get("invitation") || "";

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [institution, setInstitution] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [invitationToken, setInvitationToken] = useState(initialInvite);
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
      await apiFetch("/auth/register", {
        method: "POST",
        json: {
          name,
          email,
          institution: institution || null,
          password,
          password_confirmation: passwordConfirmation,
          invitation_token: invitationToken || null,
        },
      });
      router.push("/app");
      router.refresh();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Registration failed.");
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
          <h1 className="font-serif text-xl font-medium text-ink">Create your botanical account</h1>
          {instance?.registration_mode === "invite_only" && !invitationToken && (
            <p className="text-xs text-accent">
              This instance is invite-only. Provide an invitation token from your curator.
            </p>
          )}
        </div>

        {error && (
          <div className="rounded-md border border-danger/30 bg-danger-soft px-3.5 py-2.5 text-xs text-danger">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-3.5">
          <div className="space-y-1">
            <label htmlFor="name" className="block text-xs font-medium text-ink">
              Full name
            </label>
            <Input
              id="name"
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Dr. Ada Botanist"
            />
          </div>

          <div className="space-y-1">
            <label htmlFor="email" className="block text-xs font-medium text-ink">
              Email address
            </label>
            <Input
              id="email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="ada@university.edu"
            />
          </div>

          <div className="space-y-1">
            <label htmlFor="institution" className="block text-xs font-medium text-ink">
              Institution or Affiliation (optional)
            </label>
            <Input
              id="institution"
              value={institution}
              onChange={(e) => setInstitution(e.target.value)}
              placeholder="Oregon State Herbarium / Independent"
            />
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="space-y-1">
              <label htmlFor="password" className="block text-xs font-medium text-ink">
                Password
              </label>
              <Input
                id="password"
                type="password"
                required
                minLength={8}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <label htmlFor="password_confirmation" className="block text-xs font-medium text-ink">
                Confirm
              </label>
              <Input
                id="password_confirmation"
                type="password"
                required
                minLength={8}
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
              />
            </div>
          </div>

          {(instance?.registration_mode === "invite_only" || invitationToken) && (
            <div className="space-y-1">
              <label htmlFor="invitation_token" className="block text-xs font-medium text-ink">
                Invitation Token
              </label>
              <Input
                id="invitation_token"
                value={invitationToken}
                onChange={(e) => setInvitationToken(e.target.value)}
                className="font-mono text-xs"
              />
            </div>
          )}

          <Button type="submit" className="w-full" disabled={submitting}>
            {submitting ? "Creating account…" : "Create account"}
          </Button>
        </form>

        <p className="text-center text-xs text-ink-muted">
          Already have an account?{" "}
          <Link href="/auth/login" className="font-medium text-primary hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}

export default function RegisterPage() {
  return (
    <Suspense fallback={<div className="min-h-screen archival-paper" />}>
      <RegisterForm />
    </Suspense>
  );
}
