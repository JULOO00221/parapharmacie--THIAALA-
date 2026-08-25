'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { ApiError } from '@/lib/api/client';
import { simulateMockWavePayment } from '@/lib/api/payments';

type Outcome = 'succeeded' | 'failed' | 'expired';

/**
 * Stands in for the real pay.wave.com checkout page — reached via
 * checkout_url exactly like the real Wave flow would redirect there.
 * Clicking a button here calls the dev-only mock simulate endpoint
 * (never the real /payments/wave/callback webhook), then redirects to
 * the SAME result pages a real Wave return would land on — those pages
 * re-verify the outcome with Laravel rather than trusting anything from
 * this page or the URL.
 */
export function MockWaveCheckout({ transactionId, orderNumber }: { transactionId: string; orderNumber: string }) {
  const router = useRouter();
  const [pending, setPending] = useState<Outcome | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function simulate(outcome: Outcome) {
    if (pending !== null) return;
    setPending(outcome);
    setError(null);

    try {
      await simulateMockWavePayment(transactionId, outcome);

      const destination = outcome === 'succeeded' ? '/paiement/succes' : '/paiement/echec';
      router.push(`${destination}?order=${encodeURIComponent(orderNumber)}`);
    } catch (err) {
      setPending(null);
      setError(err instanceof ApiError ? err.message : 'La simulation a échoué. Réessayez.');
    }
  }

  return (
    <div className="mx-auto max-w-md px-4 py-10 text-center sm:px-6">
      <div className="rounded-2xl border border-dashed border-amber-400 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Simulateur Wave (environnement de développement) — aucun appel réel à Wave n&apos;est effectué ici.
      </div>

      <h1 className="mt-6 text-xl font-bold text-ink">Paiement Wave</h1>
      <p className="mt-2 text-sm text-ink-muted">Référence de la tentative : {transactionId}</p>

      {error && (
        <p role="alert" className="mt-4 rounded-xl border border-[color:var(--color-danger)]/30 bg-red-50 px-4 py-3 text-sm text-[color:var(--color-danger)]">
          {error}
        </p>
      )}

      <div className="mt-8 space-y-3">
        <Button type="button" size="lg" disabled={pending !== null} className="w-full" onClick={() => simulate('succeeded')}>
          {pending === 'succeeded' ? 'Simulation…' : 'Simuler un paiement réussi'}
        </Button>
        <Button type="button" variant="outline" disabled={pending !== null} className="w-full" onClick={() => simulate('failed')}>
          {pending === 'failed' ? 'Simulation…' : 'Simuler un paiement échoué'}
        </Button>
        <Button type="button" variant="ghost" disabled={pending !== null} className="w-full" onClick={() => simulate('expired')}>
          {pending === 'expired' ? 'Simulation…' : 'Simuler une expiration'}
        </Button>
      </div>
    </div>
  );
}
