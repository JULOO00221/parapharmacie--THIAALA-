/**
 * Minimal class-name combiner. No clsx/tailwind-merge dependency added
 * for such a small component set — this just joins truthy class strings.
 */
export function cn(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(' ');
}
