import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useSearchParams } from 'react-router'
import { z } from 'zod'

import { reenviarVerificacion } from '@/features/auth/api'
import { CLAVE_SESION, useSesion } from '@/features/auth/sesion'
import { aplicarErroresDeApi } from '@/shared/api/formulario'
import { CerrarLaCuenta } from '../CerrarLaCuenta'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'
import { Cargando } from '@/shared/ui/Cargando'

import { actualizarPerfil, cambiarContrasena } from '../api'

const esquemaDatos = z.object({
  name: z.string().min(1, 'Escribe tu nombre.').max(100),
  email: z.string().min(1, 'Escribe tu correo.').email('Eso no parece un correo.'),
})

const esquemaContrasena = z
  .object({
    current_password: z.string().min(1, 'Escribe tu contraseña actual.'),
    password: z.string().min(8, 'Al menos ocho caracteres.'),
    password_confirmation: z.string().min(1, 'Repite la contraseña.'),
  })
  .refine((d) => d.password === d.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Las dos contrasenas no coinciden.',
  })

export function Perfil() {
  const { usuario, verificado, cargando } = useSesion()
  const [parametros] = useSearchParams()

  // `RutaProtegida` ya espera a la sesion antes de montar esto, pero el
  // componente no debe depender de que alguien lo haya envuelto: sin esto,
  // renderizado suelto pinta un formulario vacio con los datos de nadie.
  if (cargando || !usuario) {
    return <Cargando />
  }

  return (
    <div className="mx-auto max-w-xl space-y-10">
      <h1 className="text-titulo text-verde">Mi cuenta</h1>

      {/* Destino del enlace de verificacion: el backend redirige aqui con
          ?verificado=1 tras marcar la direccion como verificada. */}
      {parametros.get('verificado') === '1' && (
        <Aviso tono="exito">Tu correo ha quedado verificado.</Aviso>
      )}

      {usuario && !verificado && <AvisoDeVerificacion />}

      <DatosDeLaCuenta />
      <ContrasenaDeLaCuenta />

      {/* D21 y D22 — aparcar o suprimir. Al final de la pagina, que es donde
          se busca, y con la diferencia entre las dos por escrito. */}
      <CerrarLaCuenta />
    </div>
  )
}

/** N19 — verificacion de email, que en v1 no se usaba pese a existir la columna. */
function AvisoDeVerificacion() {
  const [enviado, setEnviado] = useState(false)
  const reenviar = useMutation({
    mutationFn: reenviarVerificacion,
    onSuccess: () => setEnviado(true),
  })

  return (
    <div className="space-y-3 rounded-fefu border-l-4 border-piedra bg-rosa-suave px-4 py-3">
      <p role="status" className="text-base text-piedra">
        Tu correo todavía no está verificado.
      </p>

      {enviado ? (
        <p className="text-base text-verde">
          Te hemos enviado un correo nuevo. Revisa la bandeja de entrada.
        </p>
      ) : (
        <Boton
          type="button"
          variante="secundario"
          onClick={() => reenviar.mutate()}
          disabled={reenviar.isPending}
        >
          {reenviar.isPending ? 'Enviando...' : 'Reenviar el correo'}
        </Boton>
      )}
    </div>
  )
}

function DatosDeLaCuenta() {
  const { usuario } = useSesion()
  const queryClient = useQueryClient()

  const [guardado, setGuardado] = useState(false)
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null)

  const guardar = useMutation({
    mutationFn: actualizarPerfil,
    onSuccess: (actualizado) => {
      queryClient.setQueryData(CLAVE_SESION, actualizado)
      setGuardado(true)
    },
  })

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof esquemaDatos>>({
    resolver: zodResolver(esquemaDatos),
    values: { name: usuario?.name ?? '', email: usuario?.email ?? '' },
  })

  const enviar = handleSubmit(async (datos) => {
    setErrorGeneral(null)
    setGuardado(false)

    try {
      await guardar.mutateAsync(datos)
    } catch (error) {
      setErrorGeneral(aplicarErroresDeApi(error, setError, ['name', 'email']))
    }
  })

  return (
    <section aria-labelledby="datos" className="space-y-4">
      <h2 id="datos" className="text-seccion text-verde">
        Datos
      </h2>

      {guardado && <Aviso tono="exito">Datos guardados.</Aviso>}
      {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

      <form onSubmit={enviar} noValidate className="space-y-4">
        <Campo etiqueta="Nombre" error={errors.name?.message} {...register('name')} />

        <Campo
          etiqueta="Correo"
          type="email"
          ayuda="Si lo cambias tendrás que verificar la dirección nueva."
          error={errors.email?.message}
          {...register('email')}
        />

        <Boton type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Guardando...' : 'Guardar'}
        </Boton>
      </form>
    </section>
  )
}

function ContrasenaDeLaCuenta() {
  const [cambiada, setCambiada] = useState(false)
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null)

  const cambiar = useMutation({ mutationFn: cambiarContrasena })

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof esquemaContrasena>>({ resolver: zodResolver(esquemaContrasena) })

  const enviar = handleSubmit(async (datos) => {
    setErrorGeneral(null)
    setCambiada(false)

    try {
      await cambiar.mutateAsync(datos)
      setCambiada(true)
      reset()
    } catch (error) {
      setErrorGeneral(
        aplicarErroresDeApi(error, setError, ['current_password', 'password']),
      )
    }
  })

  return (
    <section aria-labelledby="contrasena" className="space-y-4">
      <h2 id="contrasena" className="text-seccion text-verde">
        Contrasena
      </h2>

      {cambiada && <Aviso tono="exito">Contraseña cambiada.</Aviso>}
      {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

      <form onSubmit={enviar} noValidate className="space-y-4">
        {/* El backend la comprueba contra la contrasena del usuario
            autenticado, no contra un valor del cliente. */}
        <Campo
          etiqueta="Contraseña actual"
          type="password"
          autoComplete="current-password"
          error={errors.current_password?.message}
          {...register('current_password')}
        />

        <Campo
          etiqueta="Contraseña nueva"
          type="password"
          autoComplete="new-password"
          ayuda="Al menos ocho caracteres, y distinta de la actual."
          error={errors.password?.message}
          {...register('password')}
        />

        <Campo
          etiqueta="Repite la contraseña nueva"
          type="password"
          autoComplete="new-password"
          error={errors.password_confirmation?.message}
          {...register('password_confirmation')}
        />

        <Boton type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Cambiando...' : 'Cambiar la contraseña'}
        </Boton>
      </form>
    </section>
  )
}
