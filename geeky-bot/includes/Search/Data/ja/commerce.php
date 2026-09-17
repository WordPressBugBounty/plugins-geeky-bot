<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Japanese shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, the labels' role and the weights
 * stay aligned with English so ranking behaves identically across languages.
 *
 * Three things about Japanese shape the lists below.
 *
 * 1. No spaces. SearchLanguageService::contains_phrase() falls back to plain
 *    substring matching when the PHRASE itself is CJK, so every entry here is
 *    matched inside an unsegmented sentence -- 「軽いバッグが欲しい」 matches 軽い
 *    without the query ever being tokenised on whitespace.
 *
 * 2. Three scripts for one word. A shopper types おすすめ, オススメ or お勧め for the
 *    same request, and カバン/かばん/鞄 for the same bag. Where a word is commonly
 *    written more than one way, every common form is listed; nothing folds them
 *    together automatically.
 *
 * 3. Politeness endings. 〜です / 〜ます / 〜ください attach to the end of a request,
 *    so the stem is listed rather than one inflected form: 探し covers 探している,
 *    探しています and 探してます at once.
 *
 * positive_terms and negative_terms are matched against PRODUCT text rather
 * than the query, so the English equivalents are listed alongside the Japanese:
 * a Japanese shopper browsing an English catalog must still have "women" read
 * as a women's product.
 */
return array(
    'revision' => '2026.09.13.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => '快適さ',
            'weight' => 18,
            'phrases' => array('快適', 'かいてき', '楽な', '楽ちん', 'らくな', '履き心地', '着心地', '着心地が良い', 'やわらかい', '柔らかい', 'ソフト', 'クッション', 'ゆったり', 'ストレッチ', '肌触り', '疲れない', '締め付けない'),
        ),
        'simple' => array(
            'label' => 'シンプル',
            'weight' => 10,
            'phrases' => array('シンプル', 'しんぷる', '無地', 'ミニマル', '控えめ', 'ベーシック', '定番', '柄なし'),
        ),
        'quality' => array(
            'label' => '品質',
            'weight' => 10,
            'phrases' => array('高品質', '品質', '品質が良い', '質が良い', 'しっかりした', 'しっかり', '丈夫', '頑丈', '耐久性', '長持ち', '長く使える', '作りが良い', '縫製が良い', '良いもの'),
        ),
        'lightweight' => array(
            'label' => '軽量',
            'weight' => 14,
            'phrases' => array('軽い', 'かるい', '軽量', '超軽量', '重くない', '持ち運びやすい', '持ち運びに便利', '軽さ', 'コンパクト'),
        ),
        'gift' => array(
            'label' => 'ギフト',
            'weight' => 20,
            'phrases' => array('プレゼント', 'ギフト', '贈り物', 'おくりもの', '贈答', 'プレゼント用', 'ギフト用', '贈る'),
        ),
        'useful' => array(
            'label' => '実用的',
            'weight' => 18,
            'phrases' => array('便利', 'べんり', '実用的', '使いやすい', '使い勝手', '役に立つ', '役立つ', '普段使い', '重宝', '毎日使える'),
        ),
        'casual' => array(
            'label' => 'カジュアル',
            'weight' => 12,
            'phrases' => array('カジュアル', '普段着', '普段使い', '日常使い', 'ラフ'),
        ),
        'formal' => array(
            'label' => 'フォーマル',
            'weight' => 12,
            'phrases' => array('フォーマル', 'ビジネス', '仕事用', 'きれいめ', '上品', 'スーツ', '冠婚葬祭'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => '毎日使う',
            'weight' => 16,
            'phrases' => array('毎日', '日常', '普段使い', '日常使い', 'デイリー', '普段'),
        ),
        'office' => array(
            'label' => '仕事・オフィス',
            'weight' => 18,
            'phrases' => array('オフィス', '会社', '仕事', '仕事用', '通勤', 'ビジネス', '職場'),
        ),
        'summer' => array(
            'label' => '夏',
            'weight' => 16,
            'phrases' => array('夏', 'なつ', '夏用', '暑い', '涼しい', '通気性', '夏向け'),
        ),
        'winter' => array(
            'label' => '冬',
            'weight' => 16,
            'phrases' => array('冬', 'ふゆ', '冬用', '寒い', '暖かい', 'あたたかい', '防寒', '冬向け'),
        ),
        'travel' => array(
            'label' => '旅行',
            'weight' => 16,
            'phrases' => array('旅行', 'りょこう', '旅行用', '旅行に', '出張', '出張用', '機内持ち込み', '持ち運び', 'トラベル', '海外旅行', 'キャンプ'),
        ),
        'sports' => array(
            'label' => 'スポーツ',
            'weight' => 16,
            'phrases' => array('スポーツ', 'ジム', 'フィットネス', 'トレーニング', '運動', 'ランニング', 'ジョギング', 'ウォーキング', '筋トレ', '登山', 'アウトドア', 'ヨガ'),
        ),
        'school' => array(
            'label' => '学校・勉強',
            'weight' => 14,
            'phrases' => array('学校', '通学', '大学', '高校', '学生', '勉強', 'スクール'),
        ),
        'occasion' => array(
            'label' => '特別な機会',
            'weight' => 12,
            'phrases' => array('誕生日', '記念日', '結婚式', 'パーティー', 'お祝い', 'クリスマス', '母の日', '父の日', '入学祝い'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => '安い',
            'weight' => 12,
            'phrases' => array('安い', 'やすい', '安く', '格安', 'お手頃', '手頃', 'お手頃価格', 'リーズナブル', '予算内', '予算', '高くない', '低価格', 'プチプラ', 'コスパ重視', '節約'),
        ),
        'best_value' => array(
            'label' => 'コスパ',
            'weight' => 16,
            'phrases' => array('コスパ', 'コストパフォーマンス', 'コスパが良い', '値段の割に', '価格に見合う', 'お買い得', '無難', '一番安いのではなく'),
        ),
        'premium' => array(
            'label' => '高級',
            'weight' => 14,
            'phrases' => array('高級', 'ハイエンド', 'プレミアム', '上質', '高品質な', 'ラグジュアリー', 'いいもの'),
        ),
        'popular' => array(
            'label' => '人気',
            'weight' => 10,
            'phrases' => array('人気', 'にんき', '売れ筋', 'ベストセラー', '売れている', '流行', 'トレンド', '定番人気'),
        ),
        'top_rated' => array(
            'label' => '高評価',
            'weight' => 10,
            'phrases' => array('高評価', '評価が高い', 'レビューが良い', '口コミが良い', '評判が良い', '星が多い'),
        ),
        'newest' => array(
            'label' => '新着',
            'weight' => 8,
            'phrases' => array('新しい', '新着', '最新', '新作', '新商品', '入荷したばかり'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => 'メンズまたはユニセックス',
            'phrases' => array(
                '兄へのプレゼント', '弟へのプレゼント', '兄に', '弟に',
                '夫へのプレゼント', '旦那へのプレゼント', '夫に', '旦那に',
                '彼氏へのプレゼント', '彼氏に', '父へのプレゼント', '父に', 'お父さんに', 'パパに',
                '息子へのプレゼント', '息子に', '男性へのプレゼント', '男性に', 'メンズ',
                'うちの夫', 'うちの父', 'うちの息子'
            ),
            'recipient_tokens' => array('兄', '弟', '夫', '旦那', '彼氏', '父', 'お父さん', 'パパ', '息子', '男性', 'メンズ'),
            'positive_terms' => array('メンズ', '男性', '男性用', 'ユニセックス', '男女兼用', 'men', 'mens', 'male', 'unisex'),
            'negative_terms' => array('レディース', '女性用', 'ワンピース', 'スカート', 'women', 'womens', 'female', 'dress', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => 'レディースまたはユニセックス',
            'phrases' => array(
                '姉へのプレゼント', '妹へのプレゼント', '姉に', '妹に',
                '妻へのプレゼント', '奥さんへのプレゼント', '妻に', '奥さんに',
                '彼女へのプレゼント', '彼女に', '母へのプレゼント', '母に', 'お母さんに', 'ママに',
                '娘へのプレゼント', '娘に', '女性へのプレゼント', '女性に', 'レディース',
                'うちの妻', 'うちの母', 'うちの娘'
            ),
            'recipient_tokens' => array('姉', '妹', '妻', '奥さん', '彼女', '母', 'お母さん', 'ママ', '娘', '女性', 'レディース'),
            'positive_terms' => array('レディース', '女性', '女性用', 'ユニセックス', '男女兼用', 'women', 'womens', 'female', 'unisex'),
            'negative_terms' => array('メンズ', '男性用', '男の子', 'men', 'mens', 'male', 'boys'),
        ),
        'children' => array(
            'label' => 'お子さま向け',
            'phrases' => array('子供へのプレゼント', '子どもへのプレゼント', '子供に', '子どもに', '息子に', '娘に', '男の子に', '女の子に', '赤ちゃんに', 'ベビー用', 'うちの子', '子供用'),
            'recipient_tokens' => array('子供', '子ども', 'こども', '男の子', '女の子', '赤ちゃん', 'ベビー', 'キッズ'),
            'positive_terms' => array('キッズ', '子供用', 'こども', 'ジュニア', 'ベビー', '通学', 'kid', 'kids', 'child', 'children'),
            'negative_terms' => array('大人専用', 'adults only'),
        ),
        'neutral' => array(
            'label' => '性別を選ばないギフト',
            'phrases' => array('同僚へのプレゼント', '同僚に', '上司へのプレゼント', '友達へのプレゼント', '友人へのプレゼント', '友達に', '誰かへのプレゼント', 'ちょっとしたプレゼント'),
            'recipient_tokens' => array('同僚', '上司', '友達', '友人', '誰か'),
            'positive_terms' => array('ユニセックス', '男女兼用', 'フリーサイズ', 'unisex', 'one size'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('プレゼント', 'ギフト', '贈り物', 'ギフトセット', 'プレゼント用', '贈答用'),
        'useful_terms' => array('便利', '実用的', '役に立つ', '普段使い'),
        'easy_choice_terms' => array('フリーサイズ', 'ユニセックス', '男女兼用', 'one size'),
    ),

    'flexibility_phrases' => array('できれば', 'なるべく', '理想は', 'たぶん', 'かもしれない', 'みたいなもの', 'のようなもの', 'あれば'),

    'filler_phrases' => array(
        'お願いします', 'ください', 'ありがとう', 'ありがとうございます', 'こんにちは', 'すみません',
        'ちょっと', 'ちょっと聞きたい', '質問があります', '相談したい', '迷っています',
        'どうしようかな', 'どれにしよう', '決められない', '初めてなので', 'よくわからないので',
        'いいのありますか', 'いいものありますか', '何かありますか', '在庫ありますか',
        '取り扱っていますか', '売っていますか', '扱ってますか', '入荷しますか',
        '安いのありますか', '他にありますか', '似たもの', '同じような',
        'を探しています', 'を探してます', '探しています', '探してる', '探し',
        'が欲しい', 'がほしい', 'ほしいです', '欲しいんですが', 'が欲しいです',
        'はありますか', 'ありますか', 'ありませんか', 'ある', 'ございますか',
        '売っていますか', '売ってますか', '取り扱い', '扱っていますか',
        '見せて', '見せてください', '教えて', '教えてください', '探して',
        'おすすめ', 'オススメ', 'お勧め', 'おすすめは', 'おすすめを', 'おすすめの',
        'おすすめありますか', 'どれがいい', 'どっちがいい', 'どれがおすすめ',
        '何がいい', 'いいものありますか', 'アドバイス', '提案',
        'の違いは', '違いは何', 'どんな種類', 'どういう', 'どんな',
        'よくわからない', 'わからない', '迷っている', 'いくらですか', 'いくら',
        '見たい', 'ちょっと見たい', '見てみたい', '今', '今週', '来週', 'すぐ',
        '古い', '新しいのが', '何か', 'もの', 'こと', '在庫', '在庫あり'
    ),

    // Single junk tokens. query_terms() keeps the whole CJK phrase plus every
    // two-character chunk, so this list stays deliberately short: it removes
    // grammar and request words, never a noun that could name a product.
    // バッグ, 時計 and 靴 are products and must never appear here.
    'ignore_tokens' => array(
        'です', 'ます', 'ください', 'ありますか', 'ある', 'ない', 'これ', 'それ', 'あれ',
        'どれ', 'どの', 'なに', '何', 'いい', 'よい', 'ほしい', '欲しい', '探し', '探す',
        '見る', '見せて', '教えて', 'おすすめ', 'オススメ', 'お勧め', '提案', 'アドバイス',
        'もの', 'こと', 'やつ', '感じ', '種類', 'タイプ', 'こんにちは', 'ありがとう',
        'すみません', 'お願い', '在庫', '販売', '今', '今日', '今週', '来週', '古い',
        'ちょっと', 'けっこう', 'かなり', 'とても', 'すごく', 'あまり', 'たぶん', 'やっぱり',
        'ほう', 'ほど', 'くらい', 'ぐらい', 'まで', 'から', 'より', 'など', 'とか', 'だけ'
    ),
);
