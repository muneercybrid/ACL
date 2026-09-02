import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Inside a codespace the browser never reaches Vite on localhost:5173. It
// reaches it through GitHub's HTTPS port forwarder, on a hostname derived from
// the codespace name and on port 443. Three things follow from that:
//
//   host    -- bind every interface, or the forwarder has nothing to forward.
//   origin  -- laravel-vite-plugin writes this into public/hot, and Blade's
//              @vite turns public/hot into the <script src>. Left at the
//              default the page would ask the browser for localhost:5173,
//              which in the browser-based editor is the user's own machine.
//   hmr     -- the websocket is a separate connection the browser opens
//              itself, so it needs the forwarded host and port 443 spelled
//              out; wss because the forwarder terminates TLS.
//
// Outside a codespace both variables are undefined, `server` stays undefined,
// and Vite keeps its ordinary localhost defaults for local development.
const codespaceName = process.env.CODESPACE_NAME;
const forwardingDomain = process.env.GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN;
const vitePort = Number(process.env.ACL_VITE_PORT ?? 5173);
const appPort = Number(process.env.ACL_APP_PORT ?? 8000);

const codespaceServer = codespaceName && forwardingDomain
    ? {
          host: '0.0.0.0',
          port: vitePort,
          // Fail loudly instead of drifting to 5174, which is not forwarded.
          strictPort: true,
          origin: `https://${codespaceName}-${vitePort}.${forwardingDomain}`,
          hmr: {
              host: `${codespaceName}-${vitePort}.${forwardingDomain}`,
              protocol: 'wss',
              clientPort: 443,
          },
          // The page comes from the 8000 origin and Vite from the 5173 one, so
          // @vite/client and app.js are cross-origin module scripts -- which
          // browsers do gate on CORS, unlike a plain stylesheet. Vite 6+ stopped
          // reflecting arbitrary origins, and on its own it advertises only its
          // own origin, so the app's origin has to be named here explicitly.
          cors: {
              origin: `https://${codespaceName}-${appPort}.${forwardingDomain}`,
          },
      }
    : undefined;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: codespaceServer,
});
