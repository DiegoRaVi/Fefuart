import type { ApiError, ApiSuccess } from './types';
import { clearSession, getSessionToken } from '../lib/session';

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? 'http://127.0.0.1:8000/api/v1';

type ApiRequestErrorOptions = {
  status: number;
  code?: string;
  details?: Record<string, string[]>;
};

export class ApiRequestError extends Error {
  status: number;
  code?: string;
  details?: Record<string, string[]>;

  constructor(message: string, options: ApiRequestErrorOptions) {
    super(message);
    this.name = 'ApiRequestError';
    this.status = options.status;
    this.code = options.code;
    this.details = options.details;
  }
}

export function isApiRequestError(error: unknown): error is ApiRequestError {
  return error instanceof ApiRequestError;
}

function buildUrl(path: string): string {
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }

  const base = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;

  return `${base}${normalizedPath}`;
}

export function buildQueryString(params: Record<string, string | number | undefined | null>): string {
  const search = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      search.set(key, String(value));
    }
  });

  const query = search.toString();
  return query ? `?${query}` : '';
}

function parseJsonSafely(payload: string): unknown {
  if (!payload.trim()) {
    return null;
  }

  try {
    return JSON.parse(payload);
  } catch {
    return null;
  }
}

function isApiError(payload: unknown): payload is ApiError {
  if (!payload || typeof payload !== 'object') {
    return false;
  }

  const candidate = payload as Partial<ApiError>;
  return candidate.success === false && !!candidate.error;
}

function isApiSuccess<TData>(payload: unknown): payload is ApiSuccess<TData> {
  if (!payload || typeof payload !== 'object') {
    return false;
  }

  return (payload as Partial<ApiSuccess<TData>>).success === true;
}

function flattenErrorDetails(details?: Record<string, string[]>): string {
  if (!details) {
    return '';
  }

  const flattenedMessages = Object.values(details).flat();
  if (!flattenedMessages.length) {
    return '';
  }

  return flattenedMessages.join(' | ');
}

export async function apiRequest<TData>(path: string, init: RequestInit = {}): Promise<ApiSuccess<TData>> {
  const token = getSessionToken();

  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');

  const isFormData = init.body instanceof FormData;
  if (!isFormData && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  if (token && !headers.has('Authorization')) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  let response: Response;

  try {
    response = await fetch(buildUrl(path), {
      ...init,
      headers,
    });
  } catch {
    throw new ApiRequestError('No se pudo conectar con la API. Verifica que el backend local este levantado.', {
      status: 0,
    });
  }

  const rawBody = await response.text();
  const parsedBody = parseJsonSafely(rawBody);

  if (!response.ok) {
    if (response.status === 401) {
      clearSession();
    }

    if (isApiError(parsedBody)) {
      const detailsMessage = flattenErrorDetails(parsedBody.error.details);
      const finalMessage = detailsMessage ? `${parsedBody.error.message} | ${detailsMessage}` : parsedBody.error.message;

      throw new ApiRequestError(finalMessage, {
        status: response.status,
        code: parsedBody.error.code,
        details: parsedBody.error.details,
      });
    }

    throw new ApiRequestError(`Request failed with status ${response.status}.`, {
      status: response.status,
    });
  }

  if (isApiError(parsedBody)) {
    throw new ApiRequestError(parsedBody.error.message, {
      status: response.status,
      code: parsedBody.error.code,
      details: parsedBody.error.details,
    });
  }

  if (!isApiSuccess<TData>(parsedBody)) {
    throw new ApiRequestError('Formato de respuesta invalido de la API.', {
      status: response.status,
    });
  }

  return parsedBody;
}
