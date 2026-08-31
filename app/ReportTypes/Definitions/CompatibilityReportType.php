<?php

namespace App\ReportTypes\Definitions;

use App\ReportTypes\ChapterSpec;
use App\ReportTypes\InputShape;
use App\ReportTypes\ReportType;
use App\ReportTypes\ReportTypeDefinition;

/**
 * "궁합분석" — 기존 "프리미엄 궁합 리포트"(compat, 3,900원, 800~1200자 HTML 조각)를
 * 대체하는 챕터형 프리미엄 리포트. InputShape::TwoPerson을 쓰며, reports.js의
 * buildTwoPersonInput()이 보내는 { personA, personB, score, levelLabel, notes, relation }
 * 을 그대로 활용합니다 — personA/personB는 각자의 pillars/dayElement/dayYinYang/
 * wuxingCount/deep을 담고 있어서(연애운분석의 Self 입력과 같은 모양), 두 사람 각자의
 * 명리학적 특징까지 챕터마다 근거로 쓸 수 있습니다(예전 compat 입력은 일간 오행 정도만
 * 보냈는데, 20챕터를 채우기엔 부족해서 이번에 확장했습니다).
 *
 * 가격(price)은 계획 문서의 제안 범위(19,900~24,900원) 중 임시로 잡은 값입니다 —
 * ReportTypeRegistry에 실제로 등록해서 판매를 시작하는 5단계(컷오버) 시점에 최종
 * 확정합니다(아직 등록 전이라 이 값은 어디에도 영향을 주지 않습니다).
 *
 * (2026-08-24 개정) 20챕터 중 12개가 그냥 paragraphs(글 문단)만 쓰고 있어서, 연애운분석
 * 20단계에서 만든 radar_chart/timeline/keyword_chips/compare_cards 4종 시각 블록이
 * 궁합분석에는 하나도 안 쓰이던 걸 이번에 재배치:
 *   - compat_scores_a/b: score_bars → radar_chart(스키마 동일, blocks만 교체).
 *   - long_term_outlook: step_flow → timeline(스키마 동일, blocks만 교체).
 *   - two_people_profile/why_attracted/communication_style_gap: 원래 두 사람 각자를
 *     따로 설명하는 구조라 compare_cards(VS 비교 카드)가 자연스럽게 맞아서 paragraphs
 *     → compare(label은 실제 이름으로, text는 각자의 내용)로 재구성.
 *   - final_verdict: 연애운분석의 final_verdict와 같은 패턴으로 keywords 필드를
 *     분리해서 keyword_chips 추가(기존엔 "키워드 5개를 한 문장으로" 지침만 있고 실제
 *     태그 UI는 없었음).
 * elemental_dynamic처럼 "두 사람의 상호작용" 자체를 다루는 챕터(둘을 나란히 비교하는
 * 구조가 아님)나 이미 구조형 블록(strength_weakness/stage_grid/advice_cards)을 쓰는
 * 챕터는 이번엔 그대로 뒀다.
 *
 * (2026-08-24 추가) compat_overview에 concern_answer 필드/블록을 새로 추가 — 궁합 폼의
 * "지금 가장 궁금한 것"(primaryConcern/concernDetail)에 대해, 여러 챕터에 지침으로
 * 흩어서 "언급하며 답하라"고 하는 대신 리포트 맨 앞에서 질문 그대로 + 직접적인 답을
 * 눈에 띄게 보여준다(resources/views/reports/partials/blocks/concern_answer.blade.php
 * 참고). 이 필드가 새로 생기면서 이 챕터의 legacy report_chapters는
 * chapters:revalidate로 재검증이 필요하다.
 */
class CompatibilityReportType implements ReportTypeDefinition
{
    public static function make(): ReportType
    {
        return new ReportType(
            key: 'compatibility',
            // (2026-08-31 수정) 브랜드 개편 — "궁합분석" → "우리의 연애온도"(4종 라인업의
            // 02번). key/report_chapters는 그대로 유지, 화면 노출용 label만 변경.
            label: '우리의 연애온도',
            price: 21900,
            inputShape: InputShape::TwoPerson,
            chapters: self::chapters(),
            // (2026-08-24 추가) 20챕터 전부를 결제 전 목차 미리보기에 보여주면 구매 결정에
            // 필요한 궁금증이 다 해소돼버리니, 서사 흐름이 자연스럽게 이어지는 12개만 골라
            // 미리보기용으로 노출합니다(개요→매력→마찰→소통→갈등→지표→미래→위기→신뢰→
            // 표현방식→신호→결론). 나머지 8개(성장 방향/필요한 것/이상적 관계 모습 등)는
            // 구매 후에만 볼 수 있어요.
            previewChapterKeys: [
                'compat_overview', 'why_attracted', 'daily_life_friction', 'communication_style_gap',
                'conflict_pattern', 'compat_scores_a', 'long_term_outlook', 'crisis_moments',
                'jealousy_and_trust', 'love_language_gap', 'red_flags_green_flags', 'final_verdict',
            ],
            // (2026-08-24 추가) 무료 궁합 결과 화면에서 "점수만 띡 나오고 끝"이라는 피드백에
            // 대응해, compat_overview 챕터 하나만 결제 전에 실제로 생성해서 일부는 공개, 일부는
            // (진짜 콘텐츠를 그대로 둔 채) 시각적으로만 블러 처리하는 티저를 보여준다. 전체
            // 20챕터 중 이 챕터를 고른 이유: ① 무료 점수 바로 다음에 자연스럽게 이어지는 "왜
            // 이 점수가 나왔는지" 설명이라 이탈 지점을 바로 해소해주고, ② 20챕터 중 가장 저렴한
            // 챕터(maxTokens 1000)라 미리보기 비용이 가장 적게 들고, ③ concern_answer(사용자가
            // 직접 고른/적은 "가장 궁금한 것"에 대한 직접 답)까지 담고 있어 개인화 훅으로 가장
            // 강력하다. resources/views/reports/partials/blocks/concern_answer.blade.php,
            // App\Services\ChapterGenerator::previewInputHash()/savePreviewResponse(),
            // App\Http\Controllers\ChapterPreviewController 참고.
            freePreviewChapterKey: 'compat_overview',
        );
    }

    /**
     * @return array<int, ChapterSpec>
     */
    private static function chapters(): array
    {
        $baseGuidance = "두 사람의 이름(personA.name/personB.name, 없으면 'A'/'B')을 자연스럽게 언급하며 ".
            "쓰세요. 근거 없이 일반론만 쓰지 말고, 반드시 두 사람의 사주 데이터와 연결하세요. '반드시/절대로/".
            "무조건' 같은 단정적 표현 대신 '~한 경향이 있습니다' 식으로 쓰세요. relationshipStage(현재 관계 ".
            "단계 — seom/couple/married/breakup)가 있으면 그 단계에 맞는 톤과 시제로 쓰세요: seom(썸)은 ".
            '아직 확정되지 않은 설렘/탐색 단계로, couple(커플)은 이미 사귀는 사이의 현재진행형으로, '.
            "married(부부)는 장기적으로 함께 살아가는 관점으로, breakup(헤어짐)은 지나간 관계를 돌아보는 ".
            '회고적 톤(과거형)으로 씁니다. relationshipStage가 없으면 특정 단계를 가정하지 말고 중립적으로 쓰세요.';

        return [
            new ChapterSpec(
                key: 'compat_overview',
                title: '이 궁합, 숫자로 보면 어떤 관계일까',
                teaser: '이미 본 궁합 점수가 왜 이렇게 나왔는지 훨씬 구체적으로 풀어봅니다.',
                schema: ['concern_answer' => '', 'paragraphs' => ['', '', '']],
                promptGuidance: '이미 계산된 궁합 점수(score)와 등급(levelLabel), 그리고 무료로 보여준 짧은 '.
                    '풀이(notes)를 그대로 반복하지 말고, 그 점수가 왜 이렇게 나왔는지 두 사람의 관계(relation, '.
                    '오행 상생상극 등)를 근거로 정확히 3문단(각 1~2문장, 90자 이내)으로 훨씬 구체적으로 설명하세요. '.
                    "relationshipStage가 있으면 그 단계에 맞는 톤으로(seom/couple/married/breakup에 따라 ".
                    '설렘/현재진행형/장기 관점/회고 톤). primaryConcern 또는 concernDetail(사용자가 직접 고르거나 '.
                    '적은 궁금증)이 있으면 concern_answer 필드에 그 질문에 대한 직접적인 답을 정확히 2~4문장(180자 '.
                    "이내)으로 명확하게 쓰세요 — 뒤 챕터에서 더 자세히 다루더라도 여기서 핵심 답은 분명하게 줘야 ".
                    "합니다('자세한 건 뒤에서' 식으로 미루지 마세요). primaryConcern/concernDetail이 둘 다 없으면 ".
                    'concern_answer는 정확히 빈 문자열로 두세요(질문을 지어내지 마세요).',
                maxTokens: 1000,
                inputKeys: ['score', 'levelLabel', 'notes', 'relation', 'relationshipStage', 'primaryConcern', 'concernDetail'],
                blocks: ['concern_answer', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'two_people_profile',
                title: '두 사람은 각각 어떤 사주를 가진 사람들일까',
                teaser: '궁합을 논하기 전에, 각자가 어떤 사람인지부터.',
                schema: [
                    'compare' => [
                        'left' => ['label' => 'A', 'text' => ''],
                        'right' => ['label' => 'B', 'text' => ''],
                    ],
                ],
                promptGuidance: "compare.left/right의 label은 실제 이름(personA.name/personB.name, 없으면 ".
                    "'A'/'B')으로 바꿔 쓰세요. text에는 각자의 일간 오행/음양과 신강신약을 근거로, 궁합을 논하기 ".
                    '전에 이 사람이 어떤 사람인지 1~2문장(90자 이내)으로 요약하세요. 두 사람을 비교하지 말고 각자 '.
                    '독립적으로 설명하세요.',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'why_attracted',
                title: '두 사람은 왜 서로에게 끌렸을까',
                teaser: '서로에게 매력을 느끼는 구체적인 지점.',
                schema: [
                    'compare' => [
                        'left' => ['label' => 'A가 끌리는 지점', 'text' => ''],
                        'right' => ['label' => 'B가 끌리는 지점', 'text' => ''],
                    ],
                ],
                promptGuidance: "compare.left/right의 label은 실제 이름을 넣어 'OO가 끌리는 지점' 형태로 바꿔 ".
                    "쓰세요(이름이 없으면 'A가 끌리는 지점'/'B가 끌리는 지점' 그대로). text에는 두 사람의 사주 ".
                    '데이터를 근거로, 그 사람이 상대의 어떤 점에 끌리는지 1~2문장(90자 이내)으로 구체적으로 쓰세요. '.
                    $baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'elemental_dynamic',
                title: '오행으로 보면 두 사람은 어떤 관계일까',
                teaser: '상생인지 상극인지, 오행 궁합의 원리로 설명합니다.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: '두 사람 일간의 오행이 상생 관계인지 상극 관계인지, 혹은 같은 오행인지를 근거로 '.
                    '그것이 실제 관계에 어떤 영향을 주는지 정확히 2문단(각 1~2문장, 90자 이내)으로 설명하세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'daily_life_friction',
                title: '함께할 때 잘 맞는 점, 그리고 부딪히기 쉬운 지점',
                teaser: '같은 성향이 처음엔 장점이었다가 나중엔 마찰이 되는 지점.',
                schema: ['items' => [['strength' => '', 'escalation' => '', 'weakness' => '']]],
                promptGuidance: '두 사람 관계에서 처음엔 장점(strength)이었던 부분이 시간이 지나며 어떻게 마찰 '.
                    '지점(weakness)으로 바뀔 수 있는지, 그 사이(escalation)를 정확히 1~2개 항목으로 쓰세요(각 '.
                    '필드 1~2문장, 90자 이내).',
                maxTokens: 800,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['strength_weakness'],
            ),
            new ChapterSpec(
                key: 'communication_style_gap',
                title: '대화할 때 스타일 차이, 오해가 생기는 지점',
                teaser: '같은 말도 다르게 받아들이는 이유.',
                schema: [
                    'compare' => [
                        'left' => ['label' => 'A의 대화 스타일', 'text' => ''],
                        'right' => ['label' => 'B의 대화 스타일', 'text' => ''],
                    ],
                ],
                promptGuidance: "compare.left/right의 label은 실제 이름을 넣어 'OO의 대화 스타일' 형태로 바꿔 ".
                    '쓰세요. text에는 그 사람의 소통 방식과, 그 방식이 상대에게 어떻게 오해를 살 수 있는지 1~2문장'.
                    '(90자 이내)으로 쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'conflict_pattern',
                title: '싸우면 각자 어떻게 행동할까',
                teaser: '갈등이 생겼을 때 두 사람 각자의 반응 패턴.',
                schema: [
                    'stages' => [
                        'person_a' => [
                            'title' => 'A가 갈등 상황에서 보이는 모습',
                            'lines' => [['label' => '반응', 'text' => ''], ['label' => '신호', 'text' => '']],
                        ],
                        'person_b' => [
                            'title' => 'B가 갈등 상황에서 보이는 모습',
                            'lines' => [['label' => '반응', 'text' => ''], ['label' => '신호', 'text' => '']],
                        ],
                    ],
                ],
                promptGuidance: "title은 실제 이름으로 자연스럽게 바꿔서 쓰세요(예: 'OO가 갈등 상황에서 보이는 ".
                    "모습'). '신호' 줄은 1문장(40자 이내), '반응' 줄은 1~2문장(90자 이내)으로.",
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'reconciliation_style',
                title: '화해는 누가 먼저, 어떻게 하면 좋을까',
                teaser: '갈등 이후 관계를 회복하는 두 사람만의 방법.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: '갈등 이후 누가 먼저 다가가는 편이 자연스러운지, 어떤 방식의 화해가 두 사람에게 '.
                    '효과적일지 정확히 2문단(각 1~2문장, 90자 이내)으로 쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'compat_scores_a',
                title: '관계 궁합 지표 ① 독립성·현실감각·책임감',
                teaser: '두 사람이 함께일 때의 궁합을 수치로 짚어봅니다.',
                schema: [
                    'scores' => [
                        'independence' => ['label' => '독립성', 'value' => 0],
                        'realism' => ['label' => '현실 감각', 'value' => 0],
                        'responsibility' => ['label' => '책임감', 'value' => 0],
                    ],
                    'scores_note' => '',
                ],
                promptGuidance: '두 사람 각자가 아니라 "함께일 때"의 궁합 지표로 0~100 정수를 산정하세요(라벨 '.
                    '텍스트는 절대 바꾸지 말고 value만 채우세요). scores_note는 1~2문장(90자 이내) 또는 빈 문자열.',
                maxTokens: 700,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['radar_chart'],
            ),
            new ChapterSpec(
                key: 'compat_scores_b',
                title: '관계 궁합 지표 ② 의존도·감정기복·소통력·생활방식',
                teaser: '앞선 지표에 이어지는 나머지 4개 궁합 지표.',
                schema: [
                    'scores' => [
                        'dependency' => ['label' => '의존도', 'value' => 0],
                        'emotional_volatility' => ['label' => '감정 기복', 'value' => 0],
                        'communication' => ['label' => '소통력', 'value' => 0],
                        'lifestyle_compatibility' => ['label' => '생활 방식', 'value' => 0],
                    ],
                    'scores_note' => '',
                ],
                promptGuidance: 'compat_scores_a와 같은 방식으로 나머지 4개 지표를 산정하세요(라벨 텍스트는 '.
                    '절대 바꾸지 말고 value만 채우세요). scores_note는 1~2문장(90자 이내) 또는 빈 문자열.',
                maxTokens: 700,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['radar_chart'],
            ),
            new ChapterSpec(
                key: 'long_term_outlook',
                title: '오래 만나면 이 관계는 어떻게 흘러갈까',
                teaser: '초반부터 안정기까지, 관계가 흘러가는 5단계 흐름.',
                schema: ['steps' => ['', '', '', '', ''], 'key_point' => ''],
                promptGuidance: '두 사람의 관계가 시간이 지나며 어떻게 변화할 수 있는지를 5단계 흐름(steps, 정확히 '.
                    '5개, 각 40자 이내)으로 서술하고, key_point에 가장 중요한 포인트를 1문장(90자 이내)으로 쓰세요. '.
                    "운명론적 확언 금지. primaryConcern이 'continuity'(지속 가능성) 또는 'flow'(앞으로의 흐름) ".
                    '면 이 챕터가 사용자가 가장 궁금해하는 주제이니 특히 구체적으로 쓰고, key_point에서 그 관심사에 '.
                    '직접 답하듯 마무리하세요.',
                maxTokens: 800,
                inputKeys: ['personA', 'personB', 'relationshipStage', 'primaryConcern'],
                blocks: ['timeline'],
            ),
            new ChapterSpec(
                key: 'crisis_moments',
                title: '이 관계가 흔들릴 수 있는 순간들',
                teaser: '실제로 위기가 되기 쉬운 상황과 대처법 3가지.',
                schema: ['items' => [['situation' => '', 'problem' => '', 'action' => '']]],
                promptGuidance: '이 관계가 실제로 흔들리기 쉬운 상황을 정확히 3가지 골라서, 상황→문제→추천 행동 '.
                    "순서로 구체적인 조언을 쓰세요(각 필드 1~2문장, 90자 이내). primaryConcern이 'friction'".
                    '(충돌 완화)이면 이 챕터가 사용자가 가장 궁금해하는 주제이니 상황 3가지를 특히 구체적이고 '.
                    "실제 있을 법한 장면으로 고르세요. concernDetail(사용자가 직접 적은 궁금증)이 있으면 그 중 ".
                    '최소 1가지 상황은 그 내용과 직접 연결되게 쓰세요.',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage', 'primaryConcern', 'concernDetail'],
                blocks: ['advice_cards'],
            ),
            new ChapterSpec(
                key: 'strengths_to_build',
                title: '두 사람이 함께 키워갈 수 있는 강점',
                teaser: '이미 갖고 있는 좋은 궁합, 더 단단하게 만드는 법.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: '두 사람이 이미 갖고 있는 좋은 궁합 요소를 어떻게 더 단단하게 키워갈 수 있는지 '.
                    '정확히 2문단(각 1~2문장, 90자 이내)으로 쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'what_a_needs',
                title: '한 사람에게 필요한 것 — personA 편',
                teaser: '이 관계에서 personA가 더 편안해지려면 필요한 것.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: "personA(이름이 있으면 그 이름으로 자연스럽게 지칭)가 이 관계에서 더 편안하고 ".
                    '안정감을 느끼려면 상대(personB)가 어떻게 해주면 좋을지 정확히 2문단(각 1~2문장, 90자 이내)'.
                    '으로 쓰세요. 제목은 화면에 이미 있으니 본문에서 굳이 반복하지 마세요.',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'what_b_needs',
                title: '한 사람에게 필요한 것 — personB 편',
                teaser: '이 관계에서 personB가 더 편안해지려면 필요한 것.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: "personB(이름이 있으면 그 이름으로 자연스럽게 지칭)가 이 관계에서 더 편안하고 ".
                    '안정감을 느끼려면 상대(personA)가 어떻게 해주면 좋을지 정확히 2문단(각 1~2문장, 90자 이내)'.
                    '으로 쓰세요. what_a_needs 챕터와 대칭되게, 하지만 내용은 다르게 쓰세요.',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'jealousy_and_trust',
                title: '질투와 믿음, 두 사람은 어떻게 다룰까',
                teaser: '신뢰를 쌓는 방식과 불안이 커지는 지점.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: '두 사람이 서로에 대한 신뢰를 쌓는 방식과, 반대로 불안·질투가 커지기 쉬운 지점을 '.
                    '정확히 2문단(각 1~2문장, 90자 이내)으로 쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'love_language_gap',
                title: '사랑을 표현하는 방식의 차이',
                teaser: '같은 마음도 다르게 표현하는 두 사람.',
                schema: [
                    'stages' => [
                        'person_a' => [
                            'title' => 'A의 사랑 표현 방식',
                            'lines' => [['label' => '표현', 'text' => ''], ['label' => '상대가 알아채는 법', 'text' => '']],
                        ],
                        'person_b' => [
                            'title' => 'B의 사랑 표현 방식',
                            'lines' => [['label' => '표현', 'text' => ''], ['label' => '상대가 알아채는 법', 'text' => '']],
                        ],
                    ],
                ],
                promptGuidance: "title은 실제 이름으로 자연스럽게 바꿔서 쓰세요(예: 'OO의 사랑 표현 방식'). 각 ".
                    '줄 1~2문장(90자 이내).',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'ideal_relationship_shape',
                title: '이 두 사람에게 이상적인 관계의 모습',
                teaser: '억지로 맞추지 않고도 편안할 수 있는 관계의 형태.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: '이 두 사람이 서로를 억지로 바꾸려 하지 않고도 편안할 수 있는 이상적인 관계의 '.
                    '모습을 정확히 2문단(각 1~2문장, 90자 이내)으로 구체적으로 쓰세요.',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'red_flags_green_flags',
                title: '지금 이 관계에서 눈여겨봐야 할 신호들',
                teaser: '좋은 신호와 조심해야 할 신호를 함께 짚어봅니다.',
                schema: ['paragraphs' => ['좋은 신호', '조심해야 할 신호']],
                promptGuidance: '정확히 2문단(순서 고정): 1) 이 관계에서 이미 나타나고 있을 법한 좋은 신호(green '.
                    'flag), 2) 조심해서 지켜봐야 할 신호(red flag). 각 문단 1~2문장, 90자 이내. 운명론적 확언 금지.',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'relationshipStage'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'final_verdict',
                title: '결론: 이 두 사람의 궁합을 한 문장으로 말한다면',
                teaser: '리포트 전체를 압축한 최종 결론과 관계 키워드 5가지.',
                schema: ['quote' => '', 'quote_variant' => 'final', 'keywords' => ['', '', '', '', ''], 'paragraphs' => ['']],
                promptGuidance: 'quote에는 이 두 사람의 궁합을 관통하는 결론을 감성적이면서도 확신 있는 한 문장으로 '.
                    "쓰세요. quote_variant는 항상 정확히 'final'이라는 문자열 그대로 두세요(바꾸지 마세요). ".
                    'keywords에는 이 관계를 압축하는 키워드를 정확히 5개, 각 8자 이내의 짧은 명사형으로 쓰세요'.
                    '(문장이 아니라 태그처럼 화면에 그대로 보여집니다). paragraphs에는 정확히 1문단(2~3문장, 140자 '.
                    '이내)으로 이 리포트를 마무리하는 따뜻한 메시지를 쓰세요. concernDetail(사용자가 직접 적은 '.
                    '궁금증)이 있으면 — 이미 compat_overview에서 그 질문에 직접 답을 줬으니 여기서 다시 답을 '.
                    '반복하지 말고, 그 답을 알고 난 뒤 두 사람이 앞으로 어떻게 나아가면 좋을지로 자연스럽게 '.
                    '이어가며 마무리하세요.',
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'score', 'levelLabel', 'relationshipStage', 'concernDetail'],
                blocks: ['quote', 'keyword_chips', 'paragraphs'],
            ),
        ];
    }
}
