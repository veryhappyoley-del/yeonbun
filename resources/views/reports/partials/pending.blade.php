{{-- content(single은 JSON, compat은 HTML)가 아직 없을 때 공통으로 쓰는 "생성 중" / 재시도 UI. --}}
<div id="report-pending-note" class="placeholder-note" style="border-color: var(--seal); margin-top:0;">
  리포트를 만들고 있어요. {{ $report->type === 'compat' ? '10~30초' : '30~90초' }} 정도 걸릴 수 있어요 — 결제는 이미 정상적으로 완료됐으니 안심하고 잠시만 기다려 주세요.
</div>
<form id="regenerate-form" method="POST" action="{{ route('reports.regenerate', $report) }}" style="margin-top:14px; text-align:center;">
  @csrf
  <button type="submit" class="btn" id="regenerate-btn">리포트 다시 생성하기</button>
</form>
<script>
  (function () {
    var key = 'gyeol_report_autogen_{{ $report->id }}';
    var alreadyTried = false;
    try { alreadyTried = !!sessionStorage.getItem(key); } catch (e) {}

    if (alreadyTried) {
      // 자동 재시도를 이미 한 번 해봤는데도 비어있는 상태 — 무한 반복을 막고 수동 버튼만 남겨둠.
      document.getElementById('report-pending-note').textContent =
        '리포트를 생성하는 중 문제가 있었어요. 결제는 정상적으로 완료됐으니 안심하시고, 아래 버튼으로 다시 시도해 주세요.';
      return;
    }

    try { sessionStorage.setItem(key, '1'); } catch (e) {}
    document.getElementById('regenerate-btn').disabled = true;
    document.getElementById('regenerate-btn').textContent = '생성하는 중…';
    document.getElementById('regenerate-form').submit();
  })();
</script>
