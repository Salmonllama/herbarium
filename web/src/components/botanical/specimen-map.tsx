"use client";

import { useEffect, useRef } from "react";
import type { GeoJsonFeature } from "@/lib/api/types";

interface SpecimenMapProps {
  features: GeoJsonFeature[];
  herbariumSlug: string;
  heightClass?: string;
  singleUncertaintyMeters?: number | null;
}

export function SpecimenMap({
  features,
  herbariumSlug,
  heightClass = "h-[460px]",
  singleUncertaintyMeters,
}: SpecimenMapProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const mapRef = useRef<import("leaflet").Map | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function init() {
      if (!containerRef.current) return;
      const L = (await import("leaflet")).default;
      if (cancelled || !containerRef.current) return;

      if (mapRef.current) {
        mapRef.current.remove();
        mapRef.current = null;
      }

      const map = L.map(containerRef.current, {
        scrollWheelZoom: true,
      });
      mapRef.current = map;

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18,
      }).addTo(map);

      const bounds: Array<[number, number]> = [];

      for (const feat of features) {
        const [lng, lat] = feat.geometry.coordinates;
        bounds.push([lat, lng]);

        const isObscured = feat.properties.coordinates_obscured;
        const radiusMeters =
          singleUncertaintyMeters ??
          (isObscured ? Math.max(5000, feat.properties.coordinate_uncertainty_m || 10000) : 0);

        if (radiusMeters >= 1000) {
          L.circle([lat, lng], {
            radius: radiusMeters,
            color: "#84623a",
            fillColor: "#84623a",
            fillOpacity: 0.16,
            weight: 1.5,
            dashArray: "4 4",
          }).addTo(map);
        }

        const marker = L.circleMarker([lat, lng], {
          radius: isObscured ? 7 : 6,
          color: "#ffffff",
          weight: 2,
          fillColor: isObscured ? "#84623a" : "#1f4234",
          fillOpacity: 0.92,
        }).addTo(map);

        const href = `/h/${encodeURIComponent(herbariumSlug)}/specimens/${encodeURIComponent(
          feat.properties.catalog_number,
        )}`;
        const popupHtml = `
          <div style="min-width:180px;font-family:system-ui,sans-serif;">
            <div style="font-family:monospace;font-size:11px;color:#555f57;">${escapeHtml(
              feat.properties.catalog_number,
            )}</div>
            <div style="font-family:Georgia,serif;font-style:italic;font-size:14px;font-weight:600;margin:2px 0;">${escapeHtml(
              feat.properties.scientific_name,
            )}</div>
            ${
              feat.properties.recorded_by
                ? `<div style="font-size:12px;color:#555f57;">Leg. ${escapeHtml(feat.properties.recorded_by)}</div>`
                : ""
            }
            ${
              isObscured
                ? `<div style="font-size:11px;color:#84623a;margin-top:3px;">Approximate location (~10 km grid)</div>`
                : ""
            }
            <a href="${href}" style="display:inline-block;margin-top:6px;font-size:12px;color:#1f4234;font-weight:600;text-decoration:underline;">View specimen →</a>
          </div>
        `;
        marker.bindPopup(popupHtml);
      }

      if (bounds.length === 1) {
        map.setView(bounds[0], singleUncertaintyMeters && singleUncertaintyMeters >= 5000 ? 10 : 12);
      } else if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [36, 36], maxZoom: 13 });
      } else {
        map.setView([39.5, -98.35], 3);
      }
    }

    init();

    return () => {
      cancelled = true;
      if (mapRef.current) {
        mapRef.current.remove();
        mapRef.current = null;
      }
    };
  }, [features, herbariumSlug, singleUncertaintyMeters]);

  return (
    <div
      ref={containerRef}
      className={`w-full rounded-lg border border-border overflow-hidden bg-surface-muted ${heightClass}`}
    />
  );
}

function escapeHtml(text: string): string {
  return text
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}
