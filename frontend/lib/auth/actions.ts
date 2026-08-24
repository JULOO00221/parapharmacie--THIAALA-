'use server';

import { redirect } from 'next/navigation';
import { ApiError } from '../api/client';
import { apiLogin, apiLogout, apiRegister } from './api';
import type { AuthFormState } from './form-state';
import { clearAuthCookie, getAuthToken, setAuthCookie } from './server';

function messageForAuthError(error: unknown, invalidCredentialsMessage: string): AuthFormState {
  if (error instanceof ApiError) {
    if (error.status === 422) {
      return { error: invalidCredentialsMessage, fieldErrors: error.payload.errors };
    }
    if (error.status === 429) {
      return { error: 'Trop de tentatives. Merci de réessayer dans quelques instants.' };
    }
    if (error.status === 0) {
      return { error: 'Impossible de contacter le serveur. Vérifiez votre connexion, puis réessayez.' };
    }
  }

  return { error: 'Une erreur est survenue. Veuillez réessayer.' };
}

export async function loginAction(_prevState: AuthFormState, formData: FormData): Promise<AuthFormState> {
  const email = String(formData.get('email') ?? '').trim();
  const password = String(formData.get('password') ?? '');

  if (!email || !password) {
    return { error: 'Merci de renseigner votre email et votre mot de passe.' };
  }

  let token: string;
  try {
    const response = await apiLogin({ email, password });
    token = response.token;
  } catch (error) {
    return messageForAuthError(error, 'Identifiants invalides.');
  }

  await setAuthCookie(token);
  redirect('/compte');
}

export async function registerAction(_prevState: AuthFormState, formData: FormData): Promise<AuthFormState> {
  const name = String(formData.get('name') ?? '').trim();
  const email = String(formData.get('email') ?? '').trim();
  const password = String(formData.get('password') ?? '');
  const passwordConfirmation = String(formData.get('password_confirmation') ?? '');

  if (!name || !email || !password || !passwordConfirmation) {
    return { error: 'Merci de renseigner tous les champs.' };
  }

  let token: string;
  try {
    const response = await apiRegister({
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    });
    token = response.token;
  } catch (error) {
    return messageForAuthError(error, 'Veuillez corriger les champs indiqués ci-dessous.');
  }

  // L'inscription connecte directement (Laravel renvoie déjà un token) —
  // aucune étape de connexion supplémentaire.
  await setAuthCookie(token);
  redirect('/compte');
}

/**
 * Best-effort: la révocation serveur du token ne doit jamais empêcher la
 * déconnexion locale. Un échec réseau vers Laravel n'a pas le droit de
 * laisser le cookie Next.js intact.
 */
export async function logoutAction(): Promise<void> {
  const token = await getAuthToken();

  if (token !== null) {
    try {
      await apiLogout(token);
    } catch {
      // Best-effort — voir le commentaire ci-dessus.
    }
  }

  await clearAuthCookie();
  redirect('/');
}
