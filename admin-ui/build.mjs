import { build, context } from 'esbuild';
import { gzipSync } from 'node:zlib';
import { readFileSync } from 'node:fs';

// The admin app of the free FloLens plugin. React itself is provided by WordPress (wp-element).
const opts = {
  entryPoints: ['src/index.tsx'],
  bundle: true,
  minify: true,
  target: ['es2019'],
  jsx: 'transform',
  jsxFactory: 'h',
  jsxFragment: 'Fragment',
  outfile: '../flolens/assets/admin/app.js',
  legalComments: 'none',
  logLevel: 'info',
};
if (process.argv.includes('--watch')) {
  const ctx = await context(opts);
  await ctx.watch();
} else {
  await build(opts);
  const buf = readFileSync(opts.outfile);
  console.log(`app.js: ${buf.length} bytes raw, ${gzipSync(buf, { level: 9 }).length} bytes gzip`);
}
