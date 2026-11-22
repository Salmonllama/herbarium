import { cn } from "@/lib/utils";

export function ScientificName({
  name,
  authorship,
  qualifier,
  className,
}: {
  name?: string | null;
  authorship?: string | null;
  qualifier?: string | null;
  className?: string;
}) {
  if (!name) {
    return (
      <span className={cn("font-serif italic text-ink-muted", className)}>
        Indeterminata (indet.)
      </span>
    );
  }

  return (
    <span className={cn("font-serif text-ink", className)}>
      {qualifier && <span className="not-italic mr-1 text-ink-muted">{qualifier}</span>}
      <em className="italic">{name}</em>
      {authorship && (
        <span className="not-italic font-normal text-ink-muted ml-1.5 text-[0.88em]">
          {authorship}
        </span>
      )}
    </span>
  );
}
