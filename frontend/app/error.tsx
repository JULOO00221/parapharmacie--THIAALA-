'use client';

import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';

export default function GlobalError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
      <EmptyState
        title="Une erreur est survenue"
        description="Impossible de charger cette page pour le moment. Vérifiez votre connexion et réessayez."
        action={
          <div className="flex gap-3">
            <Button onClick={reset} variant="primary">
              Réessayer
            </Button>
            <Button href="/" variant="outline">
              Retour à l&apos;accueil
            </Button>
          </div>
        }
      />
    </div>
  );
}
