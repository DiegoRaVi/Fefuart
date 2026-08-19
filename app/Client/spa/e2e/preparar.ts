import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'

/**
 * Deja `fefuart_e2e` en su estado de semilla antes de cada tanda.
 *
 * Con `APP_ENV=e2e`, y eso es lo unico que separa esta base de la de
 * desarrollo. Sin esa variable, `migrate:fresh` vaciaria `fefuart`, que es
 * donde estan los datos con los que se prueba a mano.
 */
const servidor = fileURLToPath(new URL('../../../Server', import.meta.url))

export default function preparar(): void {
  execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
    cwd: servidor,
    env: { ...process.env, APP_ENV: 'e2e' },
    stdio: 'inherit',
  })

  // Bandeja limpia: los recorridos buscan «el ultimo correo para X», y uno
  // de una tanda anterior daria un falso positivo.
  vaciarLaBandeja()
}

function vaciarLaBandeja(): void {
  try {
    execFileSync('curl', ['-s', '-X', 'DELETE', 'http://127.0.0.1:8025/api/v1/messages'], {
      stdio: 'ignore',
    })
  } catch {
    // Mailpit lo arranca Playwright justo despues de este gancho, asi que la
    // primera vez todavia no responde. No es un fallo: si no esta, no hay
    // bandeja que vaciar.
  }
}
