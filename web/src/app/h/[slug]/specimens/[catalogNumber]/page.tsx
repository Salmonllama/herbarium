import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium, Specimen, User } from "@/lib/api/types";
import { SiteHeader } from "@/components/layout/site-header";
import { SpecimenDetailClient } from "@/components/botanical/specimen-detail-client";

export const dynamic = "force-dynamic";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string; catalogNumber: string }>;
}): Promise<Metadata> {
  const { slug, catalogNumber } = await params;
  const specRes = await serverApiFetch<{ data: Specimen }>(
    `/api/v1/herbaria/${encodeURIComponent(slug)}/specimens/${encodeURIComponent(catalogNumber)}`,
  );

  if (!specRes?.data) {
    return { title: "Specimen Not Found" };
  }

  const s = specRes.data;
  const sci = s.current_determination?.taxon?.scientific_name || "Indeterminata";
  const image = s.media?.[0]?.public_url || s.media?.[0]?.thumb_url;

  return {
    title: `${sci} (${s.catalog_number})`,
    description: `Herbarium specimen ${s.catalog_number} — ${sci}. Collected by ${s.recorded_by || "Unknown"} in ${[s.location.state_province, s.location.country].filter(Boolean).join(", ") || "recorded locality"}.`,
    openGraph: {
      title: `${sci} · ${s.catalog_number}`,
      ...(image ? { images: [image] } : {}),
    },
  };
}

export default async function SpecimenPermalinkPage({
  params,
}: {
  params: Promise<{ slug: string; catalogNumber: string }>;
}) {
  const { slug, catalogNumber } = await params;
  const encSlug = encodeURIComponent(slug);
  const encCat = encodeURIComponent(catalogNumber);

  const [herbRes, specRes, meRes] = await Promise.all([
    serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encSlug}`),
    serverApiFetch<{ data: Specimen }>(`/api/v1/herbaria/${encSlug}/specimens/${encCat}`),
    serverApiFetch<{ user: User }>("/api/v1/me"),
  ]);

  if (!herbRes?.data || !specRes?.data) {
    notFound();
  }

  const herbarium = herbRes.data;
  const specimen = specRes.data;
  const taxon = specimen.current_determination?.taxon;

  // Schema.org / Bioschemas structured data for search engines & biodiversity harvesters
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Thing",
    identifier: specimen.catalog_number,
    name: `${taxon?.scientific_name || "Indeterminata"} (${specimen.catalog_number})`,
    description: specimen.habitat || taxon?.description_markdown || undefined,
    url: specimen.permalink || undefined,
    image: specimen.media?.[0]?.public_url || undefined,
    isPartOf: {
      "@type": "Dataset",
      name: herbarium.name,
      license: herbarium.default_license,
    },
  };

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader initialUser={meRes?.user || null} />
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">
        <SpecimenDetailClient herbarium={herbarium} specimen={specimen} />
      </main>
    </div>
  );
}
