#!/usr/bin/env bash
# git-deploy-server.sh — Atualiza agro.codebehind.pt via git + limpeza de caches
#
# No servidor:  bash /var/www/vhosts/agro.codebehind.pt/httpdocs/git-deploy-server.sh --local
# Do Windows:   bash git-deploy-server.sh   (faz SSH para o servidor)
#
# Mesmo desenho do painel Ateneya: falha alto e cedo, nunca deixa a producao a
# meio, e diz o que fazer quando o servidor nao se consegue autenticar.

set -euo pipefail

SERVER="${SERVER:-agro.codebehind.pt}"
SSH_USER="${SSH_USER:-root}"
BRANCH="${BRANCH:-main}"

# Plesk: a app vive em /var/www/vhosts/<dominio>/httpdocs. Detecta o primeiro
# caminho que exista, para o script nao morrer no "cd" e abortar em silencio.
REMOTE_DIR="${REMOTE_DIR:-}"
if [ -z "$REMOTE_DIR" ]; then
  for candidate in \
    /var/www/vhosts/agro.codebehind.pt/httpdocs \
    /var/www/gestao-agricola \
    /var/www/html/gestao-agricola
  do
    if [ -f "$candidate/artisan" ]; then
      REMOTE_DIR="$candidate"
      break
    fi
  done
fi

if [ "${1:-}" != "--local" ]; then
  SSH_KEY="${SSH_KEY:-$HOME/.ssh/ateneya_vps_key}"
  [ ! -f "$SSH_KEY" ] && SSH_KEY="$HOME/.ssh/id_rsa"
  if [ ! -f "$SSH_KEY" ]; then
    echo "ERRO: nao encontrei chave SSH nenhuma em $HOME/.ssh" >&2
    exit 1
  fi
  # bash -s envia este mesmo ficheiro por stdin: nao depende do caminho remoto.
  exec ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$SSH_USER@$SERVER" \
    "bash -s -- --local" < "$0"
fi

# ---------- corre NO SERVIDOR ----------
if [ -z "$REMOTE_DIR" ]; then
  echo "ERRO: nao encontrei a app (nenhum candidato tem artisan)." >&2
  echo "Define REMOTE_DIR=/caminho/da/app antes de correr o script." >&2
  exit 1
fi

cd "$REMOTE_DIR"

echo "=========================================="
echo "  Gestao Agricola — Deploy  $(date)"
echo "=========================================="
echo "==> Pasta: $REMOTE_DIR"
echo "==> Antes: $(git log --oneline -1)"

# 1. Proteger ficheiros carregados pelos utilizadores (faturas, fotos).
if [ -d storage/app/public ]; then
  cp -a storage/app/public /tmp/agro-storage-public-safe
fi

# 2. Atualizar codigo. O repositorio e privado: se o servidor nao se conseguir
#    autenticar no GitHub, o git morre aqui e o set -e aborta tudo — sem esta
#    mensagem ficava so o "fatal: could not read Username".
if ! git fetch origin "$BRANCH"; then
  echo "" >&2
  echo "ERRO: o servidor nao se consegue autenticar no GitHub." >&2
  echo "      remote actual: $(git remote get-url origin)" >&2
  echo "      Producao ficou como estava: $(git log --oneline -1)" >&2
  echo "" >&2
  echo "Resolver com uma deploy key (nao expira), no servidor:" >&2
  echo "  ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N ''" >&2
  echo "  cat ~/.ssh/github_deploy.pub   # colar em GitHub > repo > Settings > Deploy keys (read-only)" >&2
  echo "  printf 'Host github.com\\n  IdentityFile ~/.ssh/github_deploy\\n  IdentitiesOnly yes\\n' >> ~/.ssh/config" >&2
  echo "  git remote set-url origin git@github.com:codebehindportugal-cmd/gestao-agricola.git" >&2
  exit 1
fi

# reset --hard e nao checkout: o servidor tem ficheiros que foram copiados a
# mao e o checkout aborta em vez de os substituir.
git reset --hard "origin/$BRANCH"

# 3. Restaurar uploads que o reset tenha removido.
if [ -d /tmp/agro-storage-public-safe ]; then
  mkdir -p storage/app/public
  cp -rn /tmp/agro-storage-public-safe/. storage/app/public/ 2>/dev/null || true
  rm -rf /tmp/agro-storage-public-safe
fi

# 4. Limpeza de ficheiros que nao fazem falta no servidor.
rm -rf _local .claude .agents 2>/dev/null || true
rm -f ./*.bat 2>/dev/null || true
find storage/logs -name "*.log" -mtime +30 -delete 2>/dev/null || true

# 5. Dependencias PHP.
composer install --no-dev --optimize-autoloader --quiet

# 6. Assets. public/build esta no .gitignore, por isso NAO vem no pull: se este
#    passo nao correr, o site fica a servir o build antigo e qualquer pagina
#    nova (o calendario, por exemplo) aparece em branco.
#    Atencao: o Vue e o Vite estao em devDependencies — "npm ci --omit=dev"
#    instala sem eles e o build rebenta. Tem mesmo de ser o npm ci completo.
if command -v npm >/dev/null 2>&1; then
  echo "==> npm ci ($(node -v))"
  npm ci --no-audit --no-fund
  echo "==> npm run build"
  # 4 GB de heap: o vite build morre com "JavaScript heap out of memory" em
  # VPS pequenas e o erro nao diz que foi falta de memoria.
  NODE_OPTIONS="--max-old-space-size=4096" npm run build
  test -f public/build/manifest.json || { echo "ERRO: build nao gerou manifest.json" >&2; exit 1; }
else
  echo "AVISO: npm nao existe no servidor; os assets NAO foram recompilados." >&2
  echo "       Instalar Node 20+ ou fazer o build localmente e enviar public/build." >&2
fi

# 7. Copia de seguranca da base de dados ANTES de migrar.
#    Uma migracao que corre mal sem isto nao tem volta.
if [ -f .env ] && grep -q '^DB_CONNECTION=mysql' .env && command -v mysqldump >/dev/null 2>&1; then
  ler_env() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"'"'"'' | tr -d '\r'; }
  DB_NAME="$(ler_env DB_DATABASE)"
  DB_USER="$(ler_env DB_USERNAME)"
  DB_PASS="$(ler_env DB_PASSWORD)"
  DB_HOST_V="$(ler_env DB_HOST)"
  mkdir -p storage/backups
  DUMP="storage/backups/${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql.gz"
  if MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --quick --no-tablespaces \
       -h "${DB_HOST_V:-127.0.0.1}" -u "$DB_USER" "$DB_NAME" | gzip > "$DUMP"; then
    echo "==> Backup da BD: $DUMP ($(du -h "$DUMP" | cut -f1))"
    # Guardar so os 10 mais recentes.
    ls -1t storage/backups/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
  else
    echo "ERRO: o mysqldump falhou; nao vou migrar as cegas." >&2
    rm -f "$DUMP"
    exit 1
  fi
else
  echo "AVISO: sem backup da BD (nao e mysql ou falta o mysqldump)." >&2
fi

# 8. Migracoes e caches.
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Permissoes.
# Em Plesk o PHP corre como o utilizador da subscricao (andre.mendes), NAO como
# www-data ou root. Ficheiros a root partem o deploy seguinte do painel Plesk,
# por isso herda-se o dono/grupo do proprio directorio da app.
APP_OWNER="$(stat -c '%U:%G' "$REMOTE_DIR")"
echo "==> Dono da app: $APP_OWNER"
chown -R "$APP_OWNER" . 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# 10. O agendador. Sem esta linha no cron, o aviso ntfy das 07:00 nunca dispara.
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
  echo "==> Agendador: cron encontrado."
else
  echo "==> AVISO: nao ha 'schedule:run' no crontab do $USER."
  echo "    Os avisos do calendario NAO vao ser enviados. Adicionar:"
  echo "    * * * * * cd $REMOTE_DIR && php artisan schedule:run >> /dev/null 2>&1"
fi

echo ""
echo "==> Agora: $(git log --oneline -1)"
df -h "$REMOTE_DIR" | tail -1
echo "Deploy concluido!"
