import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'

import { aplicarErroresDeApi } from '@/shared/api/formulario'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'

import { pedirEnlaceDeRecuperacion } from '../api'

const esquema = z.object({
  email: z.string().min(1, 'Escribe tu correo.').email('Eso no parece un correo.'),
})

type Datos = z.infer<typeof esquema>

/**
 * N19 — recuperacion de contrasena, inexistente en v1 pese a que la tabla
 * `password_reset_tokens` llevaba ahi desde la primera migracion.
 */
export function RecuperarContrasena() {
  const [enviado, setEnviado] = useState<string | null>(null)
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null)

  const pedir = useMutation({ mutationFn: pedirEnlaceDeRecuperacion })

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({ resolver: zodResolver(esquema) })

  const enviar = handleSubmit(async ({ email }) => {
    setErrorGeneral(null)

    try {
      setEnviado(await pedir.mutateAsync(email))
    } catch (error) {
      setErrorGeneral(aplicarErroresDeApi(error, setError, ['email']))
    }
  })

  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="text-titulo text-verde">Recuperar contrasena</h1>

      {/*
        El backend responde lo mismo exista o no la cuenta, para que el
        formulario no sirva de oraculo de direcciones registradas. El texto
        de aqui tiene que acompanar esa decision y no prometer que el correo
        ha salido.
      */}
      {enviado ? (
        <Aviso tono="exito">{enviado}</Aviso>
      ) : (
        <>
          <p className="text-base">
            Escribe tu correo y, si hay una cuenta con esa direccion, te
            enviaremos un enlace para elegir una contrasena nueva.
          </p>

          {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

          <form onSubmit={enviar} noValidate className="space-y-4">
            <Campo
              etiqueta="Correo"
              type="email"
              autoComplete="email"
              error={errors.email?.message}
              {...register('email')}
            />

            <Boton type="submit" disabled={isSubmitting} className="w-full">
              {isSubmitting ? 'Enviando...' : 'Enviar el enlace'}
            </Boton>
          </form>
        </>
      )}

      <p className="text-base">
        <Link className="text-verde underline underline-offset-4" to="/login">
          Volver a entrar
        </Link>
      </p>
    </div>
  )
}
