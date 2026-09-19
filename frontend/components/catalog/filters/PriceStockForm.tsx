'use client';

import Form from 'next/form';
import { useId, useRef } from 'react';
import { cn } from '@/lib/utils/cn';

/**
 * Prix et disponibilité — formulaire GET (next/form : navigation côté
 * client, et formulaire natif sans JavaScript). `autoSubmit` (colonne desktop) applique « En stock » dès que
 * la case change ; le prix s'applique avec le bouton, pour ne pas recharger
 * la page à chaque chiffre tapé. Dans le panneau mobile, tout s'applique
 * avec le bouton du bas.
 */
export function PriceStockForm({
  action,
  hidden,
  priceMin,
  priceMax,
  inStock,
  autoSubmit,
  submitLabel,
  showLegend = true,
}: {
  action: string;
  hidden: Array<[string, string]>;
  priceMin?: string;
  priceMax?: string;
  inStock: boolean;
  autoSubmit: boolean;
  submitLabel: string;
  /** Masquée visuellement quand un titre de section l'annonce déjà (panneau mobile). */
  showLegend?: boolean;
}) {
  const formRef = useRef<HTMLFormElement>(null);
  const id = useId();

  return (
    <Form
      ref={formRef}
      action={action}
      scroll={false}
      // Champs prix vides : désactivés le temps de l'envoi pour qu'ils
      // n'apparaissent pas dans l'URL (?price_min=&price_max=), puis
      // réactivés — la navigation côté client ne démonte pas le formulaire.
      onSubmit={(event) => {
        const empty = [...event.currentTarget.querySelectorAll<HTMLInputElement>('input[type="number"]')].filter(
          (input) => input.value === '',
        );
        for (const input of empty) input.disabled = true;
        window.setTimeout(() => {
          for (const input of empty) input.disabled = false;
        });
      }}
      className="flex flex-col gap-4"
    >
      {hidden.map(([name, value]) => (
        <input key={`${name}=${value}`} type="hidden" name={name} value={value} />
      ))}

      <fieldset className="flex flex-col gap-3">
        <legend className={showLegend ? 'mb-3 text-[11.5px] font-semibold uppercase tracking-[0.18em] text-texte-discret' : 'sr-only'}>
          Prix (FCFA)
        </legend>
        <div className="flex items-center gap-2.5">
          <label htmlFor={`${id}-min`} className="sr-only">
            Prix minimum
          </label>
          <input
            id={`${id}-min`}
            type="number"
            name="price_min"
            inputMode="numeric"
            min={0}
            step={100}
            placeholder="Min"
            defaultValue={priceMin}
            className="h-11 w-full min-w-0 rounded-[10px] border border-bordure bg-ivoire px-3 text-base text-encre placeholder:text-texte-discret focus:border-vert focus:outline-none lg:text-sm"
          />
          <span aria-hidden="true" className="text-texte-discret">
            —
          </span>
          <label htmlFor={`${id}-max`} className="sr-only">
            Prix maximum
          </label>
          <input
            id={`${id}-max`}
            type="number"
            name="price_max"
            inputMode="numeric"
            min={0}
            step={100}
            placeholder="Max"
            defaultValue={priceMax}
            className="h-11 w-full min-w-0 rounded-[10px] border border-bordure bg-ivoire px-3 text-base text-encre placeholder:text-texte-discret focus:border-vert focus:outline-none lg:text-sm"
          />
        </div>
      </fieldset>

      <label className="flex min-h-11 cursor-pointer items-center gap-[11px] text-[14.5px] text-encre">
        <input
          type="checkbox"
          name="in_stock"
          value="1"
          defaultChecked={inStock}
          onChange={() => {
            if (autoSubmit) formRef.current?.requestSubmit();
          }}
          className="h-[17px] w-[17px] accent-vert"
        />
        En stock uniquement
      </label>

      <button
        type="submit"
        className={cn(
          'flex items-center justify-center rounded-full font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vert',
          autoSubmit
            ? 'h-11 border border-bordure-forte text-sm text-vert hover:border-vert'
            : 'h-[52px] bg-vert text-[15px] text-white hover:bg-brand-700',
        )}
      >
        {submitLabel}
      </button>
    </Form>
  );
}
