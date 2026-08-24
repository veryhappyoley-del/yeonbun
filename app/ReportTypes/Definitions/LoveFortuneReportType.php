<?php

namespace App\ReportTypes\Definitions;

use App\ReportTypes\ChapterSpec;
use App\ReportTypes\InputShape;
use App\ReportTypes\ReportType;
use App\ReportTypes\ReportTypeDefinition;

/**
 * "연애운분석" — 기존 "심층 연애 리포트"(single, 8,900원, 13개 섹션 단일 JSON)를
 * 대체하는 챕터형 프리미엄 리포트. 같은 입력(InputShape::Self, reports.js의
 * buildSingleInput()이 이미 보내는 pillars/dayElement/dayYinYang/wuxingCount/deep/
 * characterType)을 재사용하되, 예전엔 하나의 거대한 JSON 응답으로 요구하던 내용을
 * 20개의 독립된 작은 챕터로 쪼갰습니다 — 각 챕터가 별도의 (작고 안전한) Anthropic
 * 호출이 되어, 예전에 반복됐던 max_tokens truncation 문제 없이도 훨씬 풍부한
 * 분량을 안정적으로 생성할 수 있습니다.
 *
 * 챕터 순서/제목은 예전 single-report.blade.php의 섹션 구성(LOVE PROFILE → LOVE SCORE →
 * LOVE OS → WHO ATTRACTS YOU → STRENGTH/WEAKNESS → RECURRING PATTERN → PARTNER'S VIEW →
 * COMPATIBILITY → RELATIONSHIP ADVICE → FINAL VERDICT)을 그대로 계승하면서, myeongsado
 * 스타일의 "진짜 궁금해할 만한" 챕터 제목으로 세분화하고, LOVE OS/PARTNER'S VIEW의 4단계를
 * 각각 독립 챕터로 풀어서 분량과 깊이를 모두 확보했습니다.
 *
 * 가격(price)은 계획 문서의 제안 범위(14,900~19,900원) 중 임시로 잡은 값입니다 —
 * ReportTypeRegistry에 실제로 등록해서 판매를 시작하는 5단계(컷오버) 시점에 최종
 * 확정합니다(아직 등록 전이라 이 값은 어디에도 영향을 주지 않습니다).
 */
class LoveFortuneReportType implements ReportTypeDefinition
{
    public static function make(): ReportType
    {
        return new ReportType(
            key: 'love_fortune',
            label: '연애운분석',
            price: 16900,
            inputShape: InputShape::Self,
            chapters: self::chapters(),
        );
    }

    /**
     * @return array<int, ChapterSpec>
     */
    private static function chapters(): array
    {
        $baseGuidance = '모든 문장은 사주 데이터와 실제로 연결되어야 하며, 성격을 나열하지 말고 '.
            "반드시 '성격 → 실제 행동 → 상황 → 결과'까지 이어서 쓰세요. '반드시/절대로/무조건' 같은 ".
            "단정적 표현 대신 '~한 경향이 있습니다' 식으로 쓰세요.";

        return [
            new ChapterSpec(
                key: 'origin_profile',
                title: '이 사주, 연애로 풀면 어떤 그림이 나올까',
                teaser: '사주 원국과 오행 분포를 기준으로 연애 성향의 출발점을 짚어봅니다.',
                schema: ['paragraphs' => ['', '']],
                promptGuidance: '연월일시 사주 원국 전체와 오행 분포를 바탕으로, 이 사람의 연애 성향을 '.
                    "이해하기 위한 '출발점'을 정확히 2문단으로 설명하세요. 아직 결론을 내리지 말고, ".
                    '뒤에 이어질 챕터들이 어떤 근거 위에서 전개될지 보여주는 도입부로 쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['pillars', 'dayElement', 'dayYinYang', 'wuxingCount'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'why_this_style',
                title: '지금의 연애 성향은 어디서 비롯됐을까',
                teaser: '일간의 오행·음양과 신강신약을 근거로 성향의 뿌리를 설명합니다.',
                schema: ['paragraphs' => ['', '', '']],
                promptGuidance: '일간의 오행/음양, 신강신약(deep.dayMasterStrength), 십신 분포(deep.tenGods)를 '.
                    "근거로 '왜 이런 연애 성향을 갖게 됐는지'를 명리학적으로 설명하세요. 근거→해석 순서로 ".
                    '정확히 3문단. '.$baseGuidance,
                maxTokens: 1100,
                inputKeys: ['pillars', 'dayElement', 'dayYinYang', 'deep'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'character_link',
                title: '무료 카드로 본 내 유형, 사주로 다시 풀면',
                teaser: '이미 본 연애 캐릭터 유형이 왜 이렇게 나왔는지 사주 근거로 설명합니다.',
                schema: ['paragraphs' => ['', '', '', '', '']],
                promptGuidance: '입력에 characterType이 있으면, 그 유형(typeName/oneLiner/trait)이 이미 보여준 '.
                    "한줄평을 반복하지 말고 '이 유형이 왜 이 사주 요소에서 비롯됐는지 → 실제 행동 → 강해지는 ".
                    "상황 → 장점으로 작용할 때 → 과해졌을 때 문제' 순서로 정확히 5문단(문단당 1~2문장)으로 쓰세요. ".
                    'characterType이 없으면, 대신 일간 오행/음양만으로 유추할 수 있는 이 사람의 핵심 연애 캐릭터를 '.
                    '새로 정의해서 같은 구조로 쓰세요. '.$baseGuidance,
                maxTokens: 1300,
                inputKeys: ['characterType', 'dayElement', 'dayYinYang', 'deep'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'love_profile',
                title: '한 문장으로 정리한 나의 연애 타입',
                teaser: '이 리포트 전체를 관통하는 핵심 요약과 키워드 3가지.',
                schema: ['quote' => '', 'paragraphs' => ['']],
                promptGuidance: "이 사람의 연애를 한 문장(quote)으로 압축해서 표현하세요(예: '무모하게 뜨겁다', ".
                    "'천천히 스며든다' 같은 인상적인 표현). paragraphs에는 그 문장을 뒷받침하는 키워드 정확히 ".
                    '3개를 자연스러운 한 문장으로 풀어 설명하세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['dayElement', 'dayYinYang', 'deep', 'loveStyle', 'loveCharm'],
                blocks: ['quote', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'expression_scores',
                title: '호감·표현·몰입, 나는 연애에서 얼마나 적극적일까',
                teaser: '호감 표현·관계 주도력·몰입도·감정 표현·인내심, 5개 지표.',
                schema: [
                    'scores' => [
                        'attraction_expression' => ['label' => '호감 표현', 'value' => 0],
                        'relationship_leadership' => ['label' => '관계 주도력', 'value' => 0],
                        'devotion' => ['label' => '몰입도', 'value' => 0],
                        'emotional_expression' => ['label' => '감정 표현', 'value' => 0],
                        'patience' => ['label' => '인내심', 'value' => 0],
                    ],
                    'scores_note' => '',
                ],
                promptGuidance: '사주 데이터(신강신약·십신 분포·오행 균형)에 근거해서 5개 지표를 0~100 정수로 '.
                    '산정하세요(라벨 텍스트는 절대 바꾸지 말고 value만 채우세요). scores_note에는 점수 조합이 '.
                    '모순돼 보일 수 있는 부분(예: 호감 표현은 높은데 인내심은 낮음)이 있다면 정확히 1~2문장으로 '.
                    '설명하고, 없으면 빈 문자열로 두세요.',
                maxTokens: 700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['score_bars'],
            ),
            new ChapterSpec(
                key: 'stability_scores',
                title: '관계가 흔들릴 때, 나는 얼마나 버틸까',
                teaser: '관계 정리력·질투심·밀당 내성·안정감·갈등 해결력, 5개 지표.',
                schema: [
                    'scores' => [
                        'relationship_cutoff' => ['label' => '관계 정리력', 'value' => 0],
                        'jealousy' => ['label' => '질투심', 'value' => 0],
                        'push_pull_tolerance' => ['label' => '밀당 내성', 'value' => 0],
                        'relationship_stability' => ['label' => '관계 안정감', 'value' => 0],
                        'conflict_resolution' => ['label' => '갈등 해결력', 'value' => 0],
                    ],
                    'scores_note' => '',
                ],
                promptGuidance: '사주 데이터에 근거해서 5개 지표를 0~100 정수로 산정하세요(라벨 텍스트는 절대 '.
                    '바꾸지 말고 value만 채우세요). scores_note는 expression_scores 챕터와 겹치지 않는 새로운 '.
                    '해석으로, 없으면 빈 문자열로 두세요.',
                maxTokens: 700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['score_bars'],
            ),
            new ChapterSpec(
                key: 'attraction_stage',
                title: '호감이 생기고 커질 때, 내 안에서 무슨 일이 벌어질까',
                teaser: '감정·생각·행동, 그리고 상대가 눈치챌 수 있는 신호까지.',
                schema: [
                    'stages' => [
                        'attraction' => [
                            'title' => '호감이 생기고 커질 때',
                            'lines' => [
                                ['label' => '감정', 'text' => ''],
                                ['label' => '생각', 'text' => ''],
                                ['label' => '행동', 'text' => ''],
                                ['label' => '신호', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: "'신호' 줄에는 이 단계에서 상대가 실제로 눈치챌 수 있는 짧은 신호를 1문장(40자 ".
                    '이내)으로 쓰세요. 나머지 줄은 정확히 1~2문장, 90자 이내로. '.$baseGuidance,
                maxTokens: 700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'relationship_start_stage',
                title: '관계가 막 시작될 때, 나는 어떻게 행동할까',
                teaser: '썸에서 연인으로 넘어가는 순간의 감정·생각·행동.',
                schema: [
                    'stages' => [
                        'relationship_start' => [
                            'title' => '관계가 시작될 때',
                            'lines' => [
                                ['label' => '감정', 'text' => ''],
                                ['label' => '생각', 'text' => ''],
                                ['label' => '행동', 'text' => ''],
                                ['label' => '신호', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: '앞선 attraction_stage(호감이 생길 때)와 다른 시점 — 이미 서로 마음을 확인하고 '.
                    "관계가 막 시작되는 순간에 집중하세요. '신호' 줄은 1문장(40자 이내). 나머지는 정확히 1~2문장, ".
                    '90자 이내로. '.$baseGuidance,
                maxTokens: 700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'relationship_stage_deep',
                title: '관계를 이어가는 동안 반복되는 나의 패턴',
                teaser: '연애가 안정기에 접어들었을 때의 감정·생각·행동.',
                schema: [
                    'stages' => [
                        'relationship_stage' => [
                            'title' => '관계를 이어갈 때',
                            'lines' => [
                                ['label' => '감정', 'text' => ''],
                                ['label' => '생각', 'text' => ''],
                                ['label' => '행동', 'text' => ''],
                                ['label' => '신호', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: '연애가 어느 정도 안정된 이후, 이 사람이 관계를 어떻게 운영하는지에 집중하세요. '.
                    "'신호' 줄은 1문장(40자 이내). 나머지는 정확히 1~2문장, 90자 이내로. ".$baseGuidance,
                maxTokens: 700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'conflict_crisis_stage',
                title: '갈등과 위기가 찾아오면 나는 어떤 사람이 될까',
                teaser: '싸움·권태·위기 상황에서의 감정·생각·행동.',
                schema: [
                    'stages' => [
                        'conflict_crisis' => [
                            'title' => '갈등·위기가 올 때',
                            'lines' => [
                                ['label' => '감정', 'text' => ''],
                                ['label' => '생각', 'text' => ''],
                                ['label' => '행동', 'text' => ''],
                                ['label' => '신호', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: "갈등/위기 상황에서의 반응에 집중하세요. '신호' 줄은 1문장(40자 이내). 나머지는 ".
                    '정확히 1~2문장, 90자 이내로. '.$baseGuidance,
                maxTokens: 700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'who_attracts_you',
                title: '나를 강하게 끌어당기는 사람은 어떤 사람일까',
                teaser: '강하게 끌리는 사람과, 처음엔 강하지만 오래가긴 어려운 상대의 차이.',
                schema: [
                    'stages' => [
                        'strongly_attracted' => [
                            'title' => '강하게 끌리는 사람',
                            'lines' => [
                                ['label' => '설명', 'text' => ''],
                                ['label' => '특징', 'text' => ''],
                            ],
                        ],
                        'short_term_attraction' => [
                            'title' => '처음엔 강하지만 오래가긴 어려운 사람',
                            'lines' => [
                                ['label' => '설명', 'text' => ''],
                                ['label' => '특징', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: "'어떤 사람에게 강하게/일시적으로 끌리는가'(끌림 그 자체)만 다루세요. '장기적으로 잘 ".
                    "맞는 사람'은 compatibility_match 챕터에서 다루니 여기서 언급하지 마세요. '특징' 줄은 짧은 ".
                    "특징 2개를 쉼표로 이어서 한 줄(40자 이내)로 쓰세요. '설명' 줄은 1~2문장, 90자 이내.",
                maxTokens: 800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'strength_weakness',
                title: '장점이 과해지면 왜 단점이 될까',
                teaser: '같은 성향의 양면 — 강점, 그것이 과도해질 때, 그리고 약점.',
                schema: ['items' => [['strength' => '', 'escalation' => '', 'weakness' => '']]],
                promptGuidance: "장점과 약점을 별개 성향이 아니라 '같은 성향이 과도해질 때'의 양면으로, items는 ".
                    '정확히 1~2개만 쓰세요(strength → escalation(과도해지면) → weakness 순서로 자연스럽게 이어지게, '.
                    '각 필드 1~2문장 90자 이내).',
                maxTokens: 700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['strength_weakness'],
            ),
            new ChapterSpec(
                key: 'recurring_pattern',
                title: '나도 모르게 반복되는 연애 패턴',
                teaser: '관계가 애매해지거나 갈등이 생길 때 실제로 반복되는 5단계.',
                schema: ['steps' => ['', '', '', '', ''], 'key_point' => ''],
                promptGuidance: '관계가 애매하면·갈등이 생기면 실제로 어떻게 행동하는지를 5단계의 흐름(steps, 정확히 '.
                    '5개, 각 단계 40자 이내)으로 서술하고, key_point에 이 패턴에서 가장 중요한 포인트를 1문장(90자 '.
                    '이내)으로 쓰세요.',
                maxTokens: 800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['step_flow'],
            ),
            new ChapterSpec(
                key: 'partner_view_early',
                title: '상대방 눈에 비친 나: 만남부터 관계의 시작까지',
                teaser: '겉으로 보이는 모습과 상대만 아는 내면의 차이.',
                schema: [
                    'stages' => [
                        'attraction' => [
                            'title' => '① 처음 만났을 때',
                            'lines' => [
                                ['label' => '겉으로는', 'text' => ''],
                                ['label' => '내면에서는', 'text' => ''],
                            ],
                        ],
                        'relationship_start' => [
                            'title' => '② 관계가 시작됐을 때',
                            'lines' => [
                                ['label' => '겉으로는', 'text' => ''],
                                ['label' => '내면에서는', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: "'상대가 보는 나'의 관점에서 겉모습과 내면의 차이를 쓰세요. 앞선 attraction_stage/ ".
                    "relationship_start_stage(나의 내면 자체)와 겹치지 않게, 반드시 '상대방이 관찰할 수 있는 모습' ".
                    '대비 실제 내면의 괴리에 집중하세요(각 줄 1~2문장, 90자 이내).',
                maxTokens: 800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'partner_view_late',
                title: '상대방 눈에 비친 나: 관계가 깊어질 때부터 위기까지',
                teaser: '가까워질수록, 그리고 흔들릴 때 상대가 느끼는 나의 모습.',
                schema: [
                    'stages' => [
                        'relationship_stage' => [
                            'title' => '③ 관계가 깊어질 때',
                            'lines' => [
                                ['label' => '겉으로는', 'text' => ''],
                                ['label' => '내면에서는', 'text' => ''],
                            ],
                        ],
                        'conflict_crisis' => [
                            'title' => '④ 갈등·위기가 왔을 때',
                            'lines' => [
                                ['label' => '겉으로는', 'text' => ''],
                                ['label' => '내면에서는', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: 'partner_view_early와 이어지는 뒷부분입니다(중복 없이). 각 줄 1~2문장, 90자 이내.',
                maxTokens: 800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'compatibility_scores',
                title: '나와 잘 맞는 사람은 어떤 유형일까 (궁합 지표)',
                teaser: '독립성·현실감각·책임감 등 7개 지표로 보는 궁합 성향.',
                schema: [
                    'scores' => [
                        'independence' => ['label' => '독립성', 'value' => 0],
                        'realism' => ['label' => '현실 감각', 'value' => 0],
                        'responsibility' => ['label' => '책임감', 'value' => 0],
                        'dependency' => ['label' => '의존도', 'value' => 0],
                        'emotional_volatility' => ['label' => '감정 기복', 'value' => 0],
                        'communication' => ['label' => '소통력', 'value' => 0],
                        'lifestyle_compatibility' => ['label' => '생활 방식', 'value' => 0],
                    ],
                    'scores_note' => '',
                ],
                promptGuidance: "'나 자신의 연애 성향'이 아니라 '상대와 함께일 때의 궁합'에 초점을 맞춘 지표입니다. ".
                    '앞서 나온 LOVE SCORE류 지표(호감 표현, 관계 주도력, 안정감 등)와 겹치는 해석을 반복하지 마세요 '.
                    '(라벨 텍스트는 절대 바꾸지 말고 value만 채우세요).',
                maxTokens: 700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['score_bars'],
            ),
            new ChapterSpec(
                key: 'compatibility_match',
                title: '잘 맞는 상대, 조심할 상대, 이상적인 관계',
                teaser: '구체적인 상대 유형과 이상적인 관계의 모습.',
                schema: ['paragraphs' => ['잘 맞는 상대', '조심할 상대', '이상적인 관계']],
                promptGuidance: '정확히 3문단(순서 고정): 1) 잘 맞는 상대 유형, 2) 조심해야 할 상대 유형, 3) 이 '.
                    '사람에게 이상적인 관계의 모습. 각 문단은 정확히 1~2문장, 90자 이내로 구체적으로.',
                maxTokens: 800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'relationship_advice',
                title: '이런 상황이 오면, 이렇게 행동해보세요',
                teaser: '실제로 부딪히기 쉬운 상황별 맞춤 조언 3가지.',
                schema: ['items' => [['situation' => '', 'problem' => '', 'action' => '']]],
                promptGuidance: '이 사람이 실제로 자주 마주치는 연애 상황을 정확히 3가지 골라서, 상황→문제→추천 '.
                    '행동 순서로 구체적인 조언을 쓰세요(각 필드 1~2문장, 90자 이내). 운명론적 확언 금지 — '.
                    "'~한 경향이 있습니다', '~해보는 것도 방법입니다' 식으로.",
                maxTokens: 900,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['advice_cards'],
            ),
            new ChapterSpec(
                key: 'opposite_type',
                title: '나와 정반대 성향의 사람을 만나면 어떻게 될까',
                teaser: '비슷한 사람 vs 정반대 사람, 실제로 어떤 차이가 있을지.',
                schema: ['paragraphs' => ['', '', '']],
                promptGuidance: '이 사람의 오행/음양과 정반대되는 성향의 상대를 만났을 때 실제로 어떤 역학이 생기는지'.
                    '(초반 매력 vs 장기적 마찰 지점)를 정확히 3문단으로 구체적으로 서술하세요. 앞선 '.
                    "compatibility_match 챕터와 겹치지 않게, '비슷한 유형 vs 반대 유형'이라는 새로운 각도로 ".
                    '쓰세요(각 문단 1~2문장, 90자 이내).',
                maxTokens: 900,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'final_verdict',
                title: '결론: 이 사람의 연애를 한 문장으로 말한다면',
                teaser: '리포트 전체를 압축한 최종 결론과 연애 키워드 5가지.',
                schema: ['quote' => '', 'quote_variant' => 'final', 'paragraphs' => ['']],
                promptGuidance: 'quote에는 이 리포트 전체를 관통하는 결론을 감성적이면서도 확신 있는 한 문장으로 '.
                    "쓰세요. quote_variant는 항상 정확히 'final'이라는 문자열 그대로 두세요(바꾸지 마세요). ".
                    'paragraphs에는 연애 키워드 정확히 5개를 자연스러운 한 문장으로 나열하세요.',
                maxTokens: 800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['quote', 'paragraphs'],
            ),
        ];
    }
}
