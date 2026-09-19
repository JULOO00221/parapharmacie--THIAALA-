import type { ReactNode } from 'react';

export function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-[18px] border border-bordure bg-blanc px-6 py-14 text-center">
      <h2 className="font-titre text-xl text-vert">{title}</h2>
      {description && <p className="max-w-md text-sm text-texte-doux">{description}</p>}
      {action}
    </div>
  );
}
