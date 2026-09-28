{{--
  419 오류 화면 (2026-09-28 신설) — UI 전달본의 오류 상태(assets/error-state.svg) 규격.
  Laravel이 419 응답을 낼 때 자동으로 이 뷰를 씁니다(별도 라우트 등록 불필요).
  기능 변경 없이 사용자에게 보이는 예외 화면만 서비스 디자인에 맞춘 것입니다.
--}}
@include('errors.partials.user-error', [
  'code' => '419',
  'title' => '세션이 만료됐어요',
  'desc' => '보안을 위해 일정 시간이 지나면 연결이 끊겨요.<br>다시 시도하면 이어서 진행할 수 있어요.',
  'primaryLabel' => '다시 시도하기',
  'primaryHref' => null,
  'showRetry' => true,
])
