import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { BusquedaPrecisa, type CampoDeBusqueda } from './BusquedaPrecisa'

const CAMPOS: CampoDeBusqueda[] = [
  { valor: 'numero', etiqueta: 'Numero de pedido', ayuda: '7', tipo: 'number' },
  { valor: 'nombre', etiqueta: 'Nombre', ayuda: 'De la cuenta o del envio' },
  { valor: 'email', etiqueta: 'Correo', ayuda: 'marta@ejemplo.com', tipo: 'email' },
  { valor: 'telefono', etiqueta: 'Telefono', ayuda: '600123456', tipo: 'tel' },
]

/**
 * jsdom no implementa showModal/close de <dialog>. Se sustituyen por lo
 * minimo que hace falta —marcar `open`— para poder probar el contenido.
 */
beforeEach(() => {
  HTMLDialogElement.prototype.showModal = vi.fn(function (this: HTMLDialogElement) {
    this.open = true
  })
  HTMLDialogElement.prototype.close = vi.fn(function (this: HTMLDialogElement) {
    this.open = false
    this.dispatchEvent(new Event('close'))
  })
})

function abrir(props: Partial<React.ComponentProps<typeof BusquedaPrecisa>> = {}) {
  const onBuscar = vi.fn()
  const onCerrar = vi.fn()

  render(
    <BusquedaPrecisa
      abierto
      onCerrar={onCerrar}
      campos={CAMPOS}
      busqueda={null}
      onBuscar={onBuscar}
      {...props}
    />,
  )

  return { onBuscar, onCerrar }
}

/**
 * Lo que distingue esta busqueda de la caja rapida: se elige el dato, y solo
 * uno. Dos campos a la vez obligarian a decidir si se combinan con Y o con
 * O, y ninguna respuesta es evidente para quien busca con prisa.
 */
describe('solo un campo a la vez', () => {
  it('deja escribir unicamente en el elegido', () => {
    abrir()

    expect(screen.getByLabelText('Numero de pedido')).not.toHaveAttribute('readonly')
    expect(screen.getByLabelText('Nombre')).toHaveAttribute('readonly')
    expect(screen.getByLabelText('Correo')).toHaveAttribute('readonly')
    expect(screen.getByLabelText('Telefono')).toHaveAttribute('readonly')
  })

  it('cambia cual esta activo al elegir otro', async () => {
    abrir()

    await userEvent.click(screen.getByRole('radio', { name: 'Buscar por telefono' }))

    expect(screen.getByLabelText('Telefono')).not.toHaveAttribute('readonly')
    expect(screen.getByLabelText('Numero de pedido')).toHaveAttribute('readonly')
  })

  /**
   * Un telefono no vale como nombre: arrastrar el termino de un campo a otro
   * produce busquedas sin sentido que parecen fallos del buscador.
   */
  it('vacia el termino al cambiar de campo', async () => {
    abrir()

    await userEvent.type(screen.getByLabelText('Numero de pedido'), '7')
    await userEvent.click(screen.getByRole('radio', { name: 'Buscar por nombre' }))

    expect(screen.getByLabelText('Nombre')).toHaveValue('')
  })

  /** Escribir en un campo apagado no hace nada; pinchar en el lo elige. */
  it('elige el campo al pinchar en su casilla de texto', async () => {
    abrir()

    await userEvent.click(screen.getByLabelText('Correo'))

    expect(screen.getByLabelText('Correo')).not.toHaveAttribute('readonly')
    expect(screen.getByRole('radio', { name: 'Buscar por correo' })).toBeChecked()
  })
})

describe('lo que devuelve', () => {
  it('da el campo y el termino elegidos', async () => {
    const { onBuscar, onCerrar } = abrir()

    await userEvent.click(screen.getByRole('radio', { name: 'Buscar por correo' }))
    await userEvent.type(screen.getByLabelText('Correo'), 'marta@fefuart.test')
    await userEvent.click(screen.getByRole('button', { name: 'Buscar' }))

    expect(onBuscar).toHaveBeenCalledWith({
      campo: 'email',
      termino: 'marta@fefuart.test',
    })
    expect(onCerrar).toHaveBeenCalled()
  })

  it('quita los espacios de los lados', async () => {
    const { onBuscar } = abrir()

    await userEvent.click(screen.getByRole('radio', { name: 'Buscar por nombre' }))
    await userEvent.type(screen.getByLabelText('Nombre'), '  Marta  ')
    await userEvent.click(screen.getByRole('button', { name: 'Buscar' }))

    expect(onBuscar).toHaveBeenCalledWith({ campo: 'nombre', termino: 'Marta' })
  })

  /** Buscar sin escribir nada es no buscar, no buscar la cadena vacia. */
  it('deshace la busqueda si se aplica en blanco', async () => {
    const { onBuscar } = abrir()

    await userEvent.click(screen.getByRole('button', { name: 'Buscar' }))

    expect(onBuscar).toHaveBeenCalledWith(null)
  })

  it('ofrece quitar la busqueda cuando ya hay una', async () => {
    const { onBuscar } = abrir({ busqueda: { campo: 'nombre', termino: 'Marta' } })

    await userEvent.click(screen.getByRole('button', { name: 'Quitar la busqueda' }))

    expect(onBuscar).toHaveBeenCalledWith(null)
  })

  it('no la ofrece cuando no hay ninguna', () => {
    abrir()

    expect(
      screen.queryByRole('button', { name: 'Quitar la busqueda' }),
    ).not.toBeInTheDocument()
  })

  it('arranca con el campo que ya estaba buscandose', () => {
    abrir({ busqueda: { campo: 'telefono', termino: '600123456' } })

    expect(screen.getByLabelText('Telefono')).toHaveValue('600123456')
    expect(screen.getByLabelText('Nombre')).toHaveAttribute('readonly')
  })
})
