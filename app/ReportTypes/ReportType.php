<?php

namespace App\ReportTypes;

/**
 * 판매 가능한 리포트 상품 하나(예: 연애운분석, 궁합분석)를 선언하는 불변 값 객체.
 *
 * 기존 ReportController::TYPES 상수(하드코딩된 single/compat 배열)를 대체합니다.
 * TYPES는 label/price만 알았지만, ReportType은 챕터 구성 전체(chapters)까지 알고
 * 있어서 checkout()이 리포트를 만들 때 필요한 report_chapters 행을 바로 생성할 수
 * 있습니다(2단계에서 GenerateReportJob이 사용).
 */
final readonly class ReportType
{
    /**
     * @param  string  $key  URL/DB에 저장되는 리포트 타입 키(reports.type). 예: love_fortune, compatibility.
     * @param  string  $label  결제창/화면에 노출되는 한글 상품명.
     * @param  int  $price  원 단위 가격. 서버가 항상 이 값을 기준으로 결제 금액을 검증합니다.
     * @param  \App\ReportTypes\InputShape  $inputShape  프론트가 어떤 모양의 input을 보내야 하는지.
     * @param  array<int,\App\ReportTypes\ChapterSpec>  $chapters  이 리포트를 구성하는 챕터 목록
     *                                                              (순서 = sort_order).
     * @param  int  $schemaVersion  reports.schema_version에 그대로 저장되는 값. 1=레거시
     *                              (content 블롭 단일 JSON/HTML), 2=챕터형(report_chapters
     *                              기반). 새 챕터형 타입은 항상 2를 씁니다.
     * @param  array<int,string>  $previewChapterKeys  (2026-08-24 추가) 결제 전 "목차 미리보기"에
     *                             보여줄 챕터를 $chapters 전체가 아니라 일부만 고르고 싶을 때 그
     *                             챕터 key들을 이 순서대로 나열합니다. 빈 배열(기본값)이면
     *                             previewChapters()가 $chapters 전체를 그대로 반환합니다 — 예를
     *                             들어 궁합분석(20챕터)은 전부 보여주면 구매 전 콘텐츠가 다
     *                             드러나 버리니 대표성 있는 12개만 골라서 지정합니다.
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $price,
        public InputShape $inputShape,
        public array $chapters,
        public int $schemaVersion = 2,
        public array $previewChapterKeys = [],
    ) {
    }

    public function chapterCount(): int
    {
        return count($this->chapters);
    }

    public function chapterKeys(): array
    {
        return array_map(fn (ChapterSpec $c) => $c->key, $this->chapters);
    }

    public function findChapter(string $key): ?ChapterSpec
    {
        foreach ($this->chapters as $chapter) {
            if ($chapter->key === $key) {
                return $chapter;
            }
        }

        return null;
    }

    /**
     * 결제 전 목차 미리보기에 보여줄 챕터 목록. $previewChapterKeys가 비어있으면 $chapters
     * 전체(기존 동작, 하위 호환), 지정돼 있으면 그 key들을 $previewChapterKeys에 적힌
     * 순서 그대로 반환합니다(존재하지 않는 key는 조용히 건너뜀 — 오타 방지용 방어).
     *
     * @return array<int, ChapterSpec>
     */
    public function previewChapters(): array
    {
        if (empty($this->previewChapterKeys)) {
            return $this->chapters;
        }

        return array_values(array_filter(array_map(
            fn (string $key) => $this->findChapter($key),
            $this->previewChapterKeys,
        )));
    }
}
