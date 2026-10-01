# Miltydraft generator

[Visit the app here](https://milty.shenanigans.be/).

An expanded version of miltydraft.com, with saving/sharing drafts across sessions.

## Requirements: 
* make sure you have [docker](https://docs.docker.com/get-started/) installed

## Getting started

To install a local copy of this app you can clone it from the Git Repo: 

`git@github.com:shenanigans-be/miltydraft.git`

Then follow these steps:

1. Add `127.0.0.1 milty.localhost` to your `/etc/hosts` file. This first step is optional though. You can use
   127.0.0.1 directly as well.
2. Run `docker compose up -d --build`. This will first build the image, then start all services.
3. Run `docker compose exec app composer install`. This will install all php dependencies.
4. Create a `.env` file. See `.env.example` for details.
5. Go to [https://milty.localhost](https://milty.localhost) in your browser (or http://localhost if you don't want to go through the hassle of the following steps) 
6. Your browser might give you some scary warnings about untrusted certificates. That's because we're using a self-signed certificate.
If you want to, you can add the certificate to your device's truster certificate.
7. Run `docker compose exec app cat /home/app/.local/share/caddy/pki/authorities/local/root.crt > caddy-cert.crt` to make a copy of the certificate in `caddy-cert.crt` (which you can then import wherever you need it)

### Quick local setup (no image build)

`bin/dev` runs everything in a stock `php:8.2-fpm-alpine` container using PHP's built-in server, so there's no Caddy image to build and no local PHP needed:

1. `cp .env.example .env` and set `URL="http://localhost:8766/"`
2. `bin/dev install`
3. `bin/dev serve` and open http://localhost:8766 (`bin/dev stop` to stop)
4. `bin/dev check` runs everything CI runs (code style, phpstan, `core` + `data` test suites). `bin/dev test --filter Something` runs phpunit directly.

### Hosting it (Hetzner + Cloudflare Tunnel)

`deploy/docker-compose.prod.yml` runs the app plus a `cloudflared` container, so Cloudflare handles HTTPS and the server needs no open web ports.

1. **Cloudflare**: in Zero Trust → Networks → Tunnels, create a tunnel (connector type *Cloudflared*), copy its token, and add a public hostname (e.g. `milty.example.com`) with service `HTTP` → `app:80`.
2. **Hetzner**: create a small Ubuntu server (CX22 is plenty) with your SSH key. In its firewall, allow inbound SSH (22) only.
3. On the server:
   ```
   curl -fsSL https://get.docker.com | sh
   git clone https://github.com/drillbitdev/miltydraftplus.git && cd miltydraftplus
   cp .env.example .env                       # set URL="https://milty.example.com/", STORAGE="local", STORAGE_PATH="data/drafts", DEBUG=false
   cp deploy/tunnel.env.example deploy/tunnel.env   # paste TUNNEL_TOKEN
   docker compose -f deploy/docker-compose.prod.yml up -d --build
   ```
4. To update: `git pull && docker compose -f deploy/docker-compose.prod.yml up -d --build`.

Drafts live in the `drafts` Docker volume and survive rebuilds. Back them up with
`docker run --rm -v milty_drafts:/d -v "$PWD":/b alpine tar czf /b/drafts.tgz -C /d .`

### Libraries and Dependencies

Frontend runs on vanilla JS/jQuery (I'm aware jQuery is a bit of a blast from the past at this point; sue me and/or change it and PR me if you want) and the Back-end is vanilla PHP.
As such there's no build-system, or compiling required except for the steps described above.

To make this app as lean and mean (and easy to understand for anyone) as possible, external dependencies, both in the front- and backend should be kept to an absolute minimum.

### Understanding the App flow

1. Players come in on index.php and choose their options. 
2. A JSON config file is created (either locally or remotely, depending on .env settings) with a unique ID
3. That Draft ID is also the Draft URL: APP_URL/d/{draft-id} (URL rewriting is done via Caddy locally)
4. Players (or the Admin) make draft choices, which updates the draft json file (with very loose security, since we're assuming a very low amount of bad actors)
