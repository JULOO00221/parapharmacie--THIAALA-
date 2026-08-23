import Link from 'next/link';
import type { ComponentPropsWithoutRef } from 'react';
import { cn } from '@/lib/utils/cn';

const VARIANTS = {
  primary: 'bg-brand-600 text-white hover:bg-brand-700',
  secondary: 'bg-accent-600 text-white hover:bg-accent-700',
  outline: 'border border-border bg-surface-raised text-ink hover:bg-brand-50',
  ghost: 'text-ink hover:bg-brand-50',
} as const;

const SIZES = {
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-4 py-2.5 text-sm',
  lg: 'px-6 py-3 text-base',
} as const;

const baseClasses =
  'inline-flex items-center justify-center gap-2 rounded-full font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-50 disabled:pointer-events-none';

type Variant = keyof typeof VARIANTS;
type Size = keyof typeof SIZES;

interface CommonProps {
  variant?: Variant;
  size?: Size;
  className?: string;
}

type ButtonAsButton = CommonProps &
  ComponentPropsWithoutRef<'button'> & { href?: undefined };

type ButtonAsLink = CommonProps &
  ComponentPropsWithoutRef<typeof Link> & { href: string };

export function Button({ variant = 'primary', size = 'md', className, ...props }: ButtonAsButton | ButtonAsLink) {
  const classes = cn(baseClasses, VARIANTS[variant], SIZES[size], className);

  if ('href' in props && props.href !== undefined) {
    return <Link {...props} className={classes} />;
  }

  return <button {...(props as ComponentPropsWithoutRef<'button'>)} className={classes} />;
}
