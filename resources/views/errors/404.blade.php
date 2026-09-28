{{--
  404 오류 화면 (2026-09-28 신설) — UI 전달본의 오류 상태(assets/error-state.svg) 규격.
  Laravel이 404 응답을 낼 때 자동으로 이 뷰를 씁니다(별도 라우트 등록 불필요).
  기능 변경 없이 사용자에게 보이는 예외 화면만 서비스 디자인에 맞춘 것입니다.
--}}
@include('errors.partials.user-error', [
  'code' => '404',
  'title' => '찾을 수 없는 페이지예요',
  'desc' => '주소가 바뀌었거나 삭제된 화면일 수 있어요.<br>홈에서 다시 찾아봐 주세요.',
  'primaryLabel' => '홈으로 가기',
  'primaryHref' => route('home'),
  'showRetry' => false,
])
