#!/bin/bash
#==============================================================================
# deploy-sites.sh — SiteManager 패키지를 사용하는 사이트 서버에 새 버전을 반영한다
#
#   scripts/deploy-sites.sh status [site...]              서버별 설치 커밋·대기 마이그레이션 (읽기 전용)
#   scripts/deploy-sites.sh update [site...] [옵션]       서버 패키지 갱신 + 캐시 정리
#
#   site : gio edmuhak edmedu b2k hanuri d3141c  (생략 또는 all = 전부)
#   옵션 : --migrate   갱신 후 php artisan migrate --force (gio 는 deploy.sh 의 migrate)
#          --dry-run   원격에서 실행할 스크립트만 출력
#          -y          사이트별 확인 프롬프트 생략
#
# 사이트별 반영 방식 (260929 서버 점검 기준)
#   gio      로컬 lock 갱신 → 커밋 → gio deploy/deploy.sh --push (서버 composer update 금지:
#            원격 lock 이 더러워져 다음 git pull --ff-only 가 거부된다)
#   edmuhak  로컬 lock 갱신 → 커밋·push → edm 경유 54.116.29.188:/home/www.edmuhak.com 에서
#            git pull --ff-only + composer install (lock 을 git 으로 관리 — 서버 update 로 lock 과
#            vendor 가 어긋났던 문제를 260929 에 정리)
#   edmedu   edmuhak 과 같은 lock 방식 → edm 경유 edmkorean-aws:/home/www.edmedu.com (260929 부터 lock 추적)
#   b2k      ssh b2k        → /home/admin/bridge2korea          서버에서 composer update
#   hanuri   ssh hanuri-aws → /home/ubuntu/www                  서버에서 composer update → post-deploy.sh
#            (소스 배포는 별도: ssh server 후 ~/www/hanurichurch/cmd/deploy.sh — vendor 는 rsync 제외)
#   d3141c   ssh server     → ~/www/d3141c.ddns.net/sitemanager 서버에서 composer update
#
# PHP 버전: 각 사이트 composer.json 의 config.platform.php 를 운영 서버 PHP 에 맞춰 둔다(로컬 최신 PHP 로
# lock 을 만들어도 서버에서 돈다). 서버 PHP 를 올리면 platform 도 올린다. 반영 전 점검:
#   lock 방식   서버에서 받을 lock 을 check-platform-reqs --lock 으로 실제 PHP·확장과 대조, 안 맞으면 pull 전 중단
#   update 방식 platform.php 가 서버 PHP 보다 높으면 중단 (서버에 없는 PHP 용 패키지를 고르게 된다)
#
#   edmkorean 은 2026-06-29(b0e5b6c) 에 sitemanager 의존성을 제거했다. TOEFL 은 서비스 종료. 둘 다 대상 아님.
#
# 절차·점검 항목은 docs/UPGRADE_CHECKLIST.md 를 따른다. 이 스크립트는 §1-7(서버 반영)만 대신한다.
#==============================================================================

set -u

PKG="d3141cgit/sitemanager"
PKG_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ALL_SITES="gio edmuhak edmedu b2k hanuri d3141c"

GIO_DIR="${GIO_DIR:-$HOME/www/gio/gio}"
EDMUHAK_DIR="${EDMUHAK_DIR:-$HOME/www/edmuhak.com/edmuhak}"
EDMEDU_DIR="${EDMEDU_DIR:-$HOME/www/edmedu.com/edmedu}"

# edm 경유 접속 (pem 은 edm 서버에 있다)
EDMUHAK_SSH='ssh edm ssh -o BatchMode=yes -p 63322 -i ~/.ssh/edmuhak-aws.pem ubuntu@54.116.29.188'
EDMEDU_SSH='ssh edm ssh -o BatchMode=yes -p 63322 -i ~/.ssh/edmkorean.pem ubuntu@edmkorean-aws'

#------------------------------------------------------------------------------
# 사이트 설정: SSH(원격 셸 접두어), DIR(사이트 루트), POST(캐시 정리 대신 실행할 원격 스크립트),
#   LOCAL(설정 시 lock 을 로컬에서 갱신·커밋·push 하고 서버는 git pull + composer install)
#------------------------------------------------------------------------------
site_conf() {
    SSH=""; DIR=""; POST=""; LOCAL=""
    case "$1" in
        gio)     SSH="ssh gio-stg";   DIR="/srv/www/www.globalieltsonline.com"; LOCAL="$GIO_DIR" ;;
        edmuhak) SSH="$EDMUHAK_SSH";  DIR="/home/www.edmuhak.com"; LOCAL="$EDMUHAK_DIR" ;;
        edmedu)  SSH="$EDMEDU_SSH";   DIR="/home/www.edmedu.com"; LOCAL="$EDMEDU_DIR" ;;
        b2k)     SSH="ssh b2k";       DIR="/home/admin/bridge2korea" ;;
        hanuri)  SSH="ssh hanuri-aws"; DIR="/home/ubuntu/www"; POST="/home/ubuntu/cmd/post-deploy.sh" ;;
        d3141c)  SSH="ssh server";    DIR="/home/miles/www/d3141c.ddns.net/sitemanager" ;;
        *) echo "알 수 없는 사이트: $1 (가능: $ALL_SITES)" >&2; return 1 ;;
    esac
}

#------------------------------------------------------------------------------
# 원격 스크립트 — stdin 으로 넘겨 `bash -s -- <mode> <dir> <migrate> <post>` 로 실행
#------------------------------------------------------------------------------
remote_script() {
    cat <<'REMOTE'
set -u
MODE="$1"; DIR="$2"; MIGRATE="$3"; POST="${4:-}"
PKG="d3141cgit/sitemanager"
cd "$DIR" || { echo "[ERROR] 디렉토리 없음: $DIR"; exit 1; }

# storage 소유자가 접속 계정과 다르면(www-data) 그 계정으로 artisan 을 돌린다.
# 캐시 파일을 접속 계정 소유로 만들면 웹서버가 못 지워 500 이 난다.
OWNER="$(stat -c %U storage/framework/cache 2>/dev/null || whoami)"
# sudo 가 비밀번호를 요구하는 서버(d3141c)는 접속 계정으로 돌린다 — 그 계정이 소유 그룹이고
# 캐시 폴더가 그룹 쓰기(2775)면 그대로 된다 (260930 d3141c 에서 optimize:clear 가 sudo 로 실패).
if [ "$OWNER" != "$(whoami)" ] && sudo -n -u "$OWNER" true 2>/dev/null; then
    ART="sudo -u $OWNER php artisan"
else
    ART="php artisan"
    [ "$OWNER" != "$(whoami)" ] && echo "      [참고] sudo 불가 — $(whoami) 로 artisan 실행 (그룹 쓰기 권한 사용)"
fi

installed() { composer show "$PKG" 2>/dev/null | awk '/^source .*\[git\]/{print substr($NF,1,7)}'; }
pending()   { $ART migrate:status 2>/dev/null | grep -i pending | sed 's/^ */    /'; }

BEFORE="$(installed)"
PHPV="$(php -r 'echo PHP_VERSION;')"
PLATFORM="$(php -r '$j=json_decode(file_get_contents("composer.json"),true); echo $j["config"]["platform"]["php"] ?? "";')"

if [ "$MODE" = "status" ]; then
    echo "  설치: ${BEFORE:-없음}   lock 변경: $(git status -s composer.lock 2>/dev/null | cut -c1-2 | tr -d ' ' || true)   PHP $PHPV / platform ${PLATFORM:-없음}"
    # 패치 차이(8.3.6 vs 8.3.7)는 apt 보안 업데이트마다 생기므로 무시하고, 마이너가 다를 때만 알린다
    [ -n "$PLATFORM" ] && [ "${PLATFORM%.*}" != "${PHPV%.*}" ] && echo "  [주의] platform.php($PLATFORM) 와 서버 PHP($PHPV) 의 마이너 버전이 다르다 — 서버를 올렸으면 platform 도 올린다"
    P="$(pending)"; [ -n "$P" ] && { echo "  대기 마이그레이션:"; echo "$P"; }
    exit 0
fi

if [ "$MODE" = "install" ]; then
    # lock 은 git 으로 내려온다. 서버에서 lock 을 만들지 않는다.
    # 받기 전에 새 lock 을 이 서버의 실제 PHP·확장과 대조한다 — 안 맞으면 pull 하지 않고 멈춘다.
    git fetch -q origin || { echo "[ERROR] git fetch 실패"; exit 1; }
    UP="$(git rev-parse --abbrev-ref '@{u}')"
    TMP="$(mktemp -d)"
    git show "$UP:composer.json" > "$TMP/composer.json" && git show "$UP:composer.lock" > "$TMP/composer.lock" \
        || { rm -rf "$TMP"; echo "[ERROR] $UP 에 composer.json/lock 이 없다"; exit 1; }
    # 점검은 lock 만 본다. VCS 저장소(github) 초기화로 원격 접속이 일어나지 않게 repositories 를 뺀다.
    php -r '$f=$argv[1]; $j=json_decode(file_get_contents($f),true); unset($j["repositories"]); file_put_contents($f, json_encode($j));' "$TMP/composer.json"
    if ! REQ="$(cd "$TMP" && composer check-platform-reqs --lock --no-interaction --no-ansi 2>&1)"; then
        rm -rf "$TMP"
        echo "[ERROR] 새 lock 이 서버 PHP $PHPV 와 맞지 않는다 — pull 하지 않음"
        echo "$REQ" | grep -iE 'failed|missing' | head -10
        exit 1
    fi
    rm -rf "$TMP"
    echo "[0/3] 플랫폼 점검 통과 (PHP $PHPV)"
    echo "[1/3] git pull --ff-only + composer install  (현재 $BEFORE)"
    git pull --ff-only -q || { echo "[ERROR] git pull 실패 — 서버 작업 트리를 확인"; exit 1; }
    git log -1 --format='      HEAD %h %s'
    CMD="install"
else
    # 서버가 스스로 resolve 한다. platform.php 가 서버 PHP 보다 높으면 서버에서 못 도는 패키지를 고른다.
    if [ -n "$PLATFORM" ] && [ "$(printf '%s\n%s\n' "$PLATFORM" "$PHPV" | sort -V | tail -1)" != "$PHPV" ]; then
        echo "[ERROR] composer.json platform.php($PLATFORM) 가 서버 PHP($PHPV) 보다 높다 — 중단"
        exit 1
    fi
    echo "[0/3] 플랫폼 점검 통과 (PHP $PHPV, platform ${PLATFORM:-없음})"
    echo "[1/3] composer update $PKG  (현재 $BEFORE)"
    CMD="update $PKG"
fi
composer $CMD --no-interaction --no-ansi 2>&1 | sed 's/\x1b\[[0-9;]*m//g' \
    | grep -vE '^\s*$|\.{5}|funding|composer fund|vendor:publish|No publishable' | tail -15
[ "${PIPESTATUS[0]}" -eq 0 ] || { echo "[ERROR] composer $CMD 실패"; exit 1; }
AFTER="$(installed)"
echo "      $BEFORE → $AFTER"

P="$(pending)"
if [ -n "$P" ]; then
    if [ "$MIGRATE" = "1" ]; then
        # 게시판 동적 테이블을 고치는 마이그레이션이 있어 실행 전 DB 를 떠 둔다 (체크리스트 §3-1)
        eval "$(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' .env)"
        BK="$HOME/sql-backup/${DB_DATABASE}-$(date +%Y%m%d-%H%M%S)-pre-migrate.sql.gz"
        mkdir -p "$HOME/sql-backup"
        echo "[2/3] DB 백업 → $BK"
        MYSQL_PWD="$DB_PASSWORD" mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" \
            --single-transaction --no-tablespaces --routines --triggers --events "$DB_DATABASE" | gzip > "$BK"
        [ "${PIPESTATUS[0]}" -eq 0 ] && zcat "$BK" | tail -1 | grep -q "Dump completed" \
            || { echo "[ERROR] 백업 실패 — migrate 하지 않음"; exit 1; }
        echo "      $(du -h "$BK" | cut -f1), 테이블 $(zcat "$BK" | grep -c "^CREATE TABLE")개"
        echo "      migrate"; $ART migrate --force || { echo "[ERROR] migrate 실패 (백업: $BK)"; exit 1; }
    else
        echo "[2/3] 대기 마이그레이션이 있다 (--migrate 로 실행):"; echo "$P"
    fi
else
    echo "[2/3] 대기 마이그레이션 없음"
fi

if [ -n "$POST" ]; then
    echo "[3/3] $POST"; "$POST" || exit 1
else
    echo "[3/3] optimize:clear"; $ART optimize:clear 2>&1 | tail -3 || exit 1
fi

# optimize:clear 는 bootstrap/cache 의 packages.php·services.php 도 지운다. 이 디렉토리를 웹서버가
# 쓸 수 없는 서버(edmedu: ubuntu 775)에서는 다음 요청이 manifest 를 못 만들어 전 페이지 500 이 난다
# (260929 edmedu 약 2분 장애). 디렉토리 소유자 계정으로 바로 다시 만든다.
BOWNER="$(stat -c %U bootstrap/cache)"
if [ "$BOWNER" != "$(whoami)" ]; then BART="sudo -u $BOWNER php artisan"; else BART="php artisan"; fi
$BART package:discover --no-ansi >/dev/null && $BART about --only=environment >/dev/null 2>&1
[ -f bootstrap/cache/packages.php ] && [ -f bootstrap/cache/services.php ] \
    || { echo "[ERROR] bootstrap/cache manifest 재생성 실패 — 사이트가 500 일 수 있다"; exit 1; }

if git ls-files --error-unmatch composer.lock >/dev/null 2>&1 && ! git diff --quiet composer.lock; then
    echo "[참고] 서버 composer.lock 이 git 기준과 달라졌다. 이 파일을 바꾸는 커밋을 pull 하면 충돌한다."
fi
URL="$(grep -m1 '^APP_URL=' .env | cut -d= -f2- | tr -d "\"'\r")"
if [ -n "$URL" ]; then
    code="$(curl -s -o /dev/null -m 30 -w '%{http_code}' "$URL/")"
    echo "      $URL/ → $code"
    [ "$code" -lt 500 ] 2>/dev/null || { echo "[ERROR] 홈이 $code — storage/logs 확인"; exit 1; }
fi
echo "[OK] $(hostname):$DIR"
REMOTE
}

run_remote() {  # site mode migrate
    site_conf "$1" || return 1
    if [ "$DRY" = "1" ]; then
        echo "  \$ $SSH bash -s -- $2 $DIR $3 ${POST:-''}"
        return 0
    fi
    remote_script | $SSH bash -s -- "$2" "$DIR" "$3" "$POST" 2>&1 | grep -v 'setlocale'
    return "${PIPESTATUS[1]}"
}

#------------------------------------------------------------------------------
# 로컬 lock 갱신 → 커밋 (gio·edmuhak). 서버는 커밋된 lock 으로 composer install 한다.
#------------------------------------------------------------------------------
local_lock_update() {  # dir
    local dir="$1" ch
    if [ "$DRY" = "1" ]; then
        echo "  \$ cd $dir && composer update $PKG --no-install && git commit composer.lock && git push"
        return 0
    fi
    ( cd "$dir" || exit 1
      git diff --quiet composer.lock || { echo "[ERROR] $dir composer.lock 에 커밋 안 된 변경이 있다"; exit 1; }
      # Composer 2.9 는 lock 에 보안 권고가 걸린 패키지가 있으면 sitemanager 만 갱신해도 resolve 를
      # 거부한다. 다른 패키지는 건드리지 않으므로 이번 실행에서만 차단을 끈다(composer.json 은 그대로).
      ch="$(mktemp -d)"
      [ -f "$(composer config --global home 2>/dev/null)/auth.json" ] && cp "$(composer config --global home)/auth.json" "$ch/"
      echo '{"config":{"audit":{"block-insecure":false}}}' > "$ch/config.json"
      COMPOSER_HOME="$ch" COMPOSER_CACHE_DIR="$(composer config --global cache-dir 2>/dev/null)" \
          composer update "$PKG" --no-install --no-interaction --no-ansi 2>&1 | grep -E 'Upgrading|Downgrading|Installing|Removing|Problem|Nothing|Lock file' | head -10
      rc="${PIPESTATUS[0]}"; rm -rf "$ch"
      [ "$rc" -eq 0 ] || { echo "[ERROR] composer update 실패"; exit 1; }
      if git diff --quiet composer.lock; then
          echo "  lock 변경 없음 — 이미 최신"
      else
          # sitemanager 외 패키지가 딸려 오면 멈춘다 (체크리스트 §1-2)
          others="$(git diff composer.lock | grep -E '^[-+] +"(name|version)":' | grep -v "$PKG" || true)"
          [ -n "$others" ] && { echo "[ERROR] 다른 패키지도 바뀌었다 — 되돌리고 확인:"; echo "$others"; git checkout composer.lock; exit 1; }
          ref="$(grep -A6 "\"name\": \"$PKG\"" composer.lock | awk -F'"' '/reference/{print substr($4,1,7); exit}')"
          git commit -q -m "sitemanager 패키지 갱신 ($ref)" composer.lock || exit 1
          echo "  lock 커밋: $ref"
      fi )
}

push_local() {  # dir — 올라갈 커밋을 보여 주고 push
    [ "$DRY" = "1" ] && return 0
    ( cd "$1" || exit 1
      git fetch -q origin
      echo "  push 할 커밋:"; git log --oneline '@{u}..HEAD' | sed 's/^/    /'
      git push -q origin HEAD || { echo "[ERROR] push 실패 (원격이 앞서 있으면 pull 후 다시)"; exit 1; } )
}

update_gio() {
    local ops="pull,composer,clear"
    [ "$MIGRATE" = "1" ] && ops="$ops,migrate"
    local_lock_update "$GIO_DIR" || return 1
    if [ "$DRY" = "1" ]; then echo "  \$ deploy/deploy.sh --push -t both -o $ops"; return 0; fi
    ( cd "$GIO_DIR" && deploy/deploy.sh --push -t both -o "$ops" )
}

#------------------------------------------------------------------------------
# 인자
#------------------------------------------------------------------------------
CMD="${1:-}"; shift || true
SITES=""; MIGRATE=0; DRY=0; YES=0
for a in "$@"; do
    case "$a" in
        --migrate) MIGRATE=1 ;;
        --dry-run) DRY=1 ;;
        -y|--yes)  YES=1 ;;
        all)       SITES="$ALL_SITES" ;;
        -*)        echo "알 수 없는 옵션: $a" >&2; exit 1 ;;
        *)         site_conf "$a" || exit 1; SITES="$SITES $a" ;;
    esac
done
[ -z "$SITES" ] && SITES="$ALL_SITES"

case "$CMD" in
    status|update) ;;
    *) sed -n '3,12p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac

MAIN_REF="$(git -C "$PKG_DIR" rev-parse --short origin/main 2>/dev/null)"

if [ "$CMD" = "update" ]; then
    # 서버는 GitHub main 을 받는다. 로컬에만 있는 커밋이 있으면 반영되지 않으므로 먼저 확인.
    git -C "$PKG_DIR" fetch -q origin main || { echo "[ERROR] sitemanager fetch 실패" >&2; exit 1; }
    MAIN_REF="$(git -C "$PKG_DIR" rev-parse --short origin/main)"
    ahead="$(git -C "$PKG_DIR" rev-list --count origin/main..HEAD)"
    if [ "$ahead" != "0" ]; then
        echo "[ERROR] sitemanager 에 push 안 된 커밋 ${ahead}개 — 먼저 push 한다." >&2
        exit 1
    fi
fi

echo "sitemanager origin/main = $MAIN_REF"
FAILED=""
for s in $SITES; do
    echo ""
    echo "=== $s"
    if [ "$CMD" = "status" ]; then
        run_remote "$s" status 0 || FAILED="$FAILED $s"
        continue
    fi
    if [ "$YES" != "1" ] && [ "$DRY" != "1" ]; then
        printf "  %s 에 반영할까? [y/N] " "$s"; read -r ans
        [ "$ans" = "y" ] || [ "$ans" = "Y" ] || { echo "  건너뜀"; continue; }
    fi
    site_conf "$s"
    if [ "$s" = "gio" ]; then
        update_gio || FAILED="$FAILED $s"
    elif [ -n "$LOCAL" ]; then
        { local_lock_update "$LOCAL" && push_local "$LOCAL" && run_remote "$s" install "$MIGRATE"; } || FAILED="$FAILED $s"
    else
        run_remote "$s" update "$MIGRATE" || FAILED="$FAILED $s"
    fi
done

echo ""
if [ -n "$FAILED" ]; then echo "실패:$FAILED"; exit 1; fi
echo "완료."
