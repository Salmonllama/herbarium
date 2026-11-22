"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  ArrowUpRight,
  BookOpen,
  FileSpreadsheet,
  FolderKanban,
  LayoutDashboard,
  Leaf,
  Plus,
  QrCode,
  Settings,
} from "lucide-react";
import type { Herbarium } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { ThemeToggle } from "@/components/ui/theme-toggle";

export function WorkspaceShell({
  herbarium,
  children,
}: {
  herbarium: Herbarium;
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const base = `/app/${herbarium.slug}`;
  const role = herbarium.current_user_role;
  const canCurate = role === "owner" || role === "curator";
  const canContribute = canCurate || role === "contributor";
  const isOwner = role === "owner";

  const navItems = [
    { href: base, label: "Overview", icon: LayoutDashboard, exact: true, show: true },
    { href: `${base}/specimens`, label: "Specimens", icon: FolderKanban, exact: false, show: true },
    { href: `${base}/taxa`, label: "Taxa & Lookup", icon: BookOpen, exact: false, show: true },
    { href: `${base}/labels`, label: "QR Labels", icon: QrCode, exact: false, show: canContribute },
    { href: `${base}/import-export`, label: "Import & DwC-A", icon: FileSpreadsheet, exact: false, show: canCurate },
    { href: `${base}/settings`, label: "Settings", icon: Settings, exact: false, show: isOwner },
  ].filter((item) => item.show);

  return (
    <div className="min-h-screen flex flex-col bg-canvas text-ink">
      {/* Top Bar */}
      <header className="sticky top-0 z-30 border-b border-border bg-surface/95 backdrop-blur-xs">
        <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 gap-3">
          <div className="flex items-center gap-3 min-w-0">
            <Link
              href="/app"
              className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground"
              title="All Herbaria"
            >
              <Leaf className="h-4 w-4" />
            </Link>
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <Link
                  href={base}
                  className="font-serif text-lg font-semibold text-ink truncate hover:text-primary"
                >
                  {herbarium.name}
                </Link>
                {role && (
                  <Badge tone="primary" className="capitalize hidden sm:inline-flex">
                    {role}
                  </Badge>
                )}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {canContribute && (
              <Link
                href={`${base}/specimens/new`}
                className="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground hover:bg-primary-hover transition-colors"
              >
                <Plus className="h-3.5 w-3.5" />
                <span>New Specimen</span>
              </Link>
            )}
            <Link
              href={`/h/${herbarium.slug}`}
              className="inline-flex h-8 items-center gap-1 rounded-md border border-border bg-surface px-2.5 text-xs font-medium text-ink hover:bg-surface-muted"
            >
              <span className="hidden sm:inline">Public Portal</span>
              <ArrowUpRight className="h-3.5 w-3.5" />
            </Link>
            <ThemeToggle />
          </div>
        </div>

        {/* Navigation Sub-bar (responsive on mobile & desktop) */}
        <div className="border-t border-border/70 bg-surface-muted/40">
          <nav className="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-4 sm:px-6 py-1.5">
            {navItems.map(({ href, label, icon: Icon, exact }) => {
              const active = exact ? pathname === href : pathname.startsWith(href);
              return (
                <Link
                  key={href}
                  href={href}
                  className={`inline-flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                    active
                      ? "bg-surface text-ink shadow-2xs border border-border"
                      : "text-ink-muted hover:text-ink hover:bg-surface/60"
                  }`}
                >
                  <Icon className="h-3.5 w-3.5" />
                  <span>{label}</span>
                </Link>
              );
            })}
          </nav>
        </div>
      </header>

      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-7 sm:px-6">{children}</main>
    </div>
  );
}
