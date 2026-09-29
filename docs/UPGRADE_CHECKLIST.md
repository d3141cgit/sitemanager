# SiteManager 패키지 갱신 체크리스트

> 이 패키지를 쓰는 사이트에 새 버전을 반영할 때 따르는 절차와 점검 항목이다. 서버 반영은 `scripts/deploy-sites.sh`(§1-1)로 한다.
> 작성 2026-09-29 (a66d08c 기준) · 버전별 주의사항은 §5에 추가해 나간다.

---

## 0. 먼저 알아둘 것 — 로컬은 이미 새 코드로 돌고 있을 수 있다

대부분 사이트의 로컬 `vendor/d3141cgit/sitemanager`는 `~/www/sitemanager` **심링크**다. 패키지를 push하는 순간 로컬 사이트는
이미 새 코드로 동작한다. 반면 **서버는 각 사이트 `composer.lock`에 박힌 커밋**으로 돈다.

- 로컬에서 이상이 보이면 "패키지 갱신 때문"일 수 있다. 사이트 작업 중 원인 모를 변화가 생기면 먼저 여기를 의심한다.
- 서버 반영은 사이트마다 lock을 갱신해 배포해야 일어난다. **lock을 갱신하지 않은 사이트는 서버에서 옛 버전 그대로다.**

| 사이트 | 로컬 경로 | 서버 (접속) | 서버 설치 (260929) | 서버 반영 방식 |
|---|---|---|---|---|
| GIO | `gio/gio` | gio-stg `/srv/www/www.globalieltsonline.com` | 5565cf8 | **로컬** lock 갱신 → 커밋 → `deploy/deploy.sh` |
| edmuhak | `edmuhak.com/edmuhak` | edm 경유 → 54.116.29.188 `/home/www.edmuhak.com` | 5565cf8 | **로컬** lock 갱신 → 커밋·push → 서버 `git pull` + `composer install` |
| edmedu | `edmedu.com/edmedu` | edm 경유 → edmkorean-aws `/home/www.edmedu.com` | 5565cf8 | 서버 `composer update` (lock gitignore) |
| bridge2korea | `bridge2korea.com` | `b2k` `/home/admin/bridge2korea` | 5565cf8 | 서버 `composer update` |
| 한우리교회 | `hanurichurch.org/www` | `hanuri-aws` `/home/ubuntu/www` | 5565cf8 | 서버 `composer update` → `~/cmd/post-deploy.sh` |
| d3141c 데모 | `d3141c.ddns.net/sitemanager` | `server` `~/www/d3141c.ddns.net/sitemanager` | 5565cf8 | 서버 `composer update` |

- **대상 아님**: edmkorean(2026-06-29 b0e5b6c 에서 sitemanager 의존성 제거), TOEFL(서비스 종료).
- EDM 프로젝트(gio·edmuhak·edmedu)는 **edm 서버를 경유**한다(pem 이 edm 에 있다). 개인 프로젝트(b2k·hanuri·d3141c)는 `~/.ssh/config` 별칭으로 바로 붙는다.
- 한우리교회 소스 배포는 별개다: `ssh server` → `~/www/hanurichurch/cmd/deploy.sh`(rsync, vendor 제외). 이 rsync 는 `composer.lock` 을 LAN 소스의 것으로 덮으므로 서버 lock 과 vendor 가 어긋날 수 있다 — 서버에서 `composer install` 을 돌리기 전에 확인한다.

> 서버 설치 값은 260929 반영 후 기준이다. `scripts/deploy-sites.sh status` 로 서버별 설치 커밋과 대기 마이그레이션을 다시 본다.

---

## 1. 갱신 절차 (사이트 하나당)

1. **차이 확인** — 무엇이 바뀌는지부터 본다. lock이 오래된 사이트일수록 이번 수정 말고도 많은 변경이 한꺼번에 들어간다.
   ```bash
   cd ~/www/sitemanager
   git log --oneline <사이트lock>..main
   git diff --stat <사이트lock> main -- database/migrations config resources/views src/Http/Controllers
   ```
2. **lock만 갱신** (심링크 유지): 사이트 루트에서
   `composer update d3141cgit/sitemanager --no-install`
   - edmedu처럼 composer로 설치된 사이트는 `--no-install` 없이 `composer update d3141cgit/sitemanager`.
   - `composer.lock` diff에 **sitemanager 항목만** 바뀌었는지 확인한다. 다른 패키지가 딸려 오면 되돌리고 원인을 본다.
3. **마이그레이션 확인** — §3-1. 새 마이그레이션이 있으면 로컬 DB에 먼저 돌려 본다(운영 DB에 직접 DDL 금지 원칙은 사이트 CLAUDE.md를 따른다).
4. **설정 병합 확인** — §3-2. 사이트 `config/sitemanager.php`는 패키지 기본값을 **통째로 덮는다**(최상위 키 단위로만 병합된다).
5. **뷰 오버라이드 확인** — §3-3.
6. **로컬 점검** — §4 체크리스트.
7. 설정 변경이 있으면 커밋·배포 → 서버 반영(§1-1) → 서버 점검(§4 중 핵심).

**하지 않는 것**: GIO 서버에서 `composer update` (원격 lock 이 더러워져 다음 `git pull --ff-only` 가 거부된다).
여러 사이트를 몰아서 반영할 때는 사이트 하나씩 반영·점검하고 다음으로 넘어간다 (문제가 생기면 원인 사이트를 가르기 쉽게).

### 1-1. 서버 반영 스크립트

```bash
cd ~/www/sitemanager
scripts/deploy-sites.sh status                 # 서버별 설치 커밋·대기 마이그레이션 (읽기 전용)
scripts/deploy-sites.sh update edmuhak         # 한 사이트 반영 (확인 프롬프트)
scripts/deploy-sites.sh update all --dry-run   # 원격 명령만 확인
scripts/deploy-sites.sh update b2k --migrate   # 패키지 마이그레이션까지 실행
```

- 실행 전 sitemanager 에 push 안 된 커밋이 있으면 멈춘다(서버는 GitHub main 을 받는다).
- 서버: `composer update d3141cgit/sitemanager` → 대기 마이그레이션 표시(`--migrate` 면 실행) → `optimize:clear`(한우리는 `post-deploy.sh`).
  storage 가 www-data 소유인 서버는 artisan 을 `sudo -u www-data` 로 돌린다.
- GIO·edmuhak: 로컬 `composer update --no-install` → sitemanager 외 패키지가 바뀌면 멈춤 → lock 커밋 → push.
  GIO 는 `deploy/deploy.sh --push -t both -o pull,composer,clear[,migrate]`, edmuhak 은 서버 `git pull --ff-only` + `composer install`.
  서버 `git pull` 은 그 사이 다른 사람이 main 에 올린 커밋도 함께 배포한다 — push 전에 스크립트가 보여 주는 커밋 목록을 본다.
- Composer 2.9 는 lock 에 보안 권고가 걸린 패키지가 있으면 sitemanager 만 갱신해도 resolve 를 거부한다. 스크립트는 로컬 갱신에서만 임시 COMPOSER_HOME 으로 `audit.block-insecure` 를 끈다(composer.json 불변).
- 캐시 정리 뒤 `bootstrap/cache` 소유자로 manifest 를 다시 만들고 홈 응답을 확인한다. 웹서버가 이 디렉토리를 못 쓰는 서버에서 manifest 가 지워진 채 남으면 전 페이지 500 이다(260929 edmedu 2분 장애).
- **마이그레이션을 빠뜨리지 않는다.** 새 코드가 새 컬럼을 바로 쓴다(예: 5월 이후 `boards.post_fields`, 댓글 `ip_address`·`meta`). `status` 에 Pending 이 남으면 게시판 저장·댓글 작성이 SQL 오류로 실패할 수 있다. `repair_board_dynamic_table_columns` 는 실행 전 DB 백업.

---

### 1-2. PHP 버전

| 사이트 | 운영 PHP (260929) | `config.platform.php` |
|---|---|---|
| gio | 8.5.4 | 8.5.0 |
| edmuhak·edmedu | 8.3.6 (EDM — 인프라 변경 안 함) | 8.3.6 |
| 한우리 | 8.5.4 (Ubuntu 26.04, 260929 전환) | 8.5.4 |
| bridge2korea | 8.5.11 (Debian 12 + sury, 260929 전환) | 8.5.11 |
| d3141c | 8.5.4 | 8.5.4 |

- **각 사이트 `composer.json` 의 `config.platform.php` = 운영 서버 PHP.** 로컬(최신 PHP)에서 lock 을 만들어도 서버에서 돈다.
  이게 없던 b2k 는 로컬 lock 에 PHP 8.4+ 전용 symfony v8 이 들어가 서버에서 쓸 수 없었다.
- 서버 PHP 를 올리면 **같은 날** platform 도 올리고 `composer update --lock` 후 커밋한다. `status` 가 마이너 차이를 [주의]로 알린다.
- 반영 스크립트가 서버 PHP 와 대조한다: lock 방식은 pull 전에 `check-platform-reqs --lock`, update 방식은 platform 이 서버보다 높으면 중단.
- 패키지는 `php: ^8.3`(가장 낮은 운영 서버 = EDM 8.3 기준). 패키지 코드를 고치면 `scripts/lint-php.sh` 로 최저 버전 문법 검사를 한다(Docker 필요).
  문법 검사가 못 잡는 8.3+/8.4+ 전용 함수(`json_validate`, `array_find`, `mb_trim` 등)는 쓰지 않는다.
- 방향: 개인 서버(b2k·hanuri·d3141c)는 최신 PHP·OS 를 따라간다. EDM 서버(gio·edmuhak·edmedu)는 여러 개발자가 쓰므로 인프라를 바꾸지 않는다.
  최저 운영 버전이 올라가면 패키지 `require.php` 와 lint 기준도 올린다.
- **Ubuntu 26.04 로 올릴 때** (260929 hanuri-aws 에서 겪음):
  - 릴리스 업그레이드 전: MySQL 계정에 `mysql_native_password` 가 있으면 업그레이더가 조용히 abort 한다(screen 에 `utmp slot not found` 만 보임).
    `select user,host,plugin from mysql.user` 로 확인하고 `ALTER USER ... IDENTIFIED WITH caching_sha2_password BY '<같은 비밀번호>'`.
  - apache2 유닛이 샌드박스(`ProtectHome=read-only`, `MemoryDenyWriteExecute=yes`)로 바뀐다. 사이트가 /home 아래면
    `/etc/systemd/system/apache2.service.d/site-writable.conf` 에 `ReadWritePaths=<site>/storage <site>/bootstrap/cache`,
    그리고 `/etc/php/8.5/apache2/conf.d/99-site.ini` 에 `pcre.jit=0`. 없으면 전 페이지 500 또는 간헐 500("A facade root has not been set" 으로 가려진다).
  - PHP 8.3 → 8.5 가 같이 오므로 `composer check-platform-reqs --lock` 으로 상한이 걸린 패키지(예: nette/schema 1.3.2)를 찾아 올린다.
- b2k(Debian): `packages.sury.org/php` 저장소. sury 의 `php-*` 메타 패키지가 기본값(8.4)을 끌고 와 8.4 도 설치돼 있다(phpmyadmin 의존).
  웹은 `a2enmod php8.5`, CLI 는 update-alternatives 최고 버전(8.5). composer 는 `/usr/local/bin/composer`(Debian 2.5.5 대신). 롤백: `a2dismod php8.5 && a2enmod php8.2`.

## 2. 롤백

- lock을 이전 커밋으로 되돌려 배포: `git checkout <이전커밋> -- composer.lock` → 서버 `composer install`.
- 새 마이그레이션이 이미 운영에 돌았다면 `down()`이 안전한지 먼저 본다. 컬럼 추가뿐이면 되돌리지 않고 코드만 롤백해도 대개 무해하다.

---

## 3. 점검 항목 상세

### 3-1. 마이그레이션

패키지 마이그레이션은 사이트 `php artisan migrate`에 함께 잡힌다. 957e430 이전 사이트에는 아래가 새로 들어간다:

| 파일 | 내용 | 확인 |
|---|---|---|
| `2025_12_16_000000_add_seo_meta_to_menus_table` | 메뉴 SEO 메타 | 한우리교회(0aad451)만 해당 |
| `2026_05_30_030000_add_post_fields_and_meta_to_boards` | 게시판 Post Fields | 게시판 편집 화면 |
| `2026_07_30_000000_repair_board_dynamic_table_columns` | 게시판별 동적 테이블 컬럼 보정 | **게시판 수가 많은 사이트는 실행 시간 확인**, 실행 전 DB 백업 |

`php artisan migrate --pretend`로 SQL을 먼저 본다.

### 3-2. 설정 (`config/sitemanager.php`)

사이트 설정이 있으면 **패키지 기본값 변경이 반영되지 않는다.** 버전 노트(§5)의 "설정" 항목을 사이트 파일에 직접 옮겨야 한다.

- `mergeConfigFrom`은 최상위 키만 합친다. 새 최상위 키(예: `admin_theme`)는 기본값이 적용되지만, `security` 같은 기존 키 **안에** 추가된 항목은 사이트 설정에 없으면 빠진다.
- 비교: `diff <(php -r 'print_r(require "vendor/d3141cgit/sitemanager/config/sitemanager.php");') <(php -r 'print_r(require "config/sitemanager.php");')`
  (env 호출이 있어 `php artisan tinker --execute='print_r(config("sitemanager.security"));'`로 보는 편이 정확하다)

### 3-3. 뷰 오버라이드 (`resources/views/vendor/sitemanager/`)

사이트가 패키지 뷰를 복사해 고쳐 쓰면 패키지 수정이 **그 뷰에는 들어가지 않는다.**
`ls resources/views/vendor/sitemanager -R`로 목록을 보고, 이번 갱신에서 바뀐 패키지 뷰와 겹치는지 대조한다:
`git -C ~/www/sitemanager diff --name-only <lock> main -- resources/views`

### 3-4. 보안 장치를 직접 호출하는 코드

사이트 코드가 `SecurityService`(`validateFormSecurity`, `validateEmailDomainBlocking`, `verifyCaptcha`)나
`sitemanager::security.form-security` 조각을 직접 쓰면, 검사 동작 변경이 **사이트의 문의·신청 폼에도 그대로** 적용된다.
찾기: `grep -rnE "validateFormSecurity|validateEmailDomainBlocking|SecurityService|form-security|anti-spam-fields" app resources/views`

---

## 4. 로컬 점검 체크리스트 (모든 사이트 공통)

- [ ] 사이트 홈, 주요 페이지 200 (`curl -sk -o /dev/null -w "%{http_code}" https://<site>.localhost/...`)
- [ ] 관리자 로그인 → `/sitemanager/dashboard`, 메뉴·회원·게시판 목록
- [ ] 게시판: 목록, 글 보기, **회원 글쓰기**, **비회원 글쓰기**(이메일 인증 흐름), 댓글, 파일 첨부
- [ ] 사이트 자체 폼(문의·신청·예약)이 있으면 **실제로 한 건 제출** — §3-4 해당 사이트는 필수
- [ ] 비밀번호 관리자·Chrome 자동완성을 켠 브라우저로 폼 제출 (허니팟 오탐 확인)
- [ ] 폼을 열고 30분 넘게 둔 뒤 제출 (만료 오탐 확인, 필요하면 `form_timestamp`를 과거값으로 바꿔 재현)
- [ ] `storage/logs`에 `SiteManager Security:` 경고가 정상 제출에서 찍히지 않는지
- [ ] 관리자 다크 테마 등 새 기능은 기본 꺼짐(opt-in)인지 — 켜지 않았는데 화면이 바뀌었으면 결함

---

## 5. 버전별 주의사항

### (a66d08c 다음 커밋, 2026-09-29) — 서명 필수 모드에서 시각 필드 누락 거부

| 변경 | 사이트 영향 | 할 일 |
|---|---|---|
| `validateFormTiming(..., strictSignature: true)`가 `form_timestamp` **누락**도 거부. 이전엔 필드를 빼기만 하면 통과 | `strict_signature => true`로 부르는 폼만 해당. 기본값(false)인 폼은 그대로 | `grep -rn "strict_signature\|strictSignature" app` — 쓰는 폼은 `form-security` 조각(또는 같은 필드)을 렌더링하는지 확인. 빠져 있으면 정상 제출이 막힌다 |

### a66d08c (2026-09-29) — 보안 검사기 수정

| 변경 | 사이트 영향 | 할 일 |
|---|---|---|
| `validateEmailDomainBlocking()`이 올바른 키(`security.blocked_email_domains`)를 읽음. **이전엔 항상 통과** | 이 검사기를 켠 폼(`validateFormSecurity`의 `email_domain` 기본 true 포함)에서 **일회용 도메인 제출이 처음으로 막힌다**. 정상 동작이지만 행동 변화다 | §3-4 해당 사이트: 차단 목록(`security.blocked_email_domains`)이 사이트 설정에 있는지, 막힐 때 폼에 오류 문구가 제대로 보이는지 |
| 폼 최대 작성 시간 기본 30분 → 120분 | 사이트 설정에 `max_form_time => 1800`이 있으면 **여전히 30분** | 사이트 설정 값을 `7200`으로 (세션 수명과 맞춤) |
| `form-security` 조각의 허니팟 대체값에서 `website`·`url`·`homepage` 제거 | 사이트 설정에 `honeypot.fields`가 있으면 그 값이 쓰인다 | 사이트 설정이 **자동완성 표적 이름**(`website`·`url`·`homepage`·`phone_number`)을 갖고 있으면 `['company_phone']`로 |
| `verifyCaptcha()` 인자 nullable 명시 | 없음 (PHP 8.4 경고 제거) | — |

**사이트별 설정 현황 (260929 조사)**

| 사이트 | 허니팟 필드 | max_form_time | 보안 검사기 직접 사용 | 조치 |
|---|---|---|---|---|
| edmuhak | `website, url, homepage, phone_number` ⚠️ | (설정 없음 → 기본) | 없음 (게시판만) | 허니팟 → `['company_phone']`. **GIO에서 정상 고객 차단을 일으킨 목록과 같다** |
| edmedu | `website, url, homepage, phone_number, company_phone` ⚠️ | 1800 | 없음 (게시판만) | 허니팟 → `['company_phone']`, max_form_time → 7200 |
| ~~edmkorean~~ | — | — | — | **대상 아님** — 2026-06-29 sitemanager 제거 |
| bridge2korea | `company_phone` | 1800 | **있음** — InquiryController, summer apply, reCAPTCHA **켜짐** | max_form_time → 7200, 문의·신청 폼 제출 점검 |
| ~~TOEFL~~ | — | — | — | **대상 아님** — 서비스 종료 |
| 한우리교회 | (설정 파일 없음 → 기본) | (기본) | 없음 | 72커밋 차이 — 게시판·메뉴 전반과 마이그레이션 3개 점검 |
| d3141c 데모 | `company_phone` | 1800 | 게시판 뷰 1개 | max_form_time → 7200 |

**reCAPTCHA v3를 켠 사이트(bridge2korea) 참고**: 점수 0.5 기준이라 VPN·사생활 보호 브라우저 사용자가 조용히 막힐 수 있다.
GIO에서 "보안 장치를 켜면 실제 고객이 막힌다"는 문제의 원인 중 하나로 본 항목이다. 문의 누락 신고가 있으면 이 로그(`SiteManager Security:`)부터 본다.

---

## 6. 작업 시작 문구 (복사해서 쓰기)

```
~/www/sitemanager/docs/UPGRADE_CHECKLIST.md 를 읽고 <사이트명 또는 all>에 SiteManager 최신(main)을 반영해줘.
로컬 DB 를 ~/install/www/<사이트>/get-data.sh 로 최신화하고, §3 점검과 §5 해당 버전 주의사항을 적용한 뒤
§4 체크리스트를 로컬에서 확인해. 서버 반영(scripts/deploy-sites.sh update)은 내가 확인한 뒤에.
```
