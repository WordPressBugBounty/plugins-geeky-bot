<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chinese shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, the labels' role and the weights
 * stay aligned with English so ranking behaves identically across languages.
 *
 * Three things about Chinese shape the lists below.
 *
 * 1. No spaces. SearchLanguageService::contains_phrase() falls back to plain
 *    substring matching when the PHRASE itself is CJK, so every entry here is
 *    matched inside an unsegmented sentence -- 「我想买一个便宜的包」 matches 便宜
 *    without the query ever being tokenised on whitespace.
 *
 * 2. Simplified and Traditional. A shopper on the same store may type 质量 or
 *    質量, 礼物 or 禮物. Nothing folds the two scripts together, so both forms
 *    are listed for every phrase where they differ.
 *
 * 3. Regional vocabulary. Mainland, Taiwan and Hong Kong use different ordinary
 *    shopping words -- 质量/品質, 便宜/平, 旅行/旅遊 -- and all resolve to the same
 *    `zh` pack, so a form omitted here is simply not understood for part of the
 *    audience.
 *
 * positive_terms and negative_terms are matched against PRODUCT text rather
 * than the query, so the English equivalents are listed alongside the Chinese:
 * a Chinese shopper browsing an English catalog must still have "women" read as
 * a women's product.
 */
return array(
    'revision' => '2026.09.13.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => '舒适',
            'weight' => 18,
            'phrases' => array('舒适', '舒適', '舒服', '好穿', '柔软', '柔軟', '软', '加厚', '有垫', '宽松', '寬鬆'),
        ),
        'simple' => array(
            'label' => '简约',
            'weight' => 10,
            'phrases' => array('简约', '簡約', '简单', '簡單', '素色', '纯色', '純色', '极简', '極簡', '低调', '低調', '基础款', '經典'),
        ),
        'quality' => array(
            'label' => '质量',
            'weight' => 10,
            'phrases' => array('质量好', '質量好', '品质好', '品質好', '质量', '質量', '品质', '品質', '做工好', '结实', '結實', '耐用', '经久耐用'),
        ),
        'lightweight' => array(
            'label' => '轻便',
            'weight' => 14,
            'phrases' => array('轻', '輕', '轻便', '輕便', '轻量', '輕量', '不重', '好携带', '方便携带', '重量轻'),
        ),
        'gift' => array(
            'label' => '礼物',
            'weight' => 20,
            'phrases' => array('礼物', '禮物', '送礼', '送禮', '当礼物', '作为礼物', '礼品', '禮品', '送人'),
        ),
        'useful' => array(
            'label' => '实用',
            'weight' => 18,
            'phrases' => array('实用', '實用', '好用', '方便', '有用', '日常能用', '实用性'),
        ),
        'casual' => array(
            'label' => '休闲',
            'weight' => 12,
            'phrases' => array('休闲', '休閒', '日常', '平时穿', '平時穿', '随意', '隨意', '日常款'),
        ),
        'formal' => array(
            'label' => '正式',
            'weight' => 12,
            'phrases' => array('正式', '正装', '正裝', '商务', '商務', '上班穿', '优雅', '優雅', '得体'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => '日常使用',
            'weight' => 16,
            'phrases' => array('日常', '每天', '天天用', '日常使用', '平时用', '平時用', '常用'),
        ),
        'office' => array(
            'label' => '办公',
            'weight' => 18,
            'phrases' => array('办公', '辦公', '办公室', '辦公室', '上班', '工作', '通勤', '商务', '商務'),
        ),
        'summer' => array(
            'label' => '夏天',
            'weight' => 16,
            'phrases' => array('夏天', '夏季', '夏', '天热', '天熱', '炎热', '透气', '透氣', '凉快', '涼快'),
        ),
        'winter' => array(
            'label' => '冬天',
            'weight' => 16,
            'phrases' => array('冬天', '冬季', '冬', '天冷', '寒冷', '保暖', '加绒', '加絨', '暖和'),
        ),
        'travel' => array(
            'label' => '旅行',
            'weight' => 16,
            'phrases' => array('旅行', '旅遊', '旅游', '出差', '出行', '度假', '登机', '登機', '方便收纳'),
        ),
        'sports' => array(
            'label' => '运动',
            'weight' => 16,
            'phrases' => array('运动', '運動', '健身', '训练', '訓練', '跑步', '慢跑', '徒步', '锻炼', '鍛鍊'),
        ),
        'school' => array(
            'label' => '上学或学习',
            'weight' => 14,
            'phrases' => array('上学', '上學', '学校', '學校', '大学', '大學', '学生', '學生', '读书', '讀書', '学习', '開學'),
        ),
        'occasion' => array(
            'label' => '特殊场合',
            'weight' => 12,
            'phrases' => array('生日', '纪念日', '紀念日', '婚礼', '婚禮', '派对', '派對', '聚会', '节日', '節日', '圣诞', '聖誕', '春节', '母亲节'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => '便宜',
            'weight' => 12,
            'phrases' => array('便宜', '平价', '平價', '不贵', '不貴', '实惠', '實惠', '划算', '劃算', '预算内', '預算內', '低价', '低價', '价格合理', '性价比高的便宜'),
        ),
        'best_value' => array(
            'label' => '性价比',
            'weight' => 16,
            'phrases' => array('性价比', '性價比', '性价比高', 'cp值', 'CP值', '值得买', '值得買', '物有所值', '物超所值', '不是最便宜的', '稳妥的选择'),
        ),
        'premium' => array(
            'label' => '高端',
            'weight' => 14,
            'phrases' => array('高端', '高档', '高檔', '奢华', '奢華', '精品', '高品质', '高品質', '好一点的', '上档次'),
        ),
        'popular' => array(
            'label' => '热门',
            'weight' => 10,
            'phrases' => array('热门', '熱門', '畅销', '暢銷', '卖得好', '賣得好', '爆款', '流行', '受欢迎', '受歡迎', '人气'),
        ),
        'top_rated' => array(
            'label' => '好评',
            'weight' => 10,
            'phrases' => array('好评', '好評', '评价高', '評價高', '评分高', '評分高', '口碑好', '评价好'),
        ),
        'newest' => array(
            'label' => '新品',
            'weight' => 8,
            'phrases' => array('新', '新款', '新品', '最新', '新到', '刚上架', '剛上架', '新上市'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => '男士或中性',
            'phrases' => array(
                '送给哥哥', '送給哥哥', '送给弟弟', '给哥哥', '给弟弟',
                '送给老公', '送給老公', '给老公', '送给丈夫', '给丈夫',
                '送给男朋友', '给男朋友', '送给爸爸', '送給爸爸', '给爸爸', '送给父亲', '给父亲',
                '送给儿子', '给儿子', '送男生', '送给男生', '男士礼物', '给男士',
                '我老公', '我爸爸', '我儿子', '我哥哥'
            ),
            'recipient_tokens' => array('哥哥', '弟弟', '老公', '丈夫', '男朋友', '爸爸', '父亲', '儿子', '男生', '男士'),
            'positive_terms' => array('男士', '男款', '男式', '男生', '中性', '男女通用', 'men', 'mens', 'male', 'unisex'),
            'negative_terms' => array('女士', '女款', '女式', '连衣裙', '裙子', '女生', 'women', 'womens', 'female', 'dress', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => '女士或中性',
            'phrases' => array(
                '送给姐姐', '送給姐姐', '送给妹妹', '给姐姐', '给妹妹',
                '送给老婆', '送給老婆', '给老婆', '送给妻子', '给妻子',
                '送给女朋友', '给女朋友', '送给妈妈', '送給媽媽', '给妈妈', '送给母亲', '给母亲',
                '送给女儿', '给女儿', '送女生', '送给女生', '女士礼物', '给女士',
                '我老婆', '我妈妈', '我女儿', '我姐姐'
            ),
            'recipient_tokens' => array('姐姐', '妹妹', '老婆', '妻子', '女朋友', '妈妈', '媽媽', '母亲', '女儿', '女生', '女士'),
            'positive_terms' => array('女士', '女款', '女式', '女生', '中性', '男女通用', 'women', 'womens', 'female', 'unisex'),
            'negative_terms' => array('男士', '男款', '男式', '男童', 'men', 'mens', 'male', 'boys'),
        ),
        'children' => array(
            'label' => '儿童',
            'phrases' => array('送给孩子', '给孩子', '送给小孩', '给小孩', '儿童礼物', '兒童禮物', '送给儿子', '送给女儿', '送男孩', '送女孩', '给宝宝', '婴儿用', '我家孩子', '我的孩子'),
            'recipient_tokens' => array('孩子', '小孩', '儿童', '兒童', '男孩', '女孩', '宝宝', '寶寶', '婴儿', '嬰兒'),
            'positive_terms' => array('儿童', '兒童', '童装', '童款', '宝宝', '婴儿', '学生', 'kid', 'kids', 'child', 'children'),
            'negative_terms' => array('仅限成人', '成人专用', 'adults only'),
        ),
        'neutral' => array(
            'label' => '通用礼物',
            'phrases' => array('送给同事', '給同事', '给同事', '送给朋友', '給朋友', '给朋友', '送给领导', '送人用的', '送给别人', '随便送人'),
            'recipient_tokens' => array('同事', '朋友', '领导', '別人', '别人'),
            'positive_terms' => array('中性', '男女通用', '均码', '均碼', 'unisex', 'one size'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('礼物', '禮物', '礼品', '禮品', '送礼', '送禮', '礼盒', '禮盒', '送人'),
        'useful_terms' => array('实用', '實用', '好用', '有用', '方便'),
        'easy_choice_terms' => array('均码', '均碼', '中性', '男女通用', 'one size'),
    ),

    'flexibility_phrases' => array('如果可以', '最好', '尽量', '盡量', '也许', '也許', '可能', '类似的', '類似的', '之类的', '如果有'),

    'filler_phrases' => array(
        '请', '請', '谢谢', '謝謝', '你好', '您好', '麻烦', '麻煩',
        '我想买', '我想買', '想买', '想買', '我要买', '我要買', '我需要', '我想要', '我在找',
        '找一个', '找一個', '帮我找', '幫我找', '帮我看看', '给我看看', '給我看看',
        '有没有', '有沒有', '有吗', '有嗎', '你们有', '你們有', '请问有', '請問有',
        '你们卖', '你們賣', '卖不卖', '有卖吗', '有货吗', '有貨嗎',
        '推荐', '推薦', '推荐一下', '推薦一下', '有什么推荐', '有什麼推薦', '求推荐',
        '有没有推荐', '你觉得', '你覺得', '哪个好', '哪個好', '哪种好', '选哪个',
        '有什么建议', '给点建议', '什么区别', '什麼區別', '区别是什么', '有什么不同',
        '什么样的', '什麼樣的', '哪种类型', '哪種類型', '不太确定', '不太確定', '不知道',
        '多少钱', '多少錢', '价格多少', '看看', '随便看看', '隨便看看',
        '现在', '現在', '这周', '這週', '下周', '马上', '馬上', '旧的', '舊的', '新的',
        '一些', '什么', '什麼', '东西', '東西', '有货', '库存', '庫存'
    ),

    // Single junk tokens. query_terms() keeps the whole CJK phrase plus every
    // two-character chunk, so this list stays deliberately short: it removes
    // grammar and request words, never a noun that could name a product.
    // 包, 手表 and 鞋 are products and must never appear here.
    'ignore_tokens' => array(
        '的', '了', '吗', '嗎', '呢', '吧', '啊', '是', '有', '没有', '沒有',
        '这个', '這個', '那个', '那個', '哪个', '哪個', '什么', '什麼', '怎么', '怎麼',
        '想', '要', '需要', '买', '買', '找', '看', '看看', '给', '給', '帮', '幫',
        '推荐', '推薦', '建议', '建議', '介绍', '介紹', '东西', '東西', '款式',
        '类型', '類型', '一些', '一个', '一個', '你好', '谢谢', '謝謝', '请', '請',
        '有货', '有貨', '库存', '庫存', '现在', '現在', '今天', '这周', '下周', '旧'
    ),
);
