const EUROS = new Intl.NumberFormat('es-ES', {
  style: 'currency',
  currency: 'EUR',
})

/**
 * Los importes viajan como cadena decimal ('40.00'), nunca como numero.
 *
 * El servidor los calcula en centimos enteros justamente para no pasar por
 * float, asi que convertirlos a `number` aqui volveria a meter por la puerta
 * de atras el error que se evito en PricingService. Se convierte una sola
 * vez, al final, y solo para enseñarlo.
 *
 * N2 — el precio ya lleva el IVA: es el precio final.
 */
export function euros(importe: string): string {
  const valor = Number(importe)

  if (Number.isNaN(valor)) {
    return importe
  }

  return EUROS.format(valor)
}

/**
 * N4 — «40,00 € la primera copia, 10,00 € cada copia adicional». Cuando la
 * copia adicional es gratis no se menciona: decir «0,00 € cada copia
 * adicional» confunde mas que callarse.
 */
export function precioConCopias(precio: string, copiaAdicional: string): string {
  if (Number(copiaAdicional) === 0) {
    return euros(precio)
  }

  return `${euros(precio)} + ${euros(copiaAdicional)} por copia adicional`
}
