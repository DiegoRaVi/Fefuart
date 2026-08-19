import { expect, test, type Page } from '@playwright/test'

import { CLIENTE, entrar } from './ayudas'

/**
 * La CSP, comprobada donde importa: en un navegador de verdad.
 *
 * Una politica se escribe en un minuto y se rompe en el siguiente, y el modo
 * de romperla es silencioso — el navegador bloquea el recurso, la pantalla
 * queda a medias y en el servidor no consta nada. Este recorrido recoge las
 * violaciones que el navegador reporta y falla si hay alguna.
 *
 * Sirve en las dos direcciones: si alguien afloja la politica de mas, no
 * salta nada aqui; pero si alguien introduce un `eval`, un script inline o
 * una llamada a un tercero, este test lo caza antes de que llegue a
 * produccion.
 */
function recogerViolaciones(page: Page): string[] {
  const violaciones: string[] = []

  page.on('console', (mensaje) => {
    const texto = mensaje.text()

    if (/Content Security Policy|Refused to/i.test(texto)) {
      violaciones.push(texto)
    }
  })

  return violaciones
}

const PANTALLAS = ['/', '/encargos', '/encargos/letras-infantiles', '/live-art', '/login']

test('ninguna pantalla publica viola la politica', async ({ page }) => {
  const violaciones = recogerViolaciones(page)

  for (const ruta of PANTALLAS) {
    await page.goto(ruta)
    await expect(page.locator('#root')).not.toBeEmpty()
  }

  expect(violaciones).toEqual([])
})

test('ninguna pantalla con sesion viola la politica', async ({ page }) => {
  const violaciones = recogerViolaciones(page)

  await entrar(page, CLIENTE)

  for (const ruta of ['/carrito', '/pedidos', '/avisos', '/perfil']) {
    await page.goto(ruta)
    await expect(page.locator('#root')).not.toBeEmpty()
  }

  expect(violaciones).toEqual([])
})

/**
 * Y la mitad que de verdad protege: que la politica **este puesta**. Sin
 * esto, los dos tests de arriba pasarian igual de bien con la CSP borrada.
 */
test('la politica se sirve y no permite scripts inline', async ({ page }) => {
  await page.goto('/')

  const politica = await page
    .locator('meta[http-equiv="Content-Security-Policy"]')
    .getAttribute('content')

  expect(politica).toBeTruthy()
  expect(politica).toContain("default-src 'self'")
  expect(politica).toContain("object-src 'none'")

  // La concesion de estilos es de Tailwind y esta acotada a `style-src`.
  // Como `script-src` no la tiene, un `<script>` inyectado no se ejecuta.
  expect(politica).toMatch(/script-src 'self'(?!.*unsafe)/)
})
