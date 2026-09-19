import type { SVGProps } from 'react';

/**
 * Icônes au trait du layout, reprises des maquettes. Toujours décoratives
 * (aria-hidden) : le libellé accessible est porté par le bouton ou le lien.
 */
type IconProps = SVGProps<SVGSVGElement>;

function Icon({ children, strokeWidth = 1.8, ...props }: IconProps) {
  return (
    <svg
      aria-hidden="true"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      {...props}
    >
      {children}
    </svg>
  );
}

export function WhatsAppIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M3.8 20.2l1.3-4a8 8 0 1 1 3 3z" />
    </Icon>
  );
}

export function SearchIcon(props: IconProps) {
  return (
    <Icon strokeWidth={2} {...props}>
      <circle cx="11" cy="11" r="7" />
      <path d="M20 20l-3.5-3.5" />
    </Icon>
  );
}

export function UserIcon(props: IconProps) {
  return (
    <Icon strokeWidth={1.7} {...props}>
      <circle cx="12" cy="8" r="4" />
      <path d="M4.5 20c1.3-3.6 4.1-5.4 7.5-5.4s6.2 1.8 7.5 5.4" />
    </Icon>
  );
}

export function BagIcon(props: IconProps) {
  return (
    <Icon strokeWidth={1.7} {...props}>
      <path d="M4 7h16l-1.4 12.2a2 2 0 0 1-2 1.8H7.4a2 2 0 0 1-2-1.8z" />
      <path d="M9 7V5.6A3 3 0 0 1 12 3a3 3 0 0 1 3 2.6V7" />
    </Icon>
  );
}

export function MenuIcon(props: IconProps) {
  return (
    <Icon strokeWidth={1.9} {...props}>
      <path d="M4 7h16M4 12h16M4 17h16" />
    </Icon>
  );
}

export function CloseIcon(props: IconProps) {
  return (
    <Icon strokeWidth={1.9} {...props}>
      <path d="M6 6l12 12M18 6 6 18" />
    </Icon>
  );
}

export function PlusIcon(props: IconProps) {
  return (
    <Icon strokeWidth={1.9} {...props}>
      <path d="M12 6v12M6 12h12" />
    </Icon>
  );
}

export function CheckIcon(props: IconProps) {
  return (
    <Icon strokeWidth={2} {...props}>
      <path d="M5 12.5l4.5 4.5L19 7.5" />
    </Icon>
  );
}

export function ChevronDownIcon(props: IconProps) {
  return (
    <Icon strokeWidth={1.9} {...props}>
      <path d="m7 10 5 5 5-5" />
    </Icon>
  );
}
