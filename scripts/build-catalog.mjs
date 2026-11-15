import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { writeCatalog } from '../frontend/src/catalog/build-catalog.ts';

const root = resolve(import.meta.dirname, '..');
const output = resolve(root, 'frontend/public/catalog.json');

mkdirSync(dirname(output), { recursive: true });
writeCatalog(resolve(root, 'database/data'), output);
