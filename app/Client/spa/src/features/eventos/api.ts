import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/shared/api/client'
import type { Envelope, Paginated } from '@/shared/api/types'

export type EventStatus =
  | 'requested'
  | 'quoted'
  | 'accepted'
  | 'confirmed'
  | 'completed'
  | 'rejected'
  | 'cancelled'

export interface Evento {
  id: number
  title: string
  description: string | null
  phone: string | null
  event_date: string
  schedule: 'morning' | 'evening'
  location: string
  guest_count: number | null
  duration_hours: number | null
  event_type: string | null
  status: EventStatus
  /**
   * D6, N15 — nulos mientras la artista no haya presupuestado. El importe
   * llega para pintarlo; aceptar no lo manda de vuelta (SEC-006).
   */
  quoted_amount: string | null
  deposit_amount: string | null
  quote_expires_at: string | null
  quote_expired: boolean
  /**
   * N21 — si la señal esta cobrada, cancelar tiene consecuencias distintas
   * segun quien cancele. Lo resuelve el servidor para que ninguna pantalla
   * tenga que deducirlo del estado.
   */
  deposit_paid: boolean
  /** Resuelto en servidor: el cliente no deduce permisos del estado. */
  can: { update: boolean; cancel: boolean; accept_quote: boolean; quote: boolean }
  created_at: string | null
  /** SEC-009 — solo llega si quien pregunta es la administradora. */
  customer?: { id: number; name: string; email: string }
}

export interface NuevaSolicitud {
  title: string
  description?: string | null
  phone?: string | null
  event_date: string
  schedule: 'morning' | 'evening'
  location: string
  guest_count?: number | null
  duration_hours?: number | null
  event_type?: string | null
}

export const CLAVE_EVENTOS = ['events'] as const

export const clavesDeEventos = {
  lista: [...CLAVE_EVENTOS, 'list'] as const,
  detalle: (id: number) => [...CLAVE_EVENTOS, 'detail', id] as const,
}

export async function obtenerEventos(): Promise<Paginated<Evento>> {
  const { data } = await api.get<Paginated<Evento>>('/events')

  return data
}

export async function obtenerEvento(id: number): Promise<Evento> {
  const { data } = await api.get<Envelope<Evento>>(`/events/${id}`)

  return data.data
}

/**
 * SEC-010 — el cuerpo no lleva `status`, igual que el backend no lo acepta.
 * En v1 el propietario podia pasar su evento a `confirmed` y colarse en la
 * agenda.
 */
export async function pedirEvento(solicitud: NuevaSolicitud): Promise<Evento> {
  const { data } = await api.post<Envelope<Evento>>('/events', solicitud)

  return data.data
}

export async function cancelarEvento(id: number): Promise<Evento> {
  const { data } = await api.post<Envelope<Evento>>(`/events/${id}/cancel`)

  return data.data
}

export function useEventos() {
  return useQuery({ queryKey: CLAVE_EVENTOS, queryFn: obtenerEventos })
}

export function usePedirEvento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: pedirEvento,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CLAVE_EVENTOS }),
  })
}

export function useCancelarEvento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: cancelarEvento,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CLAVE_EVENTOS }),
  })
}

/** El enum manda en el backend (EventStatus); esto es solo como se enseña. */
const ESTADOS: Record<EventStatus, string> = {
  requested: 'Pendiente de revisar',
  quoted: 'Presupuesto enviado',
  accepted: 'Pendiente de la señal',
  confirmed: 'Confirmado',
  completed: 'Celebrado',
  rejected: 'No disponible',
  cancelled: 'Cancelado',
}

export function nombreDelEstado(status: EventStatus): string {
  return ESTADOS[status] ?? status
}
