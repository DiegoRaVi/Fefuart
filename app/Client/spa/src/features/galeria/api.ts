import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import type { MediaAsset } from '@/features/media/api'
import { api } from '@/shared/api/client'
import type { Envelope } from '@/shared/api/types'

/**
 * D33 — la galeria.
 *
 * Contenido, no catalogo: lo que se enseña no tiene por que coincidir con lo
 * que se vende. Por eso «Papeleria» es una categoria valida aunque no exista
 * como producto — es justo lo que trae encargos que no estan en la tienda.
 */
export interface PiezaDeGaleria {
  id: number
  title: string
  category: Categoria
  description: string | null
  is_published: boolean
  sort_order: number
  image?: MediaAsset
  thumbnail?: MediaAsset
}

export type Categoria = 'live-art' | 'dibujo' | 'letras' | 'ramos' | 'papeleria'

export const CATEGORIAS: { valor: Categoria; etiqueta: string }[] = [
  { valor: 'live-art', etiqueta: 'Live Art' },
  { valor: 'dibujo', etiqueta: 'Dibujos' },
  { valor: 'letras', etiqueta: 'Letras infantiles' },
  { valor: 'ramos', etiqueta: 'Ramos' },
  { valor: 'papeleria', etiqueta: 'Papeleria' },
]

export function nombreDeCategoria(valor: string): string {
  return CATEGORIAS.find((c) => c.valor === valor)?.etiqueta ?? valor
}

const CLAVE_GALERIA = ['gallery'] as const

export async function obtenerGaleria(categoria?: Categoria): Promise<PiezaDeGaleria[]> {
  const { data } = await api.get<Envelope<PiezaDeGaleria[]>>('/gallery', {
    params: categoria ? { category: categoria } : undefined,
  })

  return data.data
}

/** El escaparate. Sin sesion: se pide igual haya entrado alguien o no. */
export function useGaleria(categoria?: Categoria) {
  return useQuery({
    queryKey: [...CLAVE_GALERIA, categoria ?? 'todas'],
    queryFn: () => obtenerGaleria(categoria),
  })
}

export async function obtenerGaleriaDeAdmin(): Promise<PiezaDeGaleria[]> {
  const { data } = await api.get<Envelope<PiezaDeGaleria[]>>('/admin/gallery')

  return data.data
}

/** La vista de quien la gestiona: aqui si sale lo no publicado. */
export function useGaleriaDeAdmin() {
  return useQuery({
    queryKey: [...CLAVE_GALERIA, 'admin'],
    queryFn: obtenerGaleriaDeAdmin,
  })
}

export function useSubirPieza() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (datos: { file: File; title: string; category: Categoria }) => {
      const cuerpo = new FormData()
      cuerpo.append('file', datos.file)
      cuerpo.append('title', datos.title)
      cuerpo.append('category', datos.category)

      const { data } = await api.post<Envelope<PiezaDeGaleria>>('/admin/gallery', cuerpo)

      return data.data
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CLAVE_GALERIA }),
  })
}

export function useCambiarPieza() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, ...cambios }: { id: number } & Partial<PiezaDeGaleria>) => {
      const { data } = await api.patch<Envelope<PiezaDeGaleria>>(`/admin/gallery/${id}`, cambios)

      return data.data
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CLAVE_GALERIA }),
  })
}

export function useBorrarPieza() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => api.delete(`/admin/gallery/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CLAVE_GALERIA }),
  })
}
