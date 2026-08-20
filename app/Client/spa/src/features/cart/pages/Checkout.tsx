import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router'
import { z } from 'zod'

import { ApiError } from '@/shared/api/errors'
import { aplicarErroresDeApi } from '@/shared/api/formulario'
import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'
import { Cargando } from '@/shared/ui/Cargando'

import { CLAVE_CARRITO, useCarrito, useHacerPedido } from '../api'

const esquema = z.object({
  shipping_name: z.string().max(255).optional(),
  shipping_phone: z.string().max(30).optional(),
  shipping_line1: z.string().max(255).optional(),
  shipping_line2: z.string().max(255).optional(),
  shipping_city: z.string().max(100).optional(),
  shipping_province: z.string().max(100).optional(),
  shipping_postal_code: z.string().max(20).optional(),
})

type Datos = z.infer<typeof esquema>

const CAMPOS = [
  'shipping_name',
  'shipping_phone',
  'shipping_line1',
  'shipping_line2',
  'shipping_city',
  'shipping_province',
  'shipping_postal_code',
] as const

export function Checkout() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: carrito, isPending } = useCarrito()
  const pedir = useHacerPedido()

  const [errorGeneral, setErrorGeneral] = useState<string | null>(null)
  const [precioCambiado, setPrecioCambiado] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({ resolver: zodResolver(esquema) })

  if (isPending) {
    return <Cargando texto="Cargando el carrito..." />
  }

  if (!carrito || carrito.items.length === 0) {
    return (
      <div className="mx-auto max-w-2xl space-y-4">
        <h1 className="text-titulo text-verde">Confirmar el encargo</h1>
        <Aviso tono="informacion">Tu carrito esta vacío.</Aviso>
        <Link className="text-verde underline underline-offset-4" to="/encargos">
          Ver los encargos
        </Link>
      </div>
    )
  }

  // N6 — un pedido con al menos una linea fisica hay que enviarlo a alguna
  // parte. Si todo es digital, pedir la direccion seria pedir datos
  // personales sin motivo. El servidor lo vuelve a comprobar.
  const necesitaEnvio = carrito.items.some((l) => l.delivery_type === 'physical')

  const enviar = handleSubmit(async (datos) => {
    setErrorGeneral(null)
    setPrecioCambiado(null)

    try {
      const pedido = await pedir.mutateAsync(datos)
      navigate(`/pedidos/${pedido.id}`, { replace: true })
    } catch (error) {
      /**
       * 409 — los precios han cambiado mientras el carrito estaba abierto
       * (D5: Felicitas los edita desde el backoffice). El servidor ya ha
       * guardado los importes nuevos, asi que basta con refrescar el carrito
       * y avisar: el resumen de esta misma pantalla enseña la diferencia y
       * el cliente confirma otra vez.
       */
      if (error instanceof ApiError && error.status === 409) {
        await queryClient.invalidateQueries({ queryKey: CLAVE_CARRITO })
        setPrecioCambiado(error.message)

        return
      }

      setErrorGeneral(aplicarErroresDeApi(error, setError, CAMPOS))
    }
  })

  return (
    <div className="mx-auto max-w-2xl space-y-8">
      <h1 className="text-titulo text-verde">Confirmar el encargo</h1>

      {precioCambiado && <Aviso tono="informacion">{precioCambiado} Revisalo y confirma otra vez.</Aviso>}
      {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

      <section aria-labelledby="resumen" className="space-y-2 rounded-fefu bg-rosa-suave p-4">
        <h2 id="resumen" className="text-seccion text-verde">
          Resumen
        </h2>

        <ul className="space-y-1 text-base">
          {carrito.items.map((linea) => (
            <li key={linea.id} className="flex justify-between gap-4">
              <span>
                {linea.product_name} · {linea.variant_name}
                {linea.quantity > 1 && ` × ${linea.quantity}`}
              </span>
              <span>{euros(linea.line_total)}</span>
            </li>
          ))}
        </ul>

        <div className="flex justify-between border-t border-piedra/20 pt-2 text-base">
          <span>Envío</span>
          <span>{euros(carrito.shipping_total)}</span>
        </div>

        <div className="flex justify-between text-seccion text-verde">
          <span>Total</span>
          <span>{euros(carrito.total)}</span>
        </div>

        <p className="text-sm text-piedra">IVA incluido.</p>
      </section>

      <form onSubmit={enviar} noValidate className="space-y-4">
        {necesitaEnvio ? (
          <>
            <h2 className="text-seccion text-verde">A donde lo enviamos</h2>

            <Campo
              etiqueta="Nombre y apellidos"
              autoComplete="name"
              error={errors.shipping_name?.message}
              {...register('shipping_name')}
            />

            <Campo
              etiqueta="Teléfono"
              type="tel"
              autoComplete="tel"
              ayuda="Por si el repartidor necesita localizarte."
              error={errors.shipping_phone?.message}
              {...register('shipping_phone')}
            />

            <Campo
              etiqueta="Dirección"
              autoComplete="address-line1"
              error={errors.shipping_line1?.message}
              {...register('shipping_line1')}
            />

            <Campo
              etiqueta="Piso, puerta, escalera"
              autoComplete="address-line2"
              error={errors.shipping_line2?.message}
              {...register('shipping_line2')}
            />

            <div className="grid gap-4 sm:grid-cols-3">
              <Campo
                etiqueta="Código postal"
                autoComplete="postal-code"
                error={errors.shipping_postal_code?.message}
                {...register('shipping_postal_code')}
              />
              <Campo
                etiqueta="Ciudad"
                autoComplete="address-level2"
                error={errors.shipping_city?.message}
                {...register('shipping_city')}
              />
              <Campo
                etiqueta="Provincia"
                autoComplete="address-level1"
                error={errors.shipping_province?.message}
                {...register('shipping_province')}
              />
            </div>
          </>
        ) : (
          <Aviso tono="informacion">
            Tu encargo es digital, así que no hace falta dirección. Lo podrás
            descargar desde el detalle del pedido.
          </Aviso>
        )}

        {/*
          Confirmar no cobra: deja el pedido en `pending_payment` con los
          importes congelados. El pago es el paso siguiente y ocurre en el
          dominio de Stripe (D29), desde el detalle del pedido.
        */}
        <Boton type="submit" disabled={isSubmitting} className="w-full">
          {isSubmitting ? 'Confirmando...' : 'Confirmar el encargo'}
        </Boton>

        <p className="text-sm text-piedra">
          Al confirmar veras el resumen del pedido y desde ahi podras pagarlo
          con tarjeta. No se te cobra nada todavia.
        </p>
      </form>
    </div>
  )
}
