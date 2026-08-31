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
 * 가격은 27,000원으로 확정(2026-08-24). myeongsado 등 경쟁사의 챕터형 프리미엄
 * 리포트 가격대와 20챕터 분량을 감안해 책정했습니다.
 *
 * (품질 개선 — 2026-08-24) 운영 배포 직후 실측에서 20챕터 중 16개가 max_tokens
 * truncation으로 실패했습니다(챕터당 maxTokens가 700~1300으로 너무 낮았음 — 레거시
 * 단일호출 리포트가 비슷한 분량의 콘텐츠에 결국 14000까지 필요했던 전례와 대조됨).
 * 이번 개정에서: ① 챕터별 maxTokens를 전반적으로 1.8~3배 상향(1300~2700), ②
 * 글자수 상한 지침을 살짝 완화(예: 90자→140자)해서 실제로 더 풍부한 문장을 쓸 수
 * 있게 하고, ③ 일부 챕터의 스키마 자체를 확장(문단/스텝/조언 개수 증가)해서 27,000원
 * 가격에 맞는 분량감을 확보했습니다. ④ 목차 제목도 더 구체적이고 궁금증을 유발하는
 * 문구로 다듬었습니다(챕터 key는 이미 생성된 report_chapters와의 호환을 위해 그대로
 * 유지 — 제목/스키마/지침만 바뀝니다).
 *
 * (시각 블록 확장 — 2026-08-24, 21단계) "27,000원인데 그냥 글만 있다"는 지적에 대응해
 * 새 블록 4종(radar_chart/timeline/keyword_chips/compare_cards, resources/views/
 * reports/partials/blocks/ 참고)을 만들고 아래 9개 챕터에 배정했습니다:
 *   - expression_scores/stability_scores/compatibility_scores: score_bars(막대 목록)
 *     → radar_chart(방사형 차트). scores/scores_note 데이터 모양은 그대로라 스키마/
 *     프롬프트 변경 없이 blocks만 교체.
 *   - recurring_pattern: step_flow(번호 목록) → timeline(연결선 + 오행 색 순환 노드).
 *     steps/key_point 데이터 모양도 그대로라 blocks만 교체.
 *   - love_profile/final_verdict: 문단 속에 자연어로 풀어 쓰던 키워드를 keywords
 *     배열로 분리해서 keyword_chips(태그 pill)로 렌더링. paragraphs는 그만큼 1개로
 *     줄임(기존 둘째 문단 내용만 유지).
 *   - who_attracts_you: stage_grid(2칸 카드) → compare_cards(VS 구분선 세로 비교).
 *     기존 "설명"+"특징" 줄을 compare.{side}.text + compare.{side}.tags(짧은 특징
 *     키워드 3개)로 재구성.
 *   - opposite_type/compatibility_match: 기존 문단들 중 명백히 대비되는 두 항목(초반
 *     매력 vs 장기 마찰, 잘 맞는 상대 vs 조심할 상대)을 compare_cards로 분리하고,
 *     나머지는 paragraphs로 유지(compatibility_match는 4문단 → compare 1쌍 + 문단 2개).
 *
 * (maxTokens 상향 — 2026-08-25) 사용자가 실사용 중 20챕터 중 1개가 계속 "재시도 필요"로
 * 뜨는 걸 발견해서, 20개 중 요구 분량이 가장 많은 두 챕터의 여유를 더 키웠습니다:
 * character_link(5문단, 2700→3400), relationship_advice(4개 항목×3필드, 2600→3200).
 * ChapterGenerator::effectiveMaxTokens()가 max_tokens 도달로 인한 실패는 재시도마다
 * 자동으로 예산을 올려주므로(1.6배→2.2배→... 최대 8000), 이 상향은 "첫 시도부터 잘릴
 * 확률"을 낮추기 위한 것입니다 — 만약 실패 원인이 토큰 부족이 아니라 스키마 불일치
 * (last_error='schema_mismatch'/'schema_type_mismatch')였다면 이 상향만으로는 해결되지
 * 않으니, 상향 이후에도 같은 챕터가 계속 실패하면 report_chapters.last_error를 확인해야
 * 합니다. max_tokens는 상한선일 뿐 실제 사용한 토큰만큼만 과금되므로, 이 상향 자체가
 * 나머지 18개 챕터의 비용을 늘리지는 않습니다.
 *
 * (무료 티저 추가 — 2026-08-25) 사용자가 "나의 연애 사주 보기"와 "궁합 보기"의 결제
 * 유도 방식이 다르다고 지적 — 궁합분석은 compat_overview 챕터를 결제 전에 실제로 미리
 * 생성해서 일부는 공개/일부는 블러 처리하는데(App\ReportTypes\Definitions\
 * CompatibilityReportType::$freePreviewChapterKey 참고), 연애운분석은 그 기능이 아예
 * 없어서 "정보만 잔뜩 보여주고 버튼만 아래에 있는" 다른 느낌이었다. 두 리포트가 같은
 * 결제 유도 경험을 갖도록 이 타입에도 freePreviewChapterKey를 추가한다.
 *
 * 챕터는 origin_profile(1번 챕터)을 골랐다 — 이유는 compat_overview를 고른 기준과
 * 동일하게 ① 무료 화면(사주 명식/캐릭터 카드) 바로 다음에 자연스럽게 이어지는 챕터형
 * 리포트의 "도입부"라 이탈 지점을 바로 해소해주고, ② 20챕터 중 비교적 저렴한 편(maxTokens
 * 1600 — character_link의 3400보다 훨씬 쌈)이라 미리보기 비용 부담이 적고, ③ schema가
 * paragraphs 3개뿐인 가장 단순한 형태라 compat_overview와 같은 프론트 렌더 로직
 * (public/js/app.js의 renderTeaserContent, 원래 이름 startCompatPreview였다가 두 타입이
 * 공유하도록 startChapterPreview로 일반화)을 그대로 재사용할 수 있다. concern_answer는
 * 안 붙였다 — "나의 연애 사주" 폼에는 궁합 폼과 달리 "지금 가장 궁금한 것" 선택 UI가
 * 없어서 물어볼 질문 자체가 없기 때문(나중에 그 폼이 생기면 그때 추가).
 */
class LoveFortuneReportType implements ReportTypeDefinition
{
    public static function make(): ReportType
    {
        return new ReportType(
            key: 'love_fortune',
            // (2026-08-31 수정) "연애 재회 사주" 4종 브랜드 개편 — 사용자가 지정한 번호
            // 순서(01. 연애의 나침반/02. 우리의 연애온도/03. 짝사랑의 다음 장/04. 다시, 우리)에
            // 맞춰 예전 라벨("연애운분석")을 새 브랜드명으로 바꿨다. key는 이미 판매/저장된
            // report_chapters와 맞물려 있어 그대로 유지하고 label(화면 노출용)만 바꾼다.
            label: '연애의 나침반',
            price: 27000,
            inputShape: InputShape::Self,
            chapters: self::chapters(),
            freePreviewChapterKey: 'origin_profile',
        );
    }

    /**
     * @return array<int, ChapterSpec>
     */
    private static function chapters(): array
    {
        $baseGuidance = '모든 문장은 사주 데이터와 실제로 연결되어야 하며, 성격을 나열하지 말고 '.
            "반드시 '성격 → 실제 행동 → 상황 → 결과'까지 이어서 쓰세요. '반드시/절대로/무조건' 같은 ".
            "단정적 표현 대신 '~한 경향이 있습니다' 식으로 쓰세요. 뭉뚱그리지 말고 구체적인 상황과 ".
            '예시를 들어 설명해서, 읽는 사람이 실제 자기 모습을 떠올릴 수 있게 쓰세요.';

        return [
            new ChapterSpec(
                key: 'origin_profile',
                title: '내 연애의 설계도 — 사주 원국이 말해주는 시작점',
                teaser: '사주 원국과 오행 분포를 기준으로 연애 성향의 출발점을 짚어봅니다.',
                schema: ['paragraphs' => ['', '', '']],
                promptGuidance: '연월일시 사주 원국 전체와 오행 분포를 바탕으로, 이 사람의 연애 성향을 '.
                    "이해하기 위한 '출발점'을 정확히 3문단(문단당 2~3문장, 각 140자 이내)으로 설명하세요. ".
                    '아직 결론을 내리지 말고, 뒤에 이어질 챕터들이 어떤 근거 위에서 전개될지 보여주는 '.
                    '도입부로 쓰세요. 단순히 오행 분포를 나열하지 말고, 그 분포가 왜 연애에서 중요한 '.
                    '단서가 되는지까지 설명하세요. '.$baseGuidance,
                maxTokens: 1600,
                inputKeys: ['pillars', 'dayElement', 'dayYinYang', 'wuxingCount'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'why_this_style',
                title: '왜 하필 이런 연애를 하게 됐을까 — 일간과 신강신약이 만든 뿌리',
                teaser: '일간의 오행·음양과 신강신약을 근거로 성향의 뿌리를 설명합니다.',
                schema: ['paragraphs' => ['', '', '', '']],
                promptGuidance: '일간의 오행/음양, 신강신약(deep.dayMasterStrength), 십신 분포(deep.tenGods)를 '.
                    "근거로 '왜 이런 연애 성향을 갖게 됐는지'를 명리학적으로 설명하세요. 근거→해석 순서로 ".
                    '정확히 4문단(문단당 2~3문장, 각 140자 이내) — 오행/음양 근거, 신강신약 근거, 십신 근거, '.
                    '그리고 이 세 가지가 합쳐졌을 때의 종합 해석 순서로 쓰세요. '.$baseGuidance,
                maxTokens: 2100,
                inputKeys: ['pillars', 'dayElement', 'dayYinYang', 'deep'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'character_link',
                title: '무료로 본 내 유형, 사주 근거로 다시 파헤치면',
                teaser: '이미 본 연애 캐릭터 유형이 왜 이렇게 나왔는지 사주 근거로 설명합니다.',
                schema: ['paragraphs' => ['', '', '', '', '']],
                promptGuidance: '입력에 characterType이 있으면, 그 유형(typeName/oneLiner/trait)이 이미 보여준 '.
                    "한줄평을 반복하지 말고 '이 유형이 왜 이 사주 요소에서 비롯됐는지 → 실제 행동 → 강해지는 ".
                    "상황 → 장점으로 작용할 때 → 과해졌을 때 문제' 순서로 정확히 5문단(문단당 2~3문장, 각 140자 ".
                    '이내)으로 쓰세요. characterType이 없으면, 대신 일간 오행/음양만으로 유추할 수 있는 이 사람의 '.
                    '핵심 연애 캐릭터를 새로 정의해서 같은 구조로 쓰세요. '.$baseGuidance,
                maxTokens: 3400,
                inputKeys: ['characterType', 'dayElement', 'dayYinYang', 'deep'],
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'love_profile',
                title: '이 사람의 연애를 한 문장으로 압축하면',
                teaser: '이 리포트 전체를 관통하는 핵심 요약과 키워드 3가지.',
                schema: ['quote' => '', 'keywords' => ['', '', ''], 'paragraphs' => ['']],
                promptGuidance: "이 사람의 연애를 한 문장(quote)으로 압축해서 표현하세요(예: '무모하게 뜨겁다', ".
                    "'천천히 스며든다' 같은 인상적인 표현). keywords에는 그 문장을 뒷받침하는 키워드를 정확히 ".
                    "3개, 각 8자 이내의 짧은 명사형(예: '직진러', '몰입형', '밀당 없음')으로 쓰세요(문장이 아니라 ".
                    '태그처럼 화면에 그대로 보여집니다). paragraphs 문단에는 이 키워드들이 앞으로 이어질 리포트 '.
                    '전반(연애 단계별 패턴, 궁합, 조언)에서 구체적으로 어떻게 드러나는지 미리 짚어주세요(2~3문장, '.
                    '140자 이내). '.$baseGuidance,
                maxTokens: 1500,
                inputKeys: ['dayElement', 'dayYinYang', 'deep', 'loveStyle', 'loveCharm'],
                blocks: ['quote', 'keyword_chips', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'expression_scores',
                title: '나는 연애 앞에서 얼마나 적극적인 사람일까 — 5가지 지표',
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
                    '모순돼 보일 수 있는 부분(예: 호감 표현은 높은데 인내심은 낮음)이 있다면 정확히 2~3문장(160자 '.
                    '이내)으로 그 이유까지 설명하고, 모순이 없으면 대신 이 5개 점수 조합이 만드는 전체적인 인상을 '.
                    '2~3문장으로 요약하세요(빈 문자열로 두지 마세요).',
                maxTokens: 1300,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['radar_chart'],
            ),
            new ChapterSpec(
                key: 'stability_scores',
                title: '관계가 흔들릴 때 나는 어디까지 버틸까 — 5가지 지표',
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
                    '해석으로, 이 5개 점수 조합이 실제 위기 상황에서 어떻게 드러나는지 정확히 2~3문장(160자 이내)'.
                    '으로 구체적으로 쓰세요(빈 문자열로 두지 마세요).',
                maxTokens: 1300,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['radar_chart'],
            ),
            new ChapterSpec(
                key: 'attraction_stage',
                title: '호감이 싹틀 때, 내 안에서 진짜 벌어지는 일',
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
                                ['label' => '실전 팁', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: "'신호' 줄에는 이 단계에서 상대가 실제로 눈치챌 수 있는 짧은 신호를 1문장(60자 ".
                    "이내)으로 쓰세요. '실전 팁' 줄에는 이 단계의 나에게 실제로 도움이 될 구체적인 행동 조언을 ".
                    '1문장(140자 이내)으로 쓰세요. 나머지 줄은 정확히 1~2문장, 140자 이내로. '.$baseGuidance,
                maxTokens: 1800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'relationship_start_stage',
                title: '썸에서 연인으로 — 그 문턱을 넘을 때 나의 행동',
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
                                ['label' => '실전 팁', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: '앞선 attraction_stage(호감이 생길 때)와 다른 시점 — 이미 서로 마음을 확인하고 '.
                    "관계가 막 시작되는 순간에 집중하세요. '신호' 줄은 1문장(60자 이내). '실전 팁' 줄은 이 단계에서 ".
                    '도움이 될 구체적인 행동 조언 1문장(140자 이내). 나머지는 정확히 1~2문장, 140자 이내로. '.
                    $baseGuidance,
                maxTokens: 1800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'relationship_stage_deep',
                title: '연애가 안정기에 접어들면 반복되는 나의 습관',
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
                                ['label' => '실전 팁', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: '연애가 어느 정도 안정된 이후, 이 사람이 관계를 어떻게 운영하는지에 집중하세요. '.
                    "'신호' 줄은 1문장(60자 이내). '실전 팁' 줄은 안정기를 더 건강하게 유지하기 위한 구체적 행동 ".
                    '조언 1문장(140자 이내). 나머지는 정확히 1~2문장, 140자 이내로. '.$baseGuidance,
                maxTokens: 1800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'conflict_crisis_stage',
                title: '싸움과 권태가 찾아올 때, 나는 다른 사람이 된다',
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
                                ['label' => '실전 팁', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                promptGuidance: "갈등/위기 상황에서의 반응에 집중하세요. '신호' 줄은 1문장(60자 이내). '실전 팁' ".
                    '줄은 갈등을 건강하게 풀기 위한 구체적 행동 조언 1문장(140자 이내). 나머지는 정확히 1~2문장, '.
                    '140자 이내로. '.$baseGuidance,
                maxTokens: 1800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'who_attracts_you',
                title: '나를 강하게 끌어당기는 사람 vs 처음만 강렬한 사람',
                teaser: '강하게 끌리는 사람과, 처음엔 강하지만 오래가긴 어려운 상대의 차이.',
                schema: [
                    'compare' => [
                        'left' => ['label' => '강하게 끌리는 사람', 'text' => '', 'tags' => ['', '', '']],
                        'right' => ['label' => '처음엔 강하지만 오래가긴 어려운 사람', 'text' => '', 'tags' => ['', '', '']],
                    ],
                ],
                promptGuidance: "'어떤 사람에게 강하게/일시적으로 끌리는가'(끌림 그 자체)만 다루세요. '장기적으로 잘 ".
                    "맞는 사람'은 compatibility_match 챕터에서 다루니 여기서 언급하지 마세요. compare.left/right의 ".
                    "text에는 구체적인 상황 예시를 들어 1~2문장(140자 이내)으로 쓰세요. tags에는 짧은 특징 정확히 ".
                    '3개를 각 10자 이내의 단어/짧은 구로 쓰세요(화면에 태그처럼 그대로 보여지므로 문장이 아니라 '.
                    "'다정함', '눈맞춤이 진함' 같은 짧은 표현으로).",
                maxTokens: 1900,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'strength_weakness',
                title: '내 최고의 장점, 과해지면 어떻게 단점이 되는가',
                teaser: '같은 성향의 양면 — 강점, 그것이 과도해질 때, 그리고 약점.',
                schema: ['items' => [['strength' => '', 'escalation' => '', 'weakness' => '']]],
                promptGuidance: "장점과 약점을 별개 성향이 아니라 '같은 성향이 과도해질 때'의 양면으로, items는 ".
                    '서로 다른 성향 축으로 정확히 2개를 쓰세요(strength → escalation(과도해지면) → weakness 순서로 '.
                    '자연스럽게 이어지게, 각 필드 1~2문장 140자 이내, 구체적인 상황 예시 포함).',
                maxTokens: 1700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['strength_weakness'],
            ),
            new ChapterSpec(
                key: 'recurring_pattern',
                title: '나도 모르게 반복되는 연애의 6단계 패턴',
                teaser: '관계가 애매해지거나 갈등이 생길 때 실제로 반복되는 패턴의 흐름.',
                schema: ['steps' => ['', '', '', '', '', ''], 'key_point' => ''],
                promptGuidance: '관계가 애매하면·갈등이 생기면 실제로 어떻게 행동하는지를 6단계의 흐름(steps, 정확히 '.
                    '6개, 각 단계 60자 이내로 구체적인 행동 묘사)으로 서술하고, key_point에 이 패턴에서 가장 중요한 '.
                    '포인트이자 이걸 알아차렸을 때 바꿀 수 있는 것을 1~2문장(140자 이내)으로 쓰세요.',
                maxTokens: 1900,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['timeline'],
            ),
            new ChapterSpec(
                key: 'partner_view_early',
                title: '상대 눈에 비친 나 ① — 만남부터 시작까지, 겉과 속',
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
                    '대비 실제 내면의 괴리에 집중하세요(각 줄 1~2문장, 140자 이내, 구체적인 장면 묘사).',
                maxTokens: 1800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'partner_view_late',
                title: '상대 눈에 비친 나 ② — 깊어질 때와 위기의 순간',
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
                promptGuidance: 'partner_view_early와 이어지는 뒷부분입니다(중복 없이). 각 줄 1~2문장, 140자 이내, '.
                    '구체적인 장면 묘사로.',
                maxTokens: 1800,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'compatibility_scores',
                title: '나와 궁합이 좋은 사람의 조건 — 7가지 지표',
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
                    '앞서 나온 LOVE SCORE류 지표(호감 표현, 관계 주도력, 안정감 등)와 겹치는 해석을 반복하지 마세요. '.
                    'scores_note에는 이 7개 지표 조합이 만드는 궁합 성향의 전체적인 그림을 정확히 2~3문장(160자 '.
                    '이내)으로 요약하세요(라벨 텍스트는 절대 바꾸지 말고 value만 채우세요).',
                maxTokens: 1300,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['radar_chart'],
            ),
            new ChapterSpec(
                key: 'compatibility_match',
                title: '잘 맞는 상대, 반드시 조심할 상대, 이상적인 관계의 모습',
                teaser: '구체적인 상대 유형과 이상적인 관계, 그리고 오래가는 조건까지.',
                schema: [
                    'compare' => [
                        'left' => ['label' => '잘 맞는 상대', 'text' => ''],
                        'right' => ['label' => '조심할 상대', 'text' => ''],
                    ],
                    'paragraphs' => ['이상적인 관계', '오래가기 위한 핵심 조건'],
                ],
                promptGuidance: 'compare.left/right(잘 맞는 상대 vs 조심할 상대)의 text는 각각 구체적인 상대 유형을 '.
                    '1~2문장(140자 이내)으로. paragraphs는 정확히 2문단(순서 고정): 1) 이 사람에게 이상적인 관계의 '.
                    '모습, 2) 이 관계를 오래 지속시키기 위해 꼭 필요한 핵심 조건 — 각 2~3문장, 140자 이내로 구체적으로.',
                maxTokens: 2000,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['compare_cards', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'relationship_advice',
                title: '실제로 부딪히는 상황 4가지, 이렇게 풀어보세요',
                teaser: '실제로 부딪히기 쉬운 상황별 맞춤 조언 4가지.',
                schema: ['items' => [['situation' => '', 'problem' => '', 'action' => '']]],
                promptGuidance: '이 사람이 실제로 자주 마주치는 연애 상황을 정확히 4가지 골라서(앞선 챕터들과 겹치지 '.
                    '않는 새로운 상황으로), 상황→문제→추천 행동 순서로 구체적인 조언을 쓰세요(각 필드 1~2문장, 140자 '.
                    "이내). 운명론적 확언 금지 — '~한 경향이 있습니다', '~해보는 것도 방법입니다' 식으로.",
                maxTokens: 3200,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['advice_cards'],
            ),
            new ChapterSpec(
                key: 'opposite_type',
                title: '나와 정반대인 사람을 만나면 벌어지는 일',
                teaser: '비슷한 사람 vs 정반대 사람, 실제로 어떤 차이가 있을지.',
                schema: [
                    'compare' => [
                        'left' => ['label' => '초반엔 이런 매력', 'text' => ''],
                        'right' => ['label' => '장기적으론 이런 마찰', 'text' => ''],
                    ],
                    'paragraphs' => [''],
                ],
                promptGuidance: '이 사람의 오행/음양과 정반대되는 성향의 상대를 만났을 때 실제로 어떤 역학이 생기는지를 '.
                    "compare로 대비해서 쓰세요. compare.left(초반의 매력)/right(장기적 마찰)의 text는 각 1~2문장 ".
                    "(140자 이내), 구체적인 상황 예시 포함. paragraphs는 정확히 1문단으로, 이 초반 매력과 장기 마찰을 ".
                    "종합했을 때 이 관계가 실제로 어떻게 흘러가는지 결론(2~3문장, 140자 이내)을 쓰세요. 앞선 ".
                    "compatibility_match 챕터와 겹치지 않게, '비슷한 유형 vs 반대 유형'이라는 새로운 각도로 쓰세요.",
                maxTokens: 1900,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['compare_cards', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'final_verdict',
                title: '이 리포트의 결론 — 이 사람의 연애를 한 문장으로',
                teaser: '리포트 전체를 압축한 최종 결론과 연애 키워드 5가지.',
                schema: ['quote' => '', 'quote_variant' => 'final', 'keywords' => ['', '', '', '', ''], 'paragraphs' => ['']],
                promptGuidance: 'quote에는 이 리포트 전체를 관통하는 결론을 감성적이면서도 확신 있는 한 문장으로 '.
                    "쓰세요. quote_variant는 항상 정확히 'final'이라는 문자열 그대로 두세요(바꾸지 마세요). ".
                    "keywords에는 이 리포트를 압축하는 연애 키워드를 정확히 5개, 각 8자 이내의 짧은 명사형으로 ".
                    '쓰세요(문장이 아니라 태그처럼 화면에 그대로 보여집니다, 앞선 love_profile의 3개 키워드와 '.
                    '겹치지 않게). paragraphs 문단에는 이 사람에게 마지막으로 전하고 싶은 따뜻한 응원의 메시지를 '.
                    '2~3문장(160자 이내)으로 쓰세요.',
                maxTokens: 1700,
                inputKeys: ['dayElement', 'dayYinYang', 'deep'],
                blocks: ['quote', 'keyword_chips', 'paragraphs'],
            ),
        ];
    }
}
