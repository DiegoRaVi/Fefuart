import { useMutation } from '@tanstack/react-query'

import { api } from '@/shared/api/client'

/**
 * D3 — el cobro se abre en el servidor y el navegador solo va a donde le
 * digan.
 *
 * Ni el importe ni la moneda ni el metodo salen de aqui: el cuerpo va vacio.
 * En v1 pagar era un `PATCH /orders/{id}` con `total` y `status` dentro
 * (SEC-003, SEC-006).
 */
export interface SesionDePago {
  url: string
  payment_id: number
}

export async function abrirPagoDePedido(id: string): Promise<SesionDePago> {
  const { data } = await api.post<SesionDePago>(`/orders/${id}/pay`)

  return data
}

export async function aceptarPresupuesto(id: number): Promise<SesionDePago> {
  const { data } = await api.post<SesionDePago>(`/events/${id}/accept-quote`)

  return data
}

/**
 * Se sale de la SPA a proposito: el formulario de tarjeta lo sirve Stripe en
 * su dominio, y ningun dato de pago pasa por el nuestro.
 *
 * `assign` y no `replace`: asi la flecha de «atras» del navegador devuelve al
 * pedido en vez de dejar al cliente encerrado en la pasarela.
 */
function irALaPasarela({ url }: SesionDePago): void {
  window.location.assign(url)
}

export function usePagarPedido(id: string) {
  return useMutation({
    mutationFn: () => abrirPagoDePedido(id),
    onSuccess: irALaPasarela,
  })
}

export function useAceptarPresupuesto() {
  return useMutation({
    mutationFn: aceptarPresupuesto,
    onSuccess: irALaPasarela,
  })
}
