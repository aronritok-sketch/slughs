// A 3D-modul csomagolása egyetlen, böngészőben futó fájlba (three.js-szel együtt): assets/js/fx3d.js
// Futtatás: cd tools/3d && npm install && npm run build
import { build } from 'esbuild';
import path from 'path';
import { fileURLToPath } from 'url';
const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
await build({
  entryPoints: [path.join(root, 'assets/js/src/fx3d.js')],
  outfile: path.join(root, 'assets/js/fx3d.js'),
  bundle: true, format: 'iife', minify: true, target: 'es2019', legalComments: 'eof',
  nodePaths: [path.join(here, 'node_modules')],
  banner: { js: '/* GENERÁLT – tools/3d/build.mjs (forrás: assets/js/src/fx3d.js). three.js © MIT. */' },
  logLevel: 'info',
});
