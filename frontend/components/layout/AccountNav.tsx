import Link from 'next/link';
import { logoutAction } from '@/lib/auth/actions';
import { getCurrentUser } from '@/lib/auth/server';

const DESKTOP_LINK = 'flex min-h-10 items-center rounded-lg px-2 text-sm text-encre hover:bg-ivoire hover:text-vert';
const MOBILE_LINK = 'flex min-h-11 items-center rounded-xl px-3 text-base font-medium text-encre hover:bg-ivoire';

/**
 * Async Server Component — reads the session server-side (getCurrentUser
 * is wrapped in React's cache(), so rendering both variants on the same
 * page shares a single /auth/me round-trip). Renders as either a Client
 * or Server Component boundary depending on where it's mounted — here
 * it's a plain server child, no client JS needed even for the logout
 * button since it's a native <form action={Server Action}>. The desktop
 * variant is rendered inside the Header's account dropdown, hence stacked.
 */
export async function AccountNav({ variant }: { variant: 'desktop' | 'mobile' }) {
  const user = await getCurrentUser();
  const linkClass = variant === 'desktop' ? DESKTOP_LINK : MOBILE_LINK;

  if (user === null) {
    return (
      <div className={variant === 'desktop' ? 'flex flex-col gap-0.5' : 'flex flex-col gap-1'}>
        <Link href="/suivi-commande" className={linkClass}>
          Suivi commande
        </Link>
        <Link href="/connexion" className={linkClass}>
          Connexion
        </Link>
      </div>
    );
  }

  return (
    <div className={variant === 'desktop' ? 'flex flex-col gap-0.5' : 'flex flex-col gap-1'}>
      {variant === 'mobile' && <p className="px-3 py-1 text-sm text-texte-discret">Bonjour {user.name}</p>}
      {variant === 'desktop' && (
        <span className="truncate px-2 pb-1 text-sm text-texte-discret" title={`Bonjour ${user.name}`}>
          Bonjour {user.name}
        </span>
      )}
      <Link href="/compte" className={linkClass}>
        Compte
      </Link>
      <Link href="/compte/commandes" className={linkClass}>
        Commandes
      </Link>
      <form action={logoutAction}>
        <button type="submit" className={`${linkClass} w-full text-left`}>
          Déconnexion
        </button>
      </form>
    </div>
  );
}
