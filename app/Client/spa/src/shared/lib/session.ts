import type { SessionUser } from '../api/types';

const TOKEN_KEY = 'fefuart_v1_token';
const USER_KEY = 'fefuart_v1_user';
const SESSION_EVENT = 'fefuart-session-updated';

function emitSessionUpdate(): void {
  window.dispatchEvent(new Event(SESSION_EVENT));
}

export function getSessionEventName(): string {
  return SESSION_EVENT;
}

export function setSession(token: string, user: SessionUser): void {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(USER_KEY, JSON.stringify(user));
  emitSessionUpdate();
}

export function clearSession(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
  emitSessionUpdate();
}

export function getSessionToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function getSessionUser(): SessionUser | null {
  const raw = localStorage.getItem(USER_KEY);

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as SessionUser;
  } catch {
    return null;
  }
}
