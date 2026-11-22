"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowRight, KeyRound, Leaf, Plus, Trash2 } from "lucide-react";
import { apiFetch, ApiError } from "@/lib/api/client";
import type { ApiTokenItem, User, UserHerbariumSummary } from "@/lib/api/types";
import { SiteHeader } from "@/components/layout/site-header";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";

export default function AppWorkspaceHomePage() {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [herbaria, setHerbaria] = useState<UserHerbariumSummary[]>([]);
  const [tokens, setTokens] = useState<ApiTokenItem[]>([]);
  const [loading, setLoading] = useState(true);

  // Create Herbarium
  const [showCreate, setShowCreate] = useState(false);
  const [name, setName] = useState("");
  const [institutionCode, setInstitutionCode] = useState("");
  const [idPrefix, setIdPrefix] = useState("HERB");
  const [visibility, setVisibility] = useState("public");
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // API Tokens
  const [tokenName, setTokenName] = useState("");
  const [newPlainToken, setNewPlainToken] = useState<string | null>(null);

  async function loadAll() {
    try {
      const [meRes, tokRes] = await Promise.all([
        apiFetch<{ user: User; herbaria: UserHerbariumSummary[] }>("/api/v1/me"),
        apiFetch<{ data: ApiTokenItem[] }>("/api/v1/tokens"),
      ]);
      setUser(meRes.user);
      setHerbaria(meRes.herbaria);
      setTokens(tokRes.data);
    } catch {
      router.push("/auth/login");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let active = true;
    Promise.all([
      apiFetch<{ user: User; herbaria: UserHerbariumSummary[] }>("/api/v1/me"),
      apiFetch<{ data: ApiTokenItem[] }>("/api/v1/tokens"),
    ])
      .then(([meRes, tokRes]) => {
        if (!active) return;
        setUser(meRes.user);
        setHerbaria(meRes.herbaria);
        setTokens(tokRes.data);
        setLoading(false);
      })
      .catch(() => {
        if (!active) return;
        router.push("/auth/login");
      });
    return () => {
      active = false;
    };
  }, [router]);

  async function handleCreateHerbarium(e: React.FormEvent) {
    e.preventDefault();
    setCreating(true);
    setError(null);
    try {
      const res = await apiFetch<{ data: { slug: string } }>("/api/v1/herbaria", {
        method: "POST",
        json: {
          name: name.trim(),
          institution_code: institutionCode.trim() || null,
          id_prefix: idPrefix.trim() || "HERB",
          visibility,
        },
      });
      router.push(`/app/${res.data.slug}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create herbarium.");
      setCreating(false);
    }
  }

  async function handleCreateToken(e: React.FormEvent) {
    e.preventDefault();
    if (!tokenName.trim()) return;
    try {
      const res = await apiFetch<{ token: { plain_text_token: string } }>("/api/v1/tokens", {
        method: "POST",
        json: { name: tokenName.trim(), abilities: ["read", "write"] },
      });
      setNewPlainToken(res.token.plain_text_token);
      setTokenName("");
      await loadAll();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to create token.");
    }
  }

  async function revokeToken(id: number) {
    await apiFetch(`/api/v1/tokens/${id}`, { method: "DELETE" });
    await loadAll();
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-canvas text-sm text-ink-muted">
        Loading workspace…
      </div>
    );
  }

  return (
    <div className="min-h-screen flex flex-col bg-canvas">
      <SiteHeader initialUser={user} />

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6 space-y-10">
        <div className="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
          <div>
            <p className="font-mono text-xs uppercase tracking-widest text-accent">
              Curator Workspace
            </p>
            <h1 className="font-serif text-3xl sm:text-4xl font-semibold text-ink mt-1">
              Welcome back, {user?.name}
            </h1>
          </div>
          <Button onClick={() => setShowCreate((v) => !v)}>
            <Plus className="h-4 w-4" />
            New Herbarium
          </Button>
        </div>

        {error && (
          <div className="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">
            {error}
          </div>
        )}

        {showCreate && (
          <form
            onSubmit={handleCreateHerbarium}
            className="rounded-lg border border-primary/30 bg-surface p-6 space-y-4 shadow-xs"
          >
            <h2 className="font-serif text-xl font-semibold">Create a Digital Herbarium</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div className="space-y-1 sm:col-span-2">
                <label className="block text-xs font-medium">Herbarium Name *</label>
                <Input
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Willamette Valley Vascular Herbarium"
                />
              </div>
              <div className="space-y-1">
                <label className="block text-xs font-medium">Catalog ID Prefix</label>
                <Input
                  required
                  value={idPrefix}
                  onChange={(e) => setIdPrefix(e.target.value.toUpperCase())}
                  placeholder="WVH"
                  className="font-mono"
                />
              </div>
              <div className="space-y-1">
                <label className="block text-xs font-medium">Visibility</label>
                <Select value={visibility} onChange={(e) => setVisibility(e.target.value)}>
                  <option value="public">Public</option>
                  <option value="unlisted">Unlisted</option>
                  <option value="private">Private</option>
                </Select>
              </div>
              <div className="space-y-1 sm:col-span-2">
                <label className="block text-xs font-medium">
                  Index Herbariorum / Institution Code (optional)
                </label>
                <Input
                  value={institutionCode}
                  onChange={(e) => setInstitutionCode(e.target.value.toUpperCase())}
                  placeholder="e.g. OSC"
                  className="font-mono"
                />
              </div>
            </div>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setShowCreate(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={creating}>
                {creating ? "Creating…" : "Create Herbarium"}
              </Button>
            </div>
          </form>
        )}

        {/* My Herbaria Grid */}
        <section className="space-y-4">
          <h2 className="font-serif text-2xl font-semibold text-ink">Your Herbaria</h2>
          {herbaria.length === 0 ? (
            <div className="rounded-lg border border-dashed border-border bg-surface p-10 text-center space-y-3">
              <Leaf className="mx-auto h-8 w-8 text-ink-muted/60" />
              <p className="font-serif text-xl text-ink">No herbaria yet</p>
              <p className="text-sm text-ink-muted">
                Create your first herbarium collection to start cataloging plants and specimens.
              </p>
              <Button onClick={() => setShowCreate(true)}>
                <Plus className="h-4 w-4" /> Create Herbarium
              </Button>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
              {herbaria.map((h) => (
                <Link
                  key={h.id}
                  href={`/app/${h.slug}`}
                  className="group rounded-lg border border-border bg-surface p-5 transition-all hover:border-primary/50 hover:shadow-md flex flex-col justify-between gap-4"
                >
                  <div>
                    <div className="flex items-center justify-between gap-2">
                      <span className="font-mono text-xs text-ink-muted">
                        {h.institution_code || h.slug}
                      </span>
                      <Badge tone="primary" className="capitalize">
                        {h.role}
                      </Badge>
                    </div>
                    <h3 className="mt-2 font-serif text-xl font-semibold text-ink group-hover:text-primary">
                      {h.name}
                    </h3>
                  </div>
                  <div className="flex items-center justify-between text-xs font-medium text-primary pt-2 border-t border-border/70">
                    <span>Open Curator Workspace</span>
                    <ArrowRight className="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" />
                  </div>
                </Link>
              ))}
            </div>
          )}
        </section>

        {/* Personal API Tokens */}
        <section className="rounded-lg border border-border bg-surface p-6 space-y-4">
          <div className="flex items-center gap-2">
            <KeyRound className="h-4 w-4 text-accent" />
            <h2 className="font-serif text-xl font-semibold">Personal API Access Tokens</h2>
          </div>
          <p className="text-xs text-ink-muted">
            Use Bearer tokens to integrate Herbarium with university portals, R/Python biodiversity
            scripts, or automated digitization pipelines.
          </p>

          <form onSubmit={handleCreateToken} className="flex gap-2 max-w-md">
            <Input
              value={tokenName}
              onChange={(e) => setTokenName(e.target.value)}
              placeholder="Token label (e.g. R-Studio Research)"
            />
            <Button type="submit" variant="secondary">
              Generate
            </Button>
          </form>

          {newPlainToken && (
            <div className="rounded-md border border-success/40 bg-success-soft p-3 text-xs space-y-1">
              <div className="font-semibold text-success">
                Copy your new API token now — it will not be shown again:
              </div>
              <code className="block break-all font-mono text-ink select-all bg-surface p-2 rounded border border-border">
                {newPlainToken}
              </code>
            </div>
          )}

          {tokens.length > 0 && (
            <ul className="divide-y divide-border text-sm">
              {tokens.map((t) => (
                <li key={t.id} className="py-2.5 flex items-center justify-between gap-2">
                  <div>
                    <span className="font-medium text-ink">{t.name}</span>
                    <span className="ml-2 font-mono text-xs text-ink-muted">
                      ({t.abilities.join(", ")})
                    </span>
                  </div>
                  <Button variant="ghost" size="icon" onClick={() => revokeToken(t.id)} title="Revoke token">
                    <Trash2 className="h-3.5 w-3.5 text-danger" />
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </section>
      </main>
    </div>
  );
}
