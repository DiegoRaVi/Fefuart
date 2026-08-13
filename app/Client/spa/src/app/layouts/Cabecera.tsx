import { Link, NavLink } from 'react-router'

import { useCerrarSesion } from '@/features/auth/hooks'
import { useSesion } from '@/features/auth/sesion'

/**
 * El header de v1: fondo rosa, marca FEFUART y navegacion.
 *
 * El texto va en verde y no en blanco. v1 usaba blanco sobre el rosa, que da
 * 1,75:1 — ver el comentario del tema y `contraste.test.ts`.
 *
 * Servicios, Galeria, About y el carrito llegan en la Fase 4, con las
 * pantallas que abren. Un enlace a una ruta que no existe es peor que no
 * tenerlo.
 */
export function Cabecera() {
  const { autenticado, esAdmin, usuario } = useSesion()
  const cerrar = useCerrarSesion()

  return (
    <header className="sticky top-0 z-50 bg-rosa shadow-[0_2px_8px_rgba(0,0,0,0.1)]">
      <nav
        aria-label="Principal"
        className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-4"
      >
        <Link
          to="/"
          className="text-3xl font-bold tracking-wide text-verde hover:text-verde-hondo"
        >
          FEFUART
        </Link>

        <div className="flex items-center gap-4 text-verde">
          {esAdmin && (
            <NavLink
              to="/backoffice"
              className={({ isActive }) =>
                `rounded-fefu px-3 py-1 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
              }
            >
              Backoffice
            </NavLink>
          )}

          {autenticado ? (
            <>
              <NavLink
                to="/perfil"
                className={({ isActive }) =>
                  `rounded-fefu px-3 py-1 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
                }
              >
                {usuario?.name}
              </NavLink>

              <button
                type="button"
                onClick={() => cerrar.mutate()}
                disabled={cerrar.isPending}
                className="rounded-fefu px-3 py-1 underline underline-offset-4 hover:bg-rosa-hondo disabled:opacity-50"
              >
                {cerrar.isPending ? 'Saliendo...' : 'Salir'}
              </button>
            </>
          ) : (
            <>
              <NavLink to="/login" className="rounded-fefu px-3 py-1 hover:bg-rosa-hondo">
                Entrar
              </NavLink>
              <NavLink
                to="/registro"
                className="rounded-fefu bg-verde px-3 py-1 text-white hover:bg-verde-hondo"
              >
                Crear cuenta
              </NavLink>
            </>
          )}
        </div>
      </nav>
    </header>
  )
}
