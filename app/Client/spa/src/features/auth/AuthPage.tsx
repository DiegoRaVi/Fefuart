import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { apiRequest } from '../../shared/api/client';
import type { SessionUser } from '../../shared/api/types';
import { setSession } from '../../shared/lib/session';
import { getApiFieldErrors, pickFieldErrors } from '../../shared/lib/fieldErrors';

type AuthPageProps = {
  user: SessionUser | null;
};

type LoginField = 'email' | 'password';
type RegisterField = 'name' | 'email' | 'password' | 'password_confirmation';

export function AuthPage({ user }: AuthPageProps) {
  const [loginEmail, setLoginEmail] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [registerName, setRegisterName] = useState('');
  const [registerEmail, setRegisterEmail] = useState('');
  const [registerPassword, setRegisterPassword] = useState('');
  const [registerPasswordConfirmation, setRegisterPasswordConfirmation] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loginFieldErrors, setLoginFieldErrors] = useState<Partial<Record<LoginField, string>>>({});
  const [registerFieldErrors, setRegisterFieldErrors] = useState<Partial<Record<RegisterField, string>>>({});
  const [loginLoading, setLoginLoading] = useState(false);
  const [registerLoading, setRegisterLoading] = useState(false);

  const isBusy = loginLoading || registerLoading;

  async function login(email: string, password: string): Promise<void> {
    const result = await apiRequest<{ token: string; user: SessionUser }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });

    setSession(result.data.token, result.data.user);
  }

  async function handleLoginSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setLoginLoading(true);
    setError('');
    setMessage('');
    setLoginFieldErrors({});

    try {
      await login(loginEmail, loginPassword);
      setMessage('Sesion iniciada correctamente.');
    } catch (submitError) {
      const apiFieldErrors = pickFieldErrors(getApiFieldErrors(submitError), ['email', 'password'] as const);

      if (Object.keys(apiFieldErrors).length > 0) {
        setLoginFieldErrors(apiFieldErrors);
        setError('Revisa los campos del formulario de acceso.');
      } else {
        setError(submitError instanceof Error ? submitError.message : 'No se pudo iniciar sesion.');
      }
    } finally {
      setLoginLoading(false);
    }
  }

  async function handleRegisterSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setRegisterLoading(true);
    setError('');
    setMessage('');
    setRegisterFieldErrors({});

    if (registerPassword !== registerPasswordConfirmation) {
      setRegisterFieldErrors({ password_confirmation: 'La confirmacion debe coincidir con el password.' });
      setError('Revisa los campos del formulario de registro.');
      setRegisterLoading(false);
      return;
    }

    try {
      await apiRequest<{ user: SessionUser }>('/auth/register', {
        method: 'POST',
        body: JSON.stringify({
          name: registerName,
          email: registerEmail,
          password: registerPassword,
          password_confirmation: registerPasswordConfirmation,
        }),
      });

      await login(registerEmail, registerPassword);
      setMessage('Cuenta creada e inicio de sesion realizado.');
    } catch (submitError) {
      const apiFieldErrors = pickFieldErrors(getApiFieldErrors(submitError), [
        'name',
        'email',
        'password',
        'password_confirmation',
      ] as const);

      if (Object.keys(apiFieldErrors).length > 0) {
        setRegisterFieldErrors(apiFieldErrors);
        setError('Revisa los campos del formulario de registro.');
      } else {
        setError(submitError instanceof Error ? submitError.message : 'No se pudo crear la cuenta.');
      }
    } finally {
      setRegisterLoading(false);
    }
  }

  if (user) {
    return (
      <section className="panel stack-gap">
        <h1>Sesion activa</h1>
        <p>
          Hola {user.name}, ya tienes acceso con rol <strong>{user.role}</strong>.
        </p>
        <Link to="/" className="solid-button">
          Volver al inicio
        </Link>
      </section>
    );
  }

  return (
    <section className="split-grid">
      <article className="panel">
        <h1>Entrar</h1>
        <form className="stack-gap" onSubmit={handleLoginSubmit}>
          <label className="field">
            <span>Email</span>
            <input
              type="email"
              value={loginEmail}
              onChange={(event) => {
                setLoginEmail(event.target.value);
                setLoginFieldErrors((previous) => ({ ...previous, email: undefined }));
              }}
              required
              disabled={isBusy}
            />
          </label>
          {loginFieldErrors.email && <p className="field-error">{loginFieldErrors.email}</p>}

          <label className="field">
            <span>Password</span>
            <input
              type="password"
              value={loginPassword}
              onChange={(event) => {
                setLoginPassword(event.target.value);
                setLoginFieldErrors((previous) => ({ ...previous, password: undefined }));
              }}
              required
              disabled={isBusy}
            />
          </label>
          {loginFieldErrors.password && <p className="field-error">{loginFieldErrors.password}</p>}

          <button type="submit" className="solid-button" disabled={isBusy}>
            {loginLoading ? 'Validando...' : 'Iniciar sesion'}
          </button>
        </form>
      </article>

      <article className="panel">
        <h1>Crear cuenta</h1>
        <form className="stack-gap" onSubmit={handleRegisterSubmit}>
          <label className="field">
            <span>Nombre</span>
            <input
              type="text"
              value={registerName}
              onChange={(event) => {
                setRegisterName(event.target.value);
                setRegisterFieldErrors((previous) => ({ ...previous, name: undefined }));
              }}
              required
              disabled={isBusy}
            />
          </label>
          {registerFieldErrors.name && <p className="field-error">{registerFieldErrors.name}</p>}

          <label className="field">
            <span>Email</span>
            <input
              type="email"
              value={registerEmail}
              onChange={(event) => {
                setRegisterEmail(event.target.value);
                setRegisterFieldErrors((previous) => ({ ...previous, email: undefined }));
              }}
              required
              disabled={isBusy}
            />
          </label>
          {registerFieldErrors.email && <p className="field-error">{registerFieldErrors.email}</p>}

          <label className="field">
            <span>Password</span>
            <input
              type="password"
              value={registerPassword}
              onChange={(event) => {
                setRegisterPassword(event.target.value);
                setRegisterFieldErrors((previous) => ({ ...previous, password: undefined }));
              }}
              required
              disabled={isBusy}
            />
          </label>
          {registerFieldErrors.password && <p className="field-error">{registerFieldErrors.password}</p>}

          <label className="field">
            <span>Confirmar password</span>
            <input
              type="password"
              value={registerPasswordConfirmation}
              onChange={(event) => {
                setRegisterPasswordConfirmation(event.target.value);
                setRegisterFieldErrors((previous) => ({ ...previous, password_confirmation: undefined }));
              }}
              required
              disabled={isBusy}
            />
          </label>
          {registerFieldErrors.password_confirmation && <p className="field-error">{registerFieldErrors.password_confirmation}</p>}

          <button type="submit" className="solid-button" disabled={isBusy}>
            {registerLoading ? 'Creando...' : 'Registrar y entrar'}
          </button>
        </form>
      </article>

      {(message || error) && <p className={error ? 'feedback error' : 'feedback success'}>{error || message}</p>}
    </section>
  );
}
