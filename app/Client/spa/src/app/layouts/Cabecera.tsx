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
 * Cada enlace se anadio con la pantalla que abre, nunca antes: un enlace a
 * una ruta que no existe es peor que no tenerlo. Galeria volvio con D33; el
 * enlace a «Sobre mi» vive en el pie, que es donde se busca a quien hay
 * detras.
 */
export function Cabecera() {
  const { autenticado, esAdmin, usuario } = useSesion()
  const cerrar = useCerrarSesion()

  // Solo con sesion: el carrito vive en el servidor y sin ella no hay nada
  // que pedir. Un enlace con el numero, no el panel desplegable de v1 —
  // aquel era donde vivian la peticion por linea y el innerHTML sin escapar.
  const { data: carrito } = useCarrito(autenticado)
  const lineas = carrito?.items.length ?? 0

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
          <NavLink
            to="/encargos"
            className={({ isActive }) =>
              `rounded-fefu px-3 py-1 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
            }
          >
            Encargos
          </NavLink>

          <NavLink
            to="/live-art"
            className={({ isActive }) =>
              `rounded-fefu px-3 py-1 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
            }
          >
            Live Art
          </NavLink>

          <NavLink
            to="/galeria"
            className={({ isActive }) =>
              `rounded-fefu px-3 py-1 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
            }
          >
            Galeria
          </NavLink>

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
                to="/carrito"
                className={({ isActive }) =>
                  `rounded-fefu px-3 py-1 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
                }
              >
                Carrito{lineas > 0 && ` (${lineas})`}
              </NavLink>

              <NavLink
                to="/pedidos"
                className={({ isActive }) =>
                  `rounded-fefu px-3 py-1 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
                }
              >
                Pedidos
              </NavLink>

              <EnlaceDeAvisos />

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
