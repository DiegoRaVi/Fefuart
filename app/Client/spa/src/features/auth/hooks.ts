import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'

import type { Credentials, RegistrationData, User } from '@/shared/api/types'

import { cerrarSesion, iniciarSesion, registrarse } from './api'
import { CLAVE_SESION } from './sesion'

/**
 * Tras entrar o registrarse, el usuario que devuelve el endpoint se escribe
 * directamente en la cache: es exactamente lo que responderia `me`, asi que
 * volver a preguntarlo seria una ida y vuelta de mas.
 */
function useMutacionDeSesion<T>(
  fn: (datos: T) => Promise<User>,
  { alEntrar }: { alEntrar: string },
) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  return useMutation({
    mutationFn: fn,
    onSuccess: (usuario) => {
      queryClient.setQueryData(CLAVE_SESION, usuario)
      navigate(alEntrar, { replace: true })
    },
  })
}

export function useIniciarSesion(destino = '/') {
  return useMutacionDeSesion<Credentials>(iniciarSesion, { alEntrar: destino })
}

export function useRegistrarse(destino = '/') {
  return useMutacionDeSesion<RegistrationData>(registrarse, { alEntrar: destino })
}

export function useCerrarSesion() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  return useMutation({
    mutationFn: cerrarSesion,
    onSuccess: async () => {
      // La cache entera, no solo la sesion: puede haber pedidos, carrito y
      // datos personales de quien acaba de salir. Dejarlos ahi significa que
      // el siguiente en entrar en el mismo navegador los ve.
      queryClient.clear()
      queryClient.setQueryData(CLAVE_SESION, null)
      navigate('/', { replace: true })
    },
  })
}
