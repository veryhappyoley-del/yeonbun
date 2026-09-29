{{--
  리포트가 끝내 만들어지지 않았을 때의 "다음 행동" 안내 (2026-09-28 신설).

  배경: 출시전 점검리스트 3항 — 지금까지는 "다시 시도" 버튼만 있고, 그래도 안 되면
  사용자가 갈 곳이 없었다. 결제는 됐는데 콘텐츠를 못 받은 상태로 막다른 길에 갇히면
  그대로 분쟁이 된다. 재시도 옆에 항상 문의·환불 경로를 같이 둔다.

  메일 제목/본문에 주문번호를 미리 채워 넣는 이유: 사용자가 주문번호를 찾아 적게 하면
  대부분 그냥 포기하거나, 식별이 안 되는 문의가 와서 처리가 늦어진다.

  파라미터
    $report : App\Models\Report
    $reason : 안내 문구 앞에 붙일 상황 설명 (선택)
--}}
@php
  $supportEmail = config('business.email') ?: 'help@yeonrok.kr';
  $mailSubject = '[연록] 리포트 생성 문의 · 주문번호 '.$report->order_id;
  $mailBody = implode("\n", [
      '아래 정보를 그대로 두고 문의 내용만 적어주세요.',
      '',
      '주문번호: '.$report->order_id,
      '리포트 번호: '.$report->id,
      '결제금액: '.number_format($report->amount).'원',
      '',
      '문의 내용:',
      '',
  ]);
  $mailto = 'mailto:'.$supportEmail
      .'?subject='.rawurlencode($mailSubject)
      .'&body='.rawurlencode($mailBody);
@endphp
<div class="support-notice">
  <p>
    @if (!empty($reason)){{ $reason }} @endif
    <strong>결제는 정상적으로 완료된 상태예요.</strong>
    다시 시도해도 계속 만들어지지 않으면 아래로 알려주세요 — 확인 후 다시 만들어 드리거나 환불해 드려요.
  </p>
  <div class="support-notice-actions">
    <a class="btn outline" href="{{ $mailto }}">문의·환불 요청하기</a>
    <a class="text-button" href="{{ route('terms.index') }}#refund">환불 기준 보기</a>
  </div>
  <small>주문번호 {{ $report->order_id }}</small>
</div>
