<?php

namespace App\ReportTypes\Definitions;

use App\ReportTypes\ChapterSpec;
use App\ReportTypes\InputShape;
use App\ReportTypes\ReportType;
use App\ReportTypes\ReportTypeDefinition;

/**
 * "다시, 우리"(재회 전략) — "연애 재회 사주" 4종 라인업(01.연애의 나침반/02.우리의
 * 연애온도/03.짝사랑의 다음 장/04.다시, 우리)의 마지막 상품(2026-08-31 신설). 사용자가
 * 통째로 준 10개 섹션 원안("우리 관계는 정말 끝난 걸까?" ~ "재회 전략 처방전")을 골자로
 * 12개 챕터로 재구성했다 — UnrequitedLoveReportType과 같은 이유(한 챕터에 너무 많은
 * 내용을 몰아넣으면 텍스트가 빽빽해짐)로 원안의 4번째 섹션(발전 가능성)을 두 챕터로
 * 나눴던 것처럼, 여기서도 10번째 섹션("재회 전략 처방전")을 3개 챕터(현재단계+핵심전략
 * 서술 / 30일 행동계획 / 마무리 한 문장)로 나눴다.
 *
 * InputShape::TwoPersonWithHistory를 이 리포트에서 처음 실제로 쓴다 — 궁합/짝사랑
 * 탈출과 같은 두 사람의 deep 사주 데이터에 더해, 이별 히스토리(교제기간/이별시점/
 * 이별주도자/이별사유)가 추가로 필요하다. 이 4개 필드를 받으려면 궁합 폼(#panel-compat)의
 * "현재 관계"/"지금 가장 궁금한 것" 선택지와는 완전히 다른 질문이 필요해서, 짝사랑
 * 탈출처럼 기존 폼을 재사용하지 않고 별도 패널(#panel-reunion, saju.blade.php 참고)을
 * 새로 만들었다.
 *
 * 사용자 원안과 이 구현의 차이(사용자가 "너가 판단해서 내용 조금씩 수정해도 괜찮아"로
 * 판단을 위임함):
 *   - ③ "상대는 지금 어떤 상태일까?"는 원안의 예시 별점(감정 잔존 가능성★★★★☆ 등)을
 *     radar_chart 4개 지표(0~100)로 구현했다. 원안이 스스로 강조한 대로("해석 지표이지
 *     예언이 아닙니다") promptGuidance에서 반복해서 강조하고, paragraphs에도 "추정"
 *     어조를 강제한다.
 *   - ⑤ "재회 타이밍 캘린더"는 AI가 직접 만들지 않는다 — 대운/세운은 결정론적 산수라
 *     UnrequitedLoveReportType의 moving_timing과 같은 이유로, public/js/luck-cycle.js의
 *     monthlyCalendar()가 실제 계산한 12개월 전체를 새 블록(reunion_calendar_table,
 *     $report->input을 직접 읽음)이 그대로 그리고, AI는 그중 상위 3개 시기(input.
 *     topWindows)에 대한 코멘트만 priority_timing 블록(짝사랑 탈출의 moving_timing에서
 *     이미 검증된 블록)으로 남긴다.
 *   - ⑥ "재회 메시지 전략"은 원안의 5개 상황(오랜 무연락/최근 이별/상대가 연락 끊음/
 *     싸우고 헤어짐/상대에게 새 사람 생김)을 advice_cards 5개 항목으로 구현했다. 사용자
 *     원안이 명시한 "조종·죄책감 유발 대신 건강하고 상대의 선택을 존중하는 소통"
 *     원칙을 promptGuidance에 그대로 명문화했다.
 *   - ⑩ "재회 전략 처방전"은 앞서 설명한 대로 3개 챕터(strategy_prescription/
 *     action_plan_30days/final_message)로 나눴다. final_message는 CompatibilityReportType
 *     의 final_verdict 패턴(quote+keywords+paragraphs)을 그대로 재사용해서, 사용자가
 *     요청한 "사주 리포트가 아니라 재회 컨설팅 리포트처럼 느껴지는" 마무리를 노린다.
 *
 * 전체적으로 "재회가 반드시 된다/안 된다"를 단정하지 않는다 — 사용자 원안 1번 섹션의
 * 명시적 요구("이 리포트는 재회 가능/불가능을 확정적으로 말하지 않는다")를 모든 챕터의
 * 공통 지침(아래 $baseGuidance)에 반영했다.
 */
class ReunionStrategyReportType implements ReportTypeDefinition
{
    public static function make(): ReportType
    {
        return new ReportType(
            key: 'reunion_strategy',
            label: '다시, 우리',
            price: 23900,
            inputShape: InputShape::TwoPersonWithHistory,
            chapters: self::chapters(),
            previewChapterKeys: [
                'relationship_status', 'breakup_analysis', 'partner_state', 'reunion_calendar',
                'message_strategy', 'strategy_prescription', 'final_message',
            ],
            // relationship_status(1번 챕터, "우리 관계는 정말 끝난 걸까?")를 무료 티저로
            // 쓴다 — UnrequitedLoveReportType::$freePreviewChapterKey와 같은 이유로, 이
            // 챕터의 paragraphs 부분만 무료로 실제 생성해서 보여준다(verdict_badge 부분은
            // 결제 후에만 공개 — 무료 티저 렌더러 public/js/app.js의 renderTeaserContent가
            // content.paragraphs/concern_answer만 이해하므로 verdict 필드는 자동으로 노출
            // 안 됨, 별도 처리 불필요).
            freePreviewChapterKey: 'relationship_status',
        );
    }

    /**
     * @return array<int, ChapterSpec>
     */
    private static function chapters(): array
    {
        $baseGuidance = 'personA(나)와 personB(전 연인, 이름이 있으면 그 이름으로)를 자연스럽게 언급하며 '.
            '쓰세요. 근거 없이 일반론만 쓰지 말고, 반드시 두 사람의 사주 데이터와 이별 히스토리(교제기간/이별시점/'.
            '이별주도자/이별사유)를 연결하세요. 이 리포트는 재회가 될지 안 될지를 절대 확정적으로 단정하지 '.
            "않습니다 — 반드시 재회합니다/재회는 불가능합니다 같은 단정 대신 ~한 가능성이 있습니다, ~한 흐름을 ".
            '보입니다 같은 확률적·해석적 어조를 유지하세요. personB의 실제 현재 생각을 사실처럼 단정하지 말고 '.
            '항상 ~한 경향이 있어요, ~일 가능성이 있어요 같은 추정 표현을 쓰세요.';

        return [
            new ChapterSpec(
                key: 'relationship_status',
                title: '우리 관계는 정말 끝난 걸까',
                teaser: '지금의 흐름과 이별 패턴을 근거로, 이 관계가 지금 어디쯤 있는지 짚어봅니다.',
                schema: [
                    'verdict' => 'continue',
                    'verdict_label' => '',
                    'reason' => '',
                    'paragraphs' => ['', '', ''],
                ],
                promptGuidance: "verdict 필드는 반드시 'continue'(재회 시도 가치 높음), 'slow'(시간이 더 ".
                    "필요함), 'reconsider'(마음 정리가 유리함) 중 정확히 하나의 문자열이어야 합니다(다른 값을 ".
                    "쓰지 마세요). verdict_label에는 그 판정을 짧은 한글 문구(예: '아직 이어진 인연의 끈이 ".
                    "있어요', 14자 이내)로 쓰세요. reason에는 왜 그렇게 판정했는지 2~3문장(140자 이내)으로 ".
                    '쓰세요. paragraphs에는 두 사람의 궁합·이별 히스토리를 근거로 현재 관계 흐름/남아있는 감정의 '.
                    '끈/재회에 유리한 요소와 불리한 요소를 정확히 3문단(각 1~2문장, 90자 이내)으로 설명하세요. '.
                    "절대로 '재회할 것이다/못 할 것이다'를 단정적으로 말하지 마세요. ".$baseGuidance,
                maxTokens: 1200,
                // (2026-08-31 참고) 무료 티저에서도 쓰이는 챕터라(freePreviewChapterKey),
                // personA/personB 전체 deep 데이터는 넣지 않는다 — unrequited_overview와
                // 같은 이유(무료 티저 요청이 personSummary()를 app.js에서 재구현할 필요
                // 없게 하기 위함). score/notes/relation과 이별 히스토리 스칼라 값만으로도
                // 3문단+판정을 채우기엔 충분하다.
                inputKeys: ['score', 'levelLabel', 'notes', 'relation', 'datingDuration', 'breakupTiming', 'breakupInitiator', 'breakupReason', 'breakupReasonDetail'],
                blocks: ['paragraphs', 'verdict_badge'],
            ),
            new ChapterSpec(
                key: 'breakup_analysis',
                title: '왜 우리는 헤어졌을까',
                teaser: '내가 원했던 것과 상대가 원했을 것, 그 차이에서 시작된 반복 패턴.',
                schema: [
                    'compare' => [
                        'left' => ['label' => '내가 원했던 것', 'text' => ''],
                        'right' => ['label' => '상대가 원했을 것(추정)', 'text' => ''],
                    ],
                    'paragraphs' => ['', ''],
                ],
                promptGuidance: 'compare.left.text에는 personA의 사주 데이터를 근거로 이 관계에서 내가 정말 '.
                    '원했던 것이 무엇이었을지 1~2문장(70자 이내)으로 쓰세요. compare.right.text에는 personB의 '.
                    "사주 데이터를 근거로 상대가 원했을 가능성이 높은 것을 추정 어조로 1~2문장(70자 이내)으로 ".
                    '쓰세요(label은 이미 화면에 고정 표시되므로 절대 바꾸지 마세요, text만 채우세요). paragraphs '.
                    '에는 표현 방식의 차이·반복됐던 갈등 패턴·이별을 촉발한 결정적 계기를 정확히 2문단(각 1~2문장, '.
                    '90자 이내)으로 쓰세요. 반드시 재회의 핵심은 다시 만나는 것이 아니라 같은 이유로 다시 헤어지지 '.
                    '않는 것이라는 메시지가 paragraphs 어딘가에 자연스럽게 드러나게 하세요. '.$baseGuidance,
                maxTokens: 1100,
                inputKeys: ['personA', 'personB', 'datingDuration', 'breakupTiming', 'breakupInitiator', 'breakupReason', 'breakupReasonDetail'],
                blocks: ['compare_cards', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'partner_state',
                title: '상대는 지금 어떤 상태일까',
                teaser: '상대의 사주로 짐작해보는 지금의 마음 상태 — 예언이 아니라 해석 지표예요.',
                schema: [
                    'scores' => [
                        'lingering' => ['label' => '감정 잔존 가능성', 'value' => 0],
                        'reach_out_first' => ['label' => '먼저 연락할 가능성', 'value' => 0],
                        'guardedness' => ['label' => '경계심', 'value' => 0],
                        'reopen_dialogue' => ['label' => '대화 재개 가능성', 'value' => 0],
                    ],
                    'scores_note' => '',
                    'paragraphs' => ['', ''],
                ],
                promptGuidance: 'scores 4개 지표를 personB의 일간 오행/음양, 신강신약, 십신과 이별 히스토리를 '.
                    '근거로 0~100 정수로 추정하세요(라벨 텍스트는 절대 바꾸지 말고 value만 채우세요). scores_note에는 '.
                    "반드시 '이 지표는 예언이 아니라 사주 데이터를 근거로 한 해석이에요' 같은 취지의 안내 문장을 ".
                    '1문장(60자 이내)으로 쓰세요. paragraphs에는 상대가 이별을 감정적으로 어떻게 소화하는 편일지 '.
                    '(혼자 정리하는 편인지 등), 어떤 조건이 갖춰지면 다시 마음을 열 가능성이 있는지를 정확히 2문단 '.
                    "(각 1~2문장, 90자 이내)으로 쓰세요. 상대의 지금 실제 생각을 안다고 단정하지 말고 항상 추정 ".
                    '어조를 쓰세요. '.$baseGuidance,
                maxTokens: 1100,
                inputKeys: ['personB', 'breakupTiming', 'breakupInitiator', 'breakupReason'],
                blocks: ['radar_chart', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'contact_recommendation',
                title: '지금 연락해야 할까',
                teaser: '지금 시점에 가장 맞는 행동 하나를 정확히 짚어드려요.',
                schema: ['action' => 'wait', 'action_label' => '', 'reason' => ''],
                promptGuidance: "action 필드는 반드시 'no_contact_now'(지금 바로 연락은 피해야 함), 'wait'(조금 ".
                    "더 기다리기), 'light_contact'(가벼운 연락부터 시작), 'heartfelt_moment'(진심을 전달할 ".
                    "시점), 'no_contact_period'(당분간 접촉하지 않기) 중 정확히 하나의 문자열이어야 합니다(다른 ".
                    "값을 쓰지 마세요). action_label에는 그 판정을 짧은 한글 문구(예: '아직은 기다릴 때예요', 14자 ".
                    '이내)로 쓰세요. reason에는 이별 시점·주도자·사유와 두 사람의 사주 데이터를 근거로 왜 지금 이 '.
                    '행동이 맞는지 2~3문장(140자 이내)으로 쓰세요. '.$baseGuidance,
                maxTokens: 700,
                inputKeys: ['personA', 'personB', 'breakupTiming', 'breakupInitiator', 'breakupReason'],
                blocks: ['contact_action'],
            ),
            new ChapterSpec(
                key: 'reunion_calendar',
                title: '재회 타이밍 캘린더',
                teaser: '실제로 계산된 12개월 흐름과, 그중 다가가기 가장 좋은 시기 3곳.',
                schema: [
                    'picks' => [
                        ['period_label' => '', 'reason' => '', 'action' => ''],
                    ],
                    'overall_note' => '',
                ],
                promptGuidance: 'input.topWindows는 personA의 대운/세운/월운을 실제로 계산해서 이미 점수순 '.
                    "정렬해 둔 상위 후보 시기 목록입니다(각 항목: year, month, periodLabel, score, reasons). 이 ".
                    "중에서 정확히 3개를 골라 순서대로 picks에 담으세요 — period_label 필드에는 반드시 그 후보의 ".
                    "periodLabel 값을 토씨 하나 안 틀리고 그대로 옮겨 적으세요(새로운 날짜나 연도를 지어내지 ".
                    "마세요, topWindows에 없는 시기는 언급하지 마세요). reason 필드는 그 후보의 reasons 배열 ".
                    '내용을 자연스러운 한 문장(70자 이내)으로 풀어 쓰세요(사주 용어를 쉬운 말로). action 필드에는 '.
                    "그 시기에 구체적으로 어떤 행동을 하면 좋을지 1문장(70자 이내)으로 쓰세요. overall_note에는 ".
                    '이 세 시기를 관통하는 전체적인 흐름을 1~2문장(120자 이내)으로 정리하세요.',
                maxTokens: 900,
                inputKeys: ['personA', 'topWindows'],
                blocks: ['reunion_calendar_table', 'priority_timing'],
            ),
            new ChapterSpec(
                key: 'message_strategy',
                title: '재회 메시지 전략',
                teaser: '지금 상황에 맞는 연락 방식 5가지 — 조종이 아니라 존중에 기반한 소통.',
                schema: ['items' => [['label' => '', 'situation' => '', 'problem' => '', 'action' => '']]],
                promptGuidance: '아래 5가지 상황 각각에 대해 items를 정확히 5개(순서 고정) 채우세요: ①오랜 기간 '.
                    '무연락 상태, ②최근에 이별한 경우, ③상대가 먼저 연락을 끊은 경우, ④싸우고 헤어진 경우, '.
                    '⑤상대에게 새로운 사람이 생겼을 가능성이 있는 경우. label에는 그 상황을 8자 이내로 요약하세요 '.
                    "(예: '오랜 무연락형'). situation에는 그 상황을 1문장(50자 이내)으로 설명하세요. problem에는 ".
                    '그 상황에서 흔히 하는 실수를 1문장(50자 이내)으로 쓰세요. action에는 추천하는 메시지 방향을 '.
                    "1문장(60자 이내)으로 쓰세요. 절대로 죄책감을 유발하거나 조종하려는 문구(예: '나 없이 잘 지내?' ".
                    "같은 압박형)를 추천하지 마세요 — 항상 건강하고 담백하며 상대의 선택을 존중하는 소통 방식만 ".
                    '추천하세요. '.$baseGuidance,
                maxTokens: 1800,
                inputKeys: ['personA', 'personB', 'breakupTiming', 'breakupInitiator', 'breakupReason'],
                blocks: ['advice_cards'],
            ),
            new ChapterSpec(
                key: 'self_improvement',
                title: '재회 성공을 위해 바꿔야 할 것',
                teaser: '나도 모르게 반복하기 쉬운 패턴과, 재회 전에 가장 먼저 손봐야 할 것.',
                schema: [
                    'items' => [['label' => '', 'situation' => '', 'problem' => '', 'action' => '']],
                    'keywords' => ['', '', ''],
                ],
                promptGuidance: 'personA의 사주 데이터와 이별 사유를 근거로, 무심코 반복하기 쉬우면서 재회에 '.
                    "정말 방해가 될 수 있는 행동 패턴을 정확히 4가지 고르세요(label=패턴 이름 8자 이내, ".
                    'situation=구체적 상황 1문장 50자 이내, problem=왜 재회에 방해가 되는지 1문장 50자 이내, '.
                    'action=대신 할 수 있는 행동 1문장 50자 이내). keywords에는 이 4가지 중 재회한다면 가장 '.
                    '먼저 개선해야 할 문제 정확히 3개를 압축한 키워드로(각 8자 이내) 쓰세요. '.$baseGuidance,
                maxTokens: 1600,
                inputKeys: ['personA', 'breakupReason'],
                blocks: ['advice_cards', 'keyword_chips'],
            ),
            new ChapterSpec(
                key: 'if_reunited',
                title: '우리가 다시 만난다면',
                teaser: '재회 이후의 흐름과, 같은 이유로 다시 헤어질 위험까지 미리 짚어봅니다.',
                schema: [
                    'scores' => [
                        'outlook' => ['label' => '재회 전망', 'value' => 0],
                        'long_term' => ['label' => '장기 지속 가능성', 'value' => 0],
                        'repeat_risk' => ['label' => '같은 이유로 재이별할 위험', 'value' => 0],
                    ],
                    'scores_note' => '',
                    'paragraphs' => ['', '', ''],
                ],
                promptGuidance: 'scores 3개 지표를 두 사람의 궁합과 이별 히스토리를 근거로 0~100 정수로 '.
                    '산정하세요(라벨은 절대 바꾸지 말고 value만 채우세요, repeat_risk는 위험할수록 높은 값). '.
                    "scores_note에는 이 세 지표 중 가장 눈여겨봐야 할 포인트 1문장(90자 이내)을 쓰세요. paragraphs".
                    '에는 재회 이후 예상되는 관계 흐름, 다시 가까워지는 과정에서 필요한 변화, 장기적으로(결혼 등) '.
                    '이어질 가능성을 정확히 3문단(각 1~2문장, 90자 이내)으로 쓰세요. '.$baseGuidance,
                maxTokens: 1300,
                inputKeys: ['personA', 'personB', 'score', 'datingDuration', 'breakupReason'],
                blocks: ['radar_chart', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'things_to_avoid',
                title: '절대 하지 말아야 할 행동',
                teaser: '지금 이 순간에도 무심코 하고 있을 수 있는, 재회를 멀어지게 하는 행동들.',
                schema: [
                    'paragraphs' => [''],
                    'items' => [['situation' => '', 'problem' => '', 'action' => '']],
                ],
                promptGuidance: "paragraphs에는 '지금 이 행동을 하고 있다면 멈추세요'라는 취지의 강한 도입 문장을 ".
                    '정확히 1문단(1~2문장, 80자 이내)으로 쓰세요. items에는 아래 8가지를 순서 그대로, 각 항목의 '.
                    'situation을 그대로 옮기고 problem(왜 위험한지, 1문장 40자 이내)과 action(대신 할 수 있는 '.
                    "행동, 1문장 40자 이내)만 채우세요: ①계속 연락하기, ②답장 재촉하기, ③SNS로 질투 유발하기, ".
                    '④술 마시고 연락하기, ⑤주변 사람을 통해 압박하기, ⑥갑작스럽게 찾아가기, ⑦장문의 감정 호소 '.
                    '메시지 보내기, ⑧상대의 새로운 관계 방해하기.',
                maxTokens: 1800,
                inputKeys: ['personA'],
                blocks: ['paragraphs', 'advice_cards'],
            ),
            new ChapterSpec(
                key: 'strategy_prescription',
                title: '재회 전략 처방전',
                teaser: '지금까지의 분석을 종합한 현재 단계와, 우선순위대로 정리한 핵심 전략.',
                schema: [
                    'verdict' => 'continue',
                    'verdict_label' => '',
                    'reason' => '',
                    'paragraphs' => ['', '', '', ''],
                ],
                promptGuidance: "verdict 필드는 relationship_status 챕터와 같은 기준으로 'continue'/'slow'/".
                    "'reconsider' 중 정확히 하나의 문자열이어야 합니다. verdict_label에는 지금 단계를 구체적인 ".
                    "한글 문구(예: '관계 회복 준비 단계', 14자 이내)로 쓰세요. reason에는 왜 이 단계인지 1~2문장 ".
                    '(100자 이내)으로 쓰세요. paragraphs에는 순서대로 ①핵심 문제(무엇이 가장 큰 걸림돌인지), '.
                    '②추천 전략의 전체 흐름(순서대로 무엇을 먼저 해야 하는지), ③가장 좋은 시기와 가장 피해야 할 '.
                    "행동, ④재회 후 가장 먼저 해결해야 할 문제를 각각 1문단(1~2문장, 90자 이내)씩, 정확히 4문단으로 ".
                    '쓰세요. '.$baseGuidance,
                maxTokens: 1500,
                inputKeys: ['personA', 'personB', 'score', 'breakupReason'],
                blocks: ['verdict_badge', 'paragraphs'],
            ),
            new ChapterSpec(
                key: 'action_plan_30days',
                title: '앞으로 30일 행동 계획',
                teaser: '한 주씩, 감정 정리부터 관계 진전까지 단계적으로 밟아가는 계획.',
                schema: ['steps' => ['', '', '', ''], 'key_point' => ''],
                promptGuidance: "steps는 정확히 4개(1주차~4주차 순서, 각 50자 이내)로 쓰세요 — 1주차는 연락하지 ".
                    '않고 감정을 정리하는 내용, 2주차는 SNS·생활 패턴을 정상화하는 내용, 3주차는 가벼운 접점을 '.
                    '만드는 내용, 4주차는 상대 반응에 따라 연락 여부를 결정하는 내용을 personA의 사주 데이터에 '.
                    '맞춰 구체적으로 쓰세요. key_point에는 이 30일 계획에서 가장 중요한 태도 1문장(90자 이내)을 '.
                    '쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA'],
                blocks: ['timeline'],
            ),
            new ChapterSpec(
                key: 'final_message',
                title: '결론: 우리를 한 문장으로 말한다면',
                teaser: '리포트 전체를 압축한 마지막 한 마디.',
                schema: [
                    'quote' => '',
                    'quote_variant' => 'final',
                    'keywords' => ['', '', '', '', ''],
                    'paragraphs' => [''],
                ],
                promptGuidance: '앞선 챕터들의 분석을 종합해서, 이 두 사람의 관계를 관통하는 결론을 감성적이면서도 '.
                    "확신 있는 한 문장으로 quote에 쓰세요. quote_variant는 항상 정확히 'final' 문자열 그대로 ".
                    '두세요. keywords에는 이 관계를 압축하는 키워드 정확히 5개, 각 8자 이내로 쓰세요. paragraphs에는 '.
                    '정확히 1문단(2~3문장, 140자 이내)으로 이 리포트를 마무리하는 따뜻하지만 현실적인 응원의 '.
                    '메시지를 쓰세요. '.$baseGuidance,
                maxTokens: 900,
                inputKeys: ['personA', 'personB', 'score', 'levelLabel'],
                blocks: ['quote', 'keyword_chips', 'paragraphs'],
            ),
        ];
    }
}
