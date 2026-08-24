'use client';

import { useActionState } from 'react';
import { Button } from '@/components/ui/Button';
import { trackOrderAction } from '@/lib/orders/actions';
import { initialTrackOrderState } from '@/lib/orders/tracking-state';
import { OrderDetail } from './OrderDetail';

export function GuestTrackingForm() {
  const [state, formAction, isPending] = useActionState(trackOrderAction, initialTrackOrderState);

  return (
    <div className="space-y-8">
      <form action={formAction} noValidate className="space-y-4 rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
        {state.status === 'error' && (
          <div role="alert" className="rounded-xl border border-[color:var(--color-danger)]/30 bg-red-50 px-4 py-3 text-sm text-[color:var(--color-danger)]">
            {state.message}
          </div>
        )}

        <div>
          <label htmlFor="order_number" className="mb-1.5 block text-sm font-medium text-ink">
            Numéro de commande
          </label>
          <input
            id="order_number"
            name="order_number"
            type="text"
            required
            placeholder="TC-20260824-XXXXXXXXXX"
            className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
          />
        </div>

        <div>
          <label htmlFor="phone" className="mb-1.5 block text-sm font-medium text-ink">
            Numéro de téléphone utilisé à la commande
          </label>
          <input
            id="phone"
            name="phone"
            type="tel"
            required
            className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
          />
        </div>

        <Button type="submit" size="lg" disabled={isPending} className="w-full sm:w-auto">
          {isPending ? 'Recherche…' : 'Suivre ma commande'}
        </Button>
      </form>

      {state.status === 'found' && <OrderDetail order={state.order} />}
    </div>
  );
}
