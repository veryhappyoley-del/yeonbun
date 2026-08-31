<?php

namespace App\ReportTypes\Definitions;

use App\ReportTypes\ChapterSpec;
use App\ReportTypes\InputShape;
use App\ReportTypes\ReportType;
use App\ReportTypes\ReportTypeDefinition;

/**
 * "짝사랑 탈출" — "연애 재회 사주" 라인업(나의 연애사주/궁합보기/짝사랑 탈출/재회 리포트,
 * 최종 4종 목표) 중 세 번째로 추가하는 챕터형 프리미엄 리포트(2026-08-31 신설). 사용자가
 * 준 10개 섹션 프롬프트를 골자로 11개 챕터로 재구성했다 — CompatibilityReportType과
 * 똑같이 InputShape::TwoPerson을 쓰고, 결제 전 무료 궁합 계산(reports.js
 * buildUnrequitedInput())을 그대로 재사용한다("틀은 현재랑 동일하게" 요청사항).
 *
 * 사용자 원안과 이 구현의 차이(사용자가 "추가하거나 삭제할 건 알아서" 판단을 위임함):
 *   - ④ "이 짝사랑의 발전 가능성"(5단계: 현재관계→친밀감증가→호감형성→썸→연애, 4개
 *     전환 구간)을 progress_stages(4개 전환을 각각 stage_grid 카드로) + progress_scores
 *     (그 4개 구간의 점수를 radar_chart로 한눈에) 2개 챕터로 나눴다 — 사용자 원안의
 *     "각 단계마다 발전가능성/유리요소/방해요소/해야할행동/하지말아야할행동 + 10점 만점
 *     점수"를 한 챕터에 다 넣으면 텍스트가 너무 빽빽해서, 서술은 stage_grid로 점수는
 *     별도의 시각 차트로 분리했다.
 *   - ⑤ "언제 움직여야 하는가"(대운/세운 기준 연도→월 단위 우선순위 시기)는 AI에게
 *     직접 계산시키지 않는다 — 대운/세운은 결정론적 산수라 AI가 즉석에서 정확히 해낼 수
 *     있는 영역이 아니라서, public/js/luck-cycle.js가 실제로 계산한 후보 시기
 *     (input.timingCandidates)만 넘기고 AI는 그중에서 우선순위 3개를 골라 자연스러운
 *     문장으로 풀어 쓰는 역할만 한다(moving_timing 챕터, 새 블록
 *     resources/views/reports/partials/blocks/priority_timing.blade.php). 이 계산에는
 *     성별이 필요해서 saju.blade.php의 궁합 폼(A/B)에 성별 선택을 새로 추가했다.
 *   - ⑧ "계속해도 되는가"는 새 블록 verdict_badge(🟢계속도전/🟡천천히접근/🔴정리고려)로
 *     구현했다.
 *   - ⑨ "만약 이 사람이 아니라면"은 원안 그대로 서술형(paragraphs)으로 남겼다 — 여기서도
 *     새 인연의 "정확한 시기"를 지어내지 않고, personA의 사주 특징(용신/일간)을 근거로
 *     "어떤 특징의 사람이 잘 맞을지"만 다룬다(날짜를 또 지어내는 위험을 피함).
 *   - ⑩ "최종 결론"은 CompatibilityReportType의 final_verdict 패턴(quote+keywords+
 *     paragraphs)에 사용자가 요청한 "각 항목 점수"를 담을 radar_chart를 더했다.
 *
 * relationshipStage/primaryConcern(궁합분석에서 쓰는 "현재 관계 단계/지금 가장 궁금한
 * 것")은 이 리포트에는 없다 — 짝사랑은 정의상 아직 사귀는 사이가 아니라서 해당 선택지가
 * 자연스럽지 않기 때문에, 궁합 폼을 공유하되 이 두 필드는 이 타입의 프롬프트에서 아예
 * 참조하지 않는다.
 */
class UnrequitedLoveReportType implements ReportTypeDefinition
{
    public static function make(): ReportType
    {
        return new ReportType(
            key: 'unrequited_love',
            // (2026-08-31 수정) 브랜드 개편 — "짝사랑 탈출" → "짝사랑의 다음 장"(4종
            // 라인업의 03번). key/report_chapters는 그대로 유지, 화면 노출용 label만 변경.
            label: '짝사랑의 다음 장',
            price: 23900,
            inputShape: InputShape::TwoPerson,
            chapters: self::chapters(),
            previewChapterKeys: [
                'unrequited_overview', 'self_love_style', 'progress_stages', 'moving_timing',
                'approach_plan', 'things_to_avoid', 'final_verdict',
            ],
            // compat_overview와 같은 이유로 첫 챕터(궁합 개요)를 무료 티저로 쓴다 — 무료
            // 궁합 점수 바로 다음에 "이 짝사랑이 지금 어떤 상황인지"에 대한 진짜 답 일부를
            // 보여줘서 이탈 지점을 바로 해소한다. paragraphs만 쓰는 챕터라야 기존 무료 티저
            // 렌더러(public/js/app.js의 renderTeaserContent — paragraphs/concern_answer만
            // 이해함)가 그대로 동작한다.
            freePreviewChapterKey: 'unrequited_overview',
        );
    }

    /**
     * @return array<int, ChapterSpec>
     */
    private static function chapters(): array
    {
        $baseGuidance = "personA(짝사랑하는 사람, 이름이 있으면 그 이름으로)와 personB(짝사랑 상대, 이름이 ".
            "있으면 그 이름으로)를 자연스럽게 언급하며 쓰세요. 근거 없이 일반론만 쓰지 말고, 반드시 두 사람의 ".
            "사주 데이터와 연결하세요. '반드시/절대로/무조건' 같은 단정적 표현 대신 '~한 경향이 있습니다' 식으로 ".
            '쓰세요. 이 리포트는 아직 사귀는 사이가 아닌 짝사랑 상황을 다루므로, 이미 연인인 것처럼 단정하지 '.
            '말고 "다가가는", "가까워질 수 있는" 같은 탐색/가능성의 어조로 쓰세요.';

        return [
            new ChapterSpec(
                key: 'unrequited_overview',
                title: '이 짝사랑, 지금 어떤 상황일까',
                teaser: '두 사람의 궁합을 바탕으로, 지금 이 마음이 어떤 상황에 놓여 있는지 짚어봅니다.',
                schema: ['paragraphs' => ['', '', '']],
                promptGuidance: '이미 계산된 궁합 점수(score)와 등급(levelLabel), 짧은 풀이(notes)를 그대로 '.
                    '반복하지 말고, 두 사람의 관계(relation, 오행 상생상극 등)를 근거로 지금 이 짝사랑이 어떤 '.
                    '상황에 놓여 있는지 정확히 3문단(각 1~2문장, 90자 이내)으로 설명하세요. 아직 짝사랑 단계라는 '.
                    '전제를 유지하세요(이미 사귀는 사이처럼 쓰지 마세요). 두 사람의 이름은 이 챕터의 입력에 없으니 '.
                    "'나'와 '상대방'으로 자연스럽게 지칭하세요.",
                maxTokens: 1000,
                // (2026-08-31 수정) CompatibilityReportType의 compat_overview와 똑같은
                // 이유로 personA/personB(각자의 전체 deep 사주)는 넣지 않는다 — 이
                // 챕터는 결제 전 무료 티저로도 쓰이는데(freePreviewChapterKey), 티저
                // 요청(public/js/app.js)이 personA/personB 요약까지 만들어 보내려면
                // reports.js의 personSummary()를 app.js에서도 다시 구현해야 해서 중복이
                // 생긴다. score/levelLabel/notes/relation만으로도 3문단을 채우기엔
                // 충분하다.
                inputKeys: ['score', 'levelLabel', 'notes', 'relation'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'self_love_style',
                title: '나는 연애할 때 어떤 사람일까',
                teaser: '짝사랑을 시작하기 전에, 내 연애 기질부터 정확히 알아봅니다.',
                schema: ['paragraphs' => ['', ''], 'keywords' => ['', '', '']],
                promptGuidance: 'personA의 일간 오행/음양, 신강신약, 십신을 근거로 이 사람이 연애할 때 어떤 '.
                    '성향을 보이는지(마음을 여는 속도, 표현 방식, 밀당 스타일 등) 정확히 2문단(각 1~2문장, 90자 '.
                    '이내)으로 쓰세요. keywords에는 이 연애 성향을 압축하는 키워드 정확히 3개, 각 8자 이내의 '.
                    '짧은 명사형으로 쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA'],
                blocks: ['paragraphs', 'keyword_chips'],
            ),
            new ChapterSpec(
                key: 'other_signals',
                title: '상대방은 어떤 연애 성향을 가진 사람일까',
                teaser: '상대의 사주로 짐작해보는 연애 스타일과 마음을 여는 방식.',
                schema: ['paragraphs' => ['', ''], 'keywords' => ['', '', '']],
                promptGuidance: 'personB의 일간 오행/음양, 신강신약, 십신을 근거로 이 사람이 연애할 때 어떤 '.
                    '성향을 보일 가능성이 높은지(마음을 여는 속도, 표현 방식, 좋아하는 상대에게 보이는 신호 등) '.
                    '정확히 2문단(각 1~2문장, 90자 이내)으로 쓰세요. 확정적으로 단정하지 말고 "~한 편일 가능성이 '.
                    '있어요" 식으로 쓰세요(아직 상대의 마음을 확인한 게 아니므로). keywords에는 이 연애 성향을 '.
                    '압축하는 키워드 정확히 3개, 각 8자 이내로 쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personB'],
                blocks: ['paragraphs', 'keyword_chips'],
            ),
            new ChapterSpec(
                key: 'progress_stages',
                title: '이 짝사랑, 단계별로 보면 발전 가능성이 어느 정도일까',
                teaser: '현재 관계부터 연애까지, 4개 구간마다 발전 가능성과 해야 할 행동.',
                schema: [
                    'stages' => [
                        'stage_1' => [
                            'title' => '현재 관계 → 친밀감 증가',
                            'lines' => [
                                ['label' => '발전 가능성', 'text' => ''],
                                ['label' => '유리한 요소', 'text' => ''],
                                ['label' => '방해 요소', 'text' => ''],
                                ['label' => '해야 할 행동', 'text' => ''],
                                ['label' => '하지 말아야 할 행동', 'text' => ''],
                            ],
                        ],
                        'stage_2' => [
                            'title' => '친밀감 증가 → 호감 형성',
                            'lines' => [
                                ['label' => '발전 가능성', 'text' => ''],
                                ['label' => '유리한 요소', 'text' => ''],
                                ['label' => '방해 요소', 'text' => ''],
                                ['label' => '해야 할 행동', 'text' => ''],
                                ['label' => '하지 말아야 할 행동', 'text' => ''],
                            ],
                        ],
                        'stage_3' => [
                            'title' => '호감 형성 → 썸',
                            'lines' => [
                                ['label' => '발전 가능성', 'text' => ''],
                                ['label' => '유리한 요소', 'text' => ''],
                                ['label' => '방해 요소', 'text' => ''],
                                ['label' => '해야 할 행동', 'text' => ''],
                                ['label' => '하지 말아야 할 행동', 'text' => ''],
                            ],
                        ],
                        'stage_4' => [
                            'title' => '썸 → 연애',
                            'lines' => [
                                ['label' => '발전 가능성', 'text' => ''],
                                ['label' => '유리한 요소', 'text' => ''],
                                ['label' => '방해 요소', 'text' => ''],
                                ['label' => '해야 할 행동', 'text' => ''],
                                ['label' => '하지 말아야 할 행동', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: '두 사람의 사주 데이터를 근거로, "현재 관계→친밀감 증가→호감 형성→썸→연애"로 '.
                    '이어지는 4개 전환 구간 각각에 대해 위 스키마의 5개 줄을 채우세요. "발전 가능성" 줄은 반드시 '.
                    "'7/10 — 이유' 형태로(정수 점수/10 뒤에 em dash와 1문장 이유, 전체 60자 이내) 쓰세요. 나머지 ".
                    '4개 줄은 각 1문장(40자 이내)으로 구체적으로 쓰세요. title 필드는 이미 화면에 고정으로 표시할 '.
                    '것이므로 절대 바꾸지 마세요(내용만 채우세요).',
                // (2026-08-31 참고) love_language_gap(2단계×2줄=4줄, 900토큰)의 5배에
                // 가까운 분량(4단계×5줄=20줄)이라 예산도 비례해서 넉넉히 잡았다. 그래도
                // 부족하면 ChapterGenerator::effectiveMaxTokens()의 적응형 재시도가 자동으로
                // 예산을 올린다.
                maxTokens: 2800,
                inputKeys: ['personA', 'personB', 'score', 'relation'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'progress_scores',
                title: '발전 가능성, 점수로 한눈에 보면',
                teaser: '앞서 본 4개 구간의 발전 가능성을 그래프로 요약합니다.',
                schema: [
                    'scores' => [
                        'stage_1' => ['label' => '친밀감 증가', 'value' => 0],
                        'stage_2' => ['label' => '호감 형성', 'value' => 0],
                        'stage_3' => ['label' => '썸', 'value' => 0],
                        'stage_4' => ['label' => '연애', 'value' => 0],
                    ],
                    'scores_note' => '',
                ],
                promptGuidance: '바로 앞 챕터(progress_stages)에서 각 구간에 매긴 발전 가능성 점수(10점 만점)를 '.
                    '100점 만점으로 환산해 value에 넣으세요(예: 7/10 → 70). 라벨 텍스트는 절대 바꾸지 말고 value만 '.
                    '채우세요. scores_note는 네 구간을 통틀어 가장 중요한 포인트 1문장(90자 이내) 또는 빈 문자열.',
                maxTokens: 500,
                inputKeys: ['personA', 'personB', 'score', 'relation'],
                blocks: ['radar_chart'],
            ),
            new ChapterSpec(
                key: 'moving_timing',
                title: '언제 움직여야 할까 — 대운·세운으로 본 우선순위 시기',
                teaser: '실제로 계산된 대운/세운 흐름 중 다가가기 가장 좋은 시기 3곳.',
                schema: [
                    'picks' => [
                        ['period_label' => '', 'reason' => '', 'action' => ''],
                    ],
                    'overall_note' => '',
                ],
                promptGuidance: 'input.timingCandidates는 personA의 대운/세운/월운을 실제로 계산해서 이미 점수순 '.
                    "정렬해 둔 후보 시기 목록입니다(각 항목: year, month, periodLabel, score, reasons). 이 중에서 ".
                    "정확히 3개를 골라 순서대로 picks에 담으세요 — period_label 필드에는 반드시 그 후보의 ".
                    "periodLabel 값을 토씨 하나 안 틀리고 그대로 옮겨 적으세요(새로운 날짜나 연도를 지어내지 ".
                    "마세요, timingCandidates에 없는 시기는 언급하지 마세요). reason 필드는 그 후보의 reasons ".
                    '배열 내용을 자연스러운 한 문장(70자 이내)으로 풀어 쓰세요(사주 용어를 쉬운 말로). action '.
                    "필드에는 그 시기에 구체적으로 어떤 행동을 하면 좋을지 1문장(70자 이내)으로 쓰세요. ".
                    "overall_note에는 이 세 시기를 관통하는 전체적인 흐름을 1~2문장(120자 이내)으로 정리하세요.",
                maxTokens: 900,
                inputKeys: ['personA', 'timingCandidates'],
                blocks: ['priority_timing'],
            ),
            new ChapterSpec(
                key: 'approach_plan',
                title: '어떻게 다가가야 할까 — 4주 행동 전략',
                teaser: '한 주씩, 단계적으로 가까워지는 구체적인 행동 계획.',
                schema: ['steps' => ['', '', '', ''], 'key_point' => ''],
                promptGuidance: '두 사람의 사주 데이터를 근거로, 앞으로 4주 동안 한 주씩 밟아나갈 수 있는 구체적인 '.
                    '행동 전략을 steps(정확히 4개, 각 1주차~4주차 순서, 40자 이내)로 쓰세요. key_point에 이 4주 '.
                    '전략에서 가장 중요한 태도 1문장(90자 이내)을 쓰세요.',
                maxTokens: 800,
                inputKeys: ['personA', 'personB'],
                blocks: ['timeline'],
            ),
            new ChapterSpec(
                key: 'things_to_avoid',
                title: '이 짝사랑을 망칠 수 있는 행동 TOP 5',
                teaser: '나도 모르게 하기 쉬운, 그러나 반드시 피해야 할 행동들.',
                schema: ['items' => [['situation' => '', 'problem' => '', 'action' => '']]],
                promptGuidance: '두 사람의 사주 데이터를 근거로, personA가 무심코 하기 쉬우면서 이 짝사랑에 정말 '.
                    "해가 될 수 있는 행동을 정확히 5가지 고르세요(situation=위험 행동, problem=왜 문제가 되는지, ".
                    'action=대신 할 수 있는 행동, 각 필드 1문장 40~60자 이내).',
                maxTokens: 1500,
                inputKeys: ['personA', 'personB'],
                blocks: ['advice_cards'],
            ),
            new ChapterSpec(
                key: 'should_continue',
                title: '이 짝사랑, 계속해도 될까',
                teaser: '지금까지의 분석을 종합한 솔직한 판정.',
                schema: ['verdict' => 'continue', 'verdict_label' => '', 'reason' => ''],
                promptGuidance: "verdict 필드는 반드시 'continue'(계속 도전해볼 만함), 'slow'(천천히 접근하는 게 ".
                    "좋음), 'reconsider'(마음 정리를 고려해볼 만함) 중 정확히 하나의 문자열이어야 합니다(다른 ".
                    "값을 쓰지 마세요). verdict_label에는 그 판정을 짧은 한글 문구(예: '천천히, 하지만 가능성은 ".
                    "있어요', 12자 이내)로 쓰세요. reason에는 왜 그렇게 판정했는지 두 사람의 사주 데이터를 근거로 ".
                    '2~3문장(140자 이내)으로 쓰세요. 극단적으로 단정하지 말고, 근거를 균형 있게 제시하세요.',
                maxTokens: 700,
                inputKeys: ['personA', 'personB', 'score'],
                blocks: ['verdict_badge'],
            ),
            new ChapterSpec(
                key: 'if_not_this_person',
                title: '만약 이 사람이 아니라면',
                teaser: '지금 이 인연과 별개로, 나에게 잘 맞는 사람의 특징.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: 'personA의 일간 오행/음양과 용신(usefulGod)을 근거로, 지금 이 짝사랑과는 별개로 '.
                    'personA에게 전반적으로 잘 맞는 사람은 어떤 특징(성향/오행)을 가진 사람일지 정확히 2문단(각 '.
                    '1~2문장, 90자 이내)으로 쓰세요. 특정 시기나 날짜를 지어내지 말고, 사람의 특징에만 집중하세요.',
                maxTokens: 800,
                inputKeys: ['personA'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'final_verdict',
                title: '결론: 이 짝사랑을 한 문장으로 말한다면',
                teaser: '리포트 전체를 압축한 최종 결론과 종합 점수.',
                schema: [
                    'scores' => [
                        'compatibility' => ['label' => '기본 궁합', 'value' => 0],
                        'potential' => ['label' => '발전 가능성', 'value' => 0],
                        'timing' => ['label' => '타이밍 유리도', 'value' => 0],
                        'risk' => ['label' => '리스크 관리', 'value' => 0],
                    ],
                    'quote' => '',
                    'quote_variant' => 'final',
                    'keywords' => ['', '', '', '', ''],
                    'paragraphs' => [''],
                ],
                promptGuidance: '앞선 챕터들의 분석을 종합해서 scores 4개 지표를 0~100 정수로 산정하세요(라벨 '.
                    "텍스트는 절대 바꾸지 말고 value만 채우세요). quote에는 이 짝사랑을 관통하는 결론을 감성적이면서도 ".
                    "확신 있는 한 문장으로 쓰세요. quote_variant는 항상 정확히 'final' 문자열 그대로 두세요. ".
                    'keywords에는 이 짝사랑을 압축하는 키워드 정확히 5개, 각 8자 이내로 쓰세요. paragraphs에는 '.
                    '정확히 1문단(2~3문장, 140자 이내)으로 이 리포트를 마무리하는 따뜻한 응원의 메시지를 쓰세요.',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'score', 'levelLabel'],
                blocks: ['radar_chart', 'quote', 'keyword_chips', 'paragraphs'],
            ),
        ];
    }
}
