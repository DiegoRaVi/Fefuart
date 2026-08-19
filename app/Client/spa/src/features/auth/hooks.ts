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
      /*
       * La sesion primero, y con `setQueryData`: es lo que ve el observador
       * vivo del AuthProvider, y por tanto lo que apaga la cabecera en el
       * acto.
       *
       * **Aqui habia `queryClient.clear()`, y era un fallo real.** `clear()`
       * borra del cache la entrada a la que ese observador esta enganchado y
       * lo deja huerfano: nadie vuelve a notificarle nada, asi que seguia
       * pintando al usuario anterior —con su nombre y sus enlaces privados—
       * hasta que alguien recargaba la pagina entera. El servidor si cerraba
       * la sesion; lo que mentia era la pantalla.
       */
      queryClient.setQueryData(CLAVE_SESION, null)

      /*
       * Y despues el resto de la cache, que sigue habiendo que tirar: puede
       * haber pedidos, carrito y datos personales de quien acaba de salir, y
       * dejarlos ahi significa que el siguiente en entrar en el mismo
       * navegador los ve.
       *
       * `resetQueries` y no `clear`: devuelve cada consulta a su estado
       * inicial **notificando a quien la estuviera mirando**, que es
       * justamente lo que `clear` no hace.
       */
      await queryClient.resetQueries()

      navigate('/', { replace: true })
    },
  })
}
