import { Outlet } from 'react-router'

import { Cabecera } from './Cabecera'
import { PieDePagina } from './PieDePagina'

/**
 * La cabecera, el pie y lo que haya en medio.
 *
 * `<main>` **no** limita el ancho. Lo limita `Centrado`, que envuelve a casi
 * todas las rutas; las que quedan fuera —la portada y Live Art— son las que
 * llevan bandas de color de borde a borde, y desde dentro de un contenedor
 * con `max-w` eso no se puede hacer sin margenes negativos de 50vw, que
 * sacan una barra de scroll horizontal en cuanto la ventana tiene la
 * vertical. Sacar el limite de aqui sale mas barato que pelearse con eso.
 */
export function LayoutPrincipal() {
  return (
    <div className="flex min-h-screen flex-col">
      <Cabecera />

      <main className="flex-1">
        <Outlet />
      </main>

      <PieDePagina />
    </div>
  )
}

/** El ancho de lectura de siempre, para todo lo que no va a sangre. */
export function Centrado() {
  return (
    <div className="mx-auto w-full max-w-6xl px-6 py-10">
      <Outlet />
    </div>
  )
}
