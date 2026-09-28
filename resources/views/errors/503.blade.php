{{--
  503 오류 화면 (2026-09-28 신설) — UI 전달본의 오류 상태(assets/error-state.svg) 규격.
  Laravel이 503 응답을 낼 때 자동으로 이 뷰를 씁니다(별도 라우트 등록 불필요).
  기능 변경 없이 사용자에게 보이는 예외 화면만 서비스 디자인에 맞춘 것입니다.
--}}
@include('errors.partials.user-error', [
  'code' => '503',
  'title' => '잠시 점검 중이에요',
  'desc' => '더 나은 서비스를 위해 잠깐 정비하고 있어요.<br>조금 뒤에 다시 찾아와 주세요.',
  'primaryLabel' => '홈으로 가기',
  'primaryHref' => route('home'),
  'showRetry' => false,
])
