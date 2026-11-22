import * as React from "react";
import { cn } from "@/lib/utils";

type BadgeTone = "neutral" | "primary" | "accent" | "success" | "danger";

const toneClasses: Record<BadgeTone, string> = {
  neutral: "bg-surface-muted text-ink-muted border-border",
  primary: "bg-primary/10 text-primary border-primary/25",
  accent: "bg-accent-soft text-accent border-accent/25",
  success: "bg-success-soft text-success border-success/25",
  danger: "bg-danger-soft text-danger border-danger/25",
};

export function Badge({
  className,
  tone = "neutral",
  children,
}: {
  className?: string;
  tone?: BadgeTone;
  children: React.ReactNode;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[11px] font-medium tracking-wide",
        toneClasses[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}
