"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { Leaf, LogOut, LayoutGrid } from "lucide-react";
import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api/client";
import type { User } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { ThemeToggle } from "@/components/ui/theme-toggle";

export function SiteHeader({
  appName = "Herbarium",
  initialUser = null,
}: {
  appName?: string;
  initialUser?: User | null;
}) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(initialUser);

  useEffect(() => {
    if (initialUser !== null) return;
    apiFetch<{ user: User }>("/api/v1/me")
      .then((res) => setUser(res.user))
      .catch(() => setUser(null));
  }, [initialUser]);

  async function handleLogout() {
    await apiFetch("/auth/logout", { method: "POST" }).catch(() => {});
    setUser(null);
    router.push("/");
    router.refresh();
  }

  return (
    <header className="sticky top-0 z-30 border-b border-border bg-surface/95 backdrop-blur-xs">
      <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6">
        <div className="flex items-center gap-6">
          <Link href="/" className="flex items-center gap-2.5 group">
            <span className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground shadow-xs">
              <Leaf className="h-4 w-4" />
            </span>
            <span className="font-serif text-xl font-semibold tracking-tight text-ink">
              {appName}
            </span>
          </Link>
          <nav className="hidden sm:flex items-center gap-5 text-sm text-ink-muted">
            <Link href="/" className="hover:text-ink transition-colors">
              Collections
            </Link>
            {user && (
              <Link href="/app" className="hover:text-ink transition-colors">
                Workspace
              </Link>
            )}
          </nav>
        </div>

        <div className="flex items-center gap-2">
          <ThemeToggle />
          {user ? (
            <>
              <Link href="/app">
                <Button variant="outline" size="sm">
                  <LayoutGrid className="h-3.5 w-3.5" />
                  <span className="hidden sm:inline">Workspace</span>
                </Button>
              </Link>
              <Button variant="ghost" size="icon" onClick={handleLogout} title="Sign out" aria-label="Sign out">
                <LogOut className="h-4 w-4" />
              </Button>
            </>
          ) : (
            <>
              <Link href="/auth/login">
                <Button variant="ghost" size="sm">
                  Sign in
                </Button>
              </Link>
              <Link href="/auth/register">
                <Button variant="primary" size="sm">
                  Get started
                </Button>
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
