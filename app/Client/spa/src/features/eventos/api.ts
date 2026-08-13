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
  /** Resuelto en servidor: el cliente no deduce permisos del estado. */
  can: { update: boolean; cancel: boolean }
  created_at: string | null
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

export async function obtenerEventos(): Promise<Paginated<Evento>> {
  const { data } = await api.get<Paginated<Evento>>('/events')

  return data
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
  accepted: 'Presupuesto aceptado',
  confirmed: 'Confirmado',
  completed: 'Celebrado',
  rejected: 'No disponible',
  cancelled: 'Cancelado',
}

export function nombreDelEstado(status: EventStatus): string {
  return ESTADOS[status] ?? status
}
