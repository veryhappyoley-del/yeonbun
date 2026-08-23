# 결 — 연애 특화 사주 프로그램 (Laravel)

사주팔자 계산·연애 해석·궁합은 전부 프론트엔드(순수 JS, `public/js/app.js`)에서 외부 API 없이 계산합니다.
로그인(카카오/네이버)한 사용자만 "연애 코치" 탭을 쓸 수 있고, 여기서만 서버(`app/Http/Controllers/Api/ChatController.php`)가
**Claude API(Anthropic)**를 실시간으로 호출해 자유 대화형 상담을 제공합니다. API 키는 `.env`에만 저장되고 브라우저로는 절대 전달되지 않습니다.
대화는 코인(메시지) 단위로 소진되고, 코인은 `/billing` 페이지에서 토스페이먼츠로 충전합니다.

## ⚡ 이미 한 번 설치하셨다면 (업데이트 항목만)

이전 버전에 카카오/네이버 로그인 + 코인/결제(토스페이먼츠) 연동이 추가됐어요. 아래만 새로 하면 돼요.

```bash
composer require laravel/socialite socialiteproviders/kakao socialiteproviders/naver
php artisan migrate
```

그리고 `.env`에 아래 값을 추가해주세요 (발급 방법은 아래 "카카오/네이버 로그인 설정", "결제(토스페이먼츠) 연동" 참고).

```
KAKAO_CLIENT_ID=
KAKAO_CLIENT_SECRET=
KAKAO_REDIRECT_URI=http://yeonbun.test/auth/kakao/callback

NAVER_CLIENT_ID=
NAVER_CLIENT_SECRET=
NAVER_REDIRECT_URI=http://yeonbun.test/auth/naver/callback

TOSS_CLIENT_KEY=
TOSS_SECRET_KEY=
```
(도메인은 실제 `APP_URL`에 맞게 바꿔주세요. 지금 쓰시는 폴더명 기준이면 `yeonbun.test`가 맞을 거예요.)

`TOSS_CLIENT_KEY`/`TOSS_SECRET_KEY`를 아직 안 넣었어도 괜찮아요 — 비어있으면 `/billing` 페이지가 자동으로
"로컬 테스트용 즉시 지급" 모드로 동작해서(APP_ENV=local일 때만) 결제 없이도 화면 흐름을 테스트할 수 있어요.

## 이 프로젝트에 대해 알아두면 좋은 것

- Laravel 13 표준 스켈레톤을 기반으로 만들었어요 (`composer create-project laravel/laravel`로 만든 것과 동일한 구조).
- `vendor/`, `node_modules/`는 포함되어 있지 않아요. 아래 설치 순서대로 진행하면 돼요.
- DB는 기본적으로 SQLite 하나로 충분해요.
- **로그인은 카카오/네이버만 지원해요.** 이메일/비밀번호 가입은 없어요 — 그래서 `users` 테이블에 비밀번호 칼럼은 있지만 실제로는 랜덤값이 채워지고 안 쓰여요.
- **사주 계산·궁합·상담가이드 3개 탭은 로그인 없이 그대로 무료로 써요.** 연애 코치(AI 상담) 탭만 로그인이 필요해요 (대화 기록 저장 + 실제 API 비용이 나가는 유일한 기능이라서요).
- **신규 가입 시 코인(메시지) 10개를 무료로 줘요.** 다 쓰면 `/billing`에서 충전해야 계속 대화할 수 있어요. AI 응답 1회 = 코인 1개.
- **`APP_ENV=local`일 때는 "새 상담 시작하기"를 누르면 코인 잔액과 상관없이 무조건 결제 페이지로 이동해요.** 실제 AI 호출 없이 결제 화면만 테스트하려는 의도예요. 실제로 대화를 테스트하려면 `/billing`에서 코인을 충전(로컬에서는 결제 없이 바로 지급)한 뒤에도 여전히 결제 페이지로 가는 게 정상 동작이니 헷갈리지 마세요 — 이 강제 이동 자체를 끄고 싶으면 `app/Http/Controllers/Api/ChatController.php`의 `needsPayment()`에서 `app()->environment('local') ||` 부분을 지우면 돼요.

## 새로 설치하는 경우 전체 순서 (Laravel Herd 기준, Windows/Mac 공통)

1. **이 폴더를 Herd가 인식하는 위치로 옮기기**
   Herd 앱에서 `Sites` 탭 → `+` 버튼으로 이 프로젝트 폴더를 직접 추가하거나, Herd가 기본으로 파킹(park)해둔 폴더 안으로 옮겨주세요.
   폴더 이름이 `yeonbun`이면 Herd가 자동으로 `http://yeonbun.test` 도메인을 연결해줘요.

2. **의존성 설치**
   ```bash
   cd yeonbun
   composer install
   composer require laravel/socialite socialiteproviders/kakao socialiteproviders/naver
   ```

3. **환경 설정 파일 만들기**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   `.env`에서 `APP_URL`을 실제 Herd 도메인으로 맞춰주세요 (예: `http://yeonbun.test`).

4. **Claude API 키 넣기**
   [console.anthropic.com](https://console.anthropic.com)에서 API 키를 발급받아 `.env`의 `ANTHROPIC_API_KEY`에 넣어주세요.
   ```
   ANTHROPIC_API_KEY=sk-ant-...
   ANTHROPIC_MODEL=claude-sonnet-5
   ```

5. **카카오/네이버 로그인 설정** (아래 섹션 참고해서 키 발급 후 `.env`에 입력)

6. **결제(토스페이먼츠) 설정** (아래 "결제(토스페이먼츠) 연동" 섹션 참고 — 건너뛰어도 로컬 테스트 모드로 동작해요)

7. **데이터베이스 준비 (SQLite)**
   ```bash
   touch database/database.sqlite    # Windows PowerShell이면: New-Item database\database.sqlite -ItemType File
   php artisan migrate
   ```

8. **접속하기**
   브라우저에서 `http://yeonbun.test` (Herd가 연결해준 도메인)를 열면 끝이에요.

## 카카오/네이버 로그인 설정

### 카카오
1. [developers.kakao.com](https://developers.kakao.com) 접속 → 로그인 → "내 애플리케이션" → 애플리케이션 추가.
2. 앱 만들고 들어가서 **앱 키 → REST API 키**를 복사 → `.env`의 `KAKAO_CLIENT_ID`.
3. 왼쪽 메뉴 **제품 설정 → 카카오 로그인** → 활성화 ON.
4. 같은 화면의 **Redirect URI**에 정확히 이 값을 등록: `http://yeonbun.test/auth/kakao/callback`
5. **보안 → Client Secret** 발급 → 코드 생성 → `.env`의 `KAKAO_CLIENT_SECRET`. (Client Secret 사용을 "사용함"으로 켜야 해요.)
6. **카카오 로그인 → 동의항목**에서 닉네임, 이메일 정도를 "필수 동의"나 "선택 동의"로 설정해두세요. (이메일을 안 받으면 상담사가 부를 이름은 닉네임으로 대체돼요.)
7. 앱이 "개발 중" 상태일 땐 카카오 계정 중 "팀 멤버"로 등록된 계정만 로그인 테스트가 가능해요. **내 애플리케이션 → 팀 관리**에서 본인 계정을 추가하거나, 나중에 실제 서비스로 낼 때 "검수" 신청을 하세요.

### 네이버
1. [developers.naver.com](https://developers.naver.com) 접속 → 로그인 → "Application → 애플리케이션 등록".
2. 사용 API에 **네이버 로그인** 체크.
3. 제공 정보 선택에서 이름, 이메일 정도 체크.
4. **서비스 URL**에 `http://yeonbun.test`, **Callback URL**에 `http://yeonbun.test/auth/naver/callback` 등록.
5. 등록 완료 후 나오는 **Client ID / Client Secret**을 `.env`의 `NAVER_CLIENT_ID` / `NAVER_CLIENT_SECRET`에 입력.
6. 네이버는 개발 단계에서도 "등록된 테스트 계정" 제한 없이 애플리케이션 소유자 계정으로 바로 로그인 테스트가 가능해요.

## 결제(토스페이먼츠) 연동

코인 충전은 [토스페이먼츠](https://www.tosspayments.com)의 결제창(Payment Window) 방식으로 연동돼 있어요.
**사업자등록 없이도 이메일만으로 가입하면 테스트 키를 바로 받을 수 있어요** — 실제 서비스로 낼 준비가 될 때까지는 이 테스트 키만으로 결제 흐름 전체(결제창 → 승인 → 코인 지급)를 그대로 테스트할 수 있고, 결제수단에서 실제로 돈이 빠져나가지 않아요.

> **자주 겪는 에러**: 결제하기를 눌렀을 때 "잘못된 successUrl입니다" 또는 "처리 중 오류가 발생했습니다"(에러코드 1000)가 뜨면, 거의 항상 `.env`의 `APP_URL`이 실제 접속 도메인과 다른 경우예요. `successUrl`/`failUrl`은 `APP_URL`을 기준으로 자동 생성되기 때문에, `APP_URL`이 틀리면 토스가 이상한 주소로 인식해서 형식 오류로 막아요. Herd 폴더 이름이 `yeonbun`이면 `APP_URL=http://yeonbun.test`가 정확히 맞아야 해요.

### 테스트 키 받기
1. [developers.tosspayments.com](https://developers.tosspayments.com) 접속 → 이메일로 회원가입/로그인.
2. 상단 또는 좌측 메뉴에서 **API 키** 메뉴로 이동.
3. **테스트 키**(`test_ck_...`로 시작하는 클라이언트 키, `test_sk_...`로 시작하는 시크릿 키) 확인.
   - 전자결제 신청(사업자등록, 심사)을 아직 안 했어도 이 테스트 키는 바로 볼 수 있어요.
4. `.env`에 입력:
   ```
   TOSS_CLIENT_KEY=test_ck_...
   TOSS_SECRET_KEY=test_sk_...
   ```
5. `php artisan config:clear` 후 `/billing` 페이지에 들어가면 실제 결제창이 뜨는 방식으로 자동 전환돼요. 카드 결제 시 아무 카드번호나 입력해도(테스트 키라서) 승인이 나요 — 정확한 테스트 카드번호는 [토스페이먼츠 테스트 문서](https://docs.tosspayments.com/reference/test)를 참고하세요.

### 실제 서비스로 전환하려면 (나중에)
1. 토스페이먼츠에서 **전자결제 신청**(사업자등록증 필요 — 최소 개인사업자 등록은 돼있어야 해요) 후 심사를 통과하면 **라이브 키**(`live_ck_...`/`live_sk_...`)가 발급돼요.
2. `.env`의 `TOSS_CLIENT_KEY`/`TOSS_SECRET_KEY`를 라이브 키로 교체하면 끝이에요. 코드는 그대로 두면 돼요.
3. 이때 서버(`APP_ENV`)는 반드시 `production`이어야 해요 — `local`이면 "새 상담 시작하기"가 무조건 결제 페이지로 강제 이동하는 테스트 동작이 계속 켜져 있어서 실제 서비스에 그대로 두면 안 돼요.

### 참고
- 지금은 카드 결제(`카드`)만 켜져 있어요. 계좌이체·가상계좌·휴대폰·토스페이 등은 `resources/views/billing.blade.php`의 `requestPayment('카드', ...)` 첫 번째 인자를 바꾸거나 선택 UI를 추가하면 돼요.
- 결제 승인은 반드시 서버(`BillingController::success()`)에서 토스의 `/v1/payments/confirm` API를 직접 호출해서 검증한 뒤에만 코인을 지급하도록 짜여 있어요. 프론트 요청만 믿고 바로 지급하면 위조 요청으로 무료 충전이 가능해지기 때문에, 이 구조는 그대로 유지하는 걸 추천해요.
- 정기결제(구독)는 지금 구조에 없어요. 나중에 추가하려면 토스페이먼츠의 [빌링(자동결제)](https://docs.tosspayments.com) API를 별도로 봐야 해요.

## 사업자 정보 표시 (전자상거래법)

실제로 결제(통신판매)를 받는 순간부터는 전자상거래법에 따라 홈페이지에 사업자 정보(상호, 대표자, 사업자등록번호, 통신판매업 신고번호, 주소, 연락처)를 표시해야 해요. 이 프로젝트는 `.env`에 `BUSINESS_REG_NO`가 비어있으면 아무것도 표시하지 않고, 값이 채워지면 홈 화면과 결제 페이지 footer에 자동으로 노출되도록 만들어 뒀어요(`config/business.php`, `resources/views/partials/business-footer.blade.php`).

1. **사업자등록**을 먼저 하세요 — 어차피 토스페이먼츠 라이브 키를 받으려면 사업자등록증이 필요해요.
2. **통신판매업 신고**가 필요한지 확인하세요. 정부24에서 신청할 수 있고, 간이과세자이거나 직전년도 거래 횟수가 일정 기준 미만이면 면제될 수 있어요 — 정확한 면제 대상 여부는 정부24 안내나 세무사를 통해 한 번 확인하는 걸 추천해요(회색지대라 이 문서만으로 단정하지 않는 게 안전해요).
3. `.env`를 채우세요:
   ```
   BUSINESS_NAME=상호명
   BUSINESS_OWNER=대표자명
   BUSINESS_REG_NO=000-00-00000
   BUSINESS_MAIL_ORDER_NO=제2026-서울OO-0000호   (통신판매업 신고를 했다면)
   BUSINESS_ADDRESS=사업장 주소
   BUSINESS_PHONE=연락 가능한 전화번호
   BUSINESS_EMAIL=연락 가능한 이메일
   ```
4. `php artisan config:clear` 후 새로고침하면 footer에 사업자 정보가 뜨고, 사업자등록번호 옆에 공정거래위원회 사업자정보 확인 페이지로 연결되는 링크도 자동으로 붙어요.

이건 법률 자문이 아니라 일반적인 안내예요 — 정확한 신고 의무·면제 여부는 실제 매출·거래 방식에 따라 달라질 수 있으니 세무사나 정부24를 통해 최종 확인하시길 권해요. 참고로 아직 이 프로젝트에는 개인정보처리방침·이용약관 페이지가 없는데, 사업자 정보를 채우는 김에 같이 준비하면 좋아요(로그인 신원·생년월일·AI 상담 내용을 수집하고 있어서 필요해요).

## 폴더 구조 중 눈여겨볼 곳

| 경로 | 내용 |
|---|---|
| `public/js/app.js` | 사주 계산 엔진 + 연애 해석 + 궁합 + 고민 상담 가이드 (전부 클라이언트에서 계산, API 불필요) |
| `public/js/chat.js` | "연애 코치" 탭 프론트 로직. 로그인된 사용자만 로드됨. 402(코인 부족) 응답을 받으면 `/billing`으로 이동 |
| `app/Http/Controllers/Auth/SocialAuthController.php` | 카카오/네이버 로그인 리다이렉트·콜백·로그아웃 |
| `app/Http/Controllers/Api/ChatController.php` | Claude API를 서버에서 호출하는 컨트롤러. 사주 요약을 시스템 프롬프트에 넣어 맞춤 상담을 만듦. 세션은 로그인한 사용자에게 귀속되고, AI 응답 1회마다 코인 1개를 차감 |
| `app/Http/Controllers/BillingController.php` | 코인 충전 페이지 + 토스페이먼츠 결제창 연동(checkout/success/fail) + 로컬 테스트용 즉시 지급(purchase) |
| `app/Models/Payment.php` | 결제 1건당 1행. `pending → paid/failed` 상태로 관리 |
| `routes/web.php` | `/auth/{provider}/redirect`, `/auth/{provider}/callback`, `/chat/*`, `/billing*` (전부 `auth` 미들웨어) |
| `database/migrations/2026_01_01_*` | `chat_sessions`/`chat_messages`/`payments` 테이블, `users`에 소셜 로그인 칼럼 + `credits` 컬럼 추가 |
| `resources/views/saju.blade.php` | 메인 페이지 (4개 탭 + 상단 로그인/코인 바) |
| `resources/views/billing.blade.php` | 코인 충전 페이지 |

## 다음에 더 손볼 만한 것들

- 음력 생일 입력 지원
- 대운(10년 단위 운세) 계산 추가
- 채팅 API에 rate limit(예: 사용자당 분당 요청 수 제한) 추가해서 API 비용 관리
- 대화가 길어지면 오래된 메시지를 요약해서 압축하는 로직 (지금은 매 턴 전체 히스토리를 다시 보내서, 대화가 길수록 턴당 비용이 늘어남)
- 결제수단을 카드 외에 계좌이체/토스페이 등으로 확장
- 정기결제(구독) 플랜 추가
