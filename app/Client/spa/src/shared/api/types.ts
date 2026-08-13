/**
 * El contrato de la API, escrito a mano.
 *
 * Va aparte de los modelos de Eloquent a proposito: ARCH-005 senalaba que en
 * v1 se devolvian modelos crudos y el contrato quedaba atado al esquema. En
 * v2 lo que sale es lo que declara cada API Resource, y esto es su reflejo.
 */

/** Todas las respuestas del backend vienen envueltas en `data`. */
export interface Envelope<T> {
  data: T
}

/** Listados paginados: `meta` uniforme. */
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

/**
 * N20 — dos roles. El backend expone el nombre y nunca el id (D23), asi que
 * aqui tampoco existen los numeros.
 */
export type Role = 'customer' | 'admin'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  email_verified_at: string | null
}

export interface Credentials {
  email: string
  password: string
}

export interface RegistrationData {
  name: string
  email: string
  password: string
  password_confirmation: string
}
