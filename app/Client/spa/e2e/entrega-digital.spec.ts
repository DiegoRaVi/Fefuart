import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

import { expect, test, type Page } from '@playwright/test'

import { ARTISTA, CLIENTE, entrar, ultimoCorreoPara, vaciarLaBandeja } from './ayudas'

/**
 * D20, N11 — la artista entrega y el cliente descarga.
 *
 * Es el circuito que cierra el hueco heredado de v1: se vendia una variante
 * «Digital» que el sistema no sabia entregar. Los tests de Pest cubren los
 * permisos y la lista blanca; lo que solo se ve aqui es que el fichero que
 * sube ella es el que baja el navegador de el.
 */
const SERVIDOR = fileURLToPath(new URL('../../../Server', import.meta.url))

/** Un PNG minimo y valido, para no depender de ningun fichero del repo. */
function unPngEnDisco(): string {
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
  )

  const ruta = join(mkdtempSync(join(tmpdir(), 'entrega-')), 'lamina.png')
  writeFileSync(ruta, png)

  return ruta
}

/**
 * Deja un pedido pagado con una linea digital, sin pasar por la pasarela.
 *
 * El checkout de la SPA no sirve: la variante digital exige elegir entrega
 * digital y, sobre todo, el pedido tiene que estar **pagado** para poder
 * entregarse — y pagar de verdad esta fuera del alcance de los E2E.
 */
function unPedidoPagadoConLineaDigital(): { pedido: number; linea: number } {
  const php = `
    $cliente = App\\Models\\User::where('email', 'cliente@fefuart.test')->sole();
    $variante = App\\Models\\ProductVariant::where('name', 'Digital')->sole();
    $envio = App\\Models\\ShippingMethod::where('code', 'digital')->sole();

    $pedido = new App\\Models\\Order;
    $pedido->user_id = $cliente->id;
    $pedido->status = App\\Enums\\OrderStatus::Paid;
    $pedido->shipping_method_id = $envio->id;
    $pedido->subtotal = '20.00';
    $pedido->shipping_total = '0.00';
    $pedido->total = '20.00';
    $pedido->placed_at = now();
    $pedido->save();

    $linea = new App\\Models\\OrderItem;
    $linea->order_id = $pedido->id;
    $linea->product_id = $variante->product_id;
    $linea->product_variant_id = $variante->id;
    $linea->delivery_type = App\\Enums\\DeliveryType::Digital;
    $linea->quantity = 1;
    $linea->product_name = $variante->product->name;
    $linea->variant_name = $variante->name;
    $linea->unit_price = '20.00';
    $linea->additional_copy_price = '10.00';
    $linea->line_total = '20.00';
    $linea->save();

    echo 'FEFU:' . $pedido->id . ':' . $linea->id . ':FIN';
  `

  const salida = execFileSync('php', ['artisan', 'tinker', '--execute', php], {
    cwd: SERVIDOR,
    env: { ...process.env, APP_ENV: 'e2e' },
    encoding: 'utf-8',
  })

  // Se busca un marcador y no la ultima linea: `tinker` mezcla su propia
  // salida con la del script, y fiarse de la posicion daba `NaN`.
  const encontrado = salida.match(/FEFU:(\d+):(\d+):FIN/)

  if (encontrado === null) {
    throw new Error(`No se pudo crear el pedido de prueba. Salida:\n${salida}`)
  }

  return { pedido: Number(encontrado[1]), linea: Number(encontrado[2]) }
}

async function salir(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Salir' }).click()
  await expect(page.getByRole('link', { name: 'Entrar' })).toBeVisible()
}

test('la artista entrega y el cliente se la descarga', async ({ page }) => {
  await vaciarLaBandeja()

  const { pedido } = unPedidoPagadoConLineaDigital()

  // --- El cliente todavia no tiene nada ---
  await entrar(page, CLIENTE)
  await page.goto(`/pedidos/${pedido}`)

  await expect(page.getByText(/Felicitas todavía está con él/)).toBeVisible()
  await expect(page.getByRole('link', { name: 'Descargar mi encargo' })).not.toBeVisible()

  await salir(page)

  // --- La artista sube la obra ---
  await entrar(page, ARTISTA)
  await page.goto(`/backoffice/pedidos/${pedido}`)

  await page.getByLabel('Subir la entrega').setInputFiles(unPngEnDisco())

  await expect(page.getByText('Ya entregada.')).toBeVisible()

  await salir(page)

  // --- Y el cliente se entera y la baja ---
  const correo = await ultimoCorreoPara(CLIENTE.email)
  expect(correo.Subject).toContain('descargar')

  await entrar(page, CLIENTE)
  await page.goto(`/pedidos/${pedido}`)

  const descarga = page.waitForEvent('download')
  await page.getByRole('link', { name: 'Descargar mi encargo' }).click()

  const fichero = await descarga
  expect(await fichero.failure()).toBeNull()

  // El nombre es el del encargo, no el aleatorio del disco privado.
  expect(fichero.suggestedFilename()).toMatch(/\.png$/)
  expect(fichero.suggestedFilename()).not.toContain('entregas')
})

/**
 * El IDOR en el navegador: otra cuenta abriendo la URL de descarga a mano.
 * Los tests de Pest ya lo cubren, pero esta es la via por la que ocurriria
 * de verdad — un enlace copiado y pegado.
 */
test('otro cliente no puede abrir la descarga', async ({ page }) => {
  const { pedido, linea } = unPedidoPagadoConLineaDigital()

  await entrar(page, ARTISTA)
  await page.goto(`/backoffice/pedidos/${pedido}`)
  await page.getByLabel('Subir la entrega').setInputFiles(unPngEnDisco())
  await expect(page.getByText('Ya entregada.')).toBeVisible()
  await salir(page)

  // Una cuenta recien creada, que no tiene nada que ver con este pedido.
  await page.goto('/registro')
  await page.getByLabel('Nombre').fill('Intrusa')
  await page.getByLabel('Correo').fill(`intrusa-${Date.now()}@fefuart.test`)
  await page.getByLabel('Contraseña', { exact: true }).fill('unaclavelarga')
  await page.getByLabel('Repite la contraseña').fill('unaclavelarga')
  await page.getByRole('button', { name: 'Crear cuenta' }).click()
  await expect(page.getByRole('button', { name: 'Salir' })).toBeVisible()

  const url = `/api/orders/${pedido}/items/${linea}/download`

  /*
   * Con `Referer`, que es como llega la peticion cuando se pulsa el enlace
   * desde la SPA: Sanctum la trata como de sesion, la sesion existe, y lo que
   * decide es la Policy. **403: esta sesion no es la dueña del pedido.**
   *
   * Sin la cabecera el resultado seria 401, y el test habria pasado por el
   * motivo equivocado — «no hay sesion» en vez de «no es tuyo».
   */
  const conSesion = await page.request.get(`http://localhost:5173${url}`, {
    headers: { Referer: 'http://localhost:5173/pedidos' },
  })

  expect(conSesion.status()).toBe(403)

  /*
   * Y la otra via, que es la que usaria alguien de verdad: pegar la URL en la
   * barra. Una navegacion de primer nivel no manda `Referer`, asi que Sanctum
   * ni siquiera abre la sesion y responde 401. Falla cerrado, que es lo que
   * importa; se deja escrito para que nadie lo lea como un fallo.
   */
  const pegada = await page.goto(url)

  expect(pegada?.status()).toBe(401)
})
