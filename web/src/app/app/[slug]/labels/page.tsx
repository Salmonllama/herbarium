import { notFound } from "next/navigation";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium } from "@/lib/api/types";
import { LabelStudio } from "@/components/workspace/label-studio";

export const dynamic = "force-dynamic";

export default async function WorkspaceLabelsPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const res = await serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encodeURIComponent(slug)}`);
  if (!res?.data) notFound();

  return <LabelStudio herbarium={res.data} />;
}
