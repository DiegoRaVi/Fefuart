import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'

import { ApiError } from '@/shared/api/errors'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'

import { desactivarCuenta, suprimirCuenta } from './api'

/**
 * D21 y D22 — aparcar la cuenta o suprimirla, que no es lo mismo.
 *
 * Se enseñan juntas y con la diferencia escrita, porque es la unica forma de
 * que alguien elija bien: mucha gente que escribe «borradme la cuenta» quiere
 * dejar de recibir correos, no perder el historial de sus encargos.
 *
 * La supresion pide la contrasena y va detras de una confirmacion. No por
 * ceremonia: es irreversible y se lleva las fotos que subio.
 */
export function CerrarLaCuenta() {
  const [confirmando, setConfirmando] = useState(false)
  const [password, setPassword] = useState('')

  const alSalir = () => {
    // Recarga completa a proposito: la sesion ya no existe en el servidor y
    // lo mas limpio es que la aplicacion arranque de cero.
    window.location.assign('/')
  }

  const desactivar = useMutation({ mutationFn: desactivarCuenta, onSuccess: alSalir })
  const suprimir = useMutation({
    mutationFn: () => suprimirCuenta(password),
    onSuccess: alSalir,
  })

  return (
    <section aria-labelledby="cerrar" className="space-y-4 border-t border-piedra/20 pt-6">
      <h2 id="cerrar" className="text-seccion text-verde">
        Cerrar la cuenta
      </h2>

      <div className="space-y-2">
        <h3 className="text-apartado text-verde">Aparcarla</h3>
        <p className="text-base text-piedra">
          Dejas de poder entrar y no recibes mas correos, pero no se pierde
          nada. Escríbenos cuando quieras recuperarla.
        </p>

        <Boton
          type="button"
          variante="secundario"
          disabled={desactivar.isPending}
          onClick={() => desactivar.mutate()}
        >
          {desactivar.isPending ? 'Aparcando...' : 'Aparcar mi cuenta'}
        </Boton>

        {desactivar.isError && <Aviso tono="error">{desactivar.error.message}</Aviso>}
      </div>

      <div className="space-y-2">
        <h3 className="text-apartado text-verde">Suprimirla del todo</h3>
        <p className="text-base text-piedra">
          Borramos tu nombre, tu correo, tu teléfono, tus direcciones y las
          fotos que hayas subido. <strong>No se puede deshacer.</strong>
        </p>

        {/* Se dice lo que NO se borra, y por que: enterarse despues de que el
            pedido sigue ahi seria peor que leerlo ahora. */}
        <p className="text-sm text-piedra">
          Tus pedidos se conservan sin tus datos, solo con el importe y la
          fecha: la ley obliga a guardar esa parte durante unos años.
        </p>

        {confirmando ? (
          <form
            className="space-y-3"
            onSubmit={(e) => {
              e.preventDefault()
              suprimir.mutate()
            }}
          >
            <Campo
              etiqueta="Tu contraseña"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />

            <div className="flex flex-wrap gap-2">
              <Boton type="submit" disabled={suprimir.isPending}>
                {suprimir.isPending ? 'Suprimiendo...' : 'Suprimir mi cuenta para siempre'}
              </Boton>

              <Boton type="button" variante="secundario" onClick={() => setConfirmando(false)}>
                Dejarlo
              </Boton>
            </div>

            {suprimir.isError && (
              <Aviso tono="error">
                {suprimir.error instanceof ApiError
                  ? (suprimir.error.errors.account?.[0] ?? suprimir.error.message)
                  : suprimir.error.message}
              </Aviso>
            )}
          </form>
        ) : (
          <Boton type="button" variante="secundario" onClick={() => setConfirmando(true)}>
            Suprimir mi cuenta
          </Boton>
        )}
      </div>
    </section>
  )
}
