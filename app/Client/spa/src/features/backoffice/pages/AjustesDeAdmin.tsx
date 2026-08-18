import { useState } from 'react'

import { ApiError } from '@/shared/api/errors'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { useAjustes, useGuardarAjustes } from '../api'

/**
 * N15 — los ajustes del negocio, sin tocar codigo.
 *
 * Los limites de cada uno los declara el servidor y esta pantalla los pinta
 * en los `min`/`max` del campo. No los repite: si manana cambian, cambian en
 * un sitio. La validacion del navegador es comodidad; la que manda es la de
 * SettingsService.
 */
export function AjustesDeAdmin() {
  const { data: ajustes, isPending, isError, error } = useAjustes()
  const guardar = useGuardarAjustes()
  const [borrador, setBorrador] = useState<Record<string, string>>({})

  if (isPending) {
    return <Cargando texto="Cargando los ajustes..." />
  }

  if (isError) {
    return <Aviso tono="error">{error.message}</Aviso>
  }

  const valorDe = (clave: string) => borrador[clave] ?? String(ajustes[clave].valor)

  const cambiados = Object.fromEntries(
    Object.entries(borrador)
      .filter(([clave, valor]) => valor !== '' && Number(valor) !== ajustes[clave].valor)
      .map(([clave, valor]) => [clave, Number(valor)]),
  )

  const hayCambios = Object.keys(cambiados).length > 0

  return (
    <div className="max-w-xl space-y-6">
      <div className="space-y-1">
        <h1 className="text-titulo text-verde">Ajustes</h1>
        <p className="text-base text-piedra">
          Afectan a los presupuestos que emitas a partir de ahora. Los ya enviados
          conservan el importe y la señal que se le dijeron al cliente.
        </p>
      </div>

      <form
        className="space-y-5"
        onSubmit={(evento) => {
          evento.preventDefault()

          if (hayCambios) {
            guardar.mutate(cambiados, { onSuccess: () => setBorrador({}) })
          }
        }}
      >
        {Object.entries(ajustes).map(([clave, ajuste]) => (
          <div key={clave} className="space-y-1">
            <label className="block text-base text-piedra" htmlFor={clave}>
              {ajuste.etiqueta}
            </label>

            <div className="flex items-center gap-3">
              <input
                id={clave}
                name={clave}
                type="number"
                inputMode="numeric"
                min={ajuste.min}
                max={ajuste.max}
                value={valorDe(clave)}
                onChange={(evento) =>
                  setBorrador((actual) => ({ ...actual, [clave]: evento.target.value }))
                }
                aria-describedby={`${clave}-limites`}
                className="w-28 rounded-fefu border border-piedra/40 px-3 py-2 text-piedra focus:border-verde focus:outline-none"
              />

              <span id={`${clave}-limites`} className="text-sm text-piedra">
                entre {ajuste.min} y {ajuste.max}
              </span>
            </div>
          </div>
        ))}

        {guardar.isError && (
          <Aviso tono="error">
            {guardar.error instanceof ApiError
              ? Object.values(guardar.error.errors ?? {})
                  .flat()
                  .join(' ') || guardar.error.message
              : guardar.error.message}
          </Aviso>
        )}

        {guardar.isSuccess && !hayCambios && <Aviso tono="exito">Ajustes guardados.</Aviso>}

        <Boton type="submit" disabled={!hayCambios || guardar.isPending}>
          {guardar.isPending ? 'Guardando...' : 'Guardar'}
        </Boton>
      </form>
    </div>
  )
}
