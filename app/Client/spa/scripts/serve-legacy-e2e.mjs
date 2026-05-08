import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';

const args = process.argv.slice(2);

function getArgValue(name, fallback) {
  const token = `--${name}`;
  const idx = args.indexOf(token);

  if (idx === -1 || idx + 1 >= args.length) {
    return fallback;
  }

  return args[idx + 1];
}

const host = getArgValue('host', process.env.E2E_LEGACY_HOST ?? '127.0.0.1');
const port = Number(getArgValue('port', process.env.E2E_LEGACY_PORT ?? '4180'));
const rootDir = path.resolve(process.cwd(), '..');

const mimeByExt = {
  '.avif': 'image/avif',
  '.css': 'text/css; charset=utf-8',
  '.gif': 'image/gif',
  '.html': 'text/html; charset=utf-8',
  '.jpeg': 'image/jpeg',
  '.jpg': 'image/jpeg',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.txt': 'text/plain; charset=utf-8',
  '.webp': 'image/webp',
};

function getSafeFilePath(rawUrl) {
  const rawPath = (rawUrl || '/').split('?')[0];
  const decodedPath = decodeURIComponent(rawPath);
  const normalizedPath = path.normalize(decodedPath).replace(/^([.][./\\])+/, '');
  const relativePath = normalizedPath === '/' ? '/views/index.html' : normalizedPath;
  const resolvedPath = path.resolve(rootDir, `.${relativePath}`);

  if (!resolvedPath.startsWith(rootDir)) {
    return null;
  }

  return resolvedPath;
}

async function resolveFilePath(rawUrl) {
  const filePath = getSafeFilePath(rawUrl);

  if (!filePath) {
    return null;
  }

  const stats = await stat(filePath).catch(() => null);

  if (!stats) {
    return null;
  }

  if (stats.isDirectory()) {
    const nestedIndex = path.join(filePath, 'index.html');
    const nestedStats = await stat(nestedIndex).catch(() => null);
    return nestedStats?.isFile() ? nestedIndex : null;
  }

  return stats.isFile() ? filePath : null;
}

const server = createServer(async (request, response) => {
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    response.writeHead(405, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end('Method not allowed');
    return;
  }

  const resolvedPath = await resolveFilePath(request.url);

  if (!resolvedPath) {
    response.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end('Not found');
    return;
  }

  const ext = path.extname(resolvedPath).toLowerCase();
  const contentType = mimeByExt[ext] ?? 'application/octet-stream';
  const fileBuffer = await readFile(resolvedPath);

  response.writeHead(200, {
    'Cache-Control': 'no-store',
    'Content-Type': contentType,
  });

  if (request.method === 'HEAD') {
    response.end();
    return;
  }

  response.end(fileBuffer);
});

server.listen(port, host, () => {
  console.log(`Legacy static server ready at http://${host}:${port}`);
  console.log(`Serving directory: ${rootDir}`);
});

function shutdown() {
  server.close(() => process.exit(0));
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
