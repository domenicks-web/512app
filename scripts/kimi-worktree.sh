#!/usr/bin/env bash
# Cria uma worktree pro Kimi trabalhar isolado do checkout principal.
# Uso: scripts/kimi-worktree.sh <branch-novo> [branch-base]
# node_modules vira symlink pro checkout principal (mesmo lock), o vendor é
# instalado de verdade (com symlink o Pest resolve o caminho errado), o .env
# é copiado, o Vite (hot ou build) vira symlink e as rotas do Wayfinder
# são geradas (são ignoradas no git).
set -euo pipefail

branch="${1:?informe o nome do branch}"
base="${2:-$(git rev-parse --abbrev-ref HEAD)}"
root="$(git rev-parse --show-toplevel)"
dir="$(dirname "$root")/512app-worktrees/$branch"

git -C "$root" worktree add -q "$dir" -b "$branch" "$base"
ln -s "$root/node_modules" "$dir/node_modules"
# Os testes de página precisam do Vite: o hot do dev server se estiver no ar,
# senão o build (que pode estar velho pra telas novas).
if [ -f "$root/public/hot" ]; then ln -s "$root/public/hot" "$dir/public/hot"; elif [ -d "$root/public/build" ]; then ln -s "$root/public/build" "$dir/public/build"; fi
cp "$root/.env" "$dir/.env"
(cd "$dir" && composer install --no-interaction --quiet 2>/dev/null && php artisan wayfinder:generate --quiet)

echo "$dir"
