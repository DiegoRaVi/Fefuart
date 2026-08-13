import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * D9 — regresion del contraste.
 *
 * v1 escribia en blanco sobre el rosa de la marca (#ddbacc) en el header y
 * en el footer. Eso da 1,75:1, cuando la WCAG AA pide 4,5:1 para texto
 * normal: practicamente ilegible a plena luz.
 *
 * Al extraer la paleta se conservaron los cuatro colores y se cambio quien
 * va encima de quien. Este test lee el tema real y lo comprueba, de modo que
 * volver a poner blanco sobre rosa rompe la suite en vez de llegar a
 * produccion.
 */
// Desde la raiz del proyecto: bajo jsdom, `import.meta.url` no es un
// `file://` y `fileURLToPath` lo rechaza.
const CSS = readFileSync(resolve(process.cwd(), 'src/index.css'), 'utf8')

/** Los tokens `--color-x: #hex` declarados en el bloque @theme. */
function leerPaleta(): Record<string, string> {
  const paleta: Record<string, string> = {}

  for (const [, nombre, hex] of CSS.matchAll(
    /--color-([a-z-]+):\s*(#[0-9a-fA-F]{6})\s*;/g,
  )) {
    paleta[nombre] = hex
  }

  return paleta
}

/** Las parejas declaradas con `CONTRASTE-AA: fondo / texto` en el CSS. */
function leerParejas(): Array<[string, string]> {
  return [...CSS.matchAll(/CONTRASTE-AA:\s*([a-z-]+)\s*\/\s*([a-z-]+)/g)].map(
    ([, fondo, texto]) => [fondo, texto],
  )
}

/** WCAG 2.1, luminancia relativa. */
function luminancia(hex: string): number {
  const canales = [1, 3, 5].map((i) => {
    const c = parseInt(hex.slice(i, i + 2), 16) / 255

    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  })

  return 0.2126 * canales[0] + 0.7152 * canales[1] + 0.0722 * canales[2]
}

function contraste(a: string, b: string): number {
  const [claro, oscuro] = [luminancia(a), luminancia(b)].sort((x, y) => y - x)

  return (claro + 0.05) / (oscuro + 0.05)
}

describe('la paleta de v1 se conserva intacta', () => {
  it.each([
    ['rosa', '#ddbacc'],
    ['rosa-suave', '#fdf5f8'],
    ['verde', '#255035'],
    ['piedra', '#584f50'],
  ])('mantiene %s en %s', (nombre, hex) => {
    expect(leerPaleta()[nombre]).toBe(hex)
  })
})

describe('el reparto de papeles cumple la AA', () => {
  it('declara al menos una pareja', () => {
    expect(leerParejas().length).toBeGreaterThan(0)
  })

  it.each(leerParejas())(
    'sobre el fondo %s se puede escribir en %s',
    (fondo, texto) => {
      const paleta = leerPaleta()

      expect(paleta[fondo], `falta el token --color-${fondo}`).toBeDefined()
      expect(paleta[texto], `falta el token --color-${texto}`).toBeDefined()

      expect(contraste(paleta[fondo], paleta[texto])).toBeGreaterThanOrEqual(4.5)
    },
  )
})

describe('el fallo concreto de v1', () => {
  it('confirma que el blanco sobre el rosa no llegaba ni a 2:1', () => {
    // Si esto dejara de ser cierto, es que alguien ha cambiado el rosa.
    expect(contraste('#ddbacc', '#ffffff')).toBeLessThan(2)
  })

  it('no deja el blanco como texto sobre ninguna superficie clara', () => {
    const paleta = leerPaleta()

    for (const [fondo, texto] of leerParejas()) {
      expect(
        texto,
        `--color-${texto} sobre --color-${fondo} es el fallo de v1`,
      ).not.toBe('white')

      expect(paleta[texto]).not.toBe('#ffffff')
    }
  })
})
