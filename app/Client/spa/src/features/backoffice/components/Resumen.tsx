import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'

import { useMetricas } from '../api'

/**
 * Cuatro numeros al entrar en el backoffice: dos de dinero y dos de trabajo.
 *
 * **No es el panel definitivo**, y conviene que se note poco pero se sepa:
 * se construyo sin preguntarle a Felicitas que mira cuando abre esto un
 * lunes, que es justo lo que deberia decidirlo. Mas de cuatro numeros en una
 * pantalla dejan de leerse, asi que ampliarlo significa quitar alguno.
 */
export function Resumen() {
  const { data, isPending, isError } = useMetricas()

  if (isPending || isError) {
    // Sin sitio reservado ni error a la vista: es un resumen, no la pantalla.
    // Si falla, debajo sigue estando el trabajo de verdad.
    return isError ? <Aviso tono="informacion">No se pudo cargar el resumen.</Aviso> : null
  }

  return (
    <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <Numero etiqueta="Pedidos este mes" valor={String(data.pedidos_del_mes)} />
      <Numero etiqueta="Ingresos este mes" valor={euros(data.ingresos_del_mes)} />
      <Numero
        etiqueta="Por presupuestar"
        valor={String(data.eventos_por_presupuestar)}
        destacado={data.eventos_por_presupuestar > 0}
      />
      <Numero
        etiqueta="Entregas pendientes"
        valor={String(data.entregas_pendientes)}
        destacado={data.entregas_pendientes > 0}
      />
    </dl>
  )
}

/**
 * Los dos numeros de trabajo se destacan solo cuando hay algo que hacer. Un
 * cero en rojo permanente enseña a ignorar el color.
 */
function Numero({
  etiqueta,
  valor,
  destacado = false,
}: {
  etiqueta: string
  valor: string
  destacado?: boolean
}) {
  return (
    <div
      className={`rounded-fefu p-4 ${destacado ? 'bg-verde text-white' : 'bg-rosa-suave text-verde'}`}
    >
      <dt className={`text-sm ${destacado ? 'text-white' : 'text-piedra'}`}>{etiqueta}</dt>
      <dd className="text-2xl font-bold">{valor}</dd>
    </div>
  )
}
