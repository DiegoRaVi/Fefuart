import { api } from '@/shared/api/client'
import type { Envelope } from '@/shared/api/types'

export interface MediaAsset {
  id: number
  original_name: string
  mime_type: string
  size_bytes: number
  url?: string
}

/**
 * N9 — la foto se sube antes de que exista la linea del carrito, y devuelve
 * un id que luego se adjunta. Asi el formulario puede enseñar la miniatura y
 * el cliente sabe que ha entrado antes de confirmar nada.
 *
 * Lo que se sube no es lo que se guarda: el servidor re-encodifica la imagen
 * y tira todo lo que no sean pixeles (SEC-014), asi que el tamaño y el tipo
 * de la respuesta pueden no coincidir con los del fichero elegido.
 */
export async function subirFoto(file: File): Promise<MediaAsset> {
  const formData = new FormData()
  formData.append('file', file)

  const { data } = await api.post<Envelope<MediaAsset>>('/media', formData)

  return data.data
}

export async function borrarFoto(id: number): Promise<void> {
  await api.delete(`/media/${id}`)
}
