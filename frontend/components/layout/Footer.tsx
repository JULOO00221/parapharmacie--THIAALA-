import Link from 'next/link';

export function Footer() {
  return (
    <footer className="mt-16 border-t border-border bg-brand-900 text-brand-50">
      <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div className="grid grid-cols-1 gap-10 sm:grid-cols-3">
          <div>
            <p className="text-lg font-semibold">Tambacounda Cosmetix</p>
            <p className="mt-2 max-w-xs text-sm text-brand-100">
              Parapharmacie et cosmétiques au Sénégal — produits de soin sélectionnés avec soin.
            </p>
          </div>

          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-brand-200">Boutique</p>
            <nav aria-label="Navigation du pied de page" className="mt-3 flex flex-col gap-2 text-sm">
              <Link href="/" className="text-brand-100 hover:text-white">
                Accueil
              </Link>
              <Link href="/produits" className="text-brand-100 hover:text-white">
                Tous les produits
              </Link>
            </nav>
          </div>

          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-brand-200">Aide</p>
            <nav aria-label="Aide et compte" className="mt-3 flex flex-col gap-2 text-sm">
              <Link href="/suivi-commande" className="text-brand-100 hover:text-white">
                Suivre ma commande
              </Link>
              <Link href="/connexion" className="text-brand-100 hover:text-white">
                Connexion
              </Link>
              <Link href="/inscription" className="text-brand-100 hover:text-white">
                Créer un compte
              </Link>
            </nav>
          </div>
        </div>

        <p className="mt-10 border-t border-brand-700 pt-6 text-xs text-brand-200">
          © {new Date().getFullYear()} Tambacounda Cosmetix. Tous droits réservés.
        </p>
      </div>
    </footer>
  );
}
