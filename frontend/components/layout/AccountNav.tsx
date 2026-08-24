import Link from 'next/link';
import { logoutAction } from '@/lib/auth/actions';
import { getCurrentUser } from '@/lib/auth/server';

const DESKTOP_LINK = 'text-sm font-medium text-ink hover:text-brand-700';
const MOBILE_LINK = 'block rounded-lg px-3 py-2.5 text-base font-medium text-ink hover:bg-brand-50';

/**
 * Async Server Component — reads the session server-side (getCurrentUser
 * is wrapped in React's cache(), so rendering both variants on the same
 * page shares a single /auth/me round-trip). Renders as either a Client
 * or Server Component boundary depending on where it's mounted — here
 * it's a plain server child, no client JS needed even for the logout
 * button since it's a native <form action={Server Action}>.
 */
export async function AccountNav({ variant }: { variant: 'desktop' | 'mobile' }) {
  const user = await getCurrentUser();
  const linkClass = variant === 'desktop' ? DESKTOP_LINK : MOBILE_LINK;

  if (user === null) {
    return (
      <div className={variant === 'desktop' ? 'flex items-center gap-6' : 'flex flex-col gap-1'}>
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
    <div className={variant === 'desktop' ? 'flex items-center gap-6' : 'flex flex-col gap-1'}>
      {variant === 'mobile' && <p className="px-3 py-1 text-sm text-ink-muted">Bonjour {user.name}</p>}
      {variant === 'desktop' && <span className="text-sm text-ink-muted">Bonjour {user.name}</span>}
      <Link href="/compte" className={linkClass}>
        Compte
      </Link>
      <Link href="/compte/commandes" className={linkClass}>
        Commandes
      </Link>
      <form action={logoutAction}>
        <button type="submit" className={variant === 'desktop' ? DESKTOP_LINK : `${MOBILE_LINK} w-full text-left`}>
          Déconnexion
        </button>
      </form>
    </div>
  );
}
