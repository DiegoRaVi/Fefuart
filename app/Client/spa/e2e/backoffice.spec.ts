import { expect, test } from '@playwright/test'

import { ARTISTA, CLIENTE, entrar, ultimoCorreoPara, vaciarLaBandeja } from './ayudas'

/**
 * Flujo 4 — el backoffice.
 *
 * Lo que v1 hacia en `admin.html`, bien hecho: listar, buscar (D28), cambiar
 * estados. Y las dos cosas que en v1 estaban rotas por diseno y aqui tienen
 * que seguir estandolo por el lado contrario: un cliente no entra, y el
 * cambio de estado avisa a quien corresponde.
 */

/** Deja un pedido confirmado y devuelve su numero. */
async function unPedidoConfirmado(page: import('@playwright/test').Page): Promise<number> {
  await page.goto('/encargos/letras-infantiles')
  await page.getByRole('link', { name: 'Encargar' }).click()
  await page.getByRole('button', { name: 'Anadir al carrito' }).click()

  await page.getByRole('link', { name: 'Continuar' }).click()
  await page.getByLabel('Nombre y apellidos').fill('Cliente de prueba')
  await page.getByLabel('Telefono').fill('600123456')
  await page.getByLabel('Direccion', { exact: true }).fill('Calle Mayor 1')
  await page.getByLabel('Codigo postal').fill('28001')
  await page.getByLabel('Ciudad').fill('Madrid')
  await page.getByLabel('Provincia').fill('Madrid')
  await page.getByRole('button', { name: 'Confirmar el encargo' }).click()

  await expect(page).toHaveURL(/\/pedidos\/\d+/)

  return Number(page.url().match(/\/pedidos\/(\d+)/)![1])
}

/**
 * N20 — el backoffice es de la administradora. En v1 el enlace estaba en el
 * HTML de todos y se ocultaba con CSS segun el rol que el navegador leia del
 * JWT de `localStorage`, asi que bastaba con editarlo.
 */
test('un cliente no entra en el backoffice ni escribiendo la direccion', async ({ page }) => {
  await entrar(page, CLIENTE)

  await expect(page.getByRole('link', { name: 'Backoffice' })).not.toBeVisible()

  await page.goto('/backoffice/pedidos')

  await expect(page).not.toHaveURL(/\/backoffice/)
})

test('la artista lista, busca y cambia el estado de un pedido', async ({ page }) => {
  await vaciarLaBandeja()

  await entrar(page, CLIENTE)
  const pedido = await unPedidoConfirmado(page)
  await page.getByRole('button', { name: 'Salir' }).click()

  await entrar(page, ARTISTA)
  await page.goto('/backoffice/pedidos')

  // D28 — la caja ancha mira en varios campos a la vez; el email es el caso
  // de todos los dias.
  await page.getByRole('searchbox').fill(CLIENTE.email)
  await expect(page.getByText(`#${pedido}`).first()).toBeVisible()

  await page.getByText(`#${pedido}`).first().click()
  await expect(page).toHaveURL(new RegExp(`/backoffice/pedidos/${pedido}`))

  // El pedido esta sin pagar, asi que lo unico que la maquina de estados
  // permite es cancelarlo. Cancelar si avisa al cliente: no lo hizo el.
  await page.getByRole('button', { name: /Cancelado|Cancelar/ }).first().click()

  const correo = await ultimoCorreoPara(CLIENTE.email)
  expect(correo.Subject).toContain(`Tu pedido #${pedido}`)
})
