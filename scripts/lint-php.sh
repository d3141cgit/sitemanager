#!/bin/bash
#==============================================================================
# lint-php.sh — 패키지 PHP 파일을 지원 최저 버전으로 문법 검사한다
#
#   scripts/lint-php.sh          composer.json 의 최저 PHP (require.php ^X.Y) 로 검사
#   scripts/lint-php.sh 8.3      지정 버전으로 검사
#
# 로컬(최신 PHP)에서는 통과해도, 가장 낮은 운영 서버에서만 깨지는 문법(예: 타입 있는 클래스 상수는
# 8.3+)을 막는다. 공식 php:<ver>-cli 이미지를 쓰므로 Docker 가 필요하다.
#==============================================================================

set -u
PKG_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VER="${1:-$(grep -m1 '"php"' "$PKG_DIR/composer.json" | grep -oE '[0-9]+\.[0-9]+' | head -1)}"

docker info >/dev/null 2>&1 || { echo "Docker 가 꺼져 있다" >&2; exit 1; }

echo "PHP $VER 로 문법 검사: $PKG_DIR"
out="$(docker run --rm -v "$PKG_DIR":/app:ro -w /app "php:${VER}-cli" sh -c \
    'find src config database routes resources -name "*.php" ! -name "*.blade.php" -print0 \
     | xargs -0 -n 50 -P 4 php -l 2>&1 | grep -v "^No syntax errors"; php -v | head -1')"
echo "$out" | tail -1
errs="$(echo "$out" | sed '$d' | grep -v '^\s*$')"
if [ -n "$errs" ]; then
    echo "$errs"
    exit 1
fi
echo "문제 없음"
