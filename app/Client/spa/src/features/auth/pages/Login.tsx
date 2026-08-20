import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useLocation } from 'react-router'
import { z } from 'zod'

import { aplicarErroresDeApi } from '@/shared/api/formulario'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'

import { useIniciarSesion } from '../hooks'

const esquema = z.object({
  email: z.string().min(1, 'Escribe tu correo.').email('Eso no parece un correo.'),
  password: z.string().min(1, 'Escribe tu contraseña.'),
})

type Datos = z.infer<typeof esquema>

const CAMPOS = ['email', 'password'] as const

export function Login() {
  const ubicacion = useLocation()
  const destino = (ubicacion.state as { desde?: string } | null)?.desde ?? '/'

  const entrar = useIniciarSesion(destino)
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
      await entrar.mutateAsync(datos)
    } catch (error) {
      setErrorGeneral(aplicarErroresDeApi(error, setError, CAMPOS))
    }
  })

  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="text-titulo text-verde">Entrar</h1>

      {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

      <form onSubmit={enviar} noValidate className="space-y-4">
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
          autoComplete="current-password"
          error={errors.password?.message}
          {...register('password')}
        />

        <Boton type="submit" disabled={isSubmitting} className="w-full">
          {isSubmitting ? 'Entrando...' : 'Entrar'}
        </Boton>
      </form>

      <div className="space-y-2 text-base">
        <p>
          <Link className="text-verde underline underline-offset-4" to="/recuperar-contrasena">
            He olvidado mi contrasena
          </Link>
        </p>
        <p>
          No tienes cuenta?{' '}
          <Link className="text-verde underline underline-offset-4" to="/registro">
            Crea una
          </Link>
        </p>
      </div>
    </div>
  )
}
