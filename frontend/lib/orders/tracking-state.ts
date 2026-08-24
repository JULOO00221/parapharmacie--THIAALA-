import type { Order } from '../api/types';

/**
 * A "use server" module (lib/orders/actions.ts) can only export async
 * functions — the shared type and initial state used by useActionState
 * live here instead.
 */
export type TrackOrderState =
  | { status: 'idle' }
  | { status: 'error'; message: string }
  | { status: 'found'; order: Order };

export const initialTrackOrderState: TrackOrderState = { status: 'idle' };
