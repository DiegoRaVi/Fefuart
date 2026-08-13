import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { CLAVE_PEDIDOS, type Order } from '@/features/cart/api'
import { api } from '@/shared/api/client'
import type { Envelope, Paginated } from '@/shared/api/types'

export const clavesDePedidos = {
  lista: (pagina: number) => [...CLAVE_PEDIDOS, 'list', pagina] as const,
  detalle: (id: string) => [...CLAVE_PEDIDOS, 'detail', id] as const,
}

export async function obtenerPedidos(pagina: number): Promise<Paginated<Order>> {
  const { data } = await api.get<Paginated<Order>>('/orders', { params: { page: pagina } })

  return data
}

export async function obtenerPedido(id: string): Promise<Order> {
  const { data } = await api.get<Envelope<Order>>(`/orders/${id}`)

  return data.data
}

/** N12 — el cliente cancela solo antes de pagar. Lo decide la Policy. */
export async function cancelarPedido(id: string): Promise<Order> {
  const { data } = await api.post<Envelope<Order>>(`/orders/${id}/cancel`)

  return data.data
}

export function usePedidos(pagina = 1) {
  return useQuery({
    queryKey: clavesDePedidos.lista(pagina),
    queryFn: () => obtenerPedidos(pagina),
  })
}

export function usePedido(id: string) {
  return useQuery({
    queryKey: clavesDePedidos.detalle(id),
    queryFn: () => obtenerPedido(id),
  })
}

export function useCancelarPedido(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => cancelarPedido(id),
    onSuccess: (pedido) => {
      queryClient.setQueryData(clavesDePedidos.detalle(id), pedido)
      queryClient.invalidateQueries({ queryKey: CLAVE_PEDIDOS })
    },
  })
}

/**
 * Los estados que devuelve el servidor, en castellano. El enum vive en el
 * backend (OrderStatus) y es el que manda; esto es solo como se enseña.
 */
export const ESTADOS: Record<string, string> = {
  cart: 'En el carrito',
  pending_payment: 'Pendiente de pago',
  paid: 'Pagado',
  in_progress: 'Dibujandose',
  shipped: 'Enviado',
  completed: 'Entregado',
  cancelled: 'Cancelado',
}

export function nombreDelEstado(status: string): string {
  return ESTADOS[status] ?? status
}
