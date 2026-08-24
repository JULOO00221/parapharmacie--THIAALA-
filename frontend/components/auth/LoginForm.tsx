'use client';

import { useActionState } from 'react';
import { loginAction } from '@/lib/auth/actions';
import { initialAuthFormState } from '@/lib/auth/form-state';
import { Button } from '@/components/ui/Button';

export function LoginForm() {
  const [state, formAction, isPending] = useActionState(loginAction, initialAuthFormState);

  return (
    <form action={formAction} noValidate className="space-y-4">
      {state.error && (
        <div role="alert" className="rounded-xl border border-[color:var(--color-danger)]/30 bg-red-50 px-4 py-3 text-sm text-[color:var(--color-danger)]">
          {state.error}
        </div>
      )}

      <div>
        <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-ink">
          Email
        </label>
        <input
          id="email"
          name="email"
          type="email"
          required
          autoComplete="email"
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
        />
      </div>

      <div>
        <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-ink">
          Mot de passe
        </label>
        <input
          id="password"
          name="password"
          type="password"
          required
          autoComplete="current-password"
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
        />
      </div>

      <Button type="submit" size="lg" disabled={isPending} className="w-full">
        {isPending ? 'Connexion en cours…' : 'Se connecter'}
      </Button>
    </form>
  );
}
