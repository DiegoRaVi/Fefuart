import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'

import { useAnadirLinea } from '@/features/cart/api'
import { useSesion } from '@/features/auth/sesion'
import type { MediaAsset } from '@/features/media/api'
import { SubidaDeFoto } from '@/features/media/SubidaDeFoto'
import { ApiError } from '@/shared/api/errors'
import type { Product, ProductVariant } from '@/shared/api/types'
import { euros, precioConCopias } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { useProducto } from '../api'

export function FormularioDeEncargo() {
  const { slug = '' } = useParams()
  const { data: producto, isPending, isError, error } = useProducto(slug)

  if (isPending) {
    return <Cargando texto="Cargando el encargo..." />
  }

  if (isError) {
    return <Aviso tono="error">{error.message}</Aviso>
  }

  // `key` fuerza a rearrancar el estado si se cambia de producto sin
  // desmontar la pagina.
  return <Encargo key={producto.id} producto={producto} />
}

function Encargo({ producto }: { producto: Product }) {
  const navigate = useNavigate()
  const { verificado } = useSesion()
  const anadir = useAnadirLinea()

  const [variante, setVariante] = useState<ProductVariant>(producto.variants[0])
  const [envioId, setEnvioId] = useState<number>(
    producto.variants[0]?.shipping_methods[0]?.id ?? 0,
  )
  const [cantidad, setCantidad] = useState(1)
  const [notas, setNotas] = useState('')
  const [foto, setFoto] = useState<MediaAsset | null>(null)
  const [errores, setErrores] = useState<Record<string, string>>({})

  const envio = variante?.shipping_methods.find((m) => m.id === envioId)
  // N3/D26 — una entrega digital es un unico archivo, asi que no hay copias
  // que pedir. El servidor lo rechaza igualmente.
  const admiteCopias = envio?.code === 'physical'

  function cambiarVariante(nueva: ProductVariant) {
    setVariante(nueva)

    // N7 — la entrega elegida puede no existir en la variante nueva.
    const sigueValiendo = nueva.shipping_methods.some((m) => m.id === envioId)

    if (!sigueValiendo) {
      setEnvioId(nueva.shipping_methods[0]?.id ?? 0)
      setCantidad(1)
    }
  }

  function cambiarEnvio(id: number) {
    setEnvioId(id)

    if (variante.shipping_methods.find((m) => m.id === id)?.code !== 'physical') {
      setCantidad(1)
    }
  }

  async function enviar(evento: React.FormEvent) {
    evento.preventDefault()
    setErrores({})

    if (producto.requires_reference_image && !foto) {
      setErrores({ reference_media_id: 'Este encargo se dibuja a partir de tu foto.' })

      return
    }

    try {
      await anadir.mutateAsync({
        product_id: producto.id,
        variant_id: variante.id,
        shipping_method_id: envioId,
        quantity: cantidad,
        customer_notes: notas.trim() || null,
        reference_media_id: foto?.id ?? null,
      })

      navigate('/carrito')
    } catch (e) {
      // El servidor vuelve a comprobar N7, N9, D26 y el tope de cantidad: lo
      // de aqui es comodidad, no la regla.
      setErrores(e instanceof ApiError ? e.firstErrors() : { general: 'Algo ha fallado.' })

      if (e instanceof ApiError && !e.isValidation) {
        setErrores({ general: e.message })
      }
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <Link className="text-base text-piedra underline underline-offset-4" to={`/encargos/${producto.slug}`}>
        Volver a {producto.name}
      </Link>

      <h1 className="text-titulo text-verde">Encargar {producto.name}</h1>

      {/* N19 — quien no ha verificado el correo puede encargar, pero conviene
          que sepa que tiene el aviso pendiente: los avisos del pedido van ahi. */}
      {!verificado && (
        <Aviso tono="informacion">
          Todavía no has verificado tu correo. Puedes encargar igualmente, pero
          confirmalo desde <Link className="underline" to="/perfil">tu cuenta</Link> para
          recibir los avisos del pedido.
        </Aviso>
      )}

      {errores.general && <Aviso tono="error">{errores.general}</Aviso>}

      <form onSubmit={enviar} noValidate className="space-y-6">
        <fieldset className="space-y-2">
          <legend className="font-bold text-verde">Opcion</legend>

          {producto.variants.map((v) => (
            <label
              key={v.id}
              className="flex cursor-pointer items-baseline gap-3 rounded-fefu bg-rosa-suave p-3"
            >
              <input
                type="radio"
                name="variante"
                checked={v.id === variante.id}
                onChange={() => cambiarVariante(v)}
              />
              <span className="flex-1">
                <span className="block text-apartado text-verde">{v.name}</span>
                <span className="text-sm text-piedra">
                  {precioConCopias(v.price, v.additional_copy_price)}
                </span>
              </span>
            </label>
          ))}

          {errores.variant_id && <Aviso tono="error">{errores.variant_id}</Aviso>}
        </fieldset>

        <fieldset className="space-y-2">
          <legend className="font-bold text-verde">Como lo recibes</legend>

          {/* N7 — solo salen las entregas que admite esta variante. */}
          {variante.shipping_methods.map((metodo) => (
            <label key={metodo.id} className="flex cursor-pointer items-center gap-3">
              <input
                type="radio"
                name="envio"
                checked={metodo.id === envioId}
                onChange={() => cambiarEnvio(metodo.id)}
              />
              <span>
                {metodo.name}
                {Number(metodo.price) > 0 && (
                  <span className="text-piedra"> · {euros(metodo.price)} por pedido</span>
                )}
              </span>
            </label>
          ))}

          {errores.shipping_method_id && <Aviso tono="error">{errores.shipping_method_id}</Aviso>}
        </fieldset>

        <div className="space-y-1">
          <label htmlFor="cantidad" className="block font-bold text-verde">
            Copias
          </label>

          <p className="text-sm text-piedra">
            {admiteCopias
              ? `Copias de esta misma lámina, hasta ${producto.max_quantity}.`
              : 'Una entrega digital es un único archivo.'}
          </p>

          <input
            id="cantidad"
            type="number"
            min={1}
            max={producto.max_quantity}
            value={cantidad}
            disabled={!admiteCopias}
            onChange={(e) => setCantidad(Number(e.target.value))}
            aria-invalid={errores.quantity ? true : undefined}
            className="w-24 rounded-fefu border border-piedra/40 px-3 py-2 disabled:bg-rosa-suave disabled:text-piedra"
          />

          {errores.quantity && <Aviso tono="error">{errores.quantity}</Aviso>}
        </div>

        {producto.requires_reference_image && (
          <SubidaDeFoto foto={foto} onCambio={setFoto} error={errores.reference_media_id} />
        )}

        {producto.requires_notes && (
          <div className="space-y-1">
            <label htmlFor="notas" className="block font-bold text-verde">
              Como lo quieres
            </label>

            <textarea
              id="notas"
              rows={4}
              maxLength={1000}
              value={notas}
              onChange={(e) => setNotas(e.target.value)}
              placeholder="En blanco y negro, solo las caras, sin el fondo..."
              className="w-full rounded-fefu border border-piedra/40 px-3 py-2 text-base"
            />

            {errores.customer_notes && <Aviso tono="error">{errores.customer_notes}</Aviso>}
          </div>
        )}

        {/*
          Aqui no se suma nada. El precio de la linea, el envio y el total los
          calcula PricingService y se ven en el carrito, que es la pantalla
          siguiente. Repetir la formula N4 en JavaScript es exactamente lo que
          en v1 hacia que el ramo y las letras dieran precios distintos para
          el mismo caso.
        */}
        <Boton type="submit" disabled={anadir.isPending} className="w-full">
          {anadir.isPending ? 'Anadiendo...' : 'Añadir al carrito'}
        </Boton>
      </form>
    </div>
  )
}
