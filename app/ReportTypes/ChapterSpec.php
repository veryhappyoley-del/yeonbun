<?php

namespace App\ReportTypes;

/**
 * 챕터형 리포트의 챕터 하나를 선언하는 불변 값 객체.
 *
 * ChapterGenerator(2단계에서 구현)가 이 스펙 하나당 Anthropic 호출을 1번 만듭니다 —
 * 즉, 리포트 하나 = ChapterSpec 개수만큼의 독립적인 작은 AI 호출입니다. 예전처럼
 * 리포트 전체를 하나의 거대한 JSON 스키마로 요청하던 방식(ReportGenerator::singlePrompt())
 * 과 달리, 챕터마다 스키마/프롬프트/토큰 예산이 작고 독립적이라 max_tokens truncation
 * 위험이 훨씬 낮고, 챕터 하나가 실패해도 나머지에 영향을 주지 않습니다.
 */
final readonly class ChapterSpec
{
    /**
     * @param  string  $key  리포트 타입 내에서 유일한 챕터 식별자(report_chapters.chapter_key).
     *                       한번 판매를 시작한 뒤에는 기존 구매자의 저장된 챕터와 매칭되므로
     *                       가급적 변경하지 않습니다(제목은 바뀌어도 key는 유지).
     * @param  string  $title  목차/탭에 표시되는 챕터 제목. myeongsado 스타일로 "진짜 궁금해할
     *                         만한" 구체적 문구를 씁니다(예: "갑자기 연락이 뜸해지거나 회피하는
     *                         진짜 이유") — "관계 갈등" 같은 추상적 제목은 피합니다.
     * @param  string  $teaser  목차에서 챕터 제목 아래 보여줄 1줄 미리보기 문구.
     * @param  array<string,mixed>  $schema  이 챕터 하나만의 작은 JSON 스키마(예시 구조).
     *                                        ChapterGenerator가 프롬프트에 그대로 삽입하고,
     *                                        응답을 이 키 집합 기준으로 검증합니다.
     * @param  string  $promptGuidance  이 챕터에 특화된 톤/분량/주의사항 지침(공통 규칙은
     *                                  ChapterGenerator가 별도로 붙임 — 여기엔 챕터 고유 지침만).
     * @param  int  $maxTokens  이 챕터 호출의 max_tokens. 챕터 스키마가 작으므로 리포트 전체
     *                          호출(14000)보다 훨씬 작은 값(기본 2000)으로 시작하고, 실측
     *                          stop_reason/output_tokens 로그를 보고 튜닝합니다.
     * @param  array<int,string>  $inputKeys  전체 input(사주 deep 데이터) 중 이 챕터가 실제로
     *                                         필요로 하는 최상위 키만 선택. 20개 챕터가 매번
     *                                         전체 input을 반복 전송하면 입력 토큰이 20배로
     *                                         뛰므로, 챕터별로 필요한 부분만 잘라 보내 비용을
     *                                         관리합니다. 빈 배열이면 전체 input을 그대로 씁니다.
     * @param  array<int,string>  $blocks  이 챕터 응답을 렌더링할 때 사용할 블록 렌더러 이름
     *                                     목록(예: ['score_bars','paragraphs']). 실제 렌더링은
     *                                     resources/views/reports/partials/blocks/{name}.blade.php
     *                                     가 담당하며(3단계에서 구현), 새 리포트 타입을 추가해도
     *                                     이미 있는 블록을 조합하기만 하면 새 Blade 파일이 필요
     *                                     없게 하는 것이 목적입니다.
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $teaser,
        public array $schema,
        public string $promptGuidance,
        public int $maxTokens = 2000,
        public array $inputKeys = [],
        public array $blocks = [],
    ) {
    }
}
