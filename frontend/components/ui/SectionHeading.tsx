import Link from 'next/link';

function ArrowIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-[17px] w-[17px]">
      <path d="M5 12h13" />
      <path d="M13 6l6 6-6 6" />
    </svg>
  );
}

/**
 * En-tête de section : surtitre en or, titre Fraunces, lien « Tout voir »
 * optionnel aligné à droite sur desktop.
 */
export function SectionHeading({
  id,
  eyebrow,
  title,
  link,
}: {
  id?: string;
  eyebrow: string;
  title: string;
  link?: { href: string; label: string };
}) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
      <div className="flex flex-col gap-2.5 lg:gap-3">
        <p className="surtitre text-[10.5px] tracking-[0.2em] text-or lg:text-[12.5px] lg:tracking-[0.22em]">{eyebrow}</p>
        <h2 id={id} className="font-titre text-[28px] leading-tight text-vert lg:text-[40px]">
          {title}
        </h2>
      </div>
      {link && (
        <Link
          href={link.href}
          className="flex h-11 items-center gap-2 text-[14px] font-semibold text-vert hover:text-or lg:text-[15px]"
        >
          {link.label}
          <ArrowIcon />
        </Link>
      )}
    </div>
  );
}
