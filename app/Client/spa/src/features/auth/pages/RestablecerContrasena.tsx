import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { z } from 'zod'

import { aplicarErroresDeApi } from '@/shared/api/formulario'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'

import { restablecerContrasena } from '../api'

const esquema = z
  .object({
    password: z.string().min(8, 'Al menos ocho caracteres.'),
    password_confirmation: z.string().min(1, 'Repite la contraseña.'),
  })
  .refine((datos) => datos.password === datos.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Las dos contrasenas no coinciden.',
  })

type Datos = z.infer<typeof esquema>

/**
 * N19 — el destino del enlace que llega por correo. La URL la construye el
 * backend en `AppServiceProvider::configurePasswordResetUrl`:
 *
 *   {FRONTEND_URL}/restablecer-contrasena?token=...&email=...
 *
 * Hasta que existio esta pantalla, ese enlace apuntaba a una ruta
 * inexistente y el flujo estaba roto de punta a punta.
 */
export function RestablecerContrasena() {
  const [parametros] = useSearchParams()
  const navigate = useNavigate()

  const token = parametros.get('token') ?? ''
  const email = parametros.get('email') ?? ''

  const [errorGeneral, setErrorGeneral] = useState<string | null>(null)
  const restablecer = useMutation({ mutationFn: restablecerContrasena })

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({ resolver: zodResolver(esquema) })

  const enviar = handleSubmit(async (datos) => {
    setErrorGeneral(null)

    try {
      await restablecer.mutateAsync({ token, email, ...datos })
      // A login y no a la portada: restablecer no abre sesion, y llevar a la
      // portada dejaria al usuario preguntandose si ha funcionado.
      navigate('/login', { replace: true, state: { restablecida: true } })
    } catch (error) {
      setErrorGeneral(aplicarErroresDeApi(error, setError, ['password']))
    }
  })

  if (!token || !email) {
    return (
      <div className="mx-auto max-w-md space-y-6">
        <h1 className="text-titulo text-verde">Enlace incompleto</h1>

        <Aviso tono="error">
          Este enlace no trae los datos necesarios. Copialo entero desde el
          correo, o pide uno nuevo.
        </Aviso>

        <p className="text-base">
          <Link className="text-verde underline underline-offset-4" to="/recuperar-contrasena">
            Pedir un enlace nuevo
          </Link>
        </p>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="text-titulo text-verde">Elegir una contraseña nueva</h1>

      <p className="text-base">
        Vas a cambiar la contrasena de <strong>{email}</strong>.
      </p>

      {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

      <form onSubmit={enviar} noValidate className="space-y-4">
        <Campo
          etiqueta="Contraseña nueva"
          type="password"
          autoComplete="new-password"
          ayuda="Al menos ocho caracteres."
          error={errors.password?.message}
          {...register('password')}
        />

        <Campo
          etiqueta="Repite la contraseña"
          type="password"
          autoComplete="new-password"
          error={errors.password_confirmation?.message}
          {...register('password_confirmation')}
        />

        <Boton type="submit" disabled={isSubmitting} className="w-full">
          {isSubmitting ? 'Guardando...' : 'Guardar la contraseña'}
        </Boton>
      </form>
    </div>
  )
}
