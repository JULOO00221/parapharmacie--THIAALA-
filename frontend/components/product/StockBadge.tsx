import { Badge } from '@/components/ui/Badge';
import type { StockStatus } from '@/lib/api/types';

const CONFIG: Record<StockStatus, { label: string; tone: 'success' | 'warning' | 'danger' }> = {
  in_stock: { label: 'En stock', tone: 'success' },
  low_stock: { label: 'Stock faible', tone: 'warning' },
  out_of_stock: { label: 'Rupture de stock', tone: 'danger' },
};

export function StockBadge({ status }: { status: StockStatus }) {
  const { label, tone } = CONFIG[status];

  return <Badge tone={tone}>{label}</Badge>;
}
