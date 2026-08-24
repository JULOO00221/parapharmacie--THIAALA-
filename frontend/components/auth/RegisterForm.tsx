'use client';

import { useActionState } from 'react';
import { registerAction } from '@/lib/auth/actions';
import { initialAuthFormState } from '@/lib/auth/form-state';
import { Button } from '@/components/ui/Button';

function fieldError(errors: Record<string, string[]> | undefined, field: string): string | undefined {
  return errors?.[field]?.[0];
}

export function RegisterForm() {
  const [state, formAction, isPending] = useActionState(registerAction, initialAuthFormState);

  const nameError = fieldError(state.fieldErrors, 'name');
  const emailError = fieldError(state.fieldErrors, 'email');
  const passwordError = fieldError(state.fieldErrors, 'password');

  return (
    <form action={formAction} noValidate className="space-y-4">
      {state.error && (
        <div role="alert" className="rounded-xl border border-[color:var(--color-danger)]/30 bg-red-50 px-4 py-3 text-sm text-[color:var(--color-danger)]">
          {state.error}
        </div>
      )}

      <div>
        <label htmlFor="name" className="mb-1.5 block text-sm font-medium text-ink">
          Nom complet
        </label>
        <input
          id="name"
          name="name"
          type="text"
          required
          autoComplete="name"
          aria-invalid={nameError ? true : undefined}
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
        />
        {nameError && <p className="mt-1 text-xs text-[color:var(--color-danger)]">{nameError}</p>}
      </div>

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
          aria-invalid={emailError ? true : undefined}
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
        />
        {emailError && <p className="mt-1 text-xs text-[color:var(--color-danger)]">{emailError}</p>}
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
          autoComplete="new-password"
          aria-invalid={passwordError ? true : undefined}
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
        />
        {passwordError && <p className="mt-1 text-xs text-[color:var(--color-danger)]">{passwordError}</p>}
      </div>

      <div>
        <label htmlFor="password_confirmation" className="mb-1.5 block text-sm font-medium text-ink">
          Confirmer le mot de passe
        </label>
        <input
          id="password_confirmation"
          name="password_confirmation"
          type="password"
          required
          autoComplete="new-password"
          className="w-full rounded-lg border border-border bg-surface-raised px-3 py-2.5 text-sm"
        />
      </div>

      <Button type="submit" size="lg" disabled={isPending} className="w-full">
        {isPending ? 'Création du compte…' : "Créer mon compte"}
      </Button>
    </form>
  );
}
