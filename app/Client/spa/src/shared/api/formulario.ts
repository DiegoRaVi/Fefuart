import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'

import { ApiError } from './errors'

/**
 * Traslada el 422 de Laravel a los campos del formulario.
 *
 * La validacion de cliente y la de servidor no son la misma cosa y no se
 * pueden sustituir: Zod evita el viaje cuando el fallo es evidente, pero
 * quien decide de verdad es el backend, que es el unico que sabe si un
 * correo ya esta cogido o si el limitador de SEC-007 ha saltado.
 *
 * Devuelve el mensaje general para los errores que no pertenecen a ningun
 * campo, o `null` si todos se han podido colocar.
 */
export function aplicarErroresDeApi<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  camposConocidos: ReadonlyArray<Path<T>>,
): string | null {
  if (!(error instanceof ApiError)) {
    return 'Algo ha fallado.'
  }

  if (!error.isValidation) {
    return error.message
  }

  let colocadoAlguno = false

  for (const [campo, mensaje] of Object.entries(error.firstErrors())) {
    if (camposConocidos.includes(campo as Path<T>)) {
      setError(campo as Path<T>, { type: 'server', message: mensaje })
      colocadoAlguno = true
    }
  }

  // Un 422 cuyos campos no existen en este formulario no puede desaparecer
  // en silencio: se ensena como mensaje general.
  return colocadoAlguno ? null : error.message
}
