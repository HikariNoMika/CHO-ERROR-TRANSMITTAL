# Docker deployment for MCA Patient Docs

## Quick start (local test)

```bash
docker compose up -d --build
# app available at http://localhost:8080
docker compose logs -f app
```

## Deploy to a VPS

1. Install Docker + Compose on the VPS:
   ```bash
   curl -fsSL https://get.docker.com | sh
   sudo usermod -aG docker $USER
   ```

2. Copy the project to the VPS (excluding node_modules/vendor):
   ```bash
   rsync -avz --exclude=node_modules --exclude=vendor --exclude=.git ./ user@vps:/opt/mca/
   ```

3. On the VPS:
   ```bash
   cd /opt/mca
   cp .env.example .env   # edit APP_URL, APP_KEY, DB_*, etc.
   docker compose up -d --build
   ```

4. (Recommended) Put Caddy or nginx in front for HTTPS:
   ```caddyfile
   mca.yourdomain.com {
       reverse_proxy localhost:8080
   }
   ```

## Switching to MySQL

Edit `docker-compose.yml` environment:
```
DB_CONNECTION: mysql
DB_HOST: db
DB_DATABASE: mca
DB_USERNAME: mca
DB_PASSWORD: <strong-password>
```
and add a `db` service (mysql:8) with a volume. The image already ships pdo_mysql.

## Persistence

- `mca_storage` volume: uploaded images, generated Excel files, templates
- `mca_database` volume: the SQLite database file

Back up both volumes regularly:
```bash
docker run --rm -v mca_database:/data -v $(pwd):/backup alpine \
  tar czf /backup/mca-db-$(date +%F).tar.gz -C /data .
```

## Updating

```bash
git pull
docker compose up -d --build
```
Migrations run automatically on container start.