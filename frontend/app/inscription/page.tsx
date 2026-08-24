import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { RegisterForm } from '@/components/auth/RegisterForm';
import { getCurrentUser } from '@/lib/auth/server';

export const metadata: Metadata = {
  title: 'Créer un compte',
  alternates: { canonical: '/inscription' },
};

export default async function RegisterPage() {
  const user = await getCurrentUser();
  if (user !== null) {
    redirect('/compte');
  }

  return (
    <div className="mx-auto max-w-md px-4 py-10 sm:px-6">
      <h1 className="text-2xl font-bold text-ink sm:text-3xl">Créer un compte</h1>
      <div className="mt-8 rounded-2xl border border-border bg-surface-raised p-4 sm:p-6">
        <RegisterForm />
      </div>
      <p className="mt-4 text-center text-sm text-ink-muted">
        Déjà un compte ?{' '}
        <Link href="/connexion" className="font-medium text-brand-700 hover:underline">
          Se connecter
        </Link>
      </p>
    </div>
  );
}
