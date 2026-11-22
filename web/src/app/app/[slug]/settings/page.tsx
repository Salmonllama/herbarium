import { notFound } from "next/navigation";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium } from "@/lib/api/types";
import { SettingsManager } from "@/components/workspace/settings-manager";

export const dynamic = "force-dynamic";

export default async function WorkspaceSettingsPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const res = await serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encodeURIComponent(slug)}`);
  if (!res?.data) notFound();

  return <SettingsManager herbarium={res.data} />;
}
