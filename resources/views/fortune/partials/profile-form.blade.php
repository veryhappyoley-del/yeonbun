<form method="POST" action="{{ route('fortune.profile') }}" style="margin-top:12px;">
  @csrf
  <label for="fortune-name">이름 (선택)</label>
  <input type="text" id="fortune-name" name="name" placeholder="예: 올리" value="{{ old('name', $profile->name ?? '') }}">

  <div class="field-row" style="margin-top:8px;">
    <div><label for="fortune-year">태어난 해</label><input type="number" id="fortune-year" name="birth_year" min="1900" max="2100" required value="{{ old('birth_year', $profile?->birth_date?->format('Y')) }}"></div>
    <div><label for="fortune-month">월</label><input type="number" id="fortune-month" name="birth_month" min="1" max="12" required value="{{ old('birth_month', $profile?->birth_date?->format('n')) }}"></div>
    <div><label for="fortune-day">일</label><input type="number" id="fortune-day" name="birth_day" min="1" max="31" required value="{{ old('birth_day', $profile?->birth_date?->format('j')) }}"></div>
  </div>

  <div id="fortune-time-fields" class="field-row" style="margin-top:8px;">
    <div><label for="fortune-hour">시</label><input type="number" id="fortune-hour" name="birth_hour" min="0" max="23" value="{{ old('birth_hour', $profile?->birth_hour) }}"></div>
    <div><label for="fortune-minute">분</label><input type="number" id="fortune-minute" name="birth_minute" min="0" max="59" value="{{ old('birth_minute', $profile?->birth_minute) }}"></div>
  </div>
  <div class="check-row">
    <input type="checkbox" id="fortune-unknown" name="birth_time_unknown" value="1" @checked(old('birth_time_unknown', $profile?->birth_time_unknown))>
    <label for="fortune-unknown" style="margin:0;">태어난 시간을 몰라요</label>
  </div>

  <label style="margin-top:12px; display:block;">성별</label>
  <input type="hidden" id="fortune-gender-input" name="gender" value="{{ old('gender', $profile?->gender) }}" required>
  <div class="compat-gender-row" id="fortune-gender-row">
    <button type="button" class="compat-gender-chip @if(old('gender', $profile?->gender) === 'male') active @endif" data-gender="male">남자</button>
    <button type="button" class="compat-gender-chip @if(old('gender', $profile?->gender) === 'female') active @endif" data-gender="female">여자</button>
  </div>

  <button type="submit" class="btn btn-center" style="margin-top:18px;">저장하기</button>
</form>
