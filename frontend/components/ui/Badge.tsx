import type { ReactNode } from 'react';
import { cn } from '@/lib/utils/cn';

const TONES = {
  success: 'bg-green-50 text-[color:var(--color-success)]',
  warning: 'bg-amber-50 text-[color:var(--color-warning)]',
  danger: 'bg-red-50 text-[color:var(--color-danger)]',
  neutral: 'bg-brand-50 text-brand-700',
} as const;

export function Badge({ tone = 'neutral', children }: { tone?: keyof typeof TONES; children: ReactNode }) {
  return (
    <span className={cn('inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', TONES[tone])}>
      {children}
    </span>
  );
}
