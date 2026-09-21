# 20260921 published_at 결함 및 install auth guard 보정

## 작업 요청
- d3141c.ddns.net 마이그레이션 Phase 5 검수에서 발견된 패키지 결함 3건을 원본에서 수정 (사용자 지시).

## 작업 내용
1. `resources/views/board/show.blade.php` — 첨부 목록 날짜 `$attachment->published_at->format()` → `$attachment->created_at?->format()`. `board_attachments` 에 `published_at` 이 없어 첨부가 있는 글 상세가 500 이었다. 자체 스킨을 쓰는 사이트에선 드러나지 않았다.
2. `resources/views/board/partials/comment.blade.php` — 댓글 날짜 `$comment->published_at?->diffForHumans()` → `created_at`. 컬럼이 없어 모든 댓글이 "Just now" 였다. 자식 댓글 정렬 `sortByDesc('published_at')` → `sortBy('created_at')` (BoardService::getPostComments 가 자식을 오래된 순으로 싣는 의도와 일치. 기존엔 키가 전부 null 이라 stable sort 로 우연히 그 순서가 유지되고 있었음).
3. `src/Console/Commands/InstallCommand.php` — `publishConfig()` 뒤 `ensureAuthConfigHasPackageGuards()` 추가. `--force` 없는 `vendor:publish` 는 스켈레톤이 만든 `config/auth.php` 를 건너뛰어 `customer` guard 가 빠지고 레이아웃 렌더가 500 난다. 호스트 파일에 `'customer'` 가 없을 때만 `auth.php.backup` 백업 후 패키지 파일로 교체.

## 검증
- d3141c 프로젝트(symlink)에서 임시 오버라이드 제거 후 댓글 날짜 "17 years ago", 순서 유지, `view:cache` 컴파일 통과.
- `ensureAuthConfigHasPackageGuards()` no-op 경로(정상 파일) 리플렉션 호출로 파일 불변 확인. 교체 경로는 설치 재실행이 파괴적이라 코드 검토로 갈음.
- `php -l` 통과.

## 관련 파일
- 위 3개. **미커밋** (git 작업은 명시 요청 시에만).

## 향후 작업
- 커밋·푸시. 서버 배포는 composer 경유라 GitHub 반영이 선행돼야 함.
- 다른 사용 사이트(edmkorean, bridge2korea, gio 등)에서 첨부/댓글 화면 회귀 확인 권장.

---

## 추가 (같은 날, 서버 배포 중 발견)

### resource build 가 config/app.php 를 덮어써 배포 서버 git 이 더러워짐
`ResourceCommand::updateResourceVersion()` 이 빌드할 때마다 `config/app.php` 의 `resource_version` 을
하드코딩 값으로 고쳐 썼다. 배포 서버에서는 이 파일이 git 추적 대상이라 매 빌드마다 워킹트리가 더러워지고
다음 `git pull` 이 막힌다.

**조치**: config 값이 `env('RESOURCE_VERSION')` 형태면 설정 파일 대신 `.env` 의 해당 키를 갱신하도록 분기.
하드코딩 방식으로 쓰던 기존 사이트는 종전대로 동작한다(하위 호환).
`updateEnvResourceVersion()` 추가.
