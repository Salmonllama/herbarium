import { notFound } from "next/navigation";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium, Specimen } from "@/lib/api/types";
import { SpecimenForm } from "@/components/workspace/specimen-form";

export const dynamic = "force-dynamic";

export default async function EditSpecimenPage({
  params,
}: {
  params: Promise<{ slug: string; catalogNumber: string }>;
}) {
  const { slug, catalogNumber } = await params;
  const encSlug = encodeURIComponent(slug);
  const encCat = encodeURIComponent(catalogNumber);

  const [hRes, sRes] = await Promise.all([
    serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encSlug}`),
    serverApiFetch<{ data: Specimen }>(`/api/v1/herbaria/${encSlug}/specimens/${encCat}`),
  ]);

  if (!hRes?.data || !sRes?.data) notFound();

  return <SpecimenForm herbarium={hRes.data} initialSpecimen={sRes.data} />;
}
