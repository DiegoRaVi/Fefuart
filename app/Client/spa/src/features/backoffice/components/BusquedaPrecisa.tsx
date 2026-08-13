import { useEffect, useRef, useState } from 'react'

import { Boton } from '@/shared/ui/Boton'
import { Modal } from '@/shared/ui/Modal'

export interface CampoDeBusqueda {
  valor: string
  etiqueta: string
  ayuda: string
  tipo?: 'text' | 'number' | 'tel' | 'email'
}

export interface Busqueda {
  campo: string
  termino: string
}

interface Props {
  abierto: boolean
  onCerrar: () => void
  campos: CampoDeBusqueda[]
  busqueda: Busqueda | null
  onBuscar: (busqueda: Busqueda | null) => void
}

/**
 * Busqueda precisa: un campo por dato, y **solo uno activo a la vez**.
 *
 * La caja rapida de la pantalla mira en todo, que es comodo pero impreciso:
 * escribir «600» mezcla telefonos, numeros de pedido y cualquier nombre con
 * esas cifras. Aqui se elige el campo y lo que sale es exactamente lo que se
 * pidio.
 *
 * Que solo se pueda rellenar uno no es una limitacion tecnica: dos campos a
 * la vez obligarian a decidir si se combinan con Y o con O, y ninguna de las
 * dos respuestas es evidente para quien busca un pedido con prisa. El campo
 * elegido si se cruza con el estado y las fechas, que son otra cosa.
 */
export function BusquedaPrecisa({
  abierto,
  onCerrar,
  campos,
  busqueda,
  onBuscar,
}: Props) {
  const [campo, setCampo] = useState(busqueda?.campo ?? campos[0].valor)
  const [termino, setTermino] = useState(busqueda?.termino ?? '')
  const activo = useRef<HTMLInputElement>(null)

  // Al abrir, el foco va al campo elegido: quien abre el modal viene a
  // escribir, no a mirar.
  useEffect(() => {
    if (abierto) {
      activo.current?.focus()
    }
  }, [abierto, campo])

  function aplicar(evento: React.FormEvent) {
    evento.preventDefault()

    onBuscar(termino.trim() ? { campo, termino: termino.trim() } : null)
    onCerrar()
  }

  // El titulo dice «por un dato» y no solo «Buscar» para distinguirlo de la
  // caja rapida, que tambien se llama asi y hace otra cosa.
  return (
    <Modal titulo="Buscar por un dato" abierto={abierto} onCerrar={onCerrar}>
      <form onSubmit={aplicar} className="space-y-4">
        <p className="text-base">Elige por que dato quieres buscar.</p>

        <fieldset className="space-y-3">
          <legend className="sr-only">Campo de busqueda</legend>

          {campos.map((opcion) => {
            const elegido = opcion.valor === campo

            return (
              <div key={opcion.valor} className="flex items-start gap-3">
                {/*
                  El radio y el campo son dos controles distintos y necesitan
                  nombres distintos: si los dos se llamaran «Nombre», un
                  lector de pantalla anunciaria dos cosas iguales seguidas y
                  no habria forma de saber cual es cual. La etiqueta visible
                  nombra el campo, que es donde se escribe; el radio dice lo
                  que hace.
                */}
                <input
                  type="radio"
                  name="campo"
                  checked={elegido}
                  aria-label={`Buscar por ${opcion.etiqueta.toLowerCase()}`}
                  onChange={() => {
                    setCampo(opcion.valor)
                    // El termino escrito para otro campo no vale para este:
                    // un telefono no es un nombre.
                    setTermino('')
                  }}
                  className="mt-3"
                />

                <div className="flex-1 space-y-1">
                  <label
                    htmlFor={`campo-${opcion.valor}`}
                    className={`block font-bold ${elegido ? 'text-verde' : 'text-piedra'}`}
                  >
                    {opcion.etiqueta}
                  </label>

                  <input
                    id={`campo-${opcion.valor}`}
                    ref={elegido ? activo : undefined}
                    type={opcion.tipo ?? 'text'}
                    value={elegido ? termino : ''}
                    /*
                     * `readOnly` y no `disabled`. Solo se puede escribir en
                     * el elegido —que es lo que hace que la busqueda sea por
                     * un dato y no por varios a medias— pero los demas siguen
                     * siendo alcanzables: un input deshabilitado no recibe
                     * clics ni foco, asi que pinchar en el no haria nada, y
                     * ademas hay lectores de pantalla que se lo saltan, de
                     * modo que ni siquiera se sabria que la opcion existe.
                     */
                    readOnly={!elegido}
                    placeholder={opcion.ayuda}
                    onChange={(e) => setTermino(e.target.value)}
                    // Escribir en un campo apagado no hace nada, asi que
                    // pinchar en el lo elige: si no, parece que esta roto.
                    onFocus={() => {
                      if (!elegido) {
                        setCampo(opcion.valor)
                        setTermino('')
                      }
                    }}
                    className="w-full rounded-fefu border border-piedra/40 px-3 py-2 text-base read-only:cursor-pointer read-only:bg-rosa-suave read-only:text-piedra/50"
                  />
                </div>
              </div>
            )
          })}
        </fieldset>

        <div className="flex flex-wrap gap-2">
          <Boton type="submit">Buscar</Boton>

          {busqueda && (
            <Boton
              type="button"
              variante="secundario"
              onClick={() => {
                onBuscar(null)
                setTermino('')
                onCerrar()
              }}
            >
              Quitar la busqueda
            </Boton>
          )}
        </div>
      </form>
    </Modal>
  )
}
