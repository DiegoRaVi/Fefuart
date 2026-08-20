import { describe, expect, it } from 'vitest'

import { euros, precioConCopias } from './dinero'

/** Espacio duro: es lo que mete Intl entre el numero y el simbolo. */
const NBSP = ' '

describe('los importes se enseñan en euros y en espanol', () => {
  it.each([
    ['40.00', `40,00${NBSP}€`],
    ['0.00', `0,00${NBSP}€`],
    ['1234.50', `1234,50${NBSP}€`],
  ])('convierte %s en %s', (entrada, esperado) => {
    expect(euros(entrada)).toBe(esperado)
  })

  /**
   * Los importes llegan como cadena a proposito: el servidor los calcula en
   * centimos enteros para no pasar por float. Si algun dia llega algo que no
   * es un numero, mejor enseñarlo tal cual que un NaN.
   */
  it('no inventa nada si el importe no es un número', () => {
    expect(euros('lo-que-sea')).toBe('lo-que-sea')
  })
})

describe('el precio de las copias adicionales', () => {
  it('lo menciona cuando se cobra', () => {
    expect(precioConCopias('40.00', '10.00')).toBe(
      `40,00${NBSP}€ + 10,00${NBSP}€ por copia adicional`,
    )
  })

  it('se calla cuando es gratis', () => {
    expect(precioConCopias('20.00', '0.00')).toBe(`20,00${NBSP}€`)
  })
})
