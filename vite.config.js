import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';

// Local HTTPS dev cert for chat.test (mkcert). Used only when present, so
// `npm run build` and other machines/CI are unaffected.
const certFile = '.certs/chat.test.crt';
const keyFile = '.certs/chat.test.key';
const devTls = fs.existsSync(certFile) && fs.existsSync(keyFile)
    ? { cert: fs.readFileSync(certFile), key: fs.readFileSync(keyFile) }
    : undefined;

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/scss/app.scss',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: 'chat.test',
        https: devTls,
        hmr: { host: 'chat.test' },
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
