import { AxiosError } from 'axios'

/** Mapa `campo -> mensajes` tal y como lo devuelve Laravel en un 422. */
export type FieldErrors = Record<string, string[]>

/**
 * El error de la API en un unico tipo.
 *
 * SEC-012 — v1 serializaba el objeto excepcion completo en la respuesta:
 * traza, rutas del sistema y configuracion, y ademas con independencia de
 * `APP_DEBUG`. El backend de v2 ya devuelve solo el motivo; aqui se termina
 * de cerrar el circulo asegurando que lo que se pinta en pantalla sale
 * siempre de `message`, y nunca de un volcado del error.
 */
export class ApiError extends Error {
  // Declarados aqui y asignados en el cuerpo: las propiedades de constructor
  // emiten codigo en tiempo de ejecucion y `erasableSyntaxOnly` las prohibe.
  readonly status: number
  readonly errors: FieldErrors

  constructor(status: number, message: string, errors: FieldErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  /** 422: la peticion se entendio pero los datos no valen. */
  get isValidation(): boolean {
    return this.status === 422
  }

  /** 401: no hay sesion, o ha caducado. */
  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  /** 403: hay sesion, pero no permiso. Lo deciden las Policies del backend. */
  get isForbidden(): boolean {
    return this.status === 403
  }

  /** 429: throttle. Login, registro y recuperacion lo llevan (SEC-007). */
  get isThrottled(): boolean {
    return this.status === 429
  }

  /** El primer mensaje de cada campo, que es lo que pinta un formulario. */
  firstErrors(): Record<string, string> {
    return Object.fromEntries(
      Object.entries(this.errors).map(([campo, mensajes]) => [campo, mensajes[0]]),
    )
  }
}

interface LaravelErrorBody {
  message?: string
  errors?: FieldErrors
}

const MENSAJES_POR_ESTADO: Record<number, string> = {
  401: 'Tienes que iniciar sesión.',
  403: 'No tienes permiso para hacer esto.',
  404: 'No hemos encontrado lo que buscabas.',
  419: 'La sesión ha caducado. Vuelve a intentarlo.',
  429: 'Demasiados intentos. Espera un momento.',
  500: 'Algo ha fallado por nuestra parte.',
}

/**
 * Convierte cualquier fallo de axios en un ApiError.
 *
 * Un fallo de red no trae respuesta, y ese caso hay que distinguirlo: decir
 * «algo ha fallado por nuestra parte» cuando el problema es que el usuario
 * no tiene conexion manda a mirar donde no es.
 */
export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) {
    return error
  }

  if (error instanceof AxiosError) {
    if (!error.response) {
      return new ApiError(0, 'No hemos podido conectar. Comprueba tu conexion.')
    }

    const { status, data } = error.response
    const body = (data ?? {}) as LaravelErrorBody

    return new ApiError(
      status,
      body.message ?? MENSAJES_POR_ESTADO[status] ?? 'Algo ha fallado.',
      body.errors ?? {},
    )
  }

  return new ApiError(0, 'Algo ha fallado.')
}
