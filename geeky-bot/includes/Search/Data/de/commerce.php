<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * German shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, the labels' role and the weights
 * stay aligned with English so ranking behaves identically across languages.
 *
 * Three things about German shape the lists below.
 *
 * 1. Umlauts and ß. Phrases are matched after normalize_text(), which runs
 *    remove_accents() -- ü becomes u, ö becomes o, ä becomes a and ß becomes
 *    ss. Both spellings are written out where a shopper may type either.
 *
 * 2. Compounds. German writes a compound as one word, so "Regenjacke" is a
 *    single token and never matches a bare "Jacke" gate. Compounds that name
 *    a shopping context rather than a product ("Alltagsgebrauch") are listed;
 *    compounds that name a product are left to the catalog's own vocabulary.
 *
 * 3. Adjective endings. An attributive adjective inflects (warm, warme, warmer,
 *    warmes), so the stem plus its common endings are listed together.
 *
 * positive_terms and negative_terms are matched against PRODUCT text rather
 * than the query, so the English equivalents are listed alongside the German.
 */
return array(
    'revision' => '2026.09.13.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => 'Komfort',
            'weight' => 18,
            'phrases' => array('bequem', 'bequeme', 'bequemer', 'bequemes', 'bequemen', 'komfortabel', 'komfortable', 'weich', 'weiche', 'weicher', 'weichen', 'gepolstert', 'gepolsterte', 'angenehm zu tragen', 'locker geschnitten'),
        ),
        'simple' => array(
            'label' => 'Schlichter Stil',
            'weight' => 10,
            'phrases' => array('schlicht', 'schlichte', 'schlichten', 'einfach', 'einfache', 'minimalistisch', 'minimalistische', 'unifarben', 'ohne muster', 'dezent', 'dezente', 'klassisch', 'klassische'),
        ),
        'quality' => array(
            'label' => 'Qualität',
            'weight' => 10,
            'phrases' => array('gute qualität', 'gute qualitat', 'hohe qualität', 'qualität', 'qualitat', 'hochwertig', 'hochwertige', 'hochwertigen', 'gut verarbeitet', 'robust', 'robuste', 'robusten', 'langlebig', 'langlebige', 'haltbar'),
        ),
        'lightweight' => array(
            'label' => 'Leicht',
            'weight' => 14,
            'phrases' => array('leicht', 'leichte', 'leichter', 'leichtes', 'leichten', 'leichtem', 'leichtgewicht', 'nicht schwer', 'einfach zu tragen', 'geringes gewicht'),
        ),
        'gift' => array(
            'label' => 'Geschenk',
            'weight' => 20,
            'phrases' => array('geschenk', 'geschenke', 'als geschenk', 'zum verschenken', 'geschenkidee', 'geschenkideen', 'zum schenken'),
        ),
        'useful' => array(
            'label' => 'Nützlich',
            'weight' => 18,
            'phrases' => array('nützlich', 'nutzlich', 'nützliche', 'praktisch', 'praktische', 'praktischer', 'praktischen', 'brauchbar', 'im alltag nützlich', 'kann man gebrauchen'),
        ),
        'casual' => array(
            'label' => 'Lässig',
            'weight' => 12,
            'phrases' => array('lässig', 'lassig', 'lässige', 'casual', 'leger', 'für jeden tag', 'fur jeden tag', 'alltagstauglich', 'alltäglich'),
        ),
        'formal' => array(
            'label' => 'Elegant',
            'weight' => 12,
            'phrases' => array('elegant', 'elegante', 'eleganten', 'formell', 'formelle', 'schick', 'schicke', 'geschäftlich', 'geschaftlich', 'fürs büro', 'furs buro', 'business', 'seriös'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => 'Täglicher Gebrauch',
            'weight' => 16,
            'phrases' => array('jeden tag', 'für jeden tag', 'täglich', 'taglich', 'alltag', 'im alltag', 'alltagsgebrauch', 'für den alltag', 'fur den alltag', 'regelmäßig'),
        ),
        'office' => array(
            'label' => 'Büro',
            'weight' => 18,
            'phrases' => array('büro', 'buro', 'fürs büro', 'im büro', 'bei der arbeit', 'für die arbeit', 'fur die arbeit', 'zur arbeit', 'arbeitsalltag', 'geschäftlich'),
        ),
        'summer' => array(
            'label' => 'Sommer',
            'weight' => 16,
            'phrases' => array('sommer', 'für den sommer', 'fur den sommer', 'im sommer', 'bei hitze', 'warmes wetter', 'atmungsaktiv', 'atmungsaktive', 'luftig'),
        ),
        'winter' => array(
            'label' => 'Winter',
            'weight' => 16,
            'phrases' => array('winter', 'für den winter', 'fur den winter', 'im winter', 'bei kälte', 'bei kalte', 'kaltes wetter', 'warm', 'warme', 'warmen', 'warmer', 'warm für den winter', 'kuschelig', 'gemütlich'),
        ),
        'travel' => array(
            'label' => 'Reise',
            'weight' => 16,
            'phrases' => array('reise', 'auf reisen', 'zum reisen', 'für die reise', 'fur die reise', 'für den urlaub', 'urlaub', 'handgepäck', 'handgepack', 'leicht zu packen'),
        ),
        'sports' => array(
            'label' => 'Sport',
            'weight' => 16,
            'phrases' => array('sport', 'sportlich', 'sportliche', 'fitnessstudio', 'gym', 'training', 'workout', 'laufen', 'joggen', 'wandern', 'bewegung'),
        ),
        'school' => array(
            'label' => 'Schule oder Studium',
            'weight' => 14,
            'phrases' => array('schule', 'für die schule', 'fur die schule', 'uni', 'universität', 'universitat', 'hochschule', 'studium', 'student', 'studentin', 'schüler', 'schuler'),
        ),
        'occasion' => array(
            'label' => 'Besonderer Anlass',
            'weight' => 12,
            'phrases' => array('geburtstag', 'jahrestag', 'hochzeit', 'party', 'feier', 'anlass', 'besonderer anlass', 'weihnachten', 'ostern'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => 'Günstig',
            'weight' => 12,
            'phrases' => array('günstig', 'gunstig', 'günstige', 'günstigen', 'gunstigen', 'billig', 'billige', 'preiswert', 'preiswerte', 'nicht teuer', 'nicht zu teuer', 'kleines budget', 'in meinem budget', 'fairer preis', 'guter preis'),
        ),
        'best_value' => array(
            'label' => 'Bestes Preis-Leistungs-Verhältnis',
            'weight' => 16,
            'phrases' => array('preis leistung', 'preis leistungs verhältnis', 'preis leistungs verhaltnis', 'gutes preis leistungs verhältnis', 'für das geld', 'fur das geld', 'lohnt sich', 'sein geld wert', 'sichere wahl', 'nicht das billigste'),
        ),
        'premium' => array(
            'label' => 'Premium',
            'weight' => 14,
            'phrases' => array('premium', 'luxus', 'luxuriös', 'gehoben', 'gehobene', 'hochwertiger', 'beste qualität', 'beste qualitat', 'oberklasse'),
        ),
        'popular' => array(
            'label' => 'Beliebt',
            'weight' => 10,
            'phrases' => array('beliebt', 'beliebte', 'bestseller', 'meistverkauft', 'meistverkaufte', 'im trend', 'trendig', 'kundenliebling'),
        ),
        'top_rated' => array(
            'label' => 'Bestbewertet',
            'weight' => 10,
            'phrases' => array('bestbewertet', 'bestbewertete', 'am besten bewertet', 'beste bewertungen', 'gute bewertungen', 'gut bewertet', 'top bewertungen'),
        ),
        'newest' => array(
            'label' => 'Neuheiten',
            'weight' => 8,
            'phrases' => array('neu', 'neue', 'neuheiten', 'neueste', 'das neueste', 'gerade erschienen', 'neu eingetroffen', 'aktuell'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => 'Herren oder Unisex',
            'phrases' => array(
                'geschenk für meinen bruder', 'geschenk fur meinen bruder', 'für meinen bruder', 'fur meinen bruder',
                'geschenk für meinen mann', 'für meinen mann', 'geschenk für meinen freund', 'für meinen freund',
                'geschenk für meinen vater', 'für meinen vater', 'für meinen papa', 'fur meinen papa',
                'geschenk für meinen sohn', 'für meinen sohn', 'geschenk für männer', 'für männer', 'für manner', 'für herren',
                'mein bruder', 'mein mann', 'mein freund', 'mein vater', 'mein papa', 'mein sohn'
            ),
            'recipient_tokens' => array('bruder', 'mann', 'freund', 'vater', 'papa', 'sohn', 'männer', 'manner', 'herren'),
            'positive_terms' => array('herren', 'herrn', 'männer', 'manner', 'männlich', 'unisex', 'für ihn', 'men', 'mens', 'male'),
            'negative_terms' => array('damen', 'frauen', 'weiblich', 'kleid', 'rock', 'bluse', 'women', 'womens', 'female', 'dress', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => 'Damen oder Unisex',
            'phrases' => array(
                'geschenk für meine schwester', 'geschenk fur meine schwester', 'für meine schwester', 'fur meine schwester',
                'geschenk für meine frau', 'für meine frau', 'geschenk für meine freundin', 'für meine freundin',
                'geschenk für meine mutter', 'für meine mutter', 'für meine mama', 'fur meine mama',
                'geschenk für meine tochter', 'für meine tochter', 'geschenk für frauen', 'für frauen', 'für damen',
                'meine schwester', 'meine frau', 'meine freundin', 'meine mutter', 'meine mama', 'meine tochter'
            ),
            'recipient_tokens' => array('schwester', 'frau', 'freundin', 'mutter', 'mama', 'tochter', 'frauen', 'damen'),
            'positive_terms' => array('damen', 'frauen', 'weiblich', 'unisex', 'für sie', 'women', 'womens', 'female'),
            'negative_terms' => array('herren', 'männer', 'manner', 'männlich', 'jungen', 'men', 'mens', 'male', 'boys'),
        ),
        'children' => array(
            'label' => 'Für Kinder',
            'phrases' => array('geschenk für kinder', 'für kinder', 'fur kinder', 'geschenk für mein kind', 'für mein kind', 'für einen jungen', 'für ein mädchen', 'für ein madchen', 'für ein baby', 'fürs baby', 'mein kind', 'meine kinder', 'mein kleiner', 'meine kleine'),
            'recipient_tokens' => array('kind', 'kinder', 'junge', 'jungen', 'mädchen', 'madchen', 'baby', 'kleiner', 'kleine'),
            'positive_terms' => array('kinder', 'kind', 'junior', 'baby', 'jungen', 'mädchen', 'schul', 'kid', 'kids', 'child', 'children'),
            'negative_terms' => array('nur für erwachsene', 'adults only'),
        ),
        'neutral' => array(
            'label' => 'Neutrales Geschenk',
            'phrases' => array('geschenk für einen kollegen', 'für einen kollegen', 'für eine kollegin', 'geschenk für kollegen', 'geschenk für einen freund', 'für einen freund', 'geschenk für jemanden', 'für jemanden'),
            'recipient_tokens' => array('kollege', 'kollegen', 'kollegin', 'jemanden', 'jemand'),
            'positive_terms' => array('unisex', 'einheitsgröße', 'einheitsgrosse', 'one size'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('geschenk', 'geschenke', 'geschenkidee', 'geschenkset', 'zum verschenken', 'als geschenk'),
        'useful_terms' => array('nützlich', 'nutzlich', 'praktisch', 'brauchbar', 'im alltag nützlich'),
        'easy_choice_terms' => array('einheitsgröße', 'einheitsgrosse', 'unisex', 'one size'),
    ),

    'flexibility_phrases' => array('wenn möglich', 'wenn moglich', 'am besten', 'idealerweise', 'vielleicht', 'so etwas wie', 'so was wie', 'offen für', 'falls ihr habt', 'falls sie haben'),

    'filler_phrases' => array(
        'bitte', 'danke', 'hallo', 'guten tag', 'guten morgen', 'guten abend', 'servus',
        'ich suche', 'ich suche nach', 'ich brauche', 'ich möchte', 'ich mochte', 'ich will',
        'ich hätte gern', 'ich hatte gern', 'ich würde gern', 'ich bin auf der suche nach',
        'haben sie', 'habt ihr', 'hast du', 'gibt es', 'gibt es da', 'führen sie', 'fuhren sie',
        'verkaufen sie', 'verkauft ihr', 'zeigen sie mir', 'zeig mir', 'zeigt mir',
        'finde mir', 'finden sie', 'hilf mir', 'helfen sie mir', 'was empfehlen sie',
        'was empfiehlst du', 'was würden sie empfehlen', 'hast du empfehlungen', 'eine empfehlung',
        'empfehlungen', 'einen vorschlag', 'vorschläge', 'vorschlage', 'irgendwelche ideen',
        'was ist der unterschied zwischen', 'der unterschied zwischen', 'was für ein', 'was fur ein',
        'welche art von', 'welche sorte', 'irgendeine art von', 'ich bin mir nicht sicher',
        'nicht sicher', 'ich weiß nicht', 'ich weiss nicht', 'wie viel kostet', 'was kostet',
        'ich möchte sehen', 'mal sehen', 'mal schauen', 'nur schauen', 'gerade jetzt', 'im moment',
        'diese woche', 'nächste woche', 'nachste woche', 'sofort', 'mein alter', 'mein altes',
        'etwas neues', 'ein neues', 'irgendetwas', 'irgendwas', 'irgendwer', 'zeug', 'sachen',
        'verfügbar', 'verfugbar', 'verfügbarkeit', 'auf lager', 'lieferbar', 'empfohlen',
        'empfehlung', 'vorschlag', 'interessiert', 'ich frage mich'
    ),

    // Single junk tokens. A token that is not listed here survives as a
    // REQUIRED term, so one unrecognised word returns an empty result set
    // rather than a worse-ranked one. Nothing that could name a product goes in
    // this list -- "uhr", "tasche" and "mantel" are products, not filler.
    'ignore_tokens' => array(
        'was', 'welche', 'welcher', 'welches', 'wie', 'wo', 'ist', 'sind', 'haben', 'habt',
        'hast', 'gibt', 'kann', 'könnte', 'konnte', 'würde', 'wurde', 'möchte', 'mochte',
        'will', 'brauche', 'suche', 'zeigen', 'zeig', 'zeigt', 'finden', 'finde', 'helfen',
        'hilf', 'empfehlen', 'empfiehlst', 'empfehlung', 'empfehlungen', 'empfohlen',
        'vorschlag', 'vorschläge', 'vorschlage', 'idee', 'ideen', 'option', 'optionen',
        'auswahl', 'sache', 'sachen', 'zeug', 'dings', 'etwas', 'irgendwas', 'irgendetwas',
        'art', 'sorte', 'hallo', 'danke', 'bitte', 'verfügbar', 'verfugbar', 'verfügbarkeit',
        'verkaufen', 'verkauft', 'jetzt', 'gerade', 'heute', 'morgen', 'woche', 'nächste',
        // "neu" stays out: it is the shopper's newest-first signal, matched by
        // decision_modes above, exactly as English keeps "new" out of this list.
        'alter', 'altes', 'ok', 'okay', 'ja', 'sehen', 'schauen', 'kostet'
    ),
);
