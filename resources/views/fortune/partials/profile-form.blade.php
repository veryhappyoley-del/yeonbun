<form method="POST" action="{{ route('fortune.profile') }}" style="margin-top:12px;">
  @csrf
  <label for="fortune-name">이름 (선택)</label>
  <input type="text" id="fortune-name" name="name" placeholder="예: 올리" autocomplete="name" value="{{ old('name', $profile->name ?? '') }}">

  <div class="field-row u-mt-2">
    <div><label for="fortune-year">태어난 해</label><input type="number" inputmode="numeric" id="fortune-year" name="birth_year" min="1900" max="2100" required value="{{ old('birth_year', $profile?->birth_date?->format('Y')) }}"></div>
  </div>
  <div class="field-row u-mt-2">
    <div><label for="fortune-month">월</label><input type="number" inputmode="numeric" id="fortune-month" name="birth_month" min="1" max="12" required value="{{ old('birth_month', $profile?->birth_date?->format('n')) }}"></div>
    <div><label for="fortune-day">일</label><input type="number" inputmode="numeric" id="fortune-day" name="birth_day" min="1" max="31" required value="{{ old('birth_day', $profile?->birth_date?->format('j')) }}"></div>
  </div>

  <div id="fortune-time-fields" class="field-row u-mt-2">
    <div><label for="fortune-hour">시</label><input type="number" inputmode="numeric" id="fortune-hour" name="birth_hour" min="0" max="23" value="{{ old('birth_hour', $profile?->birth_hour) }}"></div>
    <div><label for="fortune-minute">분</label><input type="number" inputmode="numeric" id="fortune-minute" name="birth_minute" min="0" max="59" value="{{ old('birth_minute', $profile?->birth_minute) }}"></div>
  </div>
  <div class="check-row">
    <input type="checkbox" id="fortune-unknown" name="birth_time_unknown" value="1" @checked(old('birth_time_unknown', $profile?->birth_time_unknown))>
    <label for="fortune-unknown" class="u-m-0">태어난 시간을 몰라요</label>
  </div>

  {{-- (2026-09-28) label이 컨트롤과 연결되지 않던 문제 — span + role="group"으로 교체. --}}
  <span class="form-label form-label--spaced" id="fortune-gender-label">성별</span>
  <input type="hidden" id="fortune-gender-input" name="gender" value="{{ old('gender', $profile?->gender) }}" required>
  <div class="compat-gender-row" id="fortune-gender-row" role="group" aria-labelledby="fortune-gender-label">
    <button type="button" class="compat-gender-chip @if(old('gender', $profile?->gender) === 'male') active @endif" data-gender="male">남자</button>
    <button type="button" class="compat-gender-chip @if(old('gender', $profile?->gender) === 'female') active @endif" data-gender="female">여자</button>
  </div>

  {{-- (2026-09-08 수정) "저장하기"를 눌러도 반응이 없어 보인다는 피드백 — 실제로는
       성별을 안 고르면 서버 검증에서 조용히 막히고 있었다(위 fortune/index.blade.php의
       $errors 표시로 이제 그 사유가 보인다). 버튼 문구도 "누르면 다음 단계로 이어진다"는
       걸 더 분명히 하도록 바꿨다. --}}
  <button type="submit" class="btn btn-center" style="margin-top:18px;">저장하고 계속하기</button>
</form>
