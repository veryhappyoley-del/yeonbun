<?php

namespace App\ReportTypes\Definitions;

use App\ReportTypes\ChapterSpec;
use App\ReportTypes\InputShape;
use App\ReportTypes\ReportType;
use App\ReportTypes\ReportTypeDefinition;

/**
 * "재물운" — 2026-09-08 신설. CareerFortuneReportType과 같은 날 함께 추가한, 연애 라인업
 * 밖의 첫 리포트 두 종류 중 하나. 사용자가 직접 준 24개 섹션 프롬프트를 24개 챕터로
 * 옮겼다 — 구조/블록 선택 원칙은 CareerFortuneReportType의 클래스 docblock과 동일하니
 * 거기 참고(중복 설명 생략). InputShape::Self, #panel-single 폼 재사용(대운 계산에
 * 성별이 필요해서 성별 칩만 추가로 보임 — resources/views/saju.blade.php 참고).
 *
 * 사용자 원안과 이 구현의 차이:
 *   - 01번(개요)은 커리어판과 같은 이유로 paragraphs 스키마의 무료 티저 챕터로 만들었다.
 *   - 05(돈을 지키는 능력)와 15(돈이 새어나가기 쉬운 지점)이 원안에서 얼핏 겹쳐
 *     보이는데, 05는 "지키는 능력"이라는 성향/기질 자체(insight_block), 15는 "구체적으로
 *     어떤 상황에서 새는지"(situation/problem/action 3단 구조가 있는 advice_cards)로
 *     서로 다른 각도라 그대로 뒀다 — 05가 진단이라면 15는 처방에 가깝다.
 *   - 07(안정적 수입 vs 큰 기회)/13(현금흐름 vs 자산축적)/16(사람을 통해 들어오는 재물
 *     vs 나가는 재물)은 "둘 중 어느 쪽" 구조라 compare_cards를 썼다.
 *   - 14(돈이 들어올 때 나타나는 패턴)는 경로 목록이라 timeline을, 15(새는 지점)는
 *     situation/problem/action이 필요해 advice_cards를, 18(재물이 커지기 위한 조건)과
 *     23(현실적인 재물 관리 전략)은 실행 체크리스트라 step_flow를 썼다.
 *   - 21(구간 구분: 축적기/확장기/변동기/관리기/주의 시기, 5구간)은 커리어판의
 *     future_career_flow(4구간)와 같은 stage_grid 패턴이지만 원안이 5구간을 명시해서
 *     그대로 5개 카드로 만들었다.
 *   - 24(결론)는 항목이 7종류(유형 문장 + 리스트 5그룹 + 최종 키워드)라 quote + label_groups
 *     (강점/경로/위험/수입구조/전략/조심할것 5그룹) + keyword_chips 3블록 조합.
 */
class WealthFortuneReportType implements ReportTypeDefinition
{
    public static function make(): ReportType
    {
        return new ReportType(
            key: 'wealth_fortune',
            label: '재물운',
            price: 21900,
            inputShape: InputShape::Self,
            chapters: self::chapters(),
            freePreviewChapterKey: 'wealth_overview',
            personaLabel: '재물·자산 상담',
            extraPrinciples: '투자/소득 구조 추천은 추상적인 표현에 그치지 말고 구체적인 예시를 드세요(예: '.
                "'투자'가 아니라 '적립식 분산 투자, 실물자산 장기 보유'처럼). 좋은 내용만 나열하지 말고 강점과 ".
                '약점, 기회와 위험을 균형 있게 설명하세요. 같은 내용을 여러 챕터에서 반복하지 마세요(이 챕터의 '.
                '주제에만 집중). 전통 명리학적 해석과 현실적인 재물 관리 조언을 구분하되 자연스럽게 연결하세요 '.
                '(사주 근거 → 그래서 현실에서 어떻게 관리하면 좋은지). 특정 종목이나 특정 금융상품을 추천하지 '.
                '말고(이 리포트는 투자 자문이 아님), 성향과 구조에 대한 일반적인 방향만 제시하세요. 사주 '.
                '데이터에 없는 내용(존재하지 않는 신살, 기록되지 않은 연도)을 지어내지 마세요.',
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
                key: 'wealth_overview',
                title: '타고난 재물운을 한 문장으로 말한다면',
                teaser: '재물을 얻고 관리하는 전체적인 성향을 한 문장과 키워드로.',
                schema: ['paragraphs' => ['', '', '']],
                promptGuidance: '일간(dayElement/dayYinYang)과 격국(deep.gyeokguk), 재성 관련 십성 배치를 근거로 '.
                    '이 사람의 재물을 얻고 관리하는 전체적인 성향을 정확히 3문단(각 1~2문장, 90자 이내)으로 '.
                    '압축하세요. 첫 문단은 이 사람의 재물 성향을 규정하는 강한 문장으로 시작하세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['paragraphs'],
            ),
            new ChapterSpec(
                key: 'money_attitude',
                title: '돈을 바라보는 기본적인 태도',
                teaser: '안정성·성취욕·소비 욕구·위험 감수 성향 같은 돈에 대한 심리.',
                schema: $insightSchema2,
                promptGuidance: '재성(정재/편재)의 배치와 신강신약을 근거로, 이 사람이 돈을 바라보는 기본적인 심리적 '.
                    '태도(안정 지향/성취 지향/소비 성향/위험 감수 수준)를 설명하세요. 정재가 뚜렷하면 안정 지향, '.
                    '편재가 뚜렷하면 기회·확장 지향이라는 일반적 해석을 참고하되 실제 배치에 맞게 조정하세요.',
                maxTokens: 1100,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'earning_ability',
                title: '돈을 버는 능력',
                teaser: '소득을 만들어내는 힘, 실행력, 기회 포착력.',
                schema: $insightSchema2,
                promptGuidance: '식상(식신/상관, 재성을 만들어내는 힘)과 용신(deep.usefulGod)을 근거로, 이 사람이 '.
                    '소득을 만들어내는 힘과 기회 포착력을 설명하세요.',
                maxTokens: 1100,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'saving_ability',
                title: '돈을 모으는 능력',
                teaser: '저축, 예산관리, 장기적인 축적에 유리한 성향인지.',
                schema: $insightSchema2,
                promptGuidance: '정재(안정적 축적)와 비겁(소비 성향)의 균형을 근거로, 저축·예산관리·장기 축적에 '.
                    '유리한 성향인지 설명하세요.',
                maxTokens: 1100,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'protecting_ability',
                title: '돈을 지키는 능력',
                teaser: '충동적인 지출, 인간관계 지출, 투자 손실이 생기는 성향적 이유.',
                schema: $insightSchema2,
                promptGuidance: '기신(deep.usefulGod.gisin)과 비겁 과다 여부를 근거로, 이 사람이 재물을 지키는 데 '.
                    '취약해지는 "성향적" 이유(충동적 지출/인간관계 지출/투자 손실 경향 등, 구체적 상황은 다음 '.
                    '챕터(leak_points)에서 다룰 것이므로 여기선 기질 자체에 집중)를 설명하세요.',
                maxTokens: 1100,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'growth_style',
                title: '돈을 불리는 방식',
                teaser: '근로소득·사업소득·성과급·투자·자산 축적 중 잘 맞는 방식.',
                schema: $insightSchema1,
                promptGuidance: '재성·식상·관성의 비중을 근거로, 근로소득/사업소득/성과급/수수료/투자/자산 축적 중 '.
                    '이 사람에게 상대적으로 잘 맞는 방식을 detail 1문단(120자 이내)에 압축하세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'stability_vs_opportunity',
                title: '안정적인 수입과 큰 기회 중 어느 쪽에 가까운가',
                teaser: '정기적이고 안정적인 소득 vs 변동성 있지만 큰 기회.',
                schema: ['compare' => [
                    'left' => ['label' => '안정적인 수입', 'text' => '', 'tags' => ['', '']],
                    'right' => ['label' => '변동성 있는 큰 기회', 'text' => '', 'tags' => ['', '']],
                ]],
                promptGuidance: '정재(안정)와 편재(변동·기회)의 상대적 비중을 근거로 두 side를 균형 있게 설명하세요. '.
                    'text는 각 1문장(70자 이내), tags는 각 side를 대표하는 키워드 정확히 2개씩.',
                maxTokens: 800,
                inputKeys: $selfBase,
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'salary_professional_income',
                title: '월급·전문직 소득운',
                teaser: '직장·전문기술·자격증·승진을 통한 안정적인 수입 가능성.',
                schema: $insightSchema1,
                promptGuidance: '정관·정인(안정적 지위/전문성)의 비중을 근거로, 직장·전문기술·자격증·승진을 통한 '.
                    '안정적인 수입 가능성을 detail 1문단(120자 이내)으로 설명하세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'business_sales_income',
                title: '사업·영업·성과형 소득운',
                teaser: '사업·영업·중개·거래·인센티브 등 성과에 따라 커지는 소득 구조.',
                schema: $insightSchema1,
                promptGuidance: '편재·식상(영업력/확장성)의 비중을 근거로, 사업/영업/중개/거래/인센티브 등 성과에 '.
                    '따라 커지는 소득 구조와의 적합성을 detail 1문단(120자 이내)으로 설명하세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'side_income',
                title: '부업과 복수 수입원',
                teaser: '본업 외 수입, 프리랜서, 콘텐츠, 온라인 사업의 적합성.',
                schema: $insightSchema1,
                promptGuidance: '식상·편재의 비중을 근거로, 본업 외 수입원(부업/프리랜서/콘텐츠/온라인 사업)을 '.
                    '여러 개 운영하는 방식이 이 사람에게 잘 맞는지 detail 1문단(120자 이내)으로 설명하세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'investment_style',
                title: '투자 성향과 위험 감수 수준',
                teaser: '공격형·균형형·안정형 중 어떤 투자 태도가 나타나는지.',
                schema: $insightSchema2,
                promptGuidance: '신강신약과 편재 비중을 근거로, 공격형/균형형/안정형 중 이 사람에게 나타나는 투자 '.
                    '태도를 설명하고, 주의할 심리적 패턴(과신/손절 지연 등)을 application에 쓰세요.',
                maxTokens: 1100,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'real_estate_affinity',
                title: '부동산·현물자산과의 인연',
                teaser: '안정 자산, 장기 보유, 토지·건물과 관련된 성향(조건부 해석).',
                schema: $insightSchema1,
                promptGuidance: '토(土) 오행의 비중과 정재 배치를 근거로, 부동산·현물자산·장기 보유와 관련된 성향이 '.
                    '있는지를 조건부로("~라면 ~한 경향") 해석하세요. 오행 데이터가 뚜렷하지 않으면 단정하지 말고 '.
                    '"이 부분은 사주만으로 뚜렷하게 나타나지 않는다"고 솔직히 쓰세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'cashflow_vs_accumulation',
                title: '현금흐름과 자산축적 중 더 중요한 것',
                teaser: '현재 수입 확대 vs 장기 자산축적, 무엇을 우선할지.',
                schema: ['compare' => [
                    'left' => ['label' => '현금흐름 확대', 'text' => '', 'tags' => ['', '']],
                    'right' => ['label' => '장기 자산축적', 'text' => '', 'tags' => ['', '']],
                ]],
                promptGuidance: '식상(현금 창출력)과 정재(축적)의 비중을 근거로 두 side를 균형 있게 설명하세요.',
                maxTokens: 800,
                inputKeys: $selfBase,
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'income_patterns',
                title: '돈이 들어올 때 나타나는 패턴',
                teaser: '실력·인맥·조직·이동·변화·계약·사업 중 어떤 경로가 잦을까.',
                schema: ['steps' => ['', '', '', '', ''], 'key_point' => ''],
                promptGuidance: '십성 배치를 근거로, 실력/인맥/조직/이동/변화/계약/사업 중 이 사람에게 재물 기회가 '.
                    '생기기 쉬운 경로를 우선순위 정확히 5개로 골라 steps(각 "경로 — 이유", 45자 이내)에 쓰세요. '.
                    'key_point에 이 경로들의 공통점 1문장(90자 이내)을 쓰세요.',
                maxTokens: 1500,
                inputKeys: $selfBase,
                blocks: ['timeline'],
            ),
            new ChapterSpec(
                key: 'leak_points',
                title: '돈이 새어나가기 쉬운 지점',
                teaser: '소비·투자·가족·지인·동업·보증·과도한 확장의 위험.',
                schema: ['items' => [['situation' => '', 'problem' => '', 'action' => '']]],
                promptGuidance: '기신(deep.usefulGod.gisin/gusin)과 비겁 과다 여부를 근거로, 이 사람에게 돈이 새어 '.
                    '나가기 쉬운 구체적 상황을 정확히 5가지 고르세요(situation=구체적 상황, problem=왜 위험한지, '.
                    'action=대신 할 수 있는 대응, 각 필드 1문장 40~60자 이내). 소비/투자/가족/지인/동업/보증/'.
                    '과도한 확장 중에서 실제로 이 사람의 사주 데이터에 맞는 것을 고르세요.',
                maxTokens: 1600,
                inputKeys: $selfBase,
                blocks: ['advice_cards'],
            ),
            new ChapterSpec(
                key: 'people_money_flow',
                title: '사람을 통해 들어오는 재물과 사람 때문에 나가는 재물',
                teaser: '인맥·고객·파트너·가족이 재물 흐름에 미치는 영향.',
                schema: ['compare' => [
                    'left' => ['label' => '사람을 통해 들어옴', 'text' => '', 'tags' => ['', '']],
                    'right' => ['label' => '사람 때문에 나감', 'text' => '', 'tags' => ['', '']],
                ]],
                promptGuidance: '식상·재성(인맥을 통한 소득)과 비겁·기신(사람 관련 지출/손실)의 비중을 근거로 두 '.
                    'side를 설명하세요.',
                maxTokens: 800,
                inputKeys: $selfBase,
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'partnership_money',
                title: '동업운과 금전 관계',
                teaser: '동업 적성, 수익 배분, 공동투자에서 주의해야 할 점.',
                schema: $insightSchema1,
                promptGuidance: '비겁(동업 상대)과 재성의 관계를 근거로, 동업 적성과 수익 배분·공동투자에서 주의할 '.
                    '점을 detail 1문단(120자 이내)으로 설명하세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'conditions_to_grow',
                title: '재물이 커지기 위한 조건',
                teaser: '지금의 장점을 실제 소득·자산으로 연결하는 데 필요한 것들.',
                schema: ['steps' => ['', '', '', ''], 'key_point' => ''],
                promptGuidance: '용신·희신(deep.usefulGod)을 근거로, 이 사람의 사주 장점을 실제 소득·자산으로 '.
                    '연결하기 위해 필요한 환경·능력·습관 4가지를 steps(각 40자 이내)로 구체적으로 쓰세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['step_flow'],
            ),
            new ChapterSpec(
                key: 'current_daeun_wealth',
                title: '현재 대운의 재물 흐름',
                teaser: '지금 이 10년의 대운이 수입·지출·사업·투자에 주는 영향.',
                schema: $insightSchema1,
                promptGuidance: 'input.daeun.list[input.currentDaeunIndex]가 지금 이 사람이 지나고 있는 대운입니다 '.
                    '(currentDaeunIndex가 -1이면 목록에 해당 구간이 없다는 뜻이니 일반적인 현재 시점 흐름으로 '.
                    '대체하세요). 그 대운 간지의 오행/십성을 근거로 수입·지출·사업·투자·자산축적에 주는 영향을 '.
                    'detail 1문단(120자 이내)으로 쓰세요. 목록에 없는 연도나 간지를 지어내지 마세요.',
                maxTokens: 900,
                inputKeys: ['dayElement', 'dayYinYang', 'daeun', 'currentDaeunIndex'],
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'this_year_wealth',
                title: '올해의 재물운',
                teaser: '올해 세운을 기준으로 본 수입·계약·투자·손실 위험.',
                schema: $insightSchema1,
                promptGuidance: 'input.yearlyOutlook 배열의 첫 번째 항목(이번 연도)만 골라 그 십성/합충 여부를 '.
                    '근거로 수입 확대/계약/사업/투자/지출과 손실 위험을 detail 1문단(120자 이내)으로 쓰세요. '.
                    'yearlyOutlook에 없는 연도를 언급하지 마세요.',
                maxTokens: 900,
                inputKeys: ['dayElement', 'dayYinYang', 'yearlyOutlook'],
                blocks: ['insight_block'],
            ),
            new ChapterSpec(
                key: 'future_wealth_flow',
                title: '앞으로의 재물 흐름',
                teaser: '축적기·확장기·변동기·관리기·주의 시기로 나눠 보는 향후.',
                schema: ['stages' => [
                    'phase_1' => ['title' => '축적기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                    'phase_2' => ['title' => '확장기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                    'phase_3' => ['title' => '변동기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                    'phase_4' => ['title' => '관리기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                    'phase_5' => ['title' => '주의 시기', 'lines' => [['label' => '시기', 'text' => ''], ['label' => '특징', 'text' => '']]],
                ]],
                promptGuidance: 'input.yearlyOutlook(올해부터 10년치, 결정론적으로 이미 계산된 값)을 근거로 향후 '.
                    '기간을 축적기/확장기/변동기/관리기/주의 시기 5개 구간으로 나누세요. 각 phase의 "시기" 줄에는 '.
                    'yearlyOutlook에 실제로 있는 연도만 범위로 쓰고(없는 연도를 지어내지 마세요), "특징" 줄에는 '.
                    '그 구간의 십신 근거를 바탕으로 한 특징을 40자 이내로 쓰세요. title 필드는 절대 바꾸지 '.
                    '마세요(내용만 채우세요). 5개 구간이 서로 겹치지 않게 연도를 나누세요.',
                maxTokens: 1900,
                inputKeys: ['dayElement', 'dayYinYang', 'yearlyOutlook', 'daeun'],
                blocks: ['stage_grid'],
            ),
            new ChapterSpec(
                key: 'signals_of_change',
                title: '재물운이 좋아질 때 나타나는 신호',
                teaser: '기회를 활용하기 좋은 신호와 손실을 경계해야 하는 신호.',
                schema: ['compare' => [
                    'left' => ['label' => '기회의 신호', 'text' => '', 'tags' => ['', '']],
                    'right' => ['label' => '손실 경계 신호', 'text' => '', 'tags' => ['', '']],
                ]],
                promptGuidance: '용신/기신과 현재 세운(yearlyOutlook의 첫 항목)을 근거로, 기회를 활용하기 좋은 '.
                    '구체적 신호(left)와 손실을 경계해야 하는 구체적 신호(right)를 각각 text(1문장, 70자 이내)로 '.
                    '쓰세요. tags에는 각 side를 대표하는 상황 키워드 정확히 2개씩을 쓰세요.',
                maxTokens: 800,
                inputKeys: ['deep', 'yearlyOutlook'],
                blocks: ['compare_cards'],
            ),
            new ChapterSpec(
                key: 'practical_strategy',
                title: '현실적인 재물 관리 전략',
                teaser: '사주 성향을 고려한 예산관리·수입 구조·투자 원칙·계약 습관.',
                schema: ['steps' => ['', '', '', ''], 'key_point' => ''],
                promptGuidance: '앞선 챕터들의 분석(용신/기신, 재성 성향)을 종합해서 예산관리/수입 구조/투자 원칙/'.
                    '계약 습관 4가지 관점에서 각 1개씩 현실적인 전략을 steps(각 40자 이내)로 구체적으로 쓰세요. '.
                    'key_point에 가장 중요한 원칙 1문장(90자 이내)을 쓰세요.',
                maxTokens: 900,
                inputKeys: $selfBase,
                blocks: ['step_flow'],
            ),
            new ChapterSpec(
                key: 'final_verdict',
                title: '결론: 이 사람이 돈을 만드는 가장 좋은 방식',
                teaser: '리포트 전체를 압축한 최종 결론과 핵심 리스트.',
                schema: [
                    'quote' => '',
                    'quote_variant' => 'final',
                    'groups' => [
                        ['label' => '돈을 버는 강점', 'items' => ['', '', '', '', '']],
                        ['label' => '돈이 들어오기 쉬운 경로', 'items' => ['', '', '', '', '']],
                        ['label' => '돈이 나가기 쉬운 위험', 'items' => ['', '', '', '', '']],
                        ['label' => '적합한 수입 구조', 'items' => ['', '', '']],
                        ['label' => '재물운 활용 전략', 'items' => ['', '', '']],
                        ['label' => '현재 가장 조심해야 할 것', 'items' => ['', '', '']],
                    ],
                    'keywords' => ['', '', '', '', ''],
                ],
                promptGuidance: '앞선 챕터들의 분석 전체를 종합해서 최종 결론을 작성하세요. quote에는 이 사람의 '.
                    '재물 유형을 압축한 확신 있는 한 문장(50자 이내)을 쓰세요. quote_variant는 항상 정확히 '.
                    "'final' 문자열 그대로 두세요. groups 배열의 각 label(돈을 버는 강점/돈이 들어오기 쉬운 경로/".
                    '돈이 나가기 쉬운 위험/적합한 수입 구조/재물운 활용 전략/현재 가장 조심해야 할 것)은 절대 '.
                    '바꾸지 말고, items만 각각 정확한 개수(5/5/5/3/3/3)로 짧은 명사구(8자 이내)로 채우세요. 이미 '.
                    '앞 챕터에서 쓴 문장을 그대로 복사하지 말고 핵심만 압축하세요. keywords에는 이 리포트 전체를 '.
                    '압축하는 최종 키워드 정확히 5개(각 8자 이내)를 쓰세요. 마지막으로, 실제 재물의 결과는 사주 '.
                    '성향뿐 아니라 노력·시장 상황·경제 환경의 영향도 받는다는 점을 quote나 groups 어딘가에 '.
                    '자연스럽게 짧게 담으세요.',
                maxTokens: 2400,
                inputKeys: ['name', 'dayElement', 'dayYinYang', 'wuxingCount', 'deep', 'daeun', 'currentDaeunIndex', 'yearlyOutlook'],
                blocks: ['quote', 'label_groups', 'keyword_chips'],
            ),
        ];
    }
}
