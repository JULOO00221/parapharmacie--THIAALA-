import { cn } from '@/lib/utils/cn';
import type { OrderStatus } from '@/lib/api/types';

/**
 * Purely presentational — the step order and current position are
 * derived from `status` as returned by Laravel, never recomputed or
 * second-guessed here. OrderService.ALLOWED_TRANSITIONS remains the only
 * authority on what status a commande can actually be in.
 */
const STEPS: { status: OrderStatus; label: string }[] = [
  { status: 'pending', label: 'Commande reçue' },
  { status: 'confirmed', label: 'Confirmée' },
  { status: 'preparing', label: 'En préparation' },
  { status: 'ready', label: 'Prête' },
  { status: 'delivered', label: 'Retirée / Livrée' },
];

export function OrderStatusTimeline({ status }: { status: OrderStatus }) {
  if (status === 'cancelled') {
    return (
      <div className="flex items-center gap-3 rounded-xl border border-[color:var(--color-danger)]/30 bg-red-50 px-4 py-3">
        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[color:var(--color-danger)] text-white">
          <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" className="h-3.5 w-3.5">
            <path d="M5 5l10 10M15 5 5 15" strokeLinecap="round" />
          </svg>
        </span>
        <p className="text-sm font-medium text-[color:var(--color-danger)]">Commande annulée</p>
      </div>
    );
  }

  const currentIndex = STEPS.findIndex((step) => step.status === status);

  return (
    <ol className="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-2">
      {STEPS.map((step, index) => {
        const isDone = index <= currentIndex;
        const isCurrent = index === currentIndex;

        return (
          <li key={step.status} className="flex flex-1 items-center gap-3 sm:flex-col sm:items-stretch sm:gap-2">
            <span
              className={cn(
                'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                isDone ? 'bg-brand-600 text-white' : 'bg-brand-50 text-ink-muted'
              )}
            >
              {isDone ? (
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2.5" className="h-3 w-3">
                  <path d="M4 10.5l4 4 8-9" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
              ) : (
                index + 1
              )}
            </span>
            <span className={cn('text-sm', isCurrent ? 'font-semibold text-ink' : isDone ? 'text-ink' : 'text-ink-muted')}>
              {step.label}
            </span>
            {index < STEPS.length - 1 && <span className="hidden h-px flex-1 bg-border sm:block" aria-hidden="true" />}
          </li>
        );
      })}
    </ol>
  );
}
