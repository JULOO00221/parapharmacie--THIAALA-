import { WhatsAppIcon } from '@/components/ui/icons';
import { WHATSAPP_LINK_PROPS, whatsappHref } from '@/lib/config/contact';
import { cn } from '@/lib/utils/cn';

const MESSAGE = 'Bonjour, je souhaite passer une commande auprès de la Parapharmacie THIAALA.';

/**
 * WhatsApp est un vrai bouton, au même niveau que le panier (DESIGN.md §4.2).
 * `pill` : pastille verte avec libellé (en-tête desktop).
 * `icon` : cible 44 × 44 sans libellé visible (en-tête mobile).
 */
export function WhatsAppButton({ variant, className }: { variant: 'pill' | 'icon'; className?: string }) {
  if (variant === 'icon') {
    return (
      <a
        href={whatsappHref(MESSAGE)}
        {...WHATSAPP_LINK_PROPS}
        aria-label="Commander sur WhatsApp"
        className={cn('flex h-11 w-11 items-center justify-center rounded-full text-vert hover:bg-ivoire', className)}
      >
        <WhatsAppIcon className="h-5 w-5" />
      </a>
    );
  }

  return (
    <a
      href={whatsappHref(MESSAGE)}
      {...WHATSAPP_LINK_PROPS}
      className={cn(
        'flex h-[46px] shrink-0 items-center gap-2.5 rounded-full bg-vert px-5 text-sm font-semibold text-white transition-colors hover:bg-brand-700',
        className,
      )}
    >
      <WhatsAppIcon className="h-[17px] w-[17px]" />
      <span>WhatsApp</span>
    </a>
  );
}
