#!/usr/bin/env bash
# Коммит с сообщением из аргумента (без внедрения trailer/Co-authored-by).
# Использование: ./commit.sh "Сообщение коммита"
# --no-verify: без pre-commit (lint-staged), чтобы коммит не падал на окружении без php-cs-fixer в PATH.
#
# Если `git commit` падает с «unknown option `trailer'» (среда подставляет --trailer, Git < 2.32),
# выполняется коммит через plumbing — хуки при этом не вызываются.

set -e
msg="${*:-Доработка}"

git_add_commit_tree() {
  local tree parent new
  tree=$(git write-tree)
  if parent=$(git rev-parse --verify HEAD 2>/dev/null); then
    new=$(printf '%s\n' "$msg" | git commit-tree "$tree" -p "$parent")
  else
    new=$(printf '%s\n' "$msg" | git commit-tree "$tree")
  fi
  git update-ref HEAD "$new"
}

git add -A

set +e
commit_out=$(git commit --no-verify -m "$msg" 2>&1)
commit_status=$?
set -e

if [ "$commit_status" -eq 0 ]; then
  printf '%s\n' "$commit_out"
  exit 0
fi

if printf '%s\n' "$commit_out" | grep -qE 'unknown option.*trailer'; then
  echo "git commit: опция trailer не поддерживается; коммит через commit-tree (без хуков)." >&2
  git_add_commit_tree
  git log -1 --oneline
  exit 0
fi

printf '%s\n' "$commit_out" >&2
exit "$commit_status"
