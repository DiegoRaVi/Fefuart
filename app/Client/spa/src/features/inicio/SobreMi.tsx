/**
 * La pagina «Sobre mi» que tenia el legacy.
 *
 * **El texto es de Felicitas y va literal**, tal y como estaba en
 * `views/about.html`. Se recupero del historico de git al retirar el legacy.
 * Reescribirlo «mejor» seria sustituir su voz por la mia, que es lo unico
 * que esta pagina tiene que transmitir.
 */
export function SobreMi() {
  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <h1 className="text-titulo text-verde">Quien hay detras</h1>

      <div className="space-y-4 text-base">
        <p>Hola, bienvenidos!</p>

        <p>
          Me llamo Feli, y soy la mente y el corazon detras de esta travesia
          artistica. Soy una apasionada artista plastica con formacion en Bellas
          Artes, carrera que me ofrecio la oportunidad de conocer el fantastico
          mundo de las acuarelas y asi poder dar forma y color a mis ideas.
        </p>

        <p>
          Actualmente mi mision es transformar momentos especiales en recuerdos
          eternos a traves de la magia de la pintura en directo y los dibujos
          personalizados. En cada ilustracion intento plasmar todo el carino y la
          pasion que me acompanan en esta profesion. Ademas, me comprometo con la
          estetica y la decoracion para ofreceros lo experiencia lo mas bonita y
          agradable posible.
        </p>

        <p>Gracias por visitar mi pequeño rincón artistico, no dudes en escribirme!</p>
      </div>
    </div>
  )
}
