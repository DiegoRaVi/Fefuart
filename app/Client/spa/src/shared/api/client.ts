import axios, { type InternalAxiosRequestConfig } from 'axios'

import { toApiError } from './errors'

/** Metodos que Laravel protege con el token CSRF. */
const METODOS_CON_ESTADO = new Set(['post', 'put', 'patch', 'delete'])

const COOKIE_CSRF = 'XSRF-TOKEN'

/**
 * D2 — Sanctum en modo SPA.
 *
 * `withCredentials` hace que la cookie de sesion viaje en cada peticion. No
 * hay token en cabecera ni nada guardado en `localStorage`: en v1 el JWT
 * vivia ahi (`auth.js:7-10`), que es lo que convertia cualquier XSS en robo
 * de sesion (SEC-005) y hacia el acceso irrevocable (SEC-011).
 *
 * Las rutas son relativas a proposito. El proxy de Vite las manda a Laravel
 * conservando el origen `localhost:5173`, que es lo que hace que Sanctum
 * trate la peticion como stateful y que la cookie sea first-party.
 */
export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: COOKIE_CSRF,
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

function leerCookie(nombre: string): string | null {
  const encontrada = document.cookie
    .split('; ')
    .find((c) => c.startsWith(`${nombre}=`))

  return encontrada ? decodeURIComponent(encontrada.slice(nombre.length + 1)) : null
}

/**
 * Una sola peticion en vuelo aunque varias mutaciones arranquen a la vez:
 * sin esto, abrir la pagina de registro y pulsar dos veces dispararia dos
 * llamadas a csrf-cookie y la segunda invalidaria el token de la primera.
 */
let cookieEnVuelo: Promise<void> | null = null

export async function asegurarCookieCsrf(): Promise<void> {
  if (leerCookie(COOKIE_CSRF)) {
    return
  }

  cookieEnVuelo ??= axios
    .get('/sanctum/csrf-cookie', { withCredentials: true })
    .then(() => undefined)
    .finally(() => {
      cookieEnVuelo = null
    })

  await cookieEnVuelo
}

/**
 * Antes de cualquier peticion que cambie estado hay que tener la cookie
 * CSRF. Hacerlo aqui evita repetir la llamada en cada formulario y, sobre
 * todo, evita olvidarla en uno.
 */
api.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  if (METODOS_CON_ESTADO.has((config.method ?? 'get').toLowerCase())) {
    await asegurarCookieCsrf()
  }

  return config
})

/** Todo lo que sale de aqui es un ApiError, nunca un AxiosError suelto. */
api.interceptors.response.use(
  (response) => response,
  (error) => Promise.reject(toApiError(error)),
)

/** Solo para tests: reinicia el candado de la cookie entre casos. */
export function reiniciarCsrfParaTests(): void {
  cookieEnVuelo = null
}
