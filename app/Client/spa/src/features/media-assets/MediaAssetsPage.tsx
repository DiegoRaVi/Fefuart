import { FormEvent, useState } from 'react';
import { apiRequest } from '../../shared/api/client';
import type { SessionUser } from '../../shared/api/types';

type MediaAssetsPageProps = {
  user: SessionUser | null;
};

type MediaAsset = {
  id: number;
  user_id: number;
  context_type: string | null;
  context_id: number | null;
  path: string;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  visibility: 'public' | 'private';
};

export function MediaAssetsPage({ user }: MediaAssetsPageProps) {
  const [assets, setAssets] = useState<MediaAsset[]>([]);
  const [lookupId, setLookupId] = useState('');
  const [lookupAsset, setLookupAsset] = useState<MediaAsset | null>(null);
  const [visibility, setVisibility] = useState<'public' | 'private'>('public');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  async function handleUpload(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setError('');
    setMessage('');

    const formElement = event.currentTarget;
    const formData = new FormData(formElement);
    formData.set('visibility', visibility);

    try {
      const response = await apiRequest<{ asset: MediaAsset }>('/media/upload', {
        method: 'POST',
        body: formData,
      });

      setAssets((current) => [response.data.asset, ...current]);
      setMessage(`Asset #${response.data.asset.id} subido correctamente.`);
      formElement.reset();
      setVisibility('public');
    } catch (uploadError) {
      setError(uploadError instanceof Error ? uploadError.message : 'No se pudo subir el archivo.');
    }
  }

  async function handleLookup(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setError('');
    setMessage('');

    try {
      const response = await apiRequest<{ asset: MediaAsset }>(`/media/${Number(lookupId)}`);
      setLookupAsset(response.data.asset);
      setMessage(`Asset #${response.data.asset.id} recuperado.`);
    } catch (lookupError) {
      setLookupAsset(null);
      setError(lookupError instanceof Error ? lookupError.message : 'No se pudo recuperar el asset.');
    }
  }

  async function deleteAsset(assetId: number): Promise<void> {
    setError('');
    setMessage('');

    try {
      await apiRequest<{ message: string }>(`/media/${assetId}`, { method: 'DELETE' });
      setAssets((current) => current.filter((asset) => asset.id !== assetId));
      if (lookupAsset?.id === assetId) {
        setLookupAsset(null);
      }
      setMessage(`Asset #${assetId} eliminado.`);
    } catch (deleteError) {
      setError(deleteError instanceof Error ? deleteError.message : 'No se pudo eliminar el asset.');
    }
  }

  if (!user) {
    return <section className="panel">Necesitas iniciar sesion para subir o borrar media assets.</section>;
  }

  return (
    <section className="stack-gap-large">
      <article className="panel stack-gap">
        <h1>Media assets</h1>
        <p>Upload con metadata y control de visibilidad, conectado a POST/GET/DELETE de MediaAssets v1.</p>

        <form onSubmit={handleUpload} className="stack-gap">
          <label className="field">
            <span>Archivo</span>
            <input type="file" name="file" accept="image/*" required />
          </label>

          <label className="field">
            <span>Visibilidad</span>
            <select value={visibility} onChange={(event) => setVisibility(event.target.value as 'public' | 'private')}>
              <option value="public">Public</option>
              <option value="private">Private</option>
            </select>
          </label>

          <button type="submit" className="solid-button">
            Subir archivo
          </button>
        </form>
      </article>

      <article className="panel stack-gap">
        <h2>Buscar asset por ID</h2>
        <form onSubmit={handleLookup} className="filters-row">
          <label className="field">
            <span>ID</span>
            <input value={lookupId} onChange={(event) => setLookupId(event.target.value)} required />
          </label>
          <button type="submit" className="outline-button">
            Consultar
          </button>
        </form>

        {lookupAsset && (
          <div className="notice-card">
            <h3>{lookupAsset.original_name}</h3>
            <p>ID {lookupAsset.id}</p>
            <p>{lookupAsset.mime_type}</p>
            <p>{lookupAsset.path}</p>
            <p>Visibilidad: {lookupAsset.visibility}</p>
          </div>
        )}
      </article>

      <article className="panel stack-gap">
        <h2>Assets subidos en esta sesion</h2>
        {assets.length === 0 && <p>Aun no hay assets subidos.</p>}

        {assets.length > 0 && (
          <ul className="plain-list">
            {assets.map((asset) => (
              <li key={asset.id} className="row-item">
                <div>
                  <strong>#{asset.id}</strong> {asset.original_name} ({asset.visibility})
                </div>
                <button type="button" className="ghost-button" onClick={() => deleteAsset(asset.id)}>
                  Eliminar
                </button>
              </li>
            ))}
          </ul>
        )}
      </article>

      {message && <p className="feedback success">{message}</p>}
      {error && <p className="feedback error">{error}</p>}
    </section>
  );
}
