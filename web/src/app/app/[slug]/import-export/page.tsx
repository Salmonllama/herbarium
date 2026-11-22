import { notFound } from "next/navigation";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium } from "@/lib/api/types";
import { ImportExportCenter } from "@/components/workspace/import-export-center";

export const dynamic = "force-dynamic";

export default async function WorkspaceImportExportPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const res = await serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encodeURIComponent(slug)}`);
  if (!res?.data) notFound();

  return <ImportExportCenter herbarium={res.data} />;
}
