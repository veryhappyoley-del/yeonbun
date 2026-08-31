{{-- 오늘의 운세 이메일 — 이메일 클라이언트는 외부 스타일시트/대부분의 CSS를 못 쓰므로
     app.css를 재사용하지 않고 인라인 스타일만으로 최소한의 톤(종이/먹색/인주색)만
     흉내낸다. --}}
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>오늘의 운세</title>
</head>
<body style="margin:0; padding:0; background:#d9cdb0; font-family: 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif;">
  <div style="max-width:480px; margin:0 auto; padding:32px 20px;">
    <div style="text-align:center; margin-bottom:20px;">
      <span style="display:inline-block; padding:6px 14px; border:2px solid #8B5E83; border-radius:8px; color:#8B5E83; font-weight:700; font-size:14px;">연록</span>
    </div>

    <div style="background:#ede6d6; border-radius:16px; padding:28px 24px;">
      <p style="margin:0 0 4px; font-size:13px; color:#57524a;">{{ $dailyFortune->fortune_date->format('Y년 n월 j일') }}의 운세</p>
      <h1 style="margin:0 0 20px; font-size:22px; color:#201d1a;">{{ $content['headline'] ?? '오늘의 운세' }}</h1>

      @foreach (($content['paragraphs'] ?? []) as $paragraph)
        <p style="margin:0 0 14px; font-size:15px; line-height:1.65; color:#3a352e;">{{ $paragraph }}</p>
      @endforeach

      <div style="display:flex; gap:10px; margin-top:20px; padding-top:18px; border-top:1px solid rgba(32,29,26,0.14);">
        <div style="flex:1; text-align:center;">
          <div style="font-size:12px; color:#57524a;">오늘의 색</div>
          <div style="font-size:14px; font-weight:700; color:#201d1a;">{{ $content['lucky_color'] ?? '-' }}</div>
        </div>
        <div style="flex:1; text-align:center;">
          <div style="font-size:12px; color:#57524a;">오늘의 시간</div>
          <div style="font-size:14px; font-weight:700; color:#201d1a;">{{ $content['lucky_time'] ?? '-' }}</div>
        </div>
        <div style="flex:1; text-align:center;">
          <div style="font-size:12px; color:#57524a;">오늘의 키워드</div>
          <div style="font-size:14px; font-weight:700; color:#201d1a;">{{ $content['keyword'] ?? '-' }}</div>
        </div>
      </div>
    </div>

    <div style="text-align:center; margin-top:24px;">
      <a href="{{ route('fortune.today') }}" style="display:inline-block; padding:12px 24px; background:#8B5E83; color:#fbf3e9; border-radius:10px; text-decoration:none; font-weight:700; font-size:14px;">앱에서 자세히 보기</a>
    </div>

    <p style="text-align:center; margin-top:28px; font-size:12px; color:#57524a;">
      사주는 통계적·문화적 참고용 콘텐츠이며 실제 결과를 보장하지 않아요.<br>
      구독 해지는 앱의 마이페이지 &gt; 오늘의 운세 구독 관리에서 언제든 가능해요.
    </p>
  </div>
</body>
</html>
