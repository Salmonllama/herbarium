import { notFound, redirect } from "next/navigation";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium, User } from "@/lib/api/types";
import { WorkspaceShell } from "@/components/workspace/workspace-shell";

export const dynamic = "force-dynamic";

export default async function HerbariumWorkspaceLayout({
  params,
  children,
}: {
  params: Promise<{ slug: string }>;
  children: React.ReactNode;
}) {
  const { slug } = await params;
  const [meRes, herbRes] = await Promise.all([
    serverApiFetch<{ user: User }>("/api/v1/me"),
    serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encodeURIComponent(slug)}`),
  ]);

  if (!meRes?.user) {
    redirect("/auth/login");
  }

  if (!herbRes?.data || !herbRes.data.current_user_role) {
    notFound();
  }

  return <WorkspaceShell herbarium={herbRes.data}>{children}</WorkspaceShell>;
}
