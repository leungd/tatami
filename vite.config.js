import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';
import fs from 'fs';

function wordpressVite() {
  const hotFilePath = path.resolve(__dirname, 'build/hot');

  return {
    name: 'wordpress-vite',
    configureServer(server) {
      // Write the hot file only once the server is actually listening, using
      // the resolved port — Vite silently auto-increments when the configured
      // port is taken, and a hot file recording the wrong port 404s every asset.
      server.httpServer?.once('listening', () => {
        const { https, host } = server.config.server;
        const protocol = https ? 'https' : 'http';
        const address = server.httpServer.address();
        const port =
          typeof address === 'object' && address !== null
            ? address.port
            : (server.config.server.port ?? 5173);
        const origin = `${protocol}://${host || 'localhost'}:${port}`;

        fs.mkdirSync(path.dirname(hotFilePath), { recursive: true });
        fs.writeFileSync(hotFilePath, origin);
      });

      const clean = () => {
        if (fs.existsSync(hotFilePath)) fs.unlinkSync(hotFilePath);
      };
      process.on('exit', clean);
      process.on('SIGINT', () => {
        clean();
        process.exit();
      });
      process.on('SIGTERM', () => {
        clean();
        process.exit();
      });
    },
    handleHotUpdate({ file, server }) {
      if (file.endsWith('.php') || file.endsWith('.twig')) {
        server.ws.send({ type: 'full-reload' });
      }
    },
  };
}

export default defineConfig({
  base: '',
  server: {
    host: 'localhost',
    cors: true,
    origin: 'http://localhost:5173',
  },
  build: {
    emptyOutDir: true,
    manifest: true,
    outDir: 'build',
    assetsDir: 'assets',
    rollupOptions: {
      input: ['src/js/main.js'],
    },
  },
  plugins: [tailwindcss(), wordpressVite()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@styles': path.resolve(__dirname, './src/css'),
    },
  },
});
