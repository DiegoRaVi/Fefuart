import { NavLink } from 'react-router'

import { useSesion } from '@/features/auth/sesion'

import { useAvisos } from './api'

/**
 * D10 — el acceso al centro de avisos desde la cabecera, con el numero de
 * los que quedan sin leer.
 *
 * Un enlace con contador y no un panel desplegable, por la misma razon que
 * el carrito: el desplegable de v1 era donde vivian la peticion por linea y
 * el `innerHTML` sin escapar que acabo siendo SEC-005.
 */
export function EnlaceDeAvisos({ onNavegar }: { onNavegar?: () => void } = {}) {
  const { autenticado } = useSesion()
  const { data } = useAvisos(autenticado)

  const sinLeer = data?.meta.no_leidos ?? 0

  return (
    <NavLink
      to="/avisos"
      onClick={onNavegar}
      className={({ isActive }) =>
        `rounded-fefu px-3 py-2 hover:bg-rosa-hondo ${isActive ? 'bg-rosa-hondo' : ''}`
      }
    >
      {/* Sin numero cuando no hay nada: un «(0)» permanente solo es ruido. */}
      Avisos{sinLeer > 0 && ` (${sinLeer})`}
    </NavLink>
  )
}
