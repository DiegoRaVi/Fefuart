import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useLocation } from 'react-router'
import { z } from 'zod'

import { aplicarErroresDeApi } from '@/shared/api/formulario'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'

import { useRegistrarse } from '../hooks'

/**
 * SEC-001 — fijate en lo que no hay: ningun campo de rol.
 *
 * En v1 el registro leia `role` del cuerpo de la peticion, asi que enviar
 * `"role": "admin"` contra un endpoint publico bastaba para tomar el control
 * del backoffice. El backend ya lo ignora; aqui tampoco se ofrece.
 *
 * La contrasena pide ocho caracteres porque es lo que exige el servidor; v1
 * aceptaba cinco.
 */
const esquema = z
  .object({
    name: z.string().min(1, 'Escribe tu nombre.').max(255),
    email: z.string().min(1, 'Escribe tu correo.').email('Eso no parece un correo.'),
    password: z.string().min(8, 'Al menos ocho caracteres.'),
    password_confirmation: z.string().min(1, 'Repite la contraseña.'),
  })
  .refine((datos) => datos.password === datos.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Las dos contrasenas no coinciden.',
  })

type Datos = z.infer<typeof esquema>

const CAMPOS = ['name', 'email', 'password', 'password_confirmation'] as const

export function Registro() {
  /*
   * A donde se vuelve despues de crear la cuenta. Lo mismo que hace Login:
   * quien llega aqui desde «pedir presupuesto» tiene que volver alli, no a
   * su perfil. Sin sitio del que venir, al perfil, que es donde esta el
   * aviso de verificar el correo (N19).
   */
  const ubicacion = useLocation()
  const destino = (ubicacion.state as { desde?: string } | null)?.desde ?? '/perfil'

  const registrar = useRegistrarse(destino)
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({ resolver: zodResolver(esquema) })

  const enviar = handleSubmit(async (datos) => {
    setErrorGeneral(null)

    try {
      await registrar.mutateAsync(datos)
    } catch (error) {
      setErrorGeneral(aplicarErroresDeApi(error, setError, CAMPOS))
    }
  })

  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="text-titulo text-verde">Crear cuenta</h1>

      <p className="text-base">
        N18: hace falta cuenta para encargar. Te enviaremos un correo para
        confirmar tu dirección.
      </p>

      {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

      <form onSubmit={enviar} noValidate className="space-y-4">
        <Campo
          etiqueta="Nombre"
          autoComplete="name"
          error={errors.name?.message}
          {...register('name')}
        />

        <Campo
          etiqueta="Correo"
          type="email"
          autoComplete="email"
          error={errors.email?.message}
          {...register('email')}
        />

        <Campo
          etiqueta="Contraseña"
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
          {isSubmitting ? 'Creando...' : 'Crear cuenta'}
        </Boton>
      </form>

      <p className="text-base">
        Ya tienes cuenta?{' '}
        <Link
          className="text-verde underline underline-offset-4"
          to="/login"
          state={ubicacion.state}
        >
          Entra
        </Link>
      </p>
    </div>
  )
}
