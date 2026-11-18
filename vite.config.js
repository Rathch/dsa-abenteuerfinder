import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

function redirectDirectoryIndexes() {
    const redirect = (req, res, next) => {
        const url = req.url?.split('?')[0];

        if (url === '/impressum' || url === '/datenschutz') {
            res.statusCode = 302;
            res.setHeader('Location', `${url}/`);
            res.end();

            return;
        }

        if (url !== '/' && !url.endsWith('/') && !url.includes('.')) {
            req.url = '/404.html';
        }

        next();
    };

    return {
        name: 'redirect-directory-indexes',
        configurePreviewServer(server) {
            server.middlewares.use(redirect);
        },
    };
}

// Keine externen Webfonts: System-Schriftstacks (Datenschutz, Performance, deterministische Screenshots).
export default defineConfig({
    root: 'frontend',
    base: './',
    publicDir: 'public',
    plugins: [tailwindcss(), redirectDirectoryIndexes()],
    build: {
        outDir: '../dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                main: resolve('frontend/index.html'),
                impressum: resolve('frontend/impressum/index.html'),
                datenschutz: resolve('frontend/datenschutz/index.html'),
                404: resolve('frontend/404.html'),
            },
        },
    },
});
