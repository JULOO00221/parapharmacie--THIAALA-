import type { Metadata } from 'next';
import { CartPageView } from '@/components/cart/CartPageView';

export const metadata: Metadata = {
  title: 'Panier',
  alternates: { canonical: '/panier' },
};

export default function CartPage() {
  return <CartPageView />;
}
