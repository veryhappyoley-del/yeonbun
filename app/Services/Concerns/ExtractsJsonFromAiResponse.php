<?php

namespace App\Services\Concerns;

/**
 * Claude 응답에서 순수 JSON 문자열만 뽑아내는 공통 로직. 프롬프트에서 "JSON만 출력"을
 * 강하게 요구하지만, 혹시 앞뒤에 ```json 코드펜스나 설명 문구가 섞여 와도 견고하게
 * 뽑아내기 위한 방어 로직입니다.
 *
 * 원래 ReportGenerator에만 있던 private 메서드였는데, 챕터형 리포트의 ChapterGenerator도
 * 완전히 동일한 로직이 필요해서(챕터 하나하나도 결국 "JSON 하나만 출력하라"는 같은 방식의
 * 응답이므로) 공통 trait으로 뽑았습니다. 동작은 이전과 100% 동일합니다.
 */
trait ExtractsJsonFromAiResponse
{
    private function extractJson(string $text): ?string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?/i', '', $text) ?? $text;
        $text = preg_replace('/```\s*$/', '', $text) ?? $text;
        $text = trim($text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($text, $start, $end - $start + 1);
    }
}
