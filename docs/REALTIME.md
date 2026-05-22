Real-time sync (Mercure)

This project supports real-time customer updates via Mercure if a Mercure hub is available.

1) Run or configure a Mercure hub. Example (official Docker image):

   docker run -d --name mercure -p 3000:80 \
     -e MERCURE_PUBLISHER_JWT_KEY='your-256+bit-key' \
     -e MERCURE_SUBSCRIBER_JWT_KEY='your-256+bit-key' \
     -e ALLOW_ANONYMOUS=1 \
     -e CADDY_AUTOHTTPS=off \
     dunglas/mercure

2) Configure your Mercure bundle in `.env.local` (example):

   MERCURE_URL="http://127.0.0.1:3000/.well-known/mercure"
   MERCURE_JWT="<publisher-jwt>"

3) When a customer is created/updated/deleted via the API, the app will attempt to publish a Mercure Update.
   Publishing is handled by App\Service\CustomerNotifier which uses the Mercure Hub if available. If the Hub is not
   configured the app will continue to work but updates won't be published.

4) Example client (browser/mobile webview) can use EventSource to subscribe. See assets/mercure_subscriber.js

Troubleshooting & notes:

- The official Mercure Docker image uses Caddy which may redirect HTTP -> HTTPS by default. Set `CADDY_AUTOHTTPS=off` and `SERVER_NAME=127.0.0.1` when running locally to reduce redirect issues.
- Use a 256+ bit symmetric key for HS256 signing (e.g. `dev_mercure_secret_key_0123456789abcdefghijklmnop`).
- If your environment forces HTTPS, the Symfony HTTP client may reject the hub's self-signed certificate. For local dev you can either:
   - configure the client to ignore peer verification (dev only), or
   - generate a proper local CA and trust it in Windows.
- If Mercure cannot be run locally, this project includes a fallback SSE endpoint at `/sse/customers` and a helper command `php bin/console app:notify:smoke` which writes events to `var/mercure_fallback.log` so local testing still receives updates.

Fallback: If you don't want to run Mercure, implement periodic polling from the mobile app to `/api/customers?q=...` to get near-real-time updates, or use the SSE fallback endpoint above for development/testing.
