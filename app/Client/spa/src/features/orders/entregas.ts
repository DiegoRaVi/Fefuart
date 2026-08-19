import { useMutation, useQueryClient } from '@tanstack/react-query'

import { CLAVE_PEDIDOS, type Order } from '@/features/cart/api'
import { api } from '@/shared/api/client'
import type { Envelope } from '@/shared/api/types'

/**
 * D20, N11 — la entrega digital.
 *
 * La descarga **no** pasa por axios: es un fichero, no JSON, y el navegador
 * sabe bajarlo solo. Basta con abrir la ruta, que va por el proxy de Vite y
 * por tanto lleva la cookie de sesion. El servidor la sirve como adjunto.
 */
export function urlDeDescarga(pedidoId: number, lineaId: number): string {
  return `/api/orders/${pedidoId}/items/${lineaId}/download`
}

export async function subirEntrega(
  pedidoId: number,
  lineaId: number,
  archivo: File,
): Promise<Order> {
  const cuerpo = new FormData()
  cuerpo.append('file', archivo)

  const { data } = await api.post<Envelope<Order>>(
    `/admin/orders/${pedidoId}/items/${lineaId}/delivery`,
    cuerpo,
  )

  return data.data
}

/** Sube el archivo final de una linea y refresca el pedido. */
export function useSubirEntrega(pedidoId: number, lineaId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (archivo: File) => subirEntrega(pedidoId, lineaId, archivo),
    onSuccess: () => {
      // Las dos vistas, porque el mismo pedido se mira desde dos sitios: el
      // backoffice, que es desde donde se sube, y «mis pedidos», que es donde
      // el cliente vera aparecer la descarga. Invalidar solo la primera dejaba
      // la pantalla de la artista sin enterarse de su propia subida.
      queryClient.invalidateQueries({ queryKey: ['admin', 'orders'] })
      queryClient.invalidateQueries({ queryKey: CLAVE_PEDIDOS })
    },
  })
}
