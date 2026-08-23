import Link from 'next/link';

export function Footer() {
  return (
    <footer className="mt-16 border-t border-border bg-brand-900 text-brand-50">
      <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <p className="text-lg font-semibold">Tambacounda Cosmetix</p>
        <p className="mt-2 max-w-md text-sm text-brand-100">
          Parapharmacie et cosmétiques au Sénégal — produits de soin sélectionnés avec soin.
        </p>
        <nav aria-label="Navigation du pied de page" className="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm">
          <Link href="/" className="text-brand-100 hover:text-white">
            Accueil
          </Link>
          <Link href="/produits" className="text-brand-100 hover:text-white">
            Tous les produits
          </Link>
        </nav>
        <p className="mt-8 text-xs text-brand-200">
          © {new Date().getFullYear()} Tambacounda Cosmetix. Tous droits réservés.
        </p>
      </div>
    </footer>
  );
}
