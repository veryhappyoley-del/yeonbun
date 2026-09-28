{{--
  403 오류 화면 (2026-09-28 신설) — UI 전달본의 오류 상태(assets/error-state.svg) 규격.
  Laravel이 403 응답을 낼 때 자동으로 이 뷰를 씁니다(별도 라우트 등록 불필요).
  기능 변경 없이 사용자에게 보이는 예외 화면만 서비스 디자인에 맞춘 것입니다.
--}}
@include('errors.partials.user-error', [
  'code' => '403',
  'title' => '접근 권한이 없어요',
  'desc' => '이 화면을 볼 수 있는 권한이 없어요.<br>로그인 상태를 확인해 주세요.',
  'primaryLabel' => '마이페이지로 가기',
  'primaryHref' => route('my.index'),
  'showRetry' => false,
])
