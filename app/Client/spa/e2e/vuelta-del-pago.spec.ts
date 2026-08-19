import { execFileSync } from 'node:child_process'
import { createHmac } from 'node:crypto'
import { fileURLToPath } from 'node:url'

import { expect, test } from '@playwright/test'

import { CLIENTE, entrar } from './ayudas'

/**
 * La pantalla de vuelta de la pasarela, que es la unica que no cubre ningun
 * otro test.
 *
 * D29 dice que la vuelta del navegador **no prueba nada**: es una URL que
 * cualquiera puede escribir a mano. La pantalla lo respeta preguntando al
 * servidor hasta que el webhook haya movido el estado, y eso es exactamente
 * lo que se comprueba aqui: primero que quedarse mirando no basta, y despues
 * que cuando el aviso firmado llega, la pantalla cambia sola.
 *
 * El aviso se firma en este mismo fichero con el secreto de `.env.e2e`. No
 * se llama a Stripe en ningun momento.
 */
const SECRETO = 'whsec_e2e_secreto_conocido'
const BACKEND = 'http://127.0.0.1:8000'

/** Un `checkout.session.completed` con la firma que calcularia Stripe. */
async function entregarElAviso(sesion: string, centimos: number, id: string): Promise<number> {
  const cuerpo = JSON.stringify({
    id,
    object: 'event',
    api_version: '2026-07-29.dahlia',
    created: Math.floor(Date.now() / 1000),
    type: 'checkout.session.completed',
    data: {
      object: {
        id: sesion,
        object: 'checkout.session',
        status: 'complete',
        payment_status: 'paid',
        amount_total: centimos,
        currency: 'eur',
        payment_intent: `pi_${id}`,
        mode: 'payment',
      },
    },
  })

  const t = Math.floor(Date.now() / 1000)
  const firma = createHmac('sha256', SECRETO).update(`${t}.${cuerpo}`).digest('hex')

  const respuesta = await fetch(`${BACKEND}/api/webhooks/stripe`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Stripe-Signature': `t=${t},v1=${firma}` },
    body: cuerpo,
  })

  return respuesta.status
}

/**
 * Inserta el cobro que habria creado la pasarela, sin hablar con ella.
 *
 * Es lo que permite que este recorrido sea offline: `payments` necesita una
 * fila con `provider_session_id` para que el webhook sepa a que pedido se
 * refiere, y crearla por la API significaria abrir una sesion de Checkout de
 * verdad.
 */
function abrirCobroAMano(pedido: number, sesion: string): void {
  const php = `
    $pago = new App\\Models\\Payment;
    $pago->payable_type = App\\Models\\Order::class;
    $pago->payable_id = ${pedido};
    $pago->provider = 'stripe';
    $pago->provider_session_id = '${sesion}';
    $pago->amount = '45.00';
    $pago->currency = 'EUR';
    $pago->status = App\\Enums\\PaymentStatus::Pending;
    $pago->kind = App\\Enums\\PaymentKind::Full;
    $pago->idempotency_key = 'e2e:${sesion}';
    $pago->save();
  `

  execFileSync('php', ['artisan', 'tinker', '--execute', php], {
    cwd: fileURLToPath(new URL('../../../Server', import.meta.url)),
    env: { ...process.env, APP_ENV: 'e2e' },
    stdio: 'ignore',
  })
}

/**
 * Deja un pedido pendiente de pago con su cobro abierto, sin pasar por
 * Stripe. Devuelve el id del pedido y el de la sesion inventada.
 */
async function unPedidoEsperandoElCobro(page: import('@playwright/test').Page) {
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

test('la vuelta de la pasarela no da nada por pagado hasta que llega el aviso', async ({ page }) => {
  await entrar(page, CLIENTE)

  const pedido = await unPedidoEsperandoElCobro(page)

  /*
   * La demostracion de D29: se entra en la pantalla de vuelta a mano, con un
   * identificador de sesion inventado, exactamente como podria hacerlo
   * cualquiera. No pasa nada — se queda esperando.
   */
  await page.goto(`/pedidos/${pedido}/pago?sesion=cs_test_inventada`)

  await expect(page.getByText('Confirmando tu pago')).toBeVisible()
  await expect(page.getByText(/Pago recibido/)).not.toBeVisible()
})

test('la pantalla cambia sola cuando el aviso firmado llega', async ({ page }) => {
  await entrar(page, CLIENTE)

  const pedido = await unPedidoEsperandoElCobro(page)
  const sesion = `cs_test_e2e_${Date.now()}`

  /*
   * El cobro se crea a mano y no con `POST /orders/{id}/pay`.
   *
   * Ese endpoint es el que llama a Stripe para abrir la sesion, y es
   * precisamente la parte que este recorrido no ejercita. Lo que se prueba
   * aqui empieza despues: existe un cobro pendiente, llega el aviso firmado,
   * y la pantalla tiene que enterarse sola.
   */
  abrirCobroAMano(pedido, sesion)

  await page.goto(`/pedidos/${pedido}/pago?sesion=${sesion}`)
  await expect(page.getByText('Confirmando tu pago')).toBeVisible()

  expect(await entregarElAviso(sesion, 4500, `evt_e2e_${Date.now()}`)).toBe(204)

  // Sin recargar, sin tocar nada: la pantalla pregunta cada dos segundos.
  await expect(page.getByText(/Pago recibido/)).toBeVisible({ timeout: 15_000 })
})
