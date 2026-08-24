import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { LoginForm } from '@/components/auth/LoginForm';
import { getCurrentUser } from '@/lib/auth/server';

export const metadata: Metadata = {
  title: 'Connexion',
  alternates: { canonical: '/connexion' },
};

export default async function LoginPage() {
  const user = await getCurrentUser();
  if (user !== null) {
    redirect('/compte');
  }

  return (
    <div className="mx-auto max-w-md px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-bold text-ink sm:text-3xl">Connexion</h1>
      <div className="mt-8 rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
        <LoginForm />
      </div>
      <p className="mt-4 text-center text-sm text-ink-muted">
        Pas encore de compte ?{' '}
        <Link href="/inscription" className="font-medium text-brand-700 hover:underline">
          Créer un compte
        </Link>
      </p>
    </div>
  );
}
