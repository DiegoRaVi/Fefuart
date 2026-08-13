import { Navigate, Outlet, useLocation } from 'react-router'

import { useSesion } from '@/features/auth/sesion'
import { Cargando } from '@/shared/ui/Cargando'

/**
 * Exige sesion. Mientras no se sepa si la hay, no se decide nada: redirigir
 * durante la primera consulta echaria fuera a quien si tiene sesion cada vez
 * que recarga la pagina.
 *
 * El backend vuelve a comprobarlo con `auth:sanctum` y con las Policies.
 * Esto es comodidad de navegacion, no seguridad: quien se salte el router no
 * consigue nada.
 */
export function RutaProtegida() {
  const { cargando, autenticado } = useSesion()
  const ubicacion = useLocation()

  if (cargando) {
    return <Cargando />
  }

  if (!autenticado) {
    // `state.desde` permite volver a donde iba despues de entrar.
    return <Navigate to="/login" replace state={{ desde: ubicacion.pathname }} />
  }

  return <Outlet />
}

/**
 * N20 — el backoffice es de la administradora. El rol sale de
 * `GET /api/auth/me`, nunca de `localStorage`.
 */
export function RutaDeAdmin() {
  const { cargando, autenticado, esAdmin } = useSesion()
  const ubicacion = useLocation()

  if (cargando) {
    return <Cargando />
  }

  if (!autenticado) {
    return <Navigate to="/login" replace state={{ desde: ubicacion.pathname }} />
  }

  // A la portada y no a /login: quien esta dentro no tiene que volver a
  // identificarse, sencillamente esto no es para el.
  if (!esAdmin) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}

/**
 * Login y registro no tienen sentido con la sesion abierta, y dejarlos
 * accesibles invita a que alguien inicie sesion encima de otra.
 */
export function RutaDeInvitado() {
  const { cargando, autenticado } = useSesion()

  if (cargando) {
    return <Cargando />
  }

  if (autenticado) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}
