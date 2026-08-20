/**
 * La pagina «Sobre mi» que tenia el legacy.
 *
 * **El texto es de Felicitas y va literal**, tal y como estaba en
 * `views/about.html`. Se recupero del historico de git al retirar el legacy.
 * Reescribirlo «mejor» seria sustituir su voz por la mia, que es lo unico
 * que esta pagina tiene que transmitir.
 *
 * Lo unico que se ha tocado es la ortografia: las tildes que el HTML original
 * no llevaba, la apertura de las exclamaciones y un «lo experiencia» que era
 * un dedazo. Acentuar no le cambia la voz a nadie.
 */
export function SobreMi() {
  return (
    <div className="mx-auto max-w-3xl space-y-8">
      <div className="space-y-3">
        <p className="antetitulo text-verde">Sobre mí</p>
        <h1 className="titular-portada text-verde">Quién hay detrás</h1>
      </div>

      {/* Un poco mas ancho de lo normal para el cuerpo, porque aqui manda el
          ritmo del texto y no la densidad. */}
      <div className="max-w-prose space-y-5 text-lg">
        <p>¡Hola, bienvenidos!</p>

        <p>
          Me llamo Feli, y soy la mente y el corazón detrás de esta travesía
          artística. Soy una apasionada artista plástica con formación en Bellas
          Artes, carrera que me ofreció la oportunidad de conocer el fantástico
          mundo de las acuarelas y así poder dar forma y color a mis ideas.
        </p>

        <p>
          Actualmente mi misión es transformar momentos especiales en recuerdos
          eternos a través de la magia de la pintura en directo y los dibujos
          personalizados. En cada ilustración intento plasmar todo el cariño y la
          pasión que me acompañan en esta profesión. Además, me comprometo con la
          estética y la decoración para ofreceros la experiencia lo más bonita y
          agradable posible.
        </p>

        <p>
          Gracias por visitar mi pequeño rincón artístico, ¡no dudes en
          escribirme!
        </p>
      </div>
    </div>
  )
}
