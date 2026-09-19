'use client';

import Form from 'next/form';
import { useId } from 'react';
import type { ProductSort } from '@/lib/api/products';
import { SORT_OPTIONS } from '@/lib/catalog/params';
import { cn } from '@/lib/utils/cn';

/**
 * Tri — formulaire GET (next/form) envoyé dès que la valeur change. Sans JavaScript,
 * le bouton « Trier » (visible au focus clavier) prend le relais.
 */
export function SortSelect({
  action,
  hidden,
  value,
  showLabel,
  className,
}: {
  action: string;
  hidden: Array<[string, string]>;
  value: ProductSort;
  showLabel: boolean;
  className?: string;
}) {
  const id = useId();

  return (
    <Form action={action} scroll={false} className={cn('flex items-center gap-3', className)}>
      {hidden.map(([name, fieldValue]) => (
        <input key={`${name}=${fieldValue}`} type="hidden" name={name} value={fieldValue} />
      ))}
      <label htmlFor={id} className={showLabel ? 'shrink-0 text-sm text-texte-discret' : 'sr-only'}>
        Trier par
      </label>
      <select
        id={id}
        name="sort"
        defaultValue={value}
        onChange={(event) => event.currentTarget.form?.requestSubmit()}
        className="h-12 w-full min-w-0 cursor-pointer rounded-full border border-bordure-forte bg-blanc px-4 text-[14.5px] text-encre focus:border-vert focus:outline-none lg:h-11 lg:w-auto lg:border-bordure lg:text-sm"
      >
        {SORT_OPTIONS.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <button type="submit" className="sr-only focus:not-sr-only focus:rounded-full focus:border focus:border-vert focus:px-3 focus:py-2 focus:text-sm">
        Trier
      </button>
    </Form>
  );
}
