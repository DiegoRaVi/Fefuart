import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type { Order } from '@/features/cart/api'
import type { Evento, EventStatus } from '@/features/eventos/api'
import { api } from '@/shared/api/client'
import type { Envelope, Paginated, Product } from '@/shared/api/types'

export interface FiltrosDePedidos {
  status?: string
  q?: string
  /** Acota la busqueda a un solo campo. Vacio = la caja rapida mira en todo. */
  buscar_por?: string
  desde?: string
  hasta?: string
  page?: number
}

export const clavesDeBackoffice = {
  pedidos: (f: FiltrosDePedidos) => ['admin', 'orders', f] as const,
  pedido: (id: string) => ['admin', 'orders', id] as const,
  eventos: (f: FiltrosDePedidos) => ['admin', 'events', f] as const,
  catalogo: () => ['admin', 'products'] as const,
  ajustes: () => ['admin', 'settings'] as const,
}

/** Quita los filtros vacios para que no viajen como `?q=` ni ensucien la cache. */
function limpios(filtros: FiltrosDePedidos): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(filtros).filter(([, v]) => v !== '' && v !== undefined && v !== null),
  ) as Record<string, string | number>
}

export function usePedidosDeAdmin(filtros: FiltrosDePedidos) {
  return useQuery({
    queryKey: clavesDeBackoffice.pedidos(filtros),
    queryFn: async () => {
      const { data } = await api.get<Paginated<Order>>('/admin/orders', {
        params: limpios(filtros),
      })

      return data
    },
    /**
     * Sin esto la tabla se vacia en cada tecla del buscador y el contenido
     * salta. Con los datos anteriores en pantalla, lo unico que cambia es
     * que aparecen los nuevos.
     */
    placeholderData: keepPreviousData,
  })
}

export function usePedidoDeAdmin(id: string) {
  return useQuery({
    queryKey: clavesDeBackoffice.pedido(id),
    queryFn: async () => {
      const { data } = await api.get<Envelope<Order>>(`/admin/orders/${id}`)

      return data.data
    },
  })
}

export function useCambiarEstadoDePedido(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (status: string) => {
      const { data } = await api.post<Envelope<Order>>(`/admin/orders/${id}/status`, { status })

      return data.data
    },
    onSuccess: (pedido) => {
      queryClient.setQueryData(clavesDeBackoffice.pedido(id), pedido)
      queryClient.invalidateQueries({ queryKey: ['admin', 'orders'] })
    },
  })
}

export function useEventosDeAdmin(filtros: FiltrosDePedidos) {
  return useQuery({
    queryKey: clavesDeBackoffice.eventos(filtros),
    queryFn: async () => {
      const { data } = await api.get<Paginated<Evento>>('/admin/events', {
        params: limpios(filtros),
      })

      return data
    },
    placeholderData: keepPreviousData,
  })
}

export function useCambiarEstadoDeEvento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, status }: { id: number; status: EventStatus }) => {
      const { data } = await api.post<Envelope<Evento>>(`/admin/events/${id}/status`, { status })

      return data.data
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'events'] }),
  })
}

/**
 * D6, N13 — emitir el presupuesto. No pasa por `/status` porque no es
 * cambiar un estado: es fijar un importe, calcular la señal y arrancar un
 * plazo.
 *
 * La señal **no** se manda. La calcula el servidor con el porcentaje
 * configurado, igual que ningun precio del catalogo llega del cliente
 * (SEC-006). Que aqui quien escribe sea la artista no cambia la regla.
 */
export function usePresupuestarEvento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      id,
      quoted_amount,
      validez_dias,
    }: {
      id: number
      quoted_amount: string
      validez_dias?: number
    }) => {
      const { data } = await api.post<Envelope<Evento>>(`/admin/events/${id}/quote`, {
        quoted_amount,
        ...(validez_dias ? { validez_dias } : {}),
      })

      return data.data
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'events'] }),
  })
}

/** N15 — los ajustes del negocio, con sus limites tal y como los declara el servidor. */
export interface Ajuste {
  valor: number
  min: number
  max: number
  etiqueta: string
}

export type Ajustes = Record<string, Ajuste>

export function useAjustes() {
  return useQuery({
    queryKey: clavesDeBackoffice.ajustes(),
    queryFn: async () => {
      const { data } = await api.get<Envelope<Ajustes>>('/admin/settings')

      return data.data
    },
  })
}

export function useGuardarAjustes() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (valores: Record<string, number>) => {
      const { data } = await api.patch<Envelope<Ajustes>>('/admin/settings', valores)

      return data.data
    },
    onSuccess: (ajustes) => {
      queryClient.setQueryData(clavesDeBackoffice.ajustes(), ajustes)
      // Un porcentaje nuevo no reescribe los presupuestos ya emitidos, pero
      // si cambia el de los siguientes.
      queryClient.invalidateQueries({ queryKey: ['admin', 'events'] })
    },
  })
}

export function useCatalogoDeAdmin(pagina = 1) {
  return useQuery({
    queryKey: [...clavesDeBackoffice.catalogo(), pagina],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Product>>('/admin/products', {
        params: { page: pagina },
      })

      return data
    },
    placeholderData: keepPreviousData,
  })
}

/**
 * D5 — el motivo de que exista el backoffice de catalogo: Felicitas cambia
 * precios sin tocar codigo. Cambiar uno no reescribe el historico, que
 * conserva su snapshot.
 */
export function useCambiarVariante() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      id,
      ...datos
    }: {
      id: number
      price?: string
      additional_copy_price?: string
      is_active?: boolean
    }) => {
      await api.patch(`/admin/variants/${id}`, datos)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: clavesDeBackoffice.catalogo() })
      // El catalogo publico tambien cambia.
      queryClient.invalidateQueries({ queryKey: ['catalog'] })
    },
  })
}
