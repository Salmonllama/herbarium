import { notFound } from "next/navigation";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium } from "@/lib/api/types";
import { TaxaManager } from "@/components/workspace/taxa-manager";

export const dynamic = "force-dynamic";

export default async function WorkspaceTaxaPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const res = await serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encodeURIComponent(slug)}`);
  if (!res?.data) notFound();

  return <TaxaManager herbarium={res.data} />;
}
