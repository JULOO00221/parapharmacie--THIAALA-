/**
 * Pages to show: always the first and the last, the current one and its
 * neighbours; gaps become an ellipsis. E.g. 1 … 4 5 6 … 20.
 */
export function pageWindow(current: number, last: number): Array<number | 'gap'> {
  const pages = new Set([1, last, current - 1, current, current + 1].filter((page) => page >= 1 && page <= last));
  const sorted = [...pages].sort((a, b) => a - b);

  return sorted.flatMap((page, index) => {
    const previous = sorted[index - 1];
    if (previous === undefined || page - previous === 1) return [page];
    // A gap of exactly one page shows that page instead of an ellipsis.
    return page - previous === 2 ? [page - 1, page] : (['gap', page] as const);
  });
}
