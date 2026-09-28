{{--
  429 오류 화면 (2026-09-28 신설) — UI 전달본의 오류 상태(assets/error-state.svg) 규격.
  Laravel이 429 응답을 낼 때 자동으로 이 뷰를 씁니다(별도 라우트 등록 불필요).
  기능 변경 없이 사용자에게 보이는 예외 화면만 서비스 디자인에 맞춘 것입니다.
--}}
@include('errors.partials.user-error', [
  'code' => '429',
  'title' => '요청이 너무 많아요',
  'desc' => '잠시 후 다시 시도해 주세요.<br>입력하신 정보는 그대로 남아 있어요.',
  'primaryLabel' => '다시 시도하기',
  'primaryHref' => null,
  'showRetry' => true,
])
