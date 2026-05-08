import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import type { SessionUser } from '../../shared/api/types';
import { apiRequest } from '../../shared/api/client';
import { getApiFieldErrors, pickFieldErrors } from '../../shared/lib/fieldErrors';

type LiveArtPageProps = {
  user: SessionUser | null;
};

type LiveArtSchedule = 'morning' | 'evening';
type LiveArtField = 'title' | 'description' | 'phone' | 'date' | 'location' | 'schedule';

type LiveArtDraft = {
  title: string;
  description: string;
  phone: string;
  date: string;
  location: string;
  schedule: LiveArtSchedule;
};

type LiveArtRequestResult = {
  id: number;
  status: string;
  title: string;
  date: string;
  location: string;
  schedule: LiveArtSchedule;
};

const LIVE_ART_DRAFT_KEY = 'fefuart_live_art_draft_v1';

function getTodayIsoDate(): string {
  return new Date().toISOString().slice(0, 10);
}

function readLiveArtDraft(): LiveArtDraft {
  const emptyDraft: LiveArtDraft = {
    title: '',
    description: '',
    phone: '',
    date: '',
    location: '',
    schedule: 'morning',
  };

  try {
    const rawDraft = window.localStorage.getItem(LIVE_ART_DRAFT_KEY);
    if (!rawDraft) {
      return emptyDraft;
    }

    const parsed = JSON.parse(rawDraft) as Partial<LiveArtDraft>;

    return {
      ...emptyDraft,
      ...parsed,
      schedule: parsed.schedule === 'evening' ? 'evening' : 'morning',
    };
  } catch {
    return emptyDraft;
  }
}

export function LiveArtPage({ user }: LiveArtPageProps) {
  const initialDraft = useMemo(() => readLiveArtDraft(), []);

  const [title, setTitle] = useState(initialDraft.title);
  const [description, setDescription] = useState(initialDraft.description);
  const [phone, setPhone] = useState(initialDraft.phone);
  const [date, setDate] = useState(initialDraft.date);
  const [location, setLocation] = useState(initialDraft.location);
  const [schedule, setSchedule] = useState<LiveArtSchedule>(initialDraft.schedule);
  const [result, setResult] = useState('');
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<LiveArtField, string>>>({});
  const [lastRequest, setLastRequest] = useState<LiveArtRequestResult | null>(null);
  const [sending, setSending] = useState(false);

  const today = useMemo(() => getTodayIsoDate(), []);
  const canSubmit = Boolean(user) && !sending;

  useEffect(() => {
    const draft: LiveArtDraft = { title, description, phone, date, location, schedule };
    window.localStorage.setItem(LIVE_ART_DRAFT_KEY, JSON.stringify(draft));
  }, [date, description, location, phone, schedule, title]);

  function resetFormAndDraft(): void {
    setTitle('');
    setDescription('');
    setPhone('');
    setDate('');
    setLocation('');
    setSchedule('morning');
    setFieldErrors({});
    window.localStorage.removeItem(LIVE_ART_DRAFT_KEY);
  }

  function fillDemoData(): void {
    const suggestedDate = new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

    setTitle('Live art para boda intima');
    setDescription('Evento familiar con enfoque en retratos de pareja y familiares cercanos.');
    setPhone('+34 600 123 456');
    setDate(suggestedDate);
    setLocation('Valencia centro');
    setSchedule('evening');
    setFieldErrors({});
    setError('');
    setResult('Datos de ejemplo cargados.');
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();

    if (!user) {
      setError('Necesitas iniciar sesion para enviar la solicitud.');
      return;
    }

    setFieldErrors({});

    if (title.trim().length < 4) {
      setFieldErrors({ title: 'El titulo debe tener al menos 4 caracteres.' });
      setError('El titulo debe tener al menos 4 caracteres.');
      return;
    }

    if (location.trim().length < 3) {
      setFieldErrors({ location: 'La ubicacion debe tener al menos 3 caracteres.' });
      setError('La ubicacion debe tener al menos 3 caracteres.');
      return;
    }

    if (date < today) {
      setFieldErrors({ date: 'La fecha no puede estar en el pasado.' });
      setError('La fecha no puede estar en el pasado.');
      return;
    }

    if (phone.trim().length > 0) {
      const normalizedPhone = phone.trim();

      if (normalizedPhone.length > 30) {
        setFieldErrors({ phone: 'El telefono no puede superar 30 caracteres.' });
        setError('El telefono no puede superar 30 caracteres.');
        return;
      }

      if (!/^[0-9+\s()\-]+$/.test(normalizedPhone)) {
        setFieldErrors({ phone: 'El telefono solo admite numeros, espacios y caracteres + ( ) -.' });
        setError('El telefono solo admite numeros, espacios y caracteres + ( ) -.');
        return;
      }
    }

    setSending(true);
    setError('');
    setResult('');

    try {
      const response = await apiRequest<LiveArtRequestResult>('/live-art/requests', {
        method: 'POST',
        body: JSON.stringify({
          title: title.trim(),
          description: description.trim(),
          phone: phone.trim(),
          date,
          location: location.trim(),
          schedule,
        }),
      });

      setLastRequest(response.data);
      setFieldErrors({});
      setResult(`Solicitud #${response.data.id} creada en estado ${response.data.status}.`);
      resetFormAndDraft();
    } catch (submitError) {
      const apiFieldErrors = pickFieldErrors(getApiFieldErrors(submitError), [
        'title',
        'description',
        'phone',
        'date',
        'location',
        'schedule',
      ] as const);

      if (Object.keys(apiFieldErrors).length > 0) {
        setFieldErrors(apiFieldErrors);
        setError('Revisa los campos del formulario antes de reenviar.');
      } else {
        setError(submitError instanceof Error ? submitError.message : 'No se pudo crear la solicitud.');
      }
    } finally {
      setSending(false);
    }
  }

  return (
    <section className="panel stack-gap-large">
      <header className="stack-gap">
        <h1>Solicitud de Live Art</h1>
        <p>Este formulario consume el endpoint POST /api/v1/live-art/requests con contrato v1.</p>

        <div className="button-row">
          <button type="button" className="outline-button" onClick={fillDemoData} disabled={sending}>
            Cargar ejemplo
          </button>
          <button type="button" className="outline-button" onClick={resetFormAndDraft} disabled={sending}>
            Limpiar formulario
          </button>
        </div>

        {!user && (
          <p className="feedback error">
            Debes iniciar sesion para enviar solicitudes.
            {' '}
            <Link to="/auth">Ir a entrar</Link>
          </p>
        )}
      </header>

      <form onSubmit={handleSubmit} className="stack-gap">
        <label className="field">
          <span>Titulo</span>
          <input
            value={title}
            onChange={(event) => {
              setTitle(event.target.value);
              setFieldErrors((previous) => ({ ...previous, title: undefined }));
            }}
            required
            disabled={sending}
          />
        </label>
        {fieldErrors.title && <p className="field-error">{fieldErrors.title}</p>}

        <label className="field">
          <span>Descripcion</span>
          <textarea
            value={description}
            onChange={(event) => {
              setDescription(event.target.value);
              setFieldErrors((previous) => ({ ...previous, description: undefined }));
            }}
            rows={4}
            disabled={sending}
          />
        </label>
        {fieldErrors.description && <p className="field-error">{fieldErrors.description}</p>}

        <label className="field">
          <span>Telefono</span>
          <input
            value={phone}
            onChange={(event) => {
              setPhone(event.target.value);
              setFieldErrors((previous) => ({ ...previous, phone: undefined }));
            }}
            disabled={sending}
          />
        </label>
        {fieldErrors.phone && <p className="field-error">{fieldErrors.phone}</p>}

        <label className="field">
          <span>Fecha</span>
          <input
            type="date"
            value={date}
            onChange={(event) => {
              setDate(event.target.value);
              setFieldErrors((previous) => ({ ...previous, date: undefined }));
            }}
            required
            min={today}
            disabled={sending}
          />
        </label>
        {fieldErrors.date && <p className="field-error">{fieldErrors.date}</p>}

        <label className="field">
          <span>Ubicacion</span>
          <input
            value={location}
            onChange={(event) => {
              setLocation(event.target.value);
              setFieldErrors((previous) => ({ ...previous, location: undefined }));
            }}
            required
            disabled={sending}
          />
        </label>
        {fieldErrors.location && <p className="field-error">{fieldErrors.location}</p>}

        <label className="field">
          <span>Horario</span>
          <select
            value={schedule}
            onChange={(event) => {
              setSchedule(event.target.value as LiveArtSchedule);
              setFieldErrors((previous) => ({ ...previous, schedule: undefined }));
            }}
            disabled={sending}
          >
            <option value="morning">Manana</option>
            <option value="evening">Tarde</option>
          </select>
        </label>
        {fieldErrors.schedule && <p className="field-error">{fieldErrors.schedule}</p>}

        <p className="muted-text">La fecha debe ser hoy o futura. Los campos se guardan automaticamente como borrador local.</p>

        <button type="submit" className="solid-button" disabled={!canSubmit}>
          {sending ? 'Enviando...' : 'Enviar solicitud'}
        </button>
      </form>

      {lastRequest && (
        <article className="panel stack-gap">
          <h2>Ultima solicitud enviada</h2>
          <p>
            #{lastRequest.id} · {lastRequest.title} · estado {lastRequest.status}
          </p>
          <p>
            {lastRequest.date} · {lastRequest.location} · {lastRequest.schedule === 'morning' ? 'Manana' : 'Tarde'}
          </p>
        </article>
      )}

      {result && <p className="feedback success">{result}</p>}
      {error && <p className="feedback error">{error}</p>}
    </section>
  );
}
