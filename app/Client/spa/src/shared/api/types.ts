import type { MediaAsset } from '@/features/media/api'

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

/** N7 — como se entrega una linea. Coincide con `shipping_methods.code`. */
export type DeliveryType = 'physical' | 'digital'

export interface ShippingMethod {
  id: number
  code: DeliveryType
  name: string
  /** Decimal con dos cifras, tal cual lo devuelve el servidor: '5.00'. */
  price: string
}

/**
 * Donde vive el precio (N4). Llega para pintarlo; el carrito recibe
 * `variant_id` y lo vuelve a calcular en servidor (SEC-006).
 */
export interface ProductVariant {
  id: number
  name: string
  price: string
  additional_copy_price: string
  /** N7 — que entregas admite esta variante. */
  shipping_methods: ShippingMethod[]
}

export interface Product {
  id: number
  slug: string
  name: string
  description: string | null
  category: string
  /**
   * La foto del articulo. `null` mientras no se haya subido: la tienda
   * funciona sin ella, solo vende peor. Se anadio tras la auditoria de UX
   * del 2026-08-20, que encontro un catalogo de dibujos sin dibujos.
   */
  image: MediaAsset | null
  /** N9 — la foto es el material de partida, no un adjunto. */
  requires_reference_image: boolean
  requires_notes: boolean
  max_quantity: number
  delivery_days: number
  variants: ProductVariant[]
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
