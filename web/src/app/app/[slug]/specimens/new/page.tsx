import { notFound } from "next/navigation";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium } from "@/lib/api/types";
import { SpecimenForm } from "@/components/workspace/specimen-form";

export const dynamic = "force-dynamic";

export default async function NewSpecimenPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const res = await serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encodeURIComponent(slug)}`);
  if (!res?.data) notFound();

  return <SpecimenForm herbarium={res.data} />;
}
