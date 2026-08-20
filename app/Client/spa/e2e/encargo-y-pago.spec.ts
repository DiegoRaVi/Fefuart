import { expect, test } from '@playwright/test'

import { CLIENTE, entrar } from './ayudas'

/**
 * Flujo 2 — catalogo, encargo, carrito y confirmacion del pedido.
 *
 * **Aqui no se pulsa «Pagar».** Hacerlo haria que el servidor llamase a la
 * API de Stripe, y una bateria que depende de la red falla por motivos que
 * no son el codigo. La mitad nuestra del cobro la cubren los tests de Pest,
 * incluida la firma real del webhook; lo que solo se ve en un navegador —que
 * las pantallas encadenan y que los importes que se pintan son los del
 * servidor— es lo que se prueba aqui.
 *
 * Se encarga «Letras infantiles» porque es el unico producto con
 * `requires_reference_image` a false (N9): los otros dos exigen subir una
 * foto, y eso es otro recorrido.
 */
test('encargar, ver el precio del servidor en el carrito y dejar el pedido listo para pagar', async ({
  page,
}) => {
  await entrar(page, CLIENTE)

  await page.goto('/encargos')
  await page.getByRole('link', { name: /Letras infantiles/ }).click()

  await expect(page.getByRole('heading', { name: 'Letras infantiles' })).toBeVisible()
  await page.getByRole('link', { name: 'Empezar mi encargo' }).click()

  await page.getByRole('button', { name: 'Añadir al carrito' }).click()

  // El carrito es la primera pantalla donde se ve un importe, y sale del
  // servidor: N4 (40 la primera copia) + N5 (5 de envio, una vez).
  await expect(page).toHaveURL(/\/carrito/)
  await expect(page.getByText('45,00 €')).toBeVisible()

  await page.getByRole('link', { name: 'Continuar' }).click()

  await page.getByLabel('Nombre y apellidos').fill('Cliente de prueba')
  await page.getByLabel('Teléfono').fill('600123456')
  await page.getByLabel('Dirección', { exact: true }).fill('Calle Mayor 1')
  await page.getByLabel('Código postal').fill('28001')
  await page.getByLabel('Ciudad').fill('Madrid')
  await page.getByLabel('Provincia').fill('Madrid')

  await page.getByRole('button', { name: 'Confirmar el encargo' }).click()

  // El pedido existe, esta pendiente de pago y ofrece pagar el importe que
  // calculo el servidor. Hasta aqui llega el recorrido.
  await expect(page).toHaveURL(/\/pedidos\/\d+/)
  await expect(page.getByRole('button', { name: 'Pagar 45,00 €' })).toBeVisible()
})

/**
 * N4 — la segunda copia son 10 €, no otros 40. Es la regla que en v1 estaba
 * escrita en JavaScript y daba precios distintos para el mismo caso segun la
 * pantalla; aqui el precio lo pone `PricingService` y esto lo comprueba en el
 * navegador de punta a punta.
 */
test('una segunda copia suma la copia adicional, no el precio entero', async ({ page }) => {
  await entrar(page, CLIENTE)

  await page.goto('/encargos/letras-infantiles')
  await page.getByRole('link', { name: 'Empezar mi encargo' }).click()

  await page.getByRole('button', { name: 'Añadir al carrito' }).click()

  await expect(page).toHaveURL(/\/carrito/)

  await page.getByLabel(/Copias de Letras infantiles/i).fill('2')
  await page.getByLabel(/Copias de Letras infantiles/i).blur()

  // 40 + 10 + 5 de envio. Si alguien reintrodujera «unitario x cantidad»,
  // saldrian 85,00 €.
  await expect(page.getByText('55,00 €')).toBeVisible()
})
