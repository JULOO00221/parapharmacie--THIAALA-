import type { Metadata } from 'next';
import { GuestTrackingForm } from '@/components/orders/GuestTrackingForm';

export const metadata: Metadata = {
  title: 'Suivre ma commande',
  alternates: { canonical: '/suivi-commande' },
};

export default function GuestTrackingPage() {
  return (
    <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-bold text-ink sm:text-3xl">Suivre ma commande</h1>
      <p className="mt-2 text-sm text-ink-muted">
        Retrouvez votre commande avec son numéro et le numéro de téléphone utilisé au moment de la commande.
      </p>

      <div className="mt-8">
        <GuestTrackingForm />
      </div>
    </div>
  );
}
