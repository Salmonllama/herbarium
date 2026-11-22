"use client";

import { useState, useRef } from "react";
import { Maximize2, Minimize2, RotateCcw, ZoomIn, ZoomOut, Camera } from "lucide-react";
import type { MediaItem } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

const CATEGORY_LABELS: Record<string, string> = {
  sheet_scan: "Pressed Sheet",
  field_photo: "Field Photo",
  closeup: "Close-up",
  label: "Sheet Label",
  reference: "Reference",
};

export function ZoomViewer({
  media,
  alt,
}: {
  media: MediaItem[];
  alt: string;
}) {
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [scale, setScale] = useState(1);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [dragging, setDragging] = useState(false);
  const [fullscreen, setFullscreen] = useState(false);
  const dragStart = useRef({ x: 0, y: 0 });

  const current = media[selectedIndex];

  function resetView() {
    setScale(1);
    setOffset({ x: 0, y: 0 });
  }

  function zoomBy(delta: number) {
    setScale((prev) => {
      const next = Math.min(6, Math.max(1, Number((prev + delta).toFixed(2))));
      if (next === 1) setOffset({ x: 0, y: 0 });
      return next;
    });
  }

  function handleWheel(e: React.WheelEvent) {
    e.preventDefault();
    zoomBy(e.deltaY < 0 ? 0.35 : -0.35);
  }

  function handlePointerDown(e: React.PointerEvent) {
    if (scale <= 1) return;
    setDragging(true);
    dragStart.current = { x: e.clientX - offset.x, y: e.clientY - offset.y };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  }

  function handlePointerMove(e: React.PointerEvent) {
    if (!dragging) return;
    setOffset({
      x: e.clientX - dragStart.current.x,
      y: e.clientY - dragStart.current.y,
    });
  }

  function handlePointerUp() {
    setDragging(false);
  }

  if (!current) {
    return (
      <div className="flex aspect-[3/4] max-h-[540px] w-full flex-col items-center justify-center rounded-lg border border-dashed border-border bg-surface-muted/60 text-ink-muted p-6 text-center">
        <Camera className="h-10 w-10 mb-3 opacity-50" />
        <p className="font-serif text-lg text-ink">No specimen imagery uploaded</p>
        <p className="text-xs mt-1 max-w-xs">
          Upload a pressed herbarium sheet scan or live field photographs from the curator workspace.
        </p>
      </div>
    );
  }

  const imageUrl = current.public_url || current.thumb_url;

  return (
    <div
      className={
        fullscreen
          ? "fixed inset-0 z-50 flex flex-col bg-canvas p-4 sm:p-6"
          : "flex flex-col gap-3"
      }
    >
      <div className="relative overflow-hidden rounded-lg border border-border bg-surface-muted/80 select-none">
        {/* Toolbar */}
        <div className="absolute top-3 left-3 z-20 flex items-center gap-1.5">
          <Badge tone="neutral" className="bg-surface/90 backdrop-blur-xs">
            {CATEGORY_LABELS[current.category] || current.category}
          </Badge>
          <span className="rounded border border-border bg-surface/90 px-2 py-0.5 font-mono text-[11px] text-ink-muted backdrop-blur-xs">
            {Math.round(scale * 100)}%
          </span>
        </div>

        <div className="absolute top-3 right-3 z-20 flex items-center gap-1 rounded-md border border-border bg-surface/90 p-1 shadow-xs backdrop-blur-xs">
          <Button type="button" variant="ghost" size="icon" className="h-7 w-7" onClick={() => zoomBy(0.5)} title="Zoom in">
            <ZoomIn className="h-3.5 w-3.5" />
          </Button>
          <Button type="button" variant="ghost" size="icon" className="h-7 w-7" onClick={() => zoomBy(-0.5)} title="Zoom out">
            <ZoomOut className="h-3.5 w-3.5" />
          </Button>
          <Button type="button" variant="ghost" size="icon" className="h-7 w-7" onClick={resetView} title="Reset zoom">
            <RotateCcw className="h-3.5 w-3.5" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="h-7 w-7"
            onClick={() => setFullscreen((f) => !f)}
            title={fullscreen ? "Exit fullscreen" : "Fullscreen inspector"}
          >
            {fullscreen ? <Minimize2 className="h-3.5 w-3.5" /> : <Maximize2 className="h-3.5 w-3.5" />}
          </Button>
        </div>

        {/* Viewport */}
        <div
          onWheel={handleWheel}
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerUp}
          onPointerCancel={handlePointerUp}
          onDoubleClick={() => (scale > 1 ? resetView() : setScale(2.5))}
          className={`flex items-center justify-center overflow-hidden ${
            fullscreen ? "h-[calc(100vh-140px)]" : "aspect-[4/5] max-h-[600px]"
          } ${scale > 1 ? (dragging ? "cursor-grabbing" : "cursor-grab") : "cursor-zoom-in"}`}
        >
          {imageUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={imageUrl}
              alt={current.caption || alt}
              draggable={false}
              style={{
                transform: `translate3d(${offset.x}px, ${offset.y}px, 0) scale(${scale})`,
                transition: dragging ? "none" : "transform 150ms ease-out",
              }}
              className="max-h-full max-w-full object-contain"
            />
          ) : (
            <span className="text-sm text-ink-muted">Processing WebP derivative…</span>
          )}
        </div>

        {(current.caption || current.photographer || current.license_code) && (
          <div className="border-t border-border bg-surface px-3 py-2 text-xs text-ink-muted flex flex-wrap items-center justify-between gap-2">
            <span>{current.caption || alt}</span>
            <span className="font-mono text-[11px]">
              {current.photographer ? `© ${current.photographer}` : ""}
              {current.photographer && current.license_code ? " · " : ""}
              {current.license_code || ""}
            </span>
          </div>
        )}
      </div>

      {media.length > 1 && (
        <div className="flex items-center gap-2 overflow-x-auto pb-1">
          {media.map((item, idx) => (
            <button
              key={item.id}
              type="button"
              onClick={() => {
                setSelectedIndex(idx);
                resetView();
              }}
              className={`relative h-16 w-16 shrink-0 overflow-hidden rounded border transition-all cursor-pointer ${
                idx === selectedIndex
                  ? "border-primary ring-2 ring-primary/30"
                  : "border-border opacity-75 hover:opacity-100"
              }`}
            >
              {item.thumb_url ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={item.thumb_url} alt={item.caption || `Image ${idx + 1}`} className="h-full w-full object-cover" />
              ) : (
                <div className="flex h-full w-full items-center justify-center bg-surface-muted text-[10px]">
                  Pending
                </div>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
