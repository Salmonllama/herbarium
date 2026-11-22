"use client";

import { useEffect, useState, useCallback } from "react";
import { Check, Copy, MailPlus, Trash2 } from "lucide-react";
import { apiFetch, ApiError } from "@/lib/api/client";
import type { Herbarium, HerbariumInvitation, HerbariumMember } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input, Select, Textarea } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";

export function SettingsManager({ herbarium: initial }: { herbarium: Herbarium }) {
  const [herbarium, setHerbarium] = useState(initial);
  const [name, setName] = useState(initial.name);
  const [institutionCode, setInstitutionCode] = useState(initial.institution_code || "");
  const [customDomain, setCustomDomain] = useState(initial.custom_domain || "");
  const [description, setDescription] = useState(initial.description || "");
  const [visibility, setVisibility] = useState<string>(initial.visibility);
  const [idPrefix, setIdPrefix] = useState(initial.id_prefix);
  const [idSeparator, setIdSeparator] = useState(initial.id_separator);
  const [idPadding, setIdPadding] = useState(String(initial.id_padding));
  const [idNextSequence, setIdNextSequence] = useState(String(initial.id_next_sequence ?? 1));
  const [defaultGeoprivacy, setDefaultGeoprivacy] = useState<string>(initial.default_geoprivacy);
  const [defaultLicense, setDefaultLicense] = useState(initial.default_license);

  const [saving, setSaving] = useState(false);
  const [savedNotice, setSavedNotice] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [members, setMembers] = useState<HerbariumMember[]>([]);
  const [invitations, setInvitations] = useState<HerbariumInvitation[]>([]);
  const [inviteEmail, setInviteEmail] = useState("");
  const [inviteRole, setInviteRole] = useState("contributor");
  const [copiedToken, setCopiedToken] = useState<string | null>(null);

  const liveIdPreview = `${idPrefix || "HERB"}${idSeparator}${String(
    Math.max(1, Number(idNextSequence) || 1),
  ).padStart(Math.min(12, Math.max(1, Number(idPadding) || 6)), "0")}`;

  const loadAccess = useCallback(async () => {
    try {
      const [mRes, iRes] = await Promise.all([
        apiFetch<{ data: HerbariumMember[] }>(`/api/v1/herbaria/${herbarium.slug}/members`),
        apiFetch<{ data: HerbariumInvitation[] }>(`/api/v1/herbaria/${herbarium.slug}/invitations`),
      ]);
      setMembers(mRes.data);
      setInvitations(iRes.data);
    } catch {
      // ignore
    }
  }, [herbarium.slug]);

  useEffect(() => {
    let active = true;
    Promise.all([
      apiFetch<{ data: HerbariumMember[] }>(`/api/v1/herbaria/${herbarium.slug}/members`),
      apiFetch<{ data: HerbariumInvitation[] }>(`/api/v1/herbaria/${herbarium.slug}/invitations`),
    ])
      .then(([mRes, iRes]) => {
        if (!active) return;
        setMembers(mRes.data);
        setInvitations(iRes.data);
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, [herbarium.slug]);

  async function saveSettings(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSavedNotice(false);
    try {
      const res = await apiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${herbarium.slug}`, {
        method: "PATCH",
        json: {
          name,
          institution_code: institutionCode.trim() || null,
          custom_domain: customDomain.trim() || null,
          description: description.trim() || null,
          visibility,
          id_prefix: idPrefix.trim(),
          id_separator: idSeparator,
          id_padding: Number(idPadding) || 6,
          id_next_sequence: Number(idNextSequence) || 1,
          default_geoprivacy: defaultGeoprivacy,
          default_license: defaultLicense,
        },
      });
      setHerbarium(res.data);
      setSavedNotice(true);
      setTimeout(() => setSavedNotice(false), 3000);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save settings.");
    } finally {
      setSaving(false);
    }
  }

  async function sendInvite(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await apiFetch(`/api/v1/herbaria/${herbarium.slug}/invitations`, {
        method: "POST",
        json: { email: inviteEmail, role: inviteRole },
      });
      setInviteEmail("");
      await loadAccess();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create invitation.");
    }
  }

  async function updateMember(m: HerbariumMember, patch: Partial<HerbariumMember>) {
    setError(null);
    try {
      await apiFetch(`/api/v1/herbaria/${herbarium.slug}/members/${m.id}`, {
        method: "PATCH",
        json: patch,
      });
      await loadAccess();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not update member.");
    }
  }

  async function removeMember(m: HerbariumMember) {
    setError(null);
    try {
      await apiFetch(`/api/v1/herbaria/${herbarium.slug}/members/${m.id}`, { method: "DELETE" });
      await loadAccess();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not remove member.");
    }
  }

  function copyInviteLink(token: string) {
    const url = `${window.location.origin}/invitations/${token}`;
    navigator.clipboard.writeText(url).then(() => {
      setCopiedToken(token);
      setTimeout(() => setCopiedToken(null), 2000);
    });
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="font-serif text-3xl font-semibold text-ink">Herbarium Settings</h1>
        <p className="text-sm text-ink-muted mt-1">
          Configure catalog ID numbering, visibility, custom domain, geoprivacy defaults, and team RBAC.
        </p>
      </div>

      {error && (
        <div className="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      <form onSubmit={saveSettings} className="space-y-6">
        {/* General & Portal */}
        <section className="rounded-lg border border-border bg-surface p-6 space-y-4">
          <h2 className="font-serif text-xl font-semibold">Identity &amp; Sharing</h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-1">
              <label className="block text-xs font-medium">Herbarium Name</label>
              <Input required value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Index Herbariorum / Institution Code</label>
              <Input
                value={institutionCode}
                onChange={(e) => setInstitutionCode(e.target.value.toUpperCase())}
                placeholder="e.g. OSC, UC, NY"
                className="font-mono"
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Visibility</label>
              <Select value={visibility} onChange={(e) => setVisibility(e.target.value)}>
                <option value="public">Public (Listed &amp; Searchable)</option>
                <option value="unlisted">Unlisted (Shareable Link Only)</option>
                <option value="private">Private (Members Only)</option>
              </Select>
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Custom Domain (optional)</label>
              <Input
                value={customDomain}
                onChange={(e) => setCustomDomain(e.target.value)}
                placeholder="herbarium.university.edu"
                className="font-mono"
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Default Geoprivacy</label>
              <Select value={defaultGeoprivacy} onChange={(e) => setDefaultGeoprivacy(e.target.value)}>
                <option value="exact">Exact coordinates public (unless Taxon sensitive)</option>
                <option value="obscured">Obscured (~10 km grid) by default</option>
                <option value="hidden">Hidden publicly by default</option>
              </Select>
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Default Data License</label>
              <Select value={defaultLicense} onChange={(e) => setDefaultLicense(e.target.value)}>
                <option value="CC-BY-4.0">CC-BY-4.0 (Attribution)</option>
                <option value="CC0-1.0">CC0-1.0 (Public Domain Dedication)</option>
                <option value="CC-BY-NC-4.0">CC-BY-NC-4.0 (Non-Commercial)</option>
              </Select>
            </div>
          </div>

          <div className="space-y-1">
            <label className="block text-xs font-medium">Collection Description</label>
            <Textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} />
          </div>
        </section>

        {/* Configurable Specimen ID Numbering */}
        <section className="rounded-lg border border-border bg-surface p-6 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 className="font-serif text-xl font-semibold">Configurable Specimen IDs</h2>
              <p className="text-xs text-ink-muted">
                Auto-incrementing accession format. Manual overrides remain allowed for legacy sheets.
              </p>
            </div>
            <div className="rounded-md border border-primary/30 bg-primary/5 px-3.5 py-2 text-right">
              <span className="block text-[10px] uppercase tracking-wider text-ink-muted">
                Next Auto ID Preview
              </span>
              <span className="font-mono text-base font-semibold text-primary">{liveIdPreview}</span>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div className="space-y-1">
              <label className="block text-xs font-medium">Prefix</label>
              <Input
                required
                value={idPrefix}
                onChange={(e) => setIdPrefix(e.target.value.toUpperCase())}
                className="font-mono"
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Separator</label>
              <Input
                value={idSeparator}
                maxLength={4}
                onChange={(e) => setIdSeparator(e.target.value)}
                placeholder="-"
                className="font-mono"
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Zero Padding</label>
              <Input
                type="number"
                min={1}
                max={12}
                value={idPadding}
                onChange={(e) => setIdPadding(e.target.value)}
                className="font-mono"
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Next Sequence #</label>
              <Input
                type="number"
                min={1}
                value={idNextSequence}
                onChange={(e) => setIdNextSequence(e.target.value)}
                className="font-mono"
              />
            </div>
          </div>

          <div className="flex items-center justify-end gap-3 pt-2">
            {savedNotice && <span className="text-xs text-success font-medium">Settings saved.</span>}
            <Button type="submit" disabled={saving}>
              {saving ? "Saving…" : "Save Herbarium Settings"}
            </Button>
          </div>
        </section>
      </form>

      {/* Team Members & Invitations */}
      <section className="rounded-lg border border-border bg-surface p-6 space-y-5">
        <h2 className="font-serif text-xl font-semibold">Team Roles &amp; Invitations</h2>

        <form onSubmit={sendInvite} className="flex flex-col gap-2 sm:flex-row">
          <Input
            type="email"
            required
            value={inviteEmail}
            onChange={(e) => setInviteEmail(e.target.value)}
            placeholder="colleague@university.edu"
            className="flex-1"
          />
          <Select value={inviteRole} onChange={(e) => setInviteRole(e.target.value)} className="sm:w-44">
            <option value="owner">Owner / Admin</option>
            <option value="curator">Curator</option>
            <option value="contributor">Contributor / Student</option>
            <option value="viewer">Viewer</option>
          </Select>
          <Button type="submit" variant="secondary">
            <MailPlus className="h-4 w-4" /> Create Invite Link
          </Button>
        </form>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border text-xs uppercase text-ink-muted">
              <tr>
                <th className="py-2 pr-4">Member</th>
                <th className="py-2 pr-4">Role</th>
                <th className="py-2 pr-4">Exact GPS</th>
                <th className="py-2 text-right">Remove</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {members.map((m) => (
                <tr key={m.id}>
                  <td className="py-3 pr-4">
                    <div className="font-medium text-ink">{m.user.name}</div>
                    <div className="text-xs text-ink-muted">{m.user.email}</div>
                  </td>
                  <td className="py-3 pr-4">
                    <Select
                      value={m.role}
                      onChange={(e) =>
                        updateMember(m, { role: e.target.value as HerbariumMember["role"] })
                      }
                      className="h-8 w-36 text-xs"
                    >
                      <option value="owner">Owner</option>
                      <option value="curator">Curator</option>
                      <option value="contributor">Contributor</option>
                      <option value="viewer">Viewer</option>
                    </Select>
                  </td>
                  <td className="py-3 pr-4">
                    <input
                      type="checkbox"
                      checked={m.can_view_exact_coordinates}
                      onChange={(e) =>
                        updateMember(m, { can_view_exact_coordinates: e.target.checked })
                      }
                      className="h-4 w-4 accent-primary"
                    />
                  </td>
                  <td className="py-3 text-right">
                    <Button variant="ghost" size="icon" onClick={() => removeMember(m)}>
                      <Trash2 className="h-3.5 w-3.5 text-danger" />
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {invitations.filter((i) => i.is_pending).length > 0 && (
          <div className="border-t border-border pt-4 space-y-2">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-ink-muted">
              Pending Invitations
            </h3>
            {invitations
              .filter((i) => i.is_pending)
              .map((inv) => (
                <div
                  key={inv.id}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-surface-muted/40 px-3 py-2 text-xs"
                >
                  <div>
                    <span className="font-medium text-ink">{inv.email}</span>
                    <Badge tone="neutral" className="ml-2 capitalize">
                      {inv.role}
                    </Badge>
                  </div>
                  {inv.token && (
                    <Button size="sm" variant="outline" onClick={() => copyInviteLink(inv.token!)}>
                      {copiedToken === inv.token ? (
                        <Check className="h-3.5 w-3.5" />
                      ) : (
                        <Copy className="h-3.5 w-3.5" />
                      )}
                      {copiedToken === inv.token ? "Copied link" : "Copy Invite Link"}
                    </Button>
                  )}
                </div>
              ))}
          </div>
        )}
      </section>
    </div>
  );
}
