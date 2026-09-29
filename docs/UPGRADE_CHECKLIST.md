# SiteManager 패키지 갱신 체크리스트

> 이 패키지를 쓰는 사이트에 새 버전을 반영할 때 따르는 절차와 점검 항목이다. 사이트별로 **별도 세션**에서 진행한다.
> 작성 2026-09-29 (a66d08c 기준) · 버전별 주의사항은 §5에 추가해 나간다.

---

## 0. 먼저 알아둘 것 — 로컬은 이미 새 코드로 돌고 있을 수 있다

대부분 사이트의 로컬 `vendor/d3141cgit/sitemanager`는 `~/www/sitemanager` **심링크**다. 패키지를 push하는 순간 로컬 사이트는
이미 새 코드로 동작한다. 반면 **서버는 각 사이트 `composer.lock`에 박힌 커밋**으로 돈다.

- 로컬에서 이상이 보이면 "패키지 갱신 때문"일 수 있다. 사이트 작업 중 원인 모를 변화가 생기면 먼저 여기를 의심한다.
- 서버 반영은 사이트마다 lock을 갱신해 배포해야 일어난다. **lock을 갱신하지 않은 사이트는 서버에서 옛 버전 그대로다.**

| 사이트 | 경로 | 로컬 vendor | 서버 lock (260929) | a66d08c까지 |
|---|---|---|---|---|
| GIO | `gio/gio` | 심링크 | **a66d08c (반영 완료)** | — |
| edmuhak | `edmuhak.com/edmuhak` | 심링크 | 957e430 | 56커밋 · 마이그레이션 2 |
| edmkorean | `edmkorean.com/edmkorean` | 심링크 | 957e430 | 56커밋 · 마이그레이션 2 |
| edmedu | `edmedu.com/edmedu` | **composer 설치** | 8cc6cea | 15커밋 · 마이그레이션 2 |
| bridge2korea | `bridge2korea.com` | 심링크 | 8fa701b | 16커밋 · 마이그레이션 2 |
| TOEFL | `tts-toefl/toefl` | 심링크 | 298ced9 | 14커밋 · 마이그레이션 2 |
| 한우리교회 | `hanurichurch.org/www` | 심링크 | 0aad451 | **72커밋 · 마이그레이션 3** |
| d3141c 데모 | `d3141c.ddns.net/sitemanager` | 심링크 | 68c9568 | 4커밋 |

> 표의 lock 값은 작성 시점이다. 시작할 때 `grep -A6 '"name": "d3141cgit/sitemanager"' composer.lock | grep reference`로 다시 확인한다.

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
7. 커밋(lock + 필요 시 설정) → 사이트 배포 방식대로 서버에 `composer install` → 캐시 정리(`php artisan optimize:clear`) → 서버 점검(§4 중 핵심).

**하지 않는 것**: 서버에서 `composer update` (lock이 서버마다 달라진다). 여러 사이트를 한 세션에서 몰아서 갱신 (문제가 생기면 원인 사이트를 가르기 어렵다).

---

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
| edmkorean | (설정에 없음 → 기본) | (기본) | **있음** — Contact·Agent·Oneday 컨트롤러, reCAPTCHA **켜짐** | 문의·에이전트 등록·원데이 예약 폼 실제 제출 점검. `AgentController`는 `validateEmailDomainBlocking()`을 직접 부른다 → 일회용 도메인 차단이 새로 동작 |
| bridge2korea | `company_phone` | 1800 | **있음** — InquiryController, summer apply, reCAPTCHA **켜짐** | max_form_time → 7200, 문의·신청 폼 제출 점검 |
| TOEFL | `company_phone` | 1800 | 뷰 오버라이드 (`guest-author-form`, `security/form-security`) | max_form_time → 7200. 오버라이드한 `form-security`에 패키지 수정이 안 들어감 — 허니팟 대체값 확인 |
| 한우리교회 | (설정 파일 없음 → 기본) | (기본) | 없음 | 72커밋 차이 — 게시판·메뉴 전반과 마이그레이션 3개 점검 |
| d3141c 데모 | `company_phone` | 1800 | 게시판 뷰 1개 | max_form_time → 7200 |

**reCAPTCHA v3를 켠 사이트(edmkorean, bridge2korea) 참고**: 점수 0.5 기준이라 VPN·사생활 보호 브라우저 사용자가 조용히 막힐 수 있다.
GIO에서 "보안 장치를 켜면 실제 고객이 막힌다"는 문제의 원인 중 하나로 본 항목이다. 문의 누락 신고가 있으면 이 로그(`SiteManager Security:`)부터 본다.

---

## 6. 사이트별 세션 시작 문구 (복사해서 쓰기)

```
~/www/sitemanager/docs/UPGRADE_CHECKLIST.md 를 읽고 <사이트명>(<경로>)에 SiteManager 최신(main)을 반영해줘.
§1 절차대로 lock 만 갱신하고, §3 점검과 §5 해당 버전 주의사항(사이트별 현황 표의 조치)을 적용한 뒤
§4 체크리스트를 로컬에서 확인해. 서버 배포는 내가 확인한 뒤에.
```
