import axios, { AxiosError, AxiosHeaders } from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { api, asegurarCookieCsrf, reiniciarCsrfParaTests } from './client'
import { ApiError, toApiError } from './errors'

function borrarCookies() {
  for (const cookie of document.cookie.split('; ')) {
    const [nombre] = cookie.split('=')
    if (nombre) {
      document.cookie = `${nombre}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
    }
  }
}

beforeEach(() => {
  borrarCookies()
  reiniciarCsrfParaTests()
  vi.restoreAllMocks()
})

describe('el cliente habla el idioma de Sanctum', () => {
  it('manda la cookie de sesión en cada peticion', () => {
    expect(api.defaults.withCredentials).toBe(true)
  })

  /**
   * SEC-005 / SEC-011 — la sesion no esta al alcance de JavaScript. En v1 el
   * JWT vivia en `localStorage` (`auth.js:7-10`), de modo que un XSS lo
   * robaba directamente y no habia forma de revocarlo.
   */
  it('no guarda nada de la sesión donde JavaScript pueda leerlo', () => {
    expect(JSON.stringify(api.defaults.headers)).not.toContain('Authorization')
    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(0)
  })

  it('usa rutas relativas para que el proxy las haga same-site', () => {
    expect(api.defaults.baseURL).toBe('/api')
  })
})

describe('la cookie CSRF', () => {
  it('se pide cuando todavía no existe', async () => {
    const get = vi.spyOn(axios, 'get').mockResolvedValue({ data: '' })

    await asegurarCookieCsrf()

    expect(get).toHaveBeenCalledWith('/sanctum/csrf-cookie', {
      withCredentials: true,
    })
  })

  it('no se vuelve a pedir si ya esta puesta', async () => {
    document.cookie = 'XSRF-TOKEN=un-token'
    const get = vi.spyOn(axios, 'get').mockResolvedValue({ data: '' })

    await asegurarCookieCsrf()

    expect(get).not.toHaveBeenCalled()
  })

  /**
   * Sin candado, dos mutaciones simultaneas piden dos cookies y la segunda
   * invalida el token de la primera: el usuario ve un 419 al pulsar dos
   * veces seguidas.
   */
  it('se pide una sola vez aunque dos mutaciones arranquen a la vez', async () => {
    const get = vi.spyOn(axios, 'get').mockImplementation(
      () => new Promise((resolve) => setTimeout(() => resolve({ data: '' }), 10)),
    )

    await Promise.all([asegurarCookieCsrf(), asegurarCookieCsrf(), asegurarCookieCsrf()])

    expect(get).toHaveBeenCalledTimes(1)
  })
})

describe('los errores llegan normalizados', () => {
  function errorDeAxios(status: number, data: unknown): AxiosError {
    return new AxiosError('fallo', 'ERR', undefined, undefined, {
      status,
      statusText: '',
      data,
      headers: new AxiosHeaders(),
      config: { headers: new AxiosHeaders() },
    })
  }

  it('convierte el 422 de Laravel en errores por campo', () => {
    const error = toApiError(
      errorDeAxios(422, {
        message: 'Los datos no son validos.',
        errors: { email: ['Ese correo ya esta registrado.'] },
      }),
    )

    expect(error).toBeInstanceOf(ApiError)
    expect(error.isValidation).toBe(true)
    expect(error.firstErrors()).toEqual({
      email: 'Ese correo ya esta registrado.',
    })
  })

  it('distingue el 401, el 403 y el throttle del 429', () => {
    expect(toApiError(errorDeAxios(401, {})).isUnauthenticated).toBe(true)
    expect(toApiError(errorDeAxios(403, {})).isForbidden).toBe(true)
    expect(toApiError(errorDeAxios(429, {})).isThrottled).toBe(true)
  })

  /**
   * Decir «algo ha fallado por nuestra parte» cuando el problema es que no
   * hay conexion manda a mirar donde no es.
   */
  it('distingue un fallo de red de un fallo del servidor', () => {
    const sinRespuesta = new AxiosError('Network Error')

    expect(toApiError(sinRespuesta).status).toBe(0)
    expect(toApiError(sinRespuesta).message).toContain('conexion')
    expect(toApiError(errorDeAxios(500, {})).message).not.toContain('conexion')
  })

  /**
   * SEC-012 — v1 devolvia el objeto excepcion completo, con traza y rutas del
   * sistema, y lo pintaba tal cual. Lo que se muestra sale de `message`.
   */
  it('no arrastra la traza del servidor al mensaje', () => {
    const error = toApiError(
      errorDeAxios(500, {
        message: 'Algo ha fallado por nuestra parte.',
        exception: 'RuntimeException',
        file: 'C:\\xampp\\htdocs\\Fefuart\\app\\Server\\app\\Services\\X.php',
        trace: [{ file: 'X.php', line: 12 }],
      }),
    )

    expect(error.message).toBe('Algo ha fallado por nuestra parte.')
    expect(error.message).not.toContain('xampp')
    expect(JSON.stringify(error.errors)).not.toContain('trace')
  })
})
