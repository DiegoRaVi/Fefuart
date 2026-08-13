import type { ProductVariant } from '@/shared/api/types'
import { euros } from '@/shared/lib/dinero'

/**
 * «Desde 30,00 €» cuando hay varias variantes con precios distintos, y el
 * precio a secas cuando solo hay uno. Poner «desde» con un unico precio
 * sugiere que hay algo mas caro escondido.
 */
export function PrecioDesde({ variantes }: { variantes: ProductVariant[] }) {
  if (variantes.length === 0) {
    return null
  }

  const precios = variantes.map((v) => Number(v.price))
  const minimo = Math.min(...precios)
  const hayVarios = new Set(precios).size > 1

  return (
    <p className="text-apartado text-verde">
      {hayVarios && <span className="text-base text-piedra">Desde </span>}
      {euros(minimo.toFixed(2))}
    </p>
  )
}
