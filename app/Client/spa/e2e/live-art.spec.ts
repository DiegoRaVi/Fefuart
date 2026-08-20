import { expect, test } from '@playwright/test'

import { ARTISTA, CLIENTE, entrar, ultimoCorreoPara, vaciarLaBandeja } from './ayudas'

/**
 * Flujo 3 — solicitud de Live Art, presupuesto y aceptacion.
 *
 * Es el unico recorrido que cruza los dos roles: el cliente pide, la artista
 * presupuesta desde el backoffice y el cliente vuelve a ver el importe. Eso
 * es lo que ningun test de componente puede comprobar, porque cada uno monta
 * su mitad por separado.
 *
 * Para en «Aceptar y pagar la señal»: pulsarlo abriria una sesion de Checkout
 * de verdad (D29).
 */
const FECHA = '2027-06-12'

test('pedir presupuesto, recibirlo y ver la señal calculada', async ({ page }) => {
  await vaciarLaBandeja()

  // --- El cliente pide ---
  await entrar(page, CLIENTE)
  await page.goto('/live-art')

  await page.getByLabel('Qué evento es').fill('Boda de Marta y Luis')
  await page.getByLabel('Fecha', { exact: true }).fill(FECHA)
  await page.getByLabel('Dónde').fill('Finca El Olivar, Toledo')
  await page.getByLabel('Invitados').fill('120')
  await page.getByLabel('Horas').fill('3')
  await page.getByLabel('Tipo').fill('boda')
  await page.getByLabel('Teléfono').fill('600123456')
  await page.getByRole('button', { name: 'Pedir presupuesto' }).click()

  await expect(page.getByText('Boda de Marta y Luis').first()).toBeVisible()

  // D32 — a la artista le llega que hay trabajo entrando.
  const aviso = await ultimoCorreoPara('admin@fefuart.test')
  expect(aviso.Subject).toContain('Nueva solicitud de Live Art')

  await page.getByRole('button', { name: 'Salir' }).click()

  // --- La artista presupuesta ---
  await entrar(page, ARTISTA)
  await page.goto('/backoffice/eventos')

  await expect(page.getByText('Boda de Marta y Luis').first()).toBeVisible()
  await page.getByRole('button', { name: 'Presupuestar' }).click()

  await page.getByLabel('Importe total, IVA incluido').fill('1200')
  await page.getByRole('button', { name: /Enviar|Presupuestar|Guardar/ }).last().click()

  // N15 — la señal es el 30 % y la calcula el servidor, no el formulario.
  await expect(page.getByText('360,00 €')).toBeVisible()

  await page.getByRole('button', { name: 'Salir' }).click()

  // --- El cliente lo recibe ---
  const correo = await ultimoCorreoPara(CLIENTE.email)
  expect(correo.Subject).toContain('presupuesto')

  await entrar(page, CLIENTE)
  await page.goto('/live-art')

  // Sin separador de millares: en castellano un numero de cuatro cifras no
  // lo lleva, y es lo que aplica ICU con es-ES.
  await expect(page.getByText('1200,00 €')).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Aceptar y pagar la señal de 360,00 €' }),
  ).toBeVisible()
})
