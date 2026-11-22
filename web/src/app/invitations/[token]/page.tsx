"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Leaf } from "lucide-react";
import { apiFetch, ApiError } from "@/lib/api/client";
import type { HerbariumInvitation, User } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

export default function AcceptInvitationPage({
  params,
}: {
  params: Promise<{ token: string }>;
}) {
  const { token } = use(params);
  const router = useRouter();
  const [invitation, setInvitation] = useState<HerbariumInvitation | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [accepting, setAccepting] = useState(false);

  useEffect(() => {
    Promise.all([
      apiFetch<{ data: HerbariumInvitation }>(`/api/v1/invitations/${encodeURIComponent(token)}`).catch(
        (err) => {
          setError(err instanceof ApiError ? err.message : "Invitation invalid or expired.");
          return null;
        },
      ),
      apiFetch<{ user: User }>("/api/v1/me").catch(() => null),
    ]).then(([invRes, meRes]) => {
      if (invRes?.data) setInvitation(invRes.data);
      if (meRes?.user) setUser(meRes.user);
      setLoading(false);
    });
  }, [token]);

  async function handleAccept() {
    setAccepting(true);
    setError(null);
    try {
      const res = await apiFetch<{ herbarium_slug: string }>(
        `/api/v1/invitations/${encodeURIComponent(token)}/accept`,
        { method: "POST" },
      );
      router.push(`/app/${res.herbarium_slug}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to accept invitation.");
      setAccepting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center archival-paper px-4 py-12">
      <div className="w-full max-w-md rounded-xl border border-border bg-surface p-7 shadow-sm space-y-5 text-center">
        <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
          <Leaf className="h-5 w-5" />
        </div>

        {loading ? (
          <p className="text-sm text-ink-muted">Validating invitation…</p>
        ) : error ? (
          <div className="space-y-3">
            <h1 className="font-serif text-xl font-semibold text-ink">Invitation Unavailable</h1>
            <p className="text-sm text-danger">{error}</p>
            <Link href="/" className="text-xs text-primary hover:underline">
              Return home
            </Link>
          </div>
        ) : invitation ? (
          <div className="space-y-4">
            <h1 className="font-serif text-2xl font-semibold text-ink">
              Join {invitation.herbarium?.name}
            </h1>
            <p className="text-sm text-ink-muted">
              You have been invited as a{" "}
              <Badge tone="primary" className="capitalize">
                {invitation.role}
              </Badge>
              .
            </p>

            {user ? (
              <div className="space-y-3 pt-2">
                <p className="text-xs text-ink-muted">Signed in as {user.email}</p>
                <Button onClick={handleAccept} disabled={accepting} className="w-full">
                  {accepting ? "Joining…" : "Accept & Open Workspace"}
                </Button>
              </div>
            ) : (
              <div className="space-y-2.5 pt-2">
                <Link href={`/auth/register?invitation=${encodeURIComponent(token)}`} className="block">
                  <Button className="w-full">Create Account &amp; Accept</Button>
                </Link>
                <Link href="/auth/login" className="block">
                  <Button variant="outline" className="w-full">
                    Sign in to existing account
                  </Button>
                </Link>
              </div>
            )}
          </div>
        ) : null}
      </div>
    </div>
  );
}
