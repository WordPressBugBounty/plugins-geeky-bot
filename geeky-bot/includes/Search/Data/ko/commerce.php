<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Korean shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, the labels' role and the weights
 * stay aligned with English so ranking behaves identically across languages.
 *
 * Three things about Korean shape the lists below.
 *
 * 1. Particles attach to the word. 은/는/이/가/을/를/의/에/로 are written joined, so
 *    "가방을" is one token and never matches a bare "가방". Matching falls back to
 *    substring for Hangul phrases, which is what makes the stems below work.
 *
 * 2. Verb endings. A request ends in -요/-습니다/-어요/-주세요, so the stem is listed
 *    rather than one conjugation: 찾 covers 찾아요, 찾고 있어요 and 찾습니다.
 *
 * 3. Native and Sino-Korean pairs. The same idea has two ordinary words -- 값 and
 *    가격 for price, 싼 and 저렴한 for cheap -- and shoppers use both.
 *
 * positive_terms and negative_terms are matched against PRODUCT text rather
 * than the query, so the English equivalents are listed alongside the Korean.
 */
return array(
    'revision' => '2026.09.13.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => '편안함',
            'weight' => 18,
            'phrases' => array('편한', '편안', '편안한', '편해', '편합니다', '착용감', '부드러운', '푹신', '쿠션', '여유있는', '넉넉한'),
        ),
        'simple' => array(
            'label' => '심플한 디자인',
            'weight' => 10,
            'phrases' => array('심플', '심플한', '단순한', '깔끔한', '무난한', '베이직', '기본', '무지', '미니멀'),
        ),
        'quality' => array(
            'label' => '품질',
            'weight' => 10,
            'phrases' => array('품질', '품질이 좋은', '질좋은', '고급', '튼튼한', '내구성', '오래가는', '잘 만든', '마감이 좋은'),
        ),
        'lightweight' => array(
            'label' => '가벼움',
            'weight' => 14,
            'phrases' => array('가벼운', '가볍', '가볍운', '경량', '무겁지 않은', '휴대하기 좋은'),
        ),
        'gift' => array(
            'label' => '선물',
            'weight' => 20,
            'phrases' => array('선물', '선물용', '선물로', '기프트', '드릴', '선물하기 좋은'),
        ),
        'useful' => array(
            'label' => '실용적',
            'weight' => 18,
            'phrases' => array('실용적', '유용한', '편리한', '쓸모있는', '잘 쓰는', '일상에서 쓰는'),
        ),
        'casual' => array(
            'label' => '캐주얼',
            'weight' => 12,
            'phrases' => array('캐주얼', '데일리', '일상', '평상시', '편하게 입는'),
        ),
        'formal' => array(
            'label' => '포멀',
            'weight' => 12,
            'phrases' => array('포멀', '정장', '격식', '비즈니스', '단정한', '깔끔하게'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => '일상용',
            'weight' => 16,
            'phrases' => array('매일', '일상', '데일리', '평소', '자주 쓰는', '일상용'),
        ),
        'office' => array(
            'label' => '사무실',
            'weight' => 18,
            'phrases' => array('사무실', '회사', '직장', '출근', '업무', '오피스', '비즈니스'),
        ),
        'summer' => array(
            'label' => '여름',
            'weight' => 16,
            'phrases' => array('여름', '여름용', '더운', '더위', '시원한', '통기성'),
        ),
        'winter' => array(
            'label' => '겨울',
            'weight' => 16,
            'phrases' => array('겨울', '겨울용', '추운', '추위', '따뜻한', '보온'),
        ),
        'travel' => array(
            'label' => '여행',
            'weight' => 16,
            'phrases' => array('여행', '여행용', '출장', '기내', '휴가', '들고 다니기'),
        ),
        'sports' => array(
            'label' => '운동',
            'weight' => 16,
            'phrases' => array('운동', '운동용', '헬스', '헬스장', '짐', '트레이닝', '러닝', '조깅', '등산', '요가'),
        ),
        'school' => array(
            'label' => '학교 또는 공부',
            'weight' => 14,
            'phrases' => array('학교', '학생', '대학', '대학교', '공부', '등교', '개학'),
        ),
        'occasion' => array(
            'label' => '특별한 날',
            'weight' => 12,
            'phrases' => array('생일', '기념일', '결혼식', '파티', '행사', '크리스마스', '어버이날', '졸업'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => '저렴한',
            'weight' => 12,
            'phrases' => array('저렴한', '저렴', '싼', '싸게', '가성비 좋은 저렴', '예산 내', '비싸지 않은', '부담 없는', '알뜰'),
        ),
        'best_value' => array(
            'label' => '가성비',
            'weight' => 16,
            'phrases' => array('가성비', '가성비 좋은', '값어치', '가격 대비', '살 만한', '무난한 선택', '제일 싼 건 말고'),
        ),
        'premium' => array(
            'label' => '프리미엄',
            'weight' => 14,
            'phrases' => array('프리미엄', '고급', '명품', '하이엔드', '좋은 걸로', '고급스러운'),
        ),
        'popular' => array(
            'label' => '인기',
            'weight' => 10,
            'phrases' => array('인기', '인기있는', '잘나가는', '베스트', '베스트셀러', '많이 팔린', '유행'),
        ),
        'top_rated' => array(
            'label' => '평점 높은',
            'weight' => 10,
            'phrases' => array('평점 높은', '평점', '리뷰 좋은', '후기 좋은', '별점 높은', '평이 좋은'),
        ),
        'newest' => array(
            'label' => '신상',
            'weight' => 8,
            'phrases' => array('신상', '신상품', '새로운', '최신', '새로 나온', '신제품'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => '남성 또는 공용',
            'phrases' => array(
                '형 선물', '오빠 선물', '동생 선물', '남동생',
                '남편 선물', '남편에게', '남자친구 선물', '남자친구에게',
                '아버지 선물', '아빠 선물', '아버지께', '아빠한테',
                '아들 선물', '아들에게', '남자 선물', '남성용', '남자한테',
                '우리 남편', '우리 아빠', '우리 아들'
            ),
            'recipient_tokens' => array('형', '오빠', '남동생', '남편', '남자친구', '아버지', '아빠', '아들', '남자', '남성'),
            'positive_terms' => array('남성', '남성용', '남자', '공용', '남녀공용', '유니섹스', 'men', 'mens', 'male', 'unisex'),
            'negative_terms' => array('여성', '여성용', '여자', '원피스', '치마', '스커트', 'women', 'womens', 'female', 'dress', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => '여성 또는 공용',
            'phrases' => array(
                '누나 선물', '언니 선물', '여동생 선물', '여동생',
                '아내 선물', '아내에게', '와이프 선물', '여자친구 선물', '여자친구에게',
                '어머니 선물', '엄마 선물', '어머니께', '엄마한테',
                '딸 선물', '딸에게', '여자 선물', '여성용', '여자한테',
                '우리 엄마', '우리 아내', '우리 딸'
            ),
            'recipient_tokens' => array('누나', '언니', '여동생', '아내', '와이프', '여자친구', '어머니', '엄마', '딸', '여자', '여성'),
            'positive_terms' => array('여성', '여성용', '여자', '공용', '남녀공용', '유니섹스', 'women', 'womens', 'female', 'unisex'),
            'negative_terms' => array('남성', '남성용', '남자', '남아', 'men', 'mens', 'male', 'boys'),
        ),
        'children' => array(
            'label' => '아이용',
            'phrases' => array('아이 선물', '아이에게', '아이한테', '어린이 선물', '어린이용', '조카 선물', '아들 선물', '딸 선물', '남자아이', '여자아이', '아기 선물', '아기용', '우리 아이'),
            'recipient_tokens' => array('아이', '어린이', '아기', '조카', '남자아이', '여자아이', '유아', '키즈'),
            'positive_terms' => array('키즈', '아동', '어린이', '아기', '유아', '주니어', '학생', 'kid', 'kids', 'child', 'children'),
            'negative_terms' => array('성인 전용', 'adults only'),
        ),
        'neutral' => array(
            'label' => '무난한 선물',
            'phrases' => array('동료 선물', '직장 동료', '상사 선물', '친구 선물', '친구에게', '누구에게나', '가볍게 줄', '부담 없는 선물'),
            'recipient_tokens' => array('동료', '상사', '친구', '지인'),
            'positive_terms' => array('공용', '남녀공용', '유니섹스', '프리사이즈', 'unisex', 'one size'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('선물', '선물용', '기프트', '선물세트', '선물로', '드릴'),
        'useful_terms' => array('실용적', '유용한', '편리한', '잘 쓰는'),
        'easy_choice_terms' => array('프리사이즈', '공용', '남녀공용', 'one size'),
    ),

    'flexibility_phrases' => array('가능하면', '되도록', '웬만하면', '아마', '같은 거', '비슷한 거', '있으면'),

    'filler_phrases' => array(
        '주세요', '해주세요', 'please', '감사합니다', '고맙습니다', '안녕하세요', '실례합니다',
        '찾고 있어요', '찾고 있습니다', '찾아요', '찾는데', '사고 싶어요', '사고싶어요', '구매하고 싶어요',
        '필요해요', '필요한데', '있나요', '있어요', '있습니까', '파나요', '판매하나요', '취급하나요',
        '보여주세요', '보여줘', '알려주세요', '알려줘', '추천해주세요', '추천해줘', '추천 좀',
        '추천', '뭐가 좋아요', '어떤 게 좋아요', '어떤 걸 사야', '골라주세요',
        '차이가 뭐예요', '차이점', '어떤 종류', '무슨 종류', '잘 모르겠어요', '고민 중',
        '얼마예요', '얼마인가요', '가격이', '좀 보고 싶어요', '구경', '지금', '이번 주', '다음 주',
        '예전', '새로', '뭔가', '것', '거', '재고'
    ),

    // Single junk tokens. Korean writes particles joined to the word, so this
    // list stays short and removes only grammar and request words -- never a
    // noun that could name a product. 가방, 시계 and 신발 are products.
    'ignore_tokens' => array(
        '이', '가', '을', '를', '은', '는', '의', '에', '에서', '으로', '로', '와', '과', '도',
        '그', '저', '이거', '그거', '저거', '어떤', '무슨', '뭐', '무엇', '어디', '얼마',
        '있', '없', '하', '해', '주', '좀', '것', '거', '게', '수', '때',
        '찾', '보', '사', '살', '필요', '추천', '알려', '보여', '골라',
        '안녕하세요', '감사합니다', '고맙습니다', '재고', '판매', '지금', '오늘', '이번', '다음'
    ),
);
