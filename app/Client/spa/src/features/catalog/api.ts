import { useQuery } from '@tanstack/react-query'

import { api } from '@/shared/api/client'
import type { Envelope, Product } from '@/shared/api/types'

/**
 * El catalogo es publico (N18: encargar si exige cuenta, mirar no), asi que
 * estas consultas no dependen de la sesion y se pueden cachear mas rato que
 * el resto: un precio no cambia cada minuto.
 */
const CINCO_MINUTOS = 5 * 60 * 1000

export const clavesDeCatalogo = {
  todo: ['catalog'] as const,
  lista: () => [...clavesDeCatalogo.todo, 'list'] as const,
  ficha: (slug: string) => [...clavesDeCatalogo.todo, 'product', slug] as const,
}

export async function obtenerCatalogo(): Promise<Product[]> {
  const { data } = await api.get<{ data: Product[] }>('/catalog/products')

  return data.data
}

export async function obtenerProducto(slug: string): Promise<Product> {
  const { data } = await api.get<Envelope<Product>>(`/catalog/products/${slug}`)

  return data.data
}

export function useCatalogo() {
  return useQuery({
    queryKey: clavesDeCatalogo.lista(),
    queryFn: obtenerCatalogo,
    staleTime: CINCO_MINUTOS,
  })
}

export function useProducto(slug: string) {
  return useQuery({
    queryKey: clavesDeCatalogo.ficha(slug),
    queryFn: () => obtenerProducto(slug),
    staleTime: CINCO_MINUTOS,
  })
}
