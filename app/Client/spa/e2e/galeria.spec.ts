import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

import { expect, test } from '@playwright/test'

import { ARTISTA, entrar } from './ayudas'

/**
 * D33 — la galeria de punta a punta.
 *
 * Lo que solo se ve aqui: que una pieza subida desde el backoffice aparece en
 * el escaparate **sin haber iniciado sesion**, que es exactamente el caso de
 * uso — alguien que todavia no es cliente mirando lo que hace Felicitas.
 */
function unaImagenEnDisco(): string {
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
  )

  const ruta = join(mkdtempSync(join(tmpdir(), 'galeria-')), 'obra.png')
  writeFileSync(ruta, png)

  return ruta
}

test('la artista publica una pieza y se ve sin sesion', async ({ page }) => {
  const titulo = `Boda en Toledo ${Date.now()}`

  await entrar(page, ARTISTA)
  await page.goto('/backoffice/galeria')

  await page.getByLabel('Titulo').fill(titulo)
  await page.getByLabel('Imagen').setInputFiles(unaImagenEnDisco())
  await page.getByRole('button', { name: 'Anadir a la galeria' }).click()

  await expect(page.getByRole('heading', { name: titulo })).toBeVisible()

  await page.getByRole('button', { name: 'Salir' }).click()
  await expect(page.getByRole('link', { name: 'Entrar' })).toBeVisible()

  // Sin sesion: es el escaparate.
  await page.goto('/galeria')

  await expect(page.getByRole('img', { name: titulo })).toBeVisible()
})

test('lo que se oculta deja de verse en el escaparate', async ({ page }) => {
  const titulo = `Prueba oculta ${Date.now()}`

  await entrar(page, ARTISTA)
  await page.goto('/backoffice/galeria')

  await page.getByLabel('Titulo').fill(titulo)
  await page.getByLabel('Imagen').setInputFiles(unaImagenEnDisco())
  await page.getByRole('button', { name: 'Anadir a la galeria' }).click()

  const pieza = page.locator('article').filter({ hasText: titulo })
  await expect(pieza).toBeVisible()

  await pieza.getByRole('button', { name: 'Ocultar' }).click()
  await expect(pieza.getByRole('button', { name: 'Publicar' })).toBeVisible()

  await page.getByRole('button', { name: 'Salir' }).click()
  await page.goto('/galeria')

  await expect(page.getByRole('img', { name: titulo })).not.toBeVisible()
})
