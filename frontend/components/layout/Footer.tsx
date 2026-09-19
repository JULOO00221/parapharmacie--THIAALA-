import Image from 'next/image';
import Link from 'next/link';
import { WhatsAppIcon } from '@/components/ui/icons';
import { WHATSAPP_LINK_PROPS, whatsappDisplayNumber, whatsappHref } from '@/lib/config/contact';

const HEADING = 'mb-1 text-[11.5px] font-semibold uppercase tracking-[0.18em] text-sur-vert-discret';
const LINK = 'flex min-h-11 items-center text-sur-vert hover:text-white sm:min-h-0 sm:py-1';

/**
 * Pied de page (DESIGN.md §2) : fond vert, quatre colonnes — identité,
 * Boutique, Aide, Contact. Seuls les liens vers des pages qui existent
 * sont affichés. Les informations de confiance encore manquantes (nom de
 * la pharmacie, pharmacien titulaire, adresse, horaires, mentions légales)
 * ne sont pas inventées : elles s'ajouteront ici quand elles seront fournies.
 */
export function Footer() {
  const whatsappNumber = whatsappDisplayNumber();

  return (
    <footer className="mt-16 bg-vert text-sur-vert">
      <div className="mx-auto max-w-[1440px] px-5 pb-6 pt-9 sm:px-8 lg:px-16 lg:pb-[30px] lg:pt-14">
        <div className="grid grid-cols-2 gap-x-6 gap-y-8 lg:grid-cols-[1.5fr_1fr_1fr_1.2fr] lg:gap-12">
          <div className="col-span-2 flex flex-col gap-4 lg:col-span-1">
            {/*
              Logo officiel (SVG) sur une plaque blanche : sur le vert du pied
              de page, le nom « THIAALA » (dégradé vert foncé) disparaît. À
              remplacer par la version blanche du logo dès qu'elle existe
              (DESIGN.md §5) — la plaque pourra alors être retirée.
            */}
            <Link
              href="/"
              aria-label="Parapharmacie THIAALA — accueil"
              className="block w-fit rounded-[14px] bg-blanc px-2.5 py-1.5"
            >
              <Image
                src="/logo-thiaala.svg"
                alt="Parapharmacie THIAALA — Santé, beauté, bien-être"
                width={615}
                height={242}
                unoptimized
                className="h-14 w-auto lg:h-16"
              />
            </Link>
            <p className="max-w-[290px] text-[13.5px] leading-relaxed lg:text-sm">
              Votre parapharmacie à Tambacounda : soins du visage, du corps, cheveux, bébé et hygiène, livrés dans
              toute la région.
            </p>
          </div>

          <nav aria-label="Boutique" className="flex flex-col text-[13.5px] lg:gap-1.5 lg:text-sm">
            <p className={HEADING}>Boutique</p>
            <Link href="/produits" className={LINK}>
              Tous les produits
            </Link>
            <Link href="/panier" className={LINK}>
              Mon panier
            </Link>
          </nav>

          <nav aria-label="Aide" className="flex flex-col text-[13.5px] lg:gap-1.5 lg:text-sm">
            <p className={HEADING}>Aide</p>
            <Link href="/suivi-commande" className={LINK}>
              Suivre ma commande
            </Link>
            <Link href="/connexion" className={LINK}>
              Connexion
            </Link>
            <Link href="/inscription" className={LINK}>
              Créer un compte
            </Link>
          </nav>

          <div className="col-span-2 flex flex-col gap-2 text-[13.5px] lg:col-span-1 lg:text-sm">
            <p className={HEADING}>Nous contacter</p>
            <p>Tambacounda, Sénégal</p>
            <a
              href={whatsappHref()}
              {...WHATSAPP_LINK_PROPS}
              className="flex min-h-11 items-center gap-2 text-sur-vert hover:text-white sm:min-h-0"
            >
              <WhatsAppIcon className="h-4 w-4" />
              WhatsApp {whatsappNumber}
            </a>
            <p>Retrait en boutique ou livraison dans la région.</p>
          </div>
        </div>

        <div className="mt-8 flex flex-col gap-2 border-t border-white/15 pt-4 text-[11.5px] text-sur-vert-discret lg:mt-10 lg:flex-row lg:items-center lg:justify-between lg:pt-[22px] lg:text-[12.5px]">
          <p>© {new Date().getFullYear()} Parapharmacie THIAALA — Tous droits réservés.</p>
          <p>Paiement à la livraison · Wave · Retrait en boutique</p>
        </div>
      </div>
    </footer>
  );
}
