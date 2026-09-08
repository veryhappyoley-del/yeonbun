<?php

namespace App\ReportTypes\Definitions;

use App\ReportTypes\ChapterSpec;
use App\ReportTypes\InputShape;
use App\ReportTypes\ReportType;
use App\ReportTypes\ReportTypeDefinition;

/**
 * "커리어운" — 2026-09-08 신설. 연애/재회 라인업 밖으로 처음 나가는 리포트로, 사용자가
 * 직접 준 22개 섹션 프롬프트를 거의 그대로 22개 챕터로 옮겼다(InputShape::Self가 이미
 * "재물성장전략, 직업성공전략" 용도로 예정돼 있던 걸 App\ReportTypes\InputShape의 기존
 * 문서 주석에서 확인 — "연애의 나침반"과 같은 #panel-single 폼을 그대로 재사용한다).
 *
 * 사용자 원안과 이 구현의 차이(사용자가 "센스있게 바꿔도 되는데 확실한 근거가 있으면"
 * 이라고 위임한 부분):
 *   - 01번 "타고난 직업 성향을 한 문장으로"는 원안엔 없던 paragraphs 스키마로 만들었다 —
 *     기존 4개 리포트(연애의 나침반/궁합분석/짝사랑/재회) 전부 결제 전 무료 미리보기
 *     (freePreviewChapterKey)가 항상 paragraphs만 이해하는 공용 렌더러(public/js/app.js의
 *     renderTeaserContent)를 쓰기 때문에, 이 리포트의 무료 티저로 쓸 챕터도 이 모양을
 *     따라야 한다. 다른 리포트도 전부 "개요/현황" 성격의 첫 챕터를 무료 티저로 쓰는
 *     것과 같은 선택이다.
 *   - 대부분의 서술형 챕터(02~05, 08~09, 13, 15~17)는 사용자의 작성 순서 지침("제목,
 *     한 줄 요약, 상세 해석, 명리학적 근거, 현실적인 활용법")을 그대로 스키마로 만든
 *     새 블록 insight_block을 쓴다 — 어떤 챕터든 "왜 그렇게 판단했는지"와 "그래서 어떻게
 *     하라는 건지"가 항상 분리돼서 보이게 하기 위함.
 *   - 06(리더형 vs 실무형)/07(혼자 vs 함께)/10(직장인 vs 사업가)은 원안의 "둘 중 어느
 *     쪽에 가까운가" 구조가 compare_cards 블록(A vs B를 VS 구분선으로 대비)과 정확히
 *     일치해서 그 블록을 그대로 썼다.
 *   - 11(잘 맞는 직무)/12(잘 맞는 업종)은 "우선순위가 있는 목록"이라 timeline 블록(번호
 *     순서대로 나열)을 썼다.
 *   - 14(성공을 만드는 업무 방식)/18(재물이 커지기 위한 조건에 해당하는 커리어판, 여기선
 *     없음 — 대신 없음)은 원안 그대로지만, 18번(재물판의 "재물이 커지기 위한 조건"에
 *     대응하는 항목이 커리어판엔 없어서 생략) — 커리어판은 사용자 원안에 그런 항목이
 *     없으므로 추가하지 않았다.
 *   - 21(지금 눈여겨봐야 할 신호)은 "좋은 신호/경계할 신호" 대비 구조라 compare_cards로
 *     구현했다.
 *   - 22(결론)은 항목이 7종류(유형 문장 + 5개 리스트 × 3그룹 + 최종 키워드)라 quote(유형
 *     한 문장) + label_groups(핵심강점/적합직무/적합업종/피해야할환경/필요행동, 새 블록,
 *     "여러 그룹×여러 항목" 리스트 전용) + keyword_chips(최종 키워드) 3개 블록을 조합했다.
 *
 * 사용자가 준 "해석 원칙" 15개 중 공통 원칙(사주 데이터 근거, 단정적 표현 지양, 전문
 * 용어 풀어쓰기 등)은 App\Services\ChapterGenerator::prompt()의 기존 공통 문단과 이미
 * 겹쳐서 중복 지시하지 않고, 겹치지 않는 나머지(구체적 직무/업종 예시, 강점/약점/기회/
 * 위험 균형, 반복 금지, 전통 해석과 현실 조언의 구분+연결, 현실적 커리어는 경력/능력/
 * 시장환경 영향도 받는다는 안내)만 ReportType::$extraPrinciples로 추가했다.
 */
class CareerFortuneReportType implements ReportTypeDefinition
{
    public static function make(): ReportType
    {
        return new ReportType(
            key: 'career_fortune',
            label: '커리어운',
            price: 19900,
            inputShape: InputShape::Self,
            chapters: self::chapters(),
            freePreviewChapterKey: 'career_overview',
            personaLabel: '커리어 상담',
            extraPrinciples: '직업/직무/업종 추천은 추상적인 표현에 그치지 말고 구체적인 예시를 드세요(예: '.
                "'기획 직무'가 아니라 '서비스 기획, 프로덕트 매니저'처럼). 좋은 내용만 나열하지 말고 강점과 ".
                '약점, 기회와 위험을 균형 있게 설명하세요. 같은 내용을 여러 챕터에서 반복하지 마세요(이 챕터의 '.
                '주제에만 집중). 전통 명리학적 해석과 현실적인 커리어 조언을 구분하되 자연스럽게 연결하세요 '.
                '(사주 근거 → 그래서 현실에서 어떻게 하면 좋은지). 사주 데이터에 없는 내용(존재하지 않는 신살, '.
                '기록되지 않은 연도)을 지어내지 마세요.',
        );
    }

    /**
     * @return array<int, ChapterSpec>
     */
    private static function chapters(): array
    {
        $insightSchema2 = ['summary' => '', 'detail' => ['', ''], 'basis' => '', 'application' => ''];
        $insightSchema1 = ['summary' => '', 'detail' => [''], 'basis' => '', 'application' => ''];
        $selfBase = ['dayElement', 'dayYinYang', 'wuxingCount', 'deep'];

        return [
            new ChapterSpec(
                key: 'career_overview',
                title: '타고난 직업 성향을 한 문장으로 말한다면',
                teaser: '이 사람의 핵심적인 직업 성향과 커리어 키워드를 압축해서.',
                schema: ['paragraphs' => ['', '', '']],
                promptGuidance: '일간(dayElement/dayYinYang)과 격국(deep.gyeokguk), 십성 배치(deep.tenGodTally)를 '.
                    '근거로 이 사람의 핵심적인 직업 성향을 정확히 3문단(각 1~2문장, 90자 이내)으로 압축하세요. '.
                    '첫 문단은 이 사람을 한 문장으로 규정하는 강한 문장으로 시작하세요.',
                maxTokens: 1800,
                inputKeys: $selfBase,
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'core_traits',
                title: '일을 할 때 가장 강하게 나타나는 기질',
                teaser: '주도성·실행력·분석력·창의성·책임감·독립성 중 무엇이 두드러지는지.',
                schema: $insightSchema2,
                promptGuidance: '십성 배치(deep.tenGodTally)와 신강신약(deep.dayMasterStrength)을 근거로, 주도성/'.
                    '실행력/분석력/창의성/책임감/독립성 중 이 사람에게서 특히 강하게 나타나는 기질 1~2가지를 '.
                    'summary(한 줄, 25자 이내)로 압축하고 detail(2문단, 각 90자 이내)에서 왜 그런 기질이 업무 '.
                    '현장에서 어떻게 드러나는지 구체적 행동으로 설명하세요. basis에는 그 십성/신강신약 근거를, '.
                    'application에는 이 기질을 업무에서 어떻게 활용하면 좋을지 1문장(70자 이내)을 쓰세요.',
                maxTokens: 2600,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'strengths',
                title: '타고난 강점과 경쟁력',
                teaser: '다른 사람보다 자연스럽게 잘할 수 있는 능력.',
                schema: $insightSchema2,
                promptGuidance: '용신·희신(deep.usefulGod)과 십성 배치를 근거로, 이 사람이 남들보다 자연스럽게 '.
                    '잘할 수 있는 능력과 커리어 자산을 summary(한 줄)로 압축하고 detail(2문단)에서 구체적으로 '.
                    '설명하세요. basis/application도 위와 같은 형식으로 채우세요.',
                maxTokens: 2600,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'weak_moments',
                title: '일하면서 약점이 드러나는 순간',
                teaser: '집중력 저하·완벽주의·충동성·우유부단함 같은 주의할 패턴.',
                schema: $insightSchema2,
                promptGuidance: '기신(deep.usefulGod.gisin)과 과다한 오행(wuxingCount에서 가장 큰 값)을 근거로, '.
                    '이 사람이 업무에서 약점이 드러나기 쉬운 순간(집중력 저하/완벽주의/충동성/우유부단함/인간관계 '.
                    '피로 등 중 해당하는 것)을 summary·detail로 설명하세요. 단정적으로 "이 사람은 무조건 이렇다"가 '.
                    '아니라 "이런 상황에서 이런 경향이 나타날 수 있다"는 조건부 어조를 쓰세요. application에는 '.
                    '이 약점을 관리하는 구체적 방법을 쓰세요.',
                maxTokens: 2600,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'org_fit',
                title: '조직생활과 직장 적응력',
                teaser: '규칙·위계질서·보고 체계·협업 환경에서 어떻게 행동하는지.',
                schema: $insightSchema2,
                promptGuidance: '관성(정관/편관) 배치와 신강신약을 근거로, 이 사람이 규칙/위계질서/보고 체계/협업 '.
                    '환경에서 어떻게 행동하는 편인지 설명하세요. 정관이 뚜렷하면 체계를 편하게 여기는 편, 편관이 '.
                    '뚜렷하면 압박 속에서 오히려 힘을 내는 편, 관성이 약하면 자율적인 환경을 선호하는 편이라는 '.
                    '일반적 해석을 참고하되 이 사람의 실제 배치에 맞게 조정하세요.',
                maxTokens: 2600,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'leader_or_specialist',
                title: '리더형인가, 실무형인가',
                teaser: '관리자·리더·기획자·전문가·실무자 중 어디서 능력이 발휘될까.',
                schema: ['compare' => [
                    'left' => ['label' => '리더·관리형', 'text' => '', 'tags' => ['', '']],
                    'right' => ['label' => '실무·전문형', 'text' => '', 'tags' => ['', '']],
                ]],
                promptGuidance: '관성(리더십/책임)과 식상·비겁(전문성/독립적 실행력)의 상대적 비중(deep.tenGodTally)을 '.
                    '근거로, left(리더·관리형)와 right(실무·전문형) 각각에 이 사람이 그 쪽에 가까운 이유를 '.
                    'text(1문장, 70자 이내)로 쓰세요. 완전히 한쪽으로 단정하지 말고 두 side 모두 자연스러운 '.
                    '설명을 채우되, 실제로 더 강한 쪽이 자연스럽게 드러나게 쓰세요. tags에는 각 side를 대표하는 '.
                    '역할 명사 정확히 2개씩(예: "팀장", "기획 리드")을 쓰세요.',
                maxTokens: 1800,
                inputKeys: $selfBase,
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'alone_vs_team',
                title: '혼자 일할 때와 함께 일할 때의 차이',
                teaser: '독립 업무·팀 프로젝트·파트너십 환경에서 각각 어떤 모습일까.',
                schema: ['compare' => [
                    'left' => ['label' => '혼자 일할 때', 'text' => '', 'tags' => ['', '']],
                    'right' => ['label' => '함께 일할 때', 'text' => '', 'tags' => ['', '']],
                ]],
                promptGuidance: '비겁(독립성)과 식상·재성(협업/소통) 배치를 근거로, 혼자 일할 때와 팀으로 일할 때 '.
                    '각각 어떤 모습을 보이는지 left/right의 text(1문장, 70자 이내)에 쓰세요. tags에는 각 상황에서 '.
                    '이 사람이 발휘하는 능력을 정확히 2개씩 짧은 명사로 쓰세요.',
                maxTokens: 1800,
                inputKeys: $selfBase,
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'boss_relationship',
                title: '상사와의 관계에서 나타나는 특징',
                teaser: '권위에 대한 태도, 인정 욕구, 갈등이 생기기 쉬운 지점.',
                schema: $insightSchema2,
                promptGuidance: '관성(권위/상사)과 인성(인정 욕구)의 관계를 근거로, 이 사람이 상사를 대하는 태도와 '.
                    '갈등이 생기기 쉬운 지점을 설명하세요. application에는 상사와의 관계를 더 편하게 만드는 '.
                    '구체적 행동 1가지를 쓰세요.',
                maxTokens: 2600,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'peer_relationship',
                title: '동료·부하직원과의 관계',
                teaser: '협업 방식, 경쟁심, 책임 분담, 사람을 이끄는 방식.',
                schema: $insightSchema2,
                promptGuidance: '비견·겁재(동료/경쟁)와 식상(표현/육성) 배치를 근거로, 동료·부하직원과의 협업 방식과 '.
                    '사람을 이끄는 방식을 설명하세요.',
                maxTokens: 2600,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'employee_or_founder',
                title: '직장인과 사업가 중 어느 쪽에 가까운가',
                teaser: '직장생활·프리랜서·전문직·자영업·사업가 중 상대적으로 잘 맞는 형태.',
                schema: ['compare' => [
                    'left' => ['label' => '직장인·전문직', 'text' => '', 'tags' => ['', '']],
                    'right' => ['label' => '사업가·프리랜서', 'text' => '', 'tags' => ['', '']],
                ]],
                promptGuidance: '정관(안정적 조직)과 편관·편재(사업/변동)의 상대적 비중, 신강신약을 근거로 이 사람이 '.
                    '직장인·전문직과 사업가·프리랜서 중 상대적으로 어느 쪽에 더 잘 맞는지 두 side를 균형 있게 '.
                    '설명하세요(완전히 한쪽만 가능하다고 단정하지 마세요).',
                maxTokens: 1800,
                inputKeys: $selfBase,
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'fit_roles',
                title: '잘 맞는 직무 분야',
                teaser: '기획·영업·마케팅·연구·기술·교육·상담·관리·창작 중 우선순위.',
                schema: ['steps' => ['', '', '', '', ''], 'key_point' => ''],
                promptGuidance: '십성 배치(deep.tenGodTally)를 근거로, 기획/영업/마케팅/연구/기술/교육/상담/관리/'.
                    '창작 등 실제 직무 분야 중 이 사람에게 잘 맞는 것을 우선순위 순서로 정확히 5개 고르세요. '.
                    'steps 각 항목은 "직무명 — 왜 잘 맞는지 1문장(30자 이내)" 형태(예: "서비스 기획 — 구조화와 '.
                    '실행 계획을 짜는 힘이 강해요")로, 전체 45자 이내로 쓰세요. key_point에는 이 5가지를 관통하는 '.
                    '공통점 1문장(70자 이내)을 쓰세요.',
                maxTokens: 2400,
                inputKeys: $selfBase,
                blocks: ['timeline'],
            ),
            new ChapterSpec(
                key: 'fit_industries',
                title: '잘 맞는 업종과 산업',
                teaser: '사주상 강점이 발휘되기 쉬운 산업 리스트와 이유.',
                schema: ['steps' => ['', '', '', '', '', ''], 'key_point' => ''],
                promptGuidance: '오행 분포(wuxingCount)와 십성 배치를 근거로, 이 사람의 강점이 발휘되기 쉬운 산업을 '.
                    '구체적으로 정확히 6개 고르세요(예: "IT/소프트웨어", "교육/에듀테크", "금융/투자" 처럼 실제 '.
                    '업종명). steps 각 항목은 "업종명 — 이유 1문장(30자 이내)" 형태로 45자 이내로 쓰세요. '.
                    'key_point에는 왜 이런 산업들이 공통적으로 잘 맞는지 오행/십성 근거 1문장(90자 이내)을 쓰세요.',
                maxTokens: 2400,
                inputKeys: $selfBase,
                blocks: ['timeline'],
            ),
            new ChapterSpec(
                key: 'avoid_environment',
                title: '피하는 것이 좋은 업무환경',
                teaser: '능력 발휘가 어렵거나 스트레스가 누적되기 쉬운 조직.',
                schema: $insightSchema2,
                promptGuidance: '기신(deep.usefulGod.gisin)과 이 사람이 약한 오행을 근거로, 능력을 제대로 발휘하기 '.
                    '어렵거나 스트레스가 누적되기 쉬운 조직·업무환경의 구체적 특징(예: "성과가 매일 숫자로 '.
                    '공개되는 곳", "혼자 결정할 여지가 없는 곳")을 설명하세요. 추상적으로 "안 맞는 곳"이라고만 '.
                    '쓰지 말고 구체적 환경을 예로 드세요.',
                maxTokens: 2600,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'success_habits',
                title: '성공을 만드는 업무 방식',
                teaser: '일정 관리·의사결정·목표 설정·협업 방식의 성공 전략.',
                schema: ['steps' => ['', '', '', ''], 'key_point' => ''],
                promptGuidance: '용신·희신(deep.usefulGod)을 근거로, 이 사람에게 적합한 성공 전략을 일정 관리/'.
                    '의사결정/목표 설정/협업 방식 4가지 관점에서 각 1개씩 steps(각 40자 이내)로 구체적으로 '.
                    '쓰세요. key_point에 가장 중요한 태도 1문장(90자 이내)을 쓰세요.',
                maxTokens: 2000,
                inputKeys: $selfBase,
                blocks: ['step_flow'],
            ),
            new ChapterSpec(
                key: 'job_change_tendency',
                title: '이직운과 직업 변화 성향',
                teaser: '한 직업을 오래 유지하는 유형인지, 변화하며 성장하는 유형인지.',
                schema: $insightSchema2,
                promptGuidance: '지지 합충(deep.relations)과 십성 배치를 근거로, 한 직업을 오래 유지하는 유형인지 '.
                    '변화하며 성장하는 유형인지 설명하세요. application에는 이직을 고려할 때 주의할 점 1가지를 '.
                    '쓰세요.',
                maxTokens: 2600,
                inputKeys: ['deep'],
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'promotion_honor',
                title: '승진운·명예운·사회적 인정',
                teaser: '직위 상승, 권한 확대, 명예와 평판을 얻는 방식.',
                schema: $insightSchema2,
                promptGuidance: '관성·인성 배치를 근거로, 직위 상승/권한 확대/명예와 평판을 얻는 방식과 그에 유리한 '.
                    '조건을 설명하세요.',
                maxTokens: 2600,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'founding_aptitude',
                title: '창업운과 사업 적성',
                teaser: '창업 적성, 맡아야 할 역할, 혼자 할 때와 동업할 때의 차이.',
                schema: $insightSchema1,
                promptGuidance: '재성·식상 배치와 신강신약을 근거로, 창업 적성이 있는지, 창업한다면 어떤 역할(전략/'.
                    '실행/영업 등)을 맡는 게 좋은지, 혼자 할 때와 동업할 때 중 무엇이 더 잘 맞는지, 주의할 위험은 '.
                    '무엇인지를 detail 1문단(120자 이내)에 압축해서 담으세요.',
                maxTokens: 2200,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'current_daeun_career',
                title: '현재 대운에서의 커리어 흐름',
                teaser: '지금 이 10년의 대운이 직업·조직생활·성취욕에 주는 영향.',
                schema: $insightSchema1,
                promptGuidance: 'input.daeun.list[input.currentDaeunIndex]가 지금 이 사람이 지나고 있는 대운입니다 '.
                    '(currentDaeunIndex가 -1이면 목록에 해당 구간이 없다는 뜻이니 그 경우 일반적인 현재 시점 '.
                    '흐름으로 대체해서 쓰세요). 그 대운 간지의 오행/십성(dayElement 대비)을 근거로 지금 이 10년이 '.
                    '직업·조직생활·성취욕에 어떤 영향을 주는지 detail 1문단(120자 이내)으로 쓰세요. 대운 목록에 '.
                    '없는 연도나 간지를 지어내지 마세요.',
                maxTokens: 2200,
                inputKeys: ['dayElement', 'dayYinYang', 'daeun', 'currentDaeunIndex'],
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'this_year_career',
                title: '올해의 커리어운',
                teaser: '올해 세운을 기준으로 본 취업·이직·승진·창업의 기회와 주의점.',
                schema: $insightSchema1,
                promptGuidance: 'input.yearlyOutlook 배열에서 가장 첫 번째 항목(연도가 이번 연도인 항목)만 골라 '.
                    '쓰세요. 그 해 세운의 십성(stemTenGod/branchTenGod)과 일지와의 합충 여부(yukhapWithDay/'.
                    'chongWithDay)를 근거로 취업/이직/승진/창업/계약/인간관계 관점의 기회와 주의점을 detail '.
                    '1문단(120자 이내)으로 쓰세요. yearlyOutlook에 없는 연도를 언급하지 마세요.',
                maxTokens: 2200,
                inputKeys: ['dayElement', 'dayYinYang', 'yearlyOutlook'],
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'future_career_flow',
                title: '앞으로의 커리어 흐름',
                teaser: '상승기·준비기·변화기·주의 시기로 나눠 보는 향후 몇 년.',
                schema: ['stages' => [
                    'phase_1' => ['title' => '상승기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                    'phase_2' => ['title' => '준비기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                    'phase_3' => ['title' => '변화기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                    'phase_4' => ['title' => '주의 시기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                ]],
                promptGuidance: 'input.yearlyOutlook(올해부터 10년치, 결정론적으로 이미 계산된 값)을 근거로 향후 '.
                    '기간을 상승기/준비기/변화기/주의 시기 4개 구간으로 나누세요. 각 phase의 "시기" 줄에는 '.
                    'yearlyOutlook에 실제로 있는 연도만 "2027~2028년"처럼 범위로 쓰고(없는 연도를 지어내지 '.
                    '마세요), "특징" 줄에는 그 구간의 십신 근거를 바탕으로 한 특징을 40자 이내로 쓰세요. title '.
                    '필드는 이미 고정 표시되므로 절대 바꾸지 마세요(내용만 채우세요). 4개 구간이 서로 겹치지 않게 '.
                    '연도를 나누세요.',
                maxTokens: 3000,
                inputKeys: ['dayElement', 'dayYinYang', 'yearlyOutlook', 'daeun'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'signals_to_watch',
                title: '지금 커리어에서 눈여겨봐야 할 신호',
                teaser: '기회가 들어올 때 신호와 무리한 선택을 경계해야 할 신호.',
                schema: ['compare' => [
                    'left' => ['label' => '기회의 신호', 'text' => '', 'tags' => ['', '']],
                    'right' => ['label' => '경계할 신호', 'text' => '', 'tags' => ['', '']],
                ]],
                promptGuidance: '용신/기신(deep.usefulGod)과 현재 세운(yearlyOutlook의 첫 항목)을 근거로, 좋은 '.
                    '흐름이 들어올 때 나타나는 구체적 신호(left)와 무리한 선택을 경계해야 할 때 나타나는 구체적 '.
                    '신호(right)를 각각 text(1문장, 70자 이내)로 쓰세요. tags에는 각 side를 대표하는 상황 키워드 '.
                    '정확히 2개씩을 쓰세요.',
                maxTokens: 1800,
                inputKeys: ['deep', 'yearlyOutlook'],
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'final_verdict',
                title: '결론: 이 사람에게 가장 잘 맞는 성공 방식',
                teaser: '리포트 전체를 압축한 최종 결론과 핵심 리스트.',
                schema: [
                    'quote' => '',
                    'quote_variant' => 'final',
                    'groups' => [
                        ['label' => '핵심 강점', 'items' => ['', '', '', '', '']],
                        ['label' => '적합한 직무', 'items' => ['', '', '', '', '']],
                        ['label' => '적합한 업종', 'items' => ['', '', '', '', '']],
                        ['label' => '피해야 할 업무환경', 'items' => ['', '', '']],
                        ['label' => '지금 가장 필요한 행동', 'items' => ['', '', '']],
                    ],
                    'keywords' => ['', '', '', '', ''],
                ],
                promptGuidance: '앞선 챕터들의 분석 전체를 종합해서 최종 결론을 작성하세요. quote에는 이 사람의 '.
                    '커리어 유형을 압축한 확신 있는 한 문장(50자 이내)을 쓰세요. quote_variant는 항상 정확히 '.
                    "'final' 문자열 그대로 두세요. groups 배열의 각 label(핵심 강점/적합한 직무/적합한 업종/".
                    '피해야 할 업무환경/지금 가장 필요한 행동)은 절대 바꾸지 말고, items만 각각 정확한 개수(5/5/'.
                    '5/3/3)로 짧은 명사구(8자 이내)로 채우세요. 이미 앞 챕터에서 쓴 문장을 그대로 복사하지 말고 '.
                    '핵심만 압축하세요. keywords에는 이 리포트 전체를 압축하는 최종 키워드 정확히 5개(각 8자 '.
                    '이내)를 쓰세요.',
                maxTokens: 4000,
                inputKeys: ['name', 'dayElement', 'dayYinYang', 'wuxingCount', 'deep', 'daeun', 'currentDaeunIndex', 'yearlyOutlook'],
                blocks: ['quote', 'label_groups', 'keyword_chips'],
            ),
        ];
    }
}
