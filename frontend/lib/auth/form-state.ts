/**
 * A "use server" module (lib/auth/actions.ts) can only export async
 * functions — the shared type and initial state used by useActionState
 * live here instead.
 */
export interface AuthFormState {
  error: string | null;
  fieldErrors?: Record<string, string[]>;
}

export const initialAuthFormState: AuthFormState = { error: null };
