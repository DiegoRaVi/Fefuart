import { expect, test } from '@playwright/test'

import { CLIENTE, entrar, textoDelCorreo, ultimoCorreoPara, unCorreoNuevo, vaciarLaBandeja } from './ayudas'

/**
 * Flujo 1 — registro, verificacion por correo y login.
 *
 * N19: las tres cosas eran inexistentes en v1 pese a que sus columnas ya
 * estaban creadas. Este recorrido es el unico sitio del proyecto donde se
 * comprueba que el correo **sale de verdad** y que su enlace lleva a algun
 * sitio: los tests de Pest verifican que se manda, no que el destino exista.
 * Es exactamente el fallo que se encontro al cerrar la Fase 3, cuando el
 * enlace apuntaba a una ruta de la SPA que nadie habia escrito.
 */
test.beforeEach(async () => {
  await vaciarLaBandeja()
})

test('registrarse, verificar el correo y entrar', async ({ page }) => {
  const correo = unCorreoNuevo('marta')

  await page.goto('/registro')

  await page.getByLabel('Nombre').fill('Marta Ruiz')
  await page.getByLabel('Correo').fill(correo)
  await page.getByLabel('Contrasena', { exact: true }).fill('unaclavelarga')
  await page.getByLabel('Repite la contrasena').fill('unaclavelarga')
  await page.getByRole('button', { name: 'Crear cuenta' }).click()

  // Registrarse deja la sesion abierta, pero sin verificar.
  await expect(page.getByRole('button', { name: 'Salir' })).toBeVisible()

  await page.goto('/perfil')
  await expect(page.getByText(/todavia no esta verificado/i)).toBeVisible()

  // El enlace del correo, seguido de verdad.
  const mensaje = await ultimoCorreoPara(correo)
  const cuerpo = await textoDelCorreo(mensaje.ID)
  const enlace = cuerpo.match(/https?:\/\/\S*verify-email\S*/)?.[0]

  expect(enlace, 'el correo de verificacion tiene que traer un enlace').toBeTruthy()

  await page.goto(enlace!.replace(/[)\].,]+$/, ''))

  // El backend redirige a la SPA con ?verificado=1, y esa ruta si existe.
  await expect(page.getByText('Tu correo ha quedado verificado.')).toBeVisible()
  await expect(page.getByText(/todavia no esta verificado/i)).not.toBeVisible()
})

test('entrar con la cuenta sembrada', async ({ page }) => {
  await entrar(page, CLIENTE)

  await expect(page.getByRole('link', { name: 'Cliente de prueba' })).toBeVisible()
})

/**
 * SEC-005 / D2 — la sesion vive en una cookie HttpOnly y no en
 * `localStorage`. En v1 el JWT estaba ahi (`auth.js:7-10`), que es lo que
 * convertia cualquier XSS en robo de sesion.
 */
test('no deja la sesion al alcance de JavaScript', async ({ page, context }) => {
  await entrar(page, CLIENTE)

  const guardado = await page.evaluate(() => ({
    local: { ...localStorage },
    session: { ...sessionStorage },
    visiblesDesdeJs: document.cookie,
  }))

  // Nada de la sesion se guarda donde un XSS pueda leerlo.
  expect(guardado.local).toEqual({})
  expect(guardado.session).toEqual({})
  expect(guardado.visiblesDesdeJs).not.toMatch(/_session/i)

  /*
   * Y la prueba en positivo: la cookie de sesion existe y es HttpOnly.
   *
   * Sin esta mitad el test pasaria igual con una aplicacion que no
   * autenticara nada. `XSRF-TOKEN` si es legible desde JavaScript y tiene
   * que serlo —es como axios compone la cabecera `X-XSRF-TOKEN`—, asi que
   * no vale mirar «que no haya cookies»: hay que mirar cual.
   */
  // Por sufijo y no por nombre exacto: Laravel lo deriva de `APP_NAME`, asi
  // que fijarlo aqui haria que renombrar el proyecto rompiera este test por
  // algo que no tiene que ver con la seguridad de la cookie.
  const sesion = (await context.cookies()).find((c) => c.name.endsWith('_session'))

  expect(sesion, 'tiene que haber cookie de sesion').toBeTruthy()
  expect(sesion!.httpOnly).toBe(true)
})

test('cerrar sesion deja la web sin acceso a lo privado', async ({ page }) => {
  await entrar(page, CLIENTE)

  await page.getByRole('button', { name: 'Salir' }).click()
  await expect(page.getByRole('link', { name: 'Entrar' })).toBeVisible()

  await page.goto('/pedidos')
  await expect(page).toHaveURL(/\/login/)
})
