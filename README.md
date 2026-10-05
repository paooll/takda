# Takda

**Your turn, not your afternoon.**

Takda is a Filipino-inspired smart virtual queue and appointment platform. A customer joins a
queue from their phone, gets a digital ticket, and watches their real-time position and estimated
wait — so they can step away and come back instead of standing in line.

It targets the places where queues are unavoidable and opaque: clinics, government offices
(BIR, LTO, PSA), banks, and food counters. The business side gets a live counter board, one-tap
"call next", and throughput analytics.

---

## Architecture

```
takda/
├── api/    Laravel 13 REST API (PHP 8.3)
└── web/    React 19 + TypeScript + Vite + Tailwind 4 SPA
```

The two deploy independently: the SPA is static and goes to Cloudflare Pages, the API is a
long-running PHP process and goes to any PHP host.

### Why not Cloudflare D1?

D1 was the original target and it does not work with Laravel. D1 is SQLite reached through a
**Workers binding** — it has no remote TCP endpoint, so PDO (and therefore Eloquent) cannot open
a connection to it. The D1 REST API is not a substitute: Cloudflare documents that it *"does not
support D1 Sessions"*, so transaction and session semantics are lost. The only PHP driver that
exists (`pdo-cfd1`) requires running Laravel inside php-wasm inside a Worker, which is a research
path, not a production one.

Cloudflare also has no first-class PHP runtime for Workers, so "Laravel on Cloudflare" is not a
supported combination at all.

**The database is therefore chosen for what Laravel can connect to natively and reliably:**

| | Development | Production |
|---|---|---|
| Driver | SQLite | PostgreSQL |
| Why | Zero infrastructure, first-class Laravel support, instant local setup | Real concurrent writes with row locking, which the "call next" transaction needs |

Both are native PDO drivers already verified in this workspace. The schema uses no
SQLite-specific features, so switching is configuration only (`DB_CONNECTION=pgsql`).

**Cloudflare is still used where it fits:** Pages for the SPA, and CDN/DNS/WAF in front of the
API.

---

## Design system

**Type: Quicksand**, self-hosted as a single 28 KB variable woff2 (latin subset, weights 300–700)
with `font-display: swap` and a `<link rel="preload">` so text paints in Quicksand on the first
frame. It is declared once in `src/index.css` — deliberately *not* also imported from a font
package, which would bundle a second hashed copy and download the font twice.

Quicksand is geometric, so the scale compensates with a little more tracking at display sizes
(`text-display` sits at `-0.03em`). The scale is `display / title / body / caption`; components
use those tokens rather than ad-hoc sizes.

**Palette.** One interactive colour (`primary`) plus a `sun` accent reserved strictly for
"your turn" moments. Every foreground/background pair in use is verified against WCAG AA —
13 pairs, 0 failures. An earlier `ink-subtle` token measured 2.73:1 and was removed rather than
left in the palette as a trap.

**Depth** uses a two-layer shadow with a real offset and a soft blur, not a zero-offset halo.

**Browser surfaces** are themed from the palette — text selection, focus rings, scrollbars,
placeholder colour, and underline offset — rather than left at browser defaults.

**Motion is deliberately minimal.** The single authored moment is the queue position number, which
acknowledges itself when it actually changes; `prefers-reduced-motion` disables it. This is a
trust-first public-service UI, so the dials are low variance and restrained motion.

---

## Requirements

- **PHP 8.3** with `mbstring`, `xml`, `curl`, `sqlite3`, `zip`, `bcmath`, `intl`, `gd`
- **Composer 2**
- **Node 20+** and npm (for the frontend)

### Installing the PHP toolchain (Ubuntu 22.04)

Ubuntu 22.04 ships PHP 8.1, which is below Laravel 13's minimum. Use the Ondréj PPA:

```bash
sudo apt-get update
sudo apt-get install -y software-properties-common ca-certificates
sudo add-apt-repository -y ppa:ondrej/php

sudo apt-get install -y \
  php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-sqlite3 \
  php8.3-pgsql php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd unzip

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

`php8.3-pgsql` is only needed if you run against PostgreSQL; it is not required for the default
SQLite setup.

---

## Running locally

Two processes. The Vite dev server proxies `/api` to Laravel, so there are no CORS preflights in
development.

```bash
# Terminal 1 — API on :8000
cd api
composer install
cp .env.example .env
php artisan key:generate       # the API works without a key, but any web route 500s
php artisan migrate --seed     # creates the schema and demo data
php artisan serve

# Terminal 2 — web app on :5173
cd web
npm install
npm run dev
```

Open <http://localhost:5173>.

### Demo accounts

Seeded by `php artisan migrate --seed`:

| Role | Email | Password |
|---|---|---|
| Business owner | `owner@takda.test` | `password` |
| Customer | `customer@takda.test` | `password` |

Signing in as the owner shows the counter dashboard (`/counter`); the customer sees the
customer flow.

### Switching to PostgreSQL

```bash
php8.3-pgsql is installed above; then in api/.env:
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=takda
DB_USERNAME=takda
DB_PASSWORD=secret
```

Then `php artisan migrate --seed`.

---

## API

All routes are under `/api`. Authentication is a Sanctum bearer token
(`Authorization: Bearer <token>`); there are no cookies, so the API is not exposed to
cross-site cookie abuse.

### Auth

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/register` | — | Create an account, returns a token |
| POST | `/login` | — | Exchange credentials for a token |
| GET | `/user` | ✓ | Current profile |
| POST | `/logout` | ✓ | Revoke the current token |

### Customer

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/businesses` | — | List open businesses and their queues |
| GET | `/businesses/{slug}` | — | One business with active queues |
| POST | `/queues/{queue}/join` | ✓ | Join a queue, get a ticket |
| GET | `/tickets` | ✓ | Your tickets |
| GET | `/tickets/{id}` | ✓ | Live position, ETA, now-serving |
| DELETE | `/tickets/{id}` | ✓ | Leave the queue |
| GET/POST | `/appointments` | ✓ | List / book appointments |
| PATCH/DELETE | `/appointments/{id}` | ✓ | Reschedule status / cancel |
| GET | `/notifications` | ✓ | Alert history |
| GET | `/notifications/unread-count` | ✓ | Unread badge count |
| POST | `/notifications/{id}/read` | ✓ | Mark read |
| POST | `/notifications/read-all` | ✓ | Mark all read |

### Business owner

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/businesses` | ✓ | Register a business |
| PATCH | `/businesses/{slug}` | ✓ | Update settings |
| POST | `/businesses/{slug}/queues` | ✓ | Create a queue |
| PATCH | `/businesses/{slug}/queues/{id}` | ✓ | Update a queue |
| GET | `/businesses/{slug}/queues/{id}/board` | ✓ | Live counter board |
| POST | `/businesses/{slug}/queues/{id}/call-next` | ✓ | Call the next person |
| POST | `…/tickets/{id}/complete` | ✓ | Mark as served |
| GET | `/businesses/{slug}/dashboard` | ✓ | 30-day analytics |
| GET | `/businesses/{slug}/queues/{id}/qr` | — | QR code as SVG |
| GET | `/businesses/{slug}/queues/{id}/qr.json` | — | QR code + deep link as JSON |

### Response shape

Every list endpoint returns the same envelope, so the client never has to special-case one:

```json
{ "data": [ ... ], "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 3 } }
```

---

## How the queue works

**Position is derived, never stored.** Each ticket gets an immutable `sequence` at join time.
Position is the count of waiting tickets with a lower sequence. This matters: when someone is
called or drops out, everyone behind them moves up automatically, and no ticket ever has to be
renumbered.

**Ticket numbers come from an atomic counter.** `Queue::issueTicketNumber()` does the increment
and the read in a single `UPDATE`. A read-then-write (`SELECT max() + 1`) would hand out
duplicate codes when two customers tap Join at the same instant.

**ETA prefers observed history over configuration.** Once a queue has at least 5 completed
tickets, the estimate uses the median of the last 20 real service times; below that floor it
falls back to the configured average, so one slow appointment cannot dictate the whole queue's
estimate. See `app/Services/WaitTimeEstimator.php`.

**Turn alerts fire server-side.** When staff mark someone served, `TurnNotifier` checks whether the
next person is within 3 places and alerts them once, de-duplicated so repeated taps do not spam.

---

## Testing

```bash
php api/artisan test
```

40 tests / 114 assertions covering auth, the queue lifecycle, position and ETA behaviour, the
owner dashboard, appointments, and the API response contract.

---

## Deployment

**Frontend (Cloudflare Pages).** Project settings:

| Setting | Value |
|---|---|
| Root directory | `web` |
| Build command | `npm run build` |
| Build output directory | `dist` |
| Node version | `20.19+` (set `NODE_VERSION=20.19.0`, or rely on the `engines` field) |

`web/public/_redirects` carries the SPA fallback that makes `/tickets/12` and `/j/slug/1`
survive a hard refresh or a shared link; Cloudflare reads it from the build output. Do not
delete it.

The SPA and the API are on **different origins**, so these two must agree:

| Where | Variable | Value |
|---|---|---|
| Build time (static host) | `VITE_API_BASE_URL` | `https://api.example.com` — the API origin, no trailing slash |
| Runtime (API) | `TAKDA_FRONTEND_URL` | `https://takda.example.com` — the SPA origin |

`VITE_API_BASE_URL` is baked into the bundle at build time. Unset, the SPA falls back to same-origin
`/api`, which only works if you put the API behind the same host. `TAKDA_FRONTEND_URL` is what the
API's CORS config allows, so the two mismatched is the usual cause of a blank app with console
CORS errors. Set `VITE_API_PROXY` for local dev only.

**Backend.** Deploy `api/` to any PHP 8.3 host (Laravel Forge, Laravel Cloud, Railway, Render, or
a VPS). Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_KEY`, and the PostgreSQL
credentials. Run `php artisan migrate --force`.

**Cloudflare** fronts both: DNS, CDN, and WAF for the API origin. Do not proxy the API through a
Worker unless you also move it off PHP.

---

## Not built yet

Honest list of what is **not** in this repository yet:

- **Real-time transport.** The live ticket and counter screens poll every 5 seconds. Works, but
  WebSockets or SSE would cut latency and server load. Polling was chosen deliberately for v1.
- **Push/email/SMS delivery.** Alerts are stored in-app only. `TurnNotifier` is the single seam to
  add a transport behind.
- **Customer-facing business/queue management UI.** The API supports creating businesses and
  queues, but only the counter dashboard has a screen.
- **Appointment UI.** The booking API and tests exist; there is no booking screen.
- **PWA.** The app is mobile-first and installable-shaped, but has no manifest or service worker
  yet.
- **Owner queue-switching.** The dashboard loads the owner's first business and its first queue;
  a business with several queues needs a selector.
- **Authorization tests for every owner route.** Ownership is enforced and tested for the board and
  the analytics endpoints, but not exhaustively across all owner routes.
