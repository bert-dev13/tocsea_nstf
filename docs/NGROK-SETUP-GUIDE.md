# TOCSEA – Expose Local Laravel to the Internet with Ngrok

This guide lets you share your local TOCSEA Laravel app with a client via a public HTTPS URL using **Ngrok**. It is written for Windows, Visual Studio Code, and assumes you already have Laravel and MySQL running locally.

---

## Table of contents

1. [Prerequisites](#1-prerequisites)
2. [Install Ngrok on Windows](#2-install-ngrok-on-windows)
3. [Get and Set Your Ngrok Authtoken](#3-get-and-set-your-ngrok-authtoken)
4. [Prepare Laravel for Ngrok](#4-prepare-laravel-for-ngrok)
5. [Build Frontend Assets (Important)](#5-build-frontend-assets-important)
6. [Start Laravel and Expose with Ngrok](#6-start-laravel-and-expose-with-ngrok)
7. [Update .env With Your Ngrok URL](#7-update-env-with-your-ngrok-url)
8. [Avoid 419 CSRF, Mixed Content, and Invalid Host](#8-avoid-419-csrf-mixed-content-and-invalid-host)
9. [Securing the Temporary URL](#9-securing-the-temporary-url)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Prerequisites

- TOCSEA runs locally (e.g. `php artisan serve` on port 8000).
- MySQL is running and `.env` is configured (e.g. `DB_DATABASE=tocsea_system`).
- Node.js and npm installed (for building assets).
- A free [Ngrok account](https://dashboard.ngrok.com/signup).

---

## 2. Install Ngrok on Windows

Choose one method.

### Option A: Winget (recommended)

Open **PowerShell** or **Windows Terminal** and run:

```powershell
winget install ngrok.ngrok
```

If asked, allow the installer to add ngrok to your PATH.

### Option B: Direct download

1. Go to [https://ngrok.com/downloads](https://ngrok.com/downloads).
2. Download **Windows (64-bit)**.
3. Unzip the file (e.g. to `C:\ngrok`).
4. Add that folder to your system PATH, or use the full path to `ngrok.exe` in the commands below.

### Verify installation

In a **new** PowerShell window:

```powershell
ngrok version
```

You should see a version number (e.g. `ngrok version 3.x.x`).

---

## 3. Get and Set Your Ngrok Authtoken

1. Log in at [https://dashboard.ngrok.com](https://dashboard.ngrok.com).
2. Open **Your Authtoken**: [https://dashboard.ngrok.com/get-started/your-authtoken](https://dashboard.ngrok.com/get-started/your-authtoken).
3. Copy your authtoken (long string).
4. In PowerShell run (replace with your real token):

```powershell
ngrok config add-authtoken YOUR_AUTHTOKEN_HERE
```

Example:

```powershell
ngrok config add-authtoken 2abc123XYZ_your_actual_token_from_dashboard
```

You should see: `Authtoken saved to configuration file`.

---

## 4. Prepare Laravel for Ngrok

The project is already set up to trust proxies in **local** so that Laravel sees the correct host and scheme (HTTPS) when traffic comes through Ngrok.

- **Done for you:** In `bootstrap/app.php`, when `APP_ENV=local`, the app calls `trustProxies(at: '*')` so forwarded headers from Ngrok are trusted.

You only need to:

- Use the correct **APP_URL** (your Ngrok URL) while sharing (see Step 7).
- Build assets and use the Laravel server (no Vite dev server) so there are no “Invalid Host” or mixed-content issues (see Step 5 and 6).

---

## 5. Build Frontend Assets (Important)

TOCSEA uses Vite. For the client-facing Ngrok URL, **do not** use `npm run dev`. Build assets so Laravel serves them over the same URL (no separate Vite server, no mixed content, no Vite host checks).

In the project root (e.g. `c:\projects\tocsea_system`), run:

```powershell
cd c:\projects\tocsea_system
npm run build
```

Wait until it finishes. After this, CSS and JS are served from your Laravel app.

---

## 6. Start Laravel and Expose with Ngrok

You need **two** terminals: one for Laravel, one for Ngrok.

### Terminal 1 – Laravel (PHP built-in server)

Bind to all interfaces so Ngrok can reach it:

```powershell
cd c:\projects\tocsea_system
php artisan serve --host=0.0.0.0 --port=8000
```

Leave this running. You should see something like: `Server running on [http://0.0.0.0:8000]`.

### Terminal 2 – Ngrok

Open a **second** PowerShell window:

```powershell
ngrok http 8000
```

You will see a screen with:

- **Forwarding** – e.g. `https://abc123.ngrok-free.app -> http://localhost:8000`
- That HTTPS URL is your **public URL** (it changes on free tier each time you restart ngrok).

Copy the **HTTPS** URL (e.g. `https://abc123.ngrok-free.app`). You will use it in the next step and send it to your client.

---

## 7. Update .env With Your Ngrok URL

**Important:** Laravel must know the public URL so login, CSRF, redirects, and asset links work over HTTPS.

1. Open `.env` in the project root.
2. Set these (replace with your actual Ngrok URL **without** trailing slash):

```env
APP_URL=https://YOUR-NGROK-SUBDOMAIN.ngrok-free.app
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
```

Example (if your URL is `https://a1b2c3.ngrok-free.app`):

```env
APP_URL=https://a1b2c3.ngrok-free.app
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
```

3. Save `.env`.
4. Clear config and cache:

```powershell
cd c:\projects\tocsea_system
php artisan config:clear
php artisan cache:clear
```

5. **Restart** the Laravel server (Terminal 1: stop with `Ctrl+C`, then run `php artisan serve --host=0.0.0.0 --port=8000` again).

You do **not** need Sanctum for typical web login; if you add API tokens later, you can add Sanctum’s `stateful` domains in `config/sanctum.php` if needed.

---

## 8. Avoid 419 CSRF, Mixed Content, and Invalid Host

| Issue | Cause | What we did |
|-------|--------|--------------|
| **419 CSRF** | APP_URL or session domain doesn’t match the request (e.g. still `http://localhost`) | Set `APP_URL` to your Ngrok HTTPS URL and clear config/cache. |
| **Mixed content** | Page is HTTPS but links/assets use HTTP | Set `APP_URL=https://...ngrok-free.app` so Laravel generates HTTPS URLs. |
| **Invalid Host Header** | Vite or server rejecting non-localhost host | Use `npm run build` and only `php artisan serve` (no `npm run dev`). |
| **Session / cookies** | Cookie not sent on HTTPS or wrong domain | `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true`. |

After changing `.env`, always run `php artisan config:clear` and `php artisan cache:clear`, then restart `php artisan serve`.

---

## 9. Securing the Temporary URL

- **Free Ngrok:** The URL is public. Anyone with the link can open it. Use it only for short-term client demos.
- **Optional – Ngrok auth:** To add a browser login before your app:
  - In [Ngrok Dashboard](https://dashboard.ngrok.com/) → **Cloud Edge** → **IP Restrictions** or **OAuth** you can restrict who can open the tunnel (see Ngrok docs).
- **Optional – Reserved domain:** Paid plans allow a fixed subdomain so you don’t have to change `APP_URL` every time.
- **After the demo:** Stop Ngrok (Ctrl+C in the Ngrok terminal). Restore local `.env` if you want:

```env
APP_URL=http://localhost:8000
SESSION_SECURE_COOKIE=false
```

Then run `php artisan config:clear` and `php artisan cache:clear` again for local work.

---

## 10. Troubleshooting

### “ngrok” is not recognized

- If you used the zip: add the folder containing `ngrok.exe` to your system PATH, or use the full path, e.g. `C:\ngrok\ngrok.exe http 8000`.
- If you used Winget: close and reopen PowerShell/terminal, then try again.

### 502 Bad Gateway / Connection refused

- Laravel must be running: `php artisan serve --host=0.0.0.0 --port=8000`.
- Ngrok must point to port 8000: `ngrok http 8000`.
- Firewall: allow PHP and ngrok if Windows Firewall prompts.

### 419 Page Expired (CSRF)

- Set `APP_URL` in `.env` to your **exact** Ngrok HTTPS URL (no trailing slash).
- Run `php artisan config:clear` and `php artisan cache:clear`.
- Restart `php artisan serve`.
- Hard-refresh the browser (Ctrl+F5) or try in an incognito window.

### CSS/JS not loading or mixed content warnings

- Run `npm run build` and use the built assets (do not use `npm run dev` for the client URL).
- Ensure `APP_URL` is `https://...ngrok-free.app` and config/cache are cleared.

### Blank or “Invalid Host Header” page

- Do not run `npm run dev` when using the Ngrok URL. Use `npm run build` and only `php artisan serve`.

### Session / login not persisting

- In `.env`: `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true`.
- Clear config and cache, restart Laravel.
- Try in a new incognito/private window in case of old cookies.

### Ngrok “Visit Site” interstitial

- On the free plan, Ngrok may show a short “Visit Site” page before your app. Click through it; your client can do the same. Paid plans can disable this.

---

## Quick reference – commands in order

```powershell
# 1. One-time: install and auth (use your token)
winget install ngrok.ngrok
ngrok config add-authtoken YOUR_AUTHTOKEN

# 2. In project root: build assets
cd c:\projects\tocsea_system
npm run build

# 3. Terminal 1: start Laravel
php artisan serve --host=0.0.0.0 --port=8000

# 4. Terminal 2: start tunnel
ngrok http 8000
# Copy the https://....ngrok-free.app URL

# 5. In .env set APP_URL to that URL, SESSION_SECURE_COOKIE=true, then:
php artisan config:clear
php artisan cache:clear
# Restart Laravel (Terminal 1)
```

After that, send the **HTTPS** Ngrok URL to your client for testing.
