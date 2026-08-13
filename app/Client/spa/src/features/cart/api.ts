import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type { MediaAsset } from '@/features/media/api'
import { api } from '@/shared/api/client'
import type { DeliveryType, Envelope } from '@/shared/api/types'

export interface OrderItem {
  id: number
  product_id: number
  variant_id: number
  product_name: string
  variant_name: string
  delivery_type: DeliveryType
  quantity: number
  customer_notes: string | null
  unit_price: string
  additional_copy_price: string
  line_total: string
  reference_media?: MediaAsset
}

export interface ShippingAddress {
  name: string | null
  phone: string | null
  line1: string | null
  line2: string | null
  city: string | null
  province: string | null
  postal_code: string | null
  country: string | null
}

export interface Order {
  id: number | null
  status: string
  /** Todos calculados en servidor. El cliente no suma nada (SEC-006). */
  subtotal: string
  shipping_total: string
  total: string
  shipping_method?: { id: number; code: DeliveryType; name: string; price: string }
  placed_at: string | null
  shipping_address: ShippingAddress
  items: OrderItem[]
}

export const CLAVE_CARRITO = ['cart'] as const

export async function obtenerCarrito(): Promise<Order> {
  const { data } = await api.get<Envelope<Order>>('/cart')

  return data.data
}

export interface NuevaLinea {
  product_id: number
  variant_id: number
  shipping_method_id: number
  quantity: number
  customer_notes?: string | null
  reference_media_id?: number | null
}

/**
 * SEC-006 — fijate en lo que no se manda: ningun importe. El cuerpo dice
 * *que* se encarga y el servidor resuelve cuanto cuesta contra el catalogo.
 */
export async function anadirLinea(linea: NuevaLinea): Promise<Order> {
  const { data } = await api.post<Envelope<Order>>('/cart/items', linea)

  return data.data
}

export async function cambiarCantidad(id: number, quantity: number): Promise<Order> {
  const { data } = await api.patch<Envelope<Order>>(`/cart/items/${id}`, { quantity })

  return data.data
}

export async function quitarLinea(id: number): Promise<Order> {
  const { data } = await api.delete<Envelope<Order>>(`/cart/items/${id}`)

  return data.data
}

export function useCarrito(habilitado = true) {
  return useQuery({
    queryKey: CLAVE_CARRITO,
    queryFn: obtenerCarrito,
    enabled: habilitado,
  })
}

/**
 * Cada endpoint del carrito devuelve el pedido entero recalculado, asi que
 * la respuesta se escribe directamente en la cache: no hace falta invalidar
 * y volver a pedir. En v1 el navegador sumaba por su cuenta y mandaba un
 * PATCH por linea con su total parcial (BUG-005).
 */
function useEscrituraDeCarrito<TArgs>(fn: (args: TArgs) => Promise<Order>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: fn,
    onSuccess: (carrito) => queryClient.setQueryData(CLAVE_CARRITO, carrito),
  })
}

export function useAnadirLinea() {
  return useEscrituraDeCarrito(anadirLinea)
}

export function useCambiarCantidad() {
  return useEscrituraDeCarrito(({ id, quantity }: { id: number; quantity: number }) =>
    cambiarCantidad(id, quantity),
  )
}

export function useQuitarLinea() {
  return useEscrituraDeCarrito(quitarLinea)
}

export interface DireccionDeEnvio {
  shipping_name?: string
  shipping_phone?: string
  shipping_line1?: string
  shipping_line2?: string
  shipping_city?: string
  shipping_province?: string
  shipping_postal_code?: string
  shipping_country?: string
}

/**
 * SEC-006 — tampoco aqui viaja ningun importe. El servidor vuelve a calcular
 * contra el catalogo vivo antes de dar el pedido por hecho.
 */
export async function hacerPedido(direccion: DireccionDeEnvio): Promise<Order> {
  const { data } = await api.post<Envelope<Order>>('/cart/checkout', direccion)

  return data.data
}

export function useHacerPedido() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: hacerPedido,
    onSuccess: () => {
      // El carrito ha dejado de existir como tal: el mismo pedido pasa a
      // `pending_payment`.
      queryClient.invalidateQueries({ queryKey: CLAVE_CARRITO })
      queryClient.invalidateQueries({ queryKey: CLAVE_PEDIDOS })
    },
  })
}

export const CLAVE_PEDIDOS = ['orders'] as const
