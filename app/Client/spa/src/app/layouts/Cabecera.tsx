import { useState, type ReactNode } from 'react'
import { Link, NavLink } from 'react-router'

import { EnlaceDeAvisos } from '@/features/avisos/EnlaceDeAvisos'
import { useCerrarSesion } from '@/features/auth/hooks'
import { useSesion } from '@/features/auth/sesion'
import { useCarrito } from '@/features/cart/api'

/**
 * El header de v1: fondo rosa, marca FEFUART y navegacion.
 *
 * El texto va en verde y no en blanco. v1 usaba blanco sobre el rosa, que da
 * 1,75:1 — ver el comentario del tema y `contraste.test.ts`.
 *
 * **Se pliega por debajo de `md`.** Antes eran ocho enlaces en `flex-wrap`
 * dentro de un contenedor con ancho maximo, y a 390 px la barra se salia de
 * la pantalla: con sesion iniciada, la mitad de la navegacion quedaba fuera
 * del viewport y la pagina scrolleaba en horizontal. Lo destapo la auditoria
 * de UX del 2026-08-20, y para una artista que se promociona en Instagram esa
 * era la primera impresion de casi todo el mundo.
 *
 * Cada enlace se anadio con la pantalla que abre, nunca antes: un enlace a una
 * ruta que no existe es peor que no tenerlo.
 */
export function Cabecera() {
  const { autenticado, esAdmin, usuario } = useSesion()
  const cerrar = useCerrarSesion()
  const [abierto, setAbierto] = useState(false)

  // Solo con sesion: el carrito vive en el servidor y sin ella no hay nada
  // que pedir. Un enlace con el numero, no el panel desplegable de v1 —
  // aquel era donde vivian la peticion por linea y el innerHTML sin escapar.
  const { data: carrito } = useCarrito(autenticado && ! esAdmin)
  const lineas = carrito?.items.length ?? 0

  const cerrarMenu = () => setAbierto(false)

  const navegacion = (
    <>
      <Enlace to="/encargos" onClick={cerrarMenu}>
        Encargos
      </Enlace>

      <Enlace to="/live-art" onClick={cerrarMenu}>
        Live Art
      </Enlace>

      <Enlace to="/galeria" onClick={cerrarMenu}>
        Galería
      </Enlace>

      {esAdmin && (
        <Enlace to="/backoffice" onClick={cerrarMenu}>
          Backoffice
        </Enlace>
      )}

      {autenticado ? (
        <>
          {/*
            La administradora no compra en su propia tienda. Ademas eran dos
            enlaces de mas justo en la barra que se desbordaba.
          */}
          {! esAdmin && (
            <>
              <Enlace to="/carrito" onClick={cerrarMenu}>
                Carrito{lineas > 0 && ` (${lineas})`}
              </Enlace>

              <Enlace to="/pedidos" onClick={cerrarMenu}>
                Pedidos
              </Enlace>
            </>
          )}

          <EnlaceDeAvisos onNavegar={cerrarMenu} />

          <Enlace to="/perfil" onClick={cerrarMenu}>
            {usuario?.name}
          </Enlace>

          <button
            type="button"
            onClick={() => {
              cerrarMenu()
              cerrar.mutate()
            }}
            disabled={cerrar.isPending}
            className="rounded-fefu px-3 py-2 text-left underline underline-offset-4 hover:bg-rosa-hondo disabled:opacity-50"
          >
            {cerrar.isPending ? 'Saliendo...' : 'Salir'}
          </button>
        </>
      ) : (
        <>
          <Enlace to="/login" onClick={cerrarMenu}>
            Entrar
          </Enlace>

          <Link
            to="/registro"
            onClick={cerrarMenu}
            className="rounded-fefu bg-verde px-3 py-2 text-white hover:bg-verde-hondo"
          >
            Crear cuenta
          </Link>
        </>
      )}
    </>
  )

  return (
    <header className="sticky top-0 z-50 bg-rosa shadow-[0_2px_8px_rgba(0,0,0,0.1)]">
      {/*
        En escritorio, marca y navegacion comparten fila; por debajo de `md`
        la navegacion cae debajo, desplegada por el boton.
      */}
      <nav
        aria-label="Principal"
        className="mx-auto max-w-6xl px-6 py-4 md:flex md:items-center md:justify-between md:gap-6"
      >
        <div className="flex items-center justify-between gap-4">
          <Link
            to="/"
            onClick={cerrarMenu}
            className="text-3xl font-bold tracking-wide text-verde hover:text-verde-hondo"
          >
            FEFUART
          </Link>

          {/*
            Un solo boton, y solo por debajo de `md`. `aria-expanded` no es
            decoracion: es lo unico que le dice a un lector de pantalla si el
            menu esta abierto, porque la palabra sola no lo dice.
          */}
          <button
            type="button"
            onClick={() => setAbierto((estaba) => ! estaba)}
            aria-expanded={abierto}
            aria-controls="menu-principal"
            className="rounded-fefu px-3 py-2 text-verde hover:bg-rosa-hondo md:hidden"
          >
            {abierto ? 'Cerrar menu' : 'Menu'}
          </button>
        </div>

        {/*
          **Una sola copia de la navegacion**, no dos.
          Lo natural seria pintar una version ancha y otra plegable y ocultar
          cada una con `md:`, y estarian las dos en el DOM: un lector de
          pantalla anunciaria cada enlace por duplicado sin que nadie lo vea.
          Aqui el mismo bloque cambia de forma — columna desplegable abajo,
          fila en linea arriba.
        */}
        <div
          id="menu-principal"
          className={`${
            abierto
              ? 'mt-3 flex flex-col items-stretch gap-1 border-t border-verde/20 pt-3'
              : 'hidden'
          } text-verde md:mt-0 md:flex md:flex-row md:items-center md:gap-4 md:border-0 md:pt-0`}
        >
          {navegacion}
        </div>
      </nav>
    </header>
  )
}

/**
 * Un enlace de la barra. Se extrae porque el mismo juego se pinta dos veces
 * —en linea y desplegado— y mantener dos copias de las clases es como acaban
 * divergiendo.
 */
function Enlace({
  to,
  onClick,
  children,
}: {
  to: string
  onClick: () => void
  children: ReactNode
}) {
  return (
    <NavLink
      to={to}
      onClick={onClick}
      className={({ isActive }) =>
        `rounded-fefu px-3 py-2 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
      }
    >
      {children}
    </NavLink>
  )
}
