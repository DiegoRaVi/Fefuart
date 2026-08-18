import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { api } from '@/shared/api/client'
import type { Envelope, Paginated } from '@/shared/api/types'

/**
 * D10 — el centro de avisos.
 *
 * La forma de un aviso es siempre la misma cuatro campos, la declare quien
 * la declare en el backend (`App\Notifications\Aviso`). Por eso la lista se
 * pinta con un solo componente y aqui no hay ningun `switch` por `tipo`: el
 * dia que nazca un aviso nuevo, la SPA no se entera.
 */
export interface Notificacion {
  id: string
  tipo: string
  titulo: string
  cuerpo: string
  /** Ruta relativa de la SPA: navega con React Router, no recarga. */
  enlace: string
  leido: boolean
  creado_en: string
}

/** El listado trae ademas el contador que pinta la cabecera. */
type PaginaDeAvisos = Paginated<Notificacion> & { meta: { no_leidos: number } }

export const CLAVE_AVISOS = ['notifications'] as const

export const clavesDeAvisos = {
  lista: (pagina: number) => [...CLAVE_AVISOS, 'list', pagina] as const,
}

export async function obtenerAvisos(pagina: number): Promise<PaginaDeAvisos> {
  const { data } = await api.get<PaginaDeAvisos>('/notifications', { params: { page: pagina } })

  return data
}

export async function marcarLeido(id: string): Promise<Notificacion> {
  const { data } = await api.patch<Envelope<Notificacion>>(`/notifications/${id}/read`)

  return data.data
}

/**
 * `enabled` va atado a la sesion: sin ella el endpoint responde 401, y
 * pedirlo igual llenaria la consola de errores en cada pagina publica.
 *
 * Se refresca al volver a la pestaña y cada minuto. Sin websockets a
 * proposito: montar un servicio mas en el entorno local para adelantar un
 * aviso unos segundos no sale a cuenta.
 */
export function useAvisos(autenticado: boolean, pagina = 1) {
  return useQuery({
    queryKey: clavesDeAvisos.lista(pagina),
    queryFn: () => obtenerAvisos(pagina),
    enabled: autenticado,
    refetchOnWindowFocus: true,
    refetchInterval: 60_000,
  })
}

export function useMarcarLeido() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: marcarLeido,
    // Se invalida la lista entera y no solo la fila: el contador de sin leer
    // viaja en el `meta` de la misma respuesta.
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CLAVE_AVISOS }),
  })
}
