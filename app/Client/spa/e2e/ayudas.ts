import { expect, type Page } from '@playwright/test'

/**
 * Lo que comparten los cuatro recorridos.
 *
 * Las cuentas sembradas por `DatabaseSeeder`. La contrasena la fija
 * `UserFactory`, no este fichero: si algun dia cambia alli, estos
 * recorridos fallan al entrar, que es exactamente lo que tiene que pasar.
 */
export const CLIENTE = { email: 'cliente@fefuart.test', password: 'password' }
export const ARTISTA = { email: 'admin@fefuart.test', password: 'password' }

const MAILPIT = 'http://127.0.0.1:8025/api/v1'

export async function entrar(page: Page, quien: { email: string; password: string }): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('Correo').fill(quien.email)
  await page.getByLabel('Contrasena').fill(quien.password)
  await page.getByRole('button', { name: 'Entrar' }).click()

  /*
   * Se espera a que desaparezca «Crear cuenta», que es un enlace que **solo**
   * se pinta sin sesion.
   *
   * Dos cosas a la vez, y las dos hacen falta. Buscar «Salir» ataba el helper
   * al ancho de la ventana: desde que la cabecera se pliega por debajo de
   * 768 px, esa navegacion esta detras del boton de menu y cualquier
   * recorrido movil fallaba. Y comprobar que el formulario ya no esta no
   * basta: se cumple en cuanto la pantalla de login se desmonta, que ocurre
   * **antes** de que la consulta de sesion se resuelva — asi que el siguiente
   * `goto` a una ruta protegida llegaba sin usuario y rebotaba.
   *
   * Esperar a que se vaya «Crear cuenta» espera a lo segundo, y funciona en
   * los dos tamanos porque la navegacion es una sola copia en el DOM: por
   * debajo de `md` se oculta con CSS, no se desmonta.
   */
  await expect(page.getByRole('link', { name: 'Crear cuenta' })).toHaveCount(0)
}

interface CorreoDeMailpit {
  ID: string
  Subject: string
  To: { Address: string }[]
}

/**
 * El ultimo correo dirigido a una direccion.
 *
 * Se pregunta a Mailpit en bucle porque el envio y la respuesta HTTP no
 * estan sincronizados: la peticion puede haber terminado antes de que el
 * SMTP local haya aceptado el mensaje.
 */
export async function ultimoCorreoPara(direccion: string, intentos = 20): Promise<CorreoDeMailpit> {
  for (let intento = 0; intento < intentos; intento++) {
    const respuesta = await fetch(`${MAILPIT}/messages?limit=50`)
    const { messages } = (await respuesta.json()) as { messages: CorreoDeMailpit[] }

    const suyo = messages.find((m) => m.To.some((t) => t.Address === direccion))

    if (suyo) {
      return suyo
    }

    await new Promise((resolve) => setTimeout(resolve, 300))
  }

  throw new Error(`No llego ningun correo para ${direccion}.`)
}

/** El cuerpo en texto plano de un correo, para sacarle los enlaces. */
export async function textoDelCorreo(id: string): Promise<string> {
  const respuesta = await fetch(`${MAILPIT}/message/${id}`)
  const { Text } = (await respuesta.json()) as { Text: string }

  return Text
}

export async function vaciarLaBandeja(): Promise<void> {
  await fetch(`${MAILPIT}/messages`, { method: 'DELETE' })
}

/** Una direccion distinta en cada ejecucion, para no chocar con `unique:users`. */
export function unCorreoNuevo(prefijo: string): string {
  return `${prefijo}-${Date.now()}@fefuart.test`
}
