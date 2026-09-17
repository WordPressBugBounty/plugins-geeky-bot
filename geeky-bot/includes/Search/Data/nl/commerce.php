<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dutch shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, the labels' role and the weights
 * stay aligned with English so ranking behaves identically across languages.
 *
 * Two things about Dutch shape the lists below.
 *
 * 1. Adjective inflection. An attributive adjective takes -e (licht / lichte,
 *    warm / warme), so both forms are listed or the modifier survives into the
 *    term list as a required product word.
 *
 * 2. Compounds. Dutch writes a compound as one word -- regenjas, rugzak,
 *    zonnebril -- so a compound that names a shopping context is listed here,
 *    while a compound that names a product is left to the catalog's own words.
 *
 * positive_terms and negative_terms are matched against PRODUCT text rather
 * than the query, so the English equivalents are listed alongside the Dutch:
 * a Dutch shopper browsing an English catalog must still have "women" read as
 * a women's product.
 */
return array(
    'revision' => '2026.09.13.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => 'Comfort',
            'weight' => 18,
            'phrases' => array('comfortabel', 'comfortabele', 'lekker zittend', 'zit lekker', 'zacht', 'zachte', 'gevoerd', 'gevoerde', 'makkelijk te dragen', 'ruime pasvorm', 'niet knellend'),
        ),
        'simple' => array(
            'label' => 'Eenvoudige stijl',
            'weight' => 10,
            'phrases' => array('eenvoudig', 'eenvoudige', 'simpel', 'simpele', 'minimalistisch', 'minimalistische', 'effen', 'ingetogen', 'klassiek', 'klassieke', 'zonder print'),
        ),
        'quality' => array(
            'label' => 'Kwaliteit',
            'weight' => 10,
            'phrases' => array('goede kwaliteit', 'kwaliteit', 'hoogwaardig', 'hoogwaardige', 'stevig', 'stevige', 'duurzaam', 'duurzame', 'gaat lang mee', 'goed gemaakt'),
        ),
        'lightweight' => array(
            'label' => 'Licht',
            'weight' => 14,
            'phrases' => array('licht', 'lichte', 'lichtgewicht', 'niet zwaar', 'makkelijk mee te nemen', 'weinig gewicht'),
        ),
        'gift' => array(
            'label' => 'Cadeau',
            'weight' => 20,
            'phrases' => array('cadeau', 'cadeautje', 'kado', 'als cadeau', 'om cadeau te geven', 'cadeau idee', 'cadeautip', 'geschenk'),
        ),
        'useful' => array(
            'label' => 'Handig',
            'weight' => 18,
            'phrases' => array('handig', 'handige', 'praktisch', 'praktische', 'nuttig', 'nuttige', 'bruikbaar', 'dagelijks handig'),
        ),
        'casual' => array(
            'label' => 'Casual',
            'weight' => 12,
            'phrases' => array('casual', 'alledaags', 'alledaagse', 'voor elke dag', 'dagelijks', 'informeel', 'relaxte stijl'),
        ),
        'formal' => array(
            'label' => 'Net',
            'weight' => 12,
            'phrases' => array('net', 'nette', 'formeel', 'formele', 'zakelijk', 'zakelijke', 'chic', 'elegant', 'elegante', 'voor kantoor'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => 'Dagelijks gebruik',
            'weight' => 16,
            'phrases' => array('dagelijks', 'elke dag', 'voor elke dag', 'dagelijks gebruik', 'alledaags gebruik', 'regelmatig gebruik'),
        ),
        'office' => array(
            'label' => 'Kantoor',
            'weight' => 18,
            'phrases' => array('kantoor', 'op kantoor', 'voor kantoor', 'op het werk', 'voor werk', 'naar het werk', 'zakelijk gebruik'),
        ),
        'summer' => array(
            'label' => 'Zomer',
            'weight' => 16,
            'phrases' => array('zomer', 'voor de zomer', 'in de zomer', 'warm weer', 'als het warm is', 'ademend', 'ademende', 'luchtig'),
        ),
        'winter' => array(
            'label' => 'Winter',
            'weight' => 16,
            'phrases' => array('winter', 'voor de winter', 'in de winter', 'koud weer', 'als het koud is', 'warm voor de winter', 'lekker warm'),
        ),
        'travel' => array(
            'label' => 'Reizen',
            'weight' => 16,
            'phrases' => array('reizen', 'op reis', 'voor op reis', 'voor een reis', 'vakantie', 'voor vakantie', 'handbagage', 'makkelijk in te pakken'),
        ),
        'sports' => array(
            'label' => 'Sport',
            'weight' => 16,
            'phrases' => array('sport', 'sporten', 'sportief', 'sportieve', 'sportschool', 'gym', 'training', 'hardlopen', 'wandelen', 'fitness'),
        ),
        'school' => array(
            'label' => 'School of studie',
            'weight' => 14,
            'phrases' => array('school', 'voor school', 'universiteit', 'hogeschool', 'student', 'studenten', 'studeren', 'voor de les'),
        ),
        'occasion' => array(
            'label' => 'Speciale gelegenheid',
            'weight' => 12,
            'phrases' => array('verjaardag', 'jubileum', 'bruiloft', 'feest', 'feestje', 'evenement', 'speciale gelegenheid', 'kerst', 'sinterklaas'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => 'Voordelig',
            'weight' => 12,
            'phrases' => array('goedkoop', 'goedkope', 'voordelig', 'voordelige', 'betaalbaar', 'betaalbare', 'niet duur', 'niet te duur', 'lage prijs', 'binnen mijn budget', 'schappelijke prijs', 'budget'),
        ),
        'best_value' => array(
            'label' => 'Beste prijs-kwaliteit',
            'weight' => 16,
            'phrases' => array('prijs kwaliteit', 'prijs kwaliteit verhouding', 'goede prijs kwaliteit', 'waar voor je geld', 'de moeite waard', 'verstandige keuze', 'veilige keuze', 'niet de goedkoopste'),
        ),
        'premium' => array(
            'label' => 'Premium',
            'weight' => 14,
            'phrases' => array('premium', 'luxe', 'luxueus', 'high end', 'betere kwaliteit', 'topkwaliteit', 'exclusief'),
        ),
        'popular' => array(
            'label' => 'Populair',
            'weight' => 10,
            'phrases' => array('populair', 'populaire', 'bestseller', 'meest verkocht', 'best verkocht', 'trending', 'veel gekocht'),
        ),
        'top_rated' => array(
            'label' => 'Best beoordeeld',
            'weight' => 10,
            'phrases' => array('best beoordeeld', 'hoogst beoordeeld', 'goede beoordelingen', 'goede reviews', 'veel sterren', 'goed beoordeeld'),
        ),
        'newest' => array(
            'label' => 'Nieuwste',
            'weight' => 8,
            'phrases' => array('nieuw', 'nieuwe', 'nieuwste', 'net binnen', 'nieuw binnen', 'laatste', 'recent toegevoegd'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => 'Heren of unisex',
            'phrases' => array(
                'cadeau voor mijn broer', 'voor mijn broer', 'cadeau voor mijn man', 'voor mijn man',
                'cadeau voor mijn vriend', 'voor mijn vriend', 'cadeau voor mijn vader', 'voor mijn vader',
                'voor mijn papa', 'cadeau voor mijn zoon', 'voor mijn zoon',
                'cadeau voor mannen', 'voor mannen', 'voor heren', 'herencadeau',
                'mijn broer', 'mijn man', 'mijn vriend', 'mijn vader', 'mijn zoon'
            ),
            'recipient_tokens' => array('broer', 'man', 'vriend', 'vader', 'papa', 'zoon', 'mannen', 'heren'),
            'positive_terms' => array('heren', 'mannen', 'mannelijk', 'unisex', 'voor hem', 'men', 'mens', 'male'),
            'negative_terms' => array('dames', 'vrouwen', 'vrouwelijk', 'jurk', 'rok', 'blouse', 'women', 'womens', 'female', 'dress', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => 'Dames of unisex',
            'phrases' => array(
                'cadeau voor mijn zus', 'voor mijn zus', 'cadeau voor mijn vrouw', 'voor mijn vrouw',
                'cadeau voor mijn vriendin', 'voor mijn vriendin', 'cadeau voor mijn moeder', 'voor mijn moeder',
                'voor mijn mama', 'cadeau voor mijn dochter', 'voor mijn dochter',
                'cadeau voor vrouwen', 'voor vrouwen', 'voor dames', 'damescadeau',
                'mijn zus', 'mijn vrouw', 'mijn vriendin', 'mijn moeder', 'mijn dochter'
            ),
            'recipient_tokens' => array('zus', 'vrouw', 'vriendin', 'moeder', 'mama', 'dochter', 'vrouwen', 'dames'),
            'positive_terms' => array('dames', 'vrouwen', 'vrouwelijk', 'unisex', 'voor haar', 'women', 'womens', 'female'),
            'negative_terms' => array('heren', 'mannen', 'mannelijk', 'jongens', 'men', 'mens', 'male', 'boys'),
        ),
        'children' => array(
            'label' => 'Voor kinderen',
            'phrases' => array('cadeau voor kinderen', 'voor kinderen', 'cadeau voor mijn kind', 'voor mijn kind', 'voor een jongen', 'voor een meisje', 'voor een baby', 'voor de baby', 'mijn kind', 'mijn kinderen', 'mijn kleine'),
            'recipient_tokens' => array('kind', 'kinderen', 'jongen', 'meisje', 'baby', 'kleine', 'peuter'),
            'positive_terms' => array('kinderen', 'kinder', 'junior', 'baby', 'jongens', 'meisjes', 'school', 'kid', 'kids', 'child', 'children'),
            'negative_terms' => array('alleen voor volwassenen', 'adults only'),
        ),
        'neutral' => array(
            'label' => 'Neutraal cadeau',
            'phrases' => array('cadeau voor een collega', 'voor een collega', 'cadeau voor collega', 'cadeau voor een vriend', 'voor een vriend', 'cadeau voor iemand', 'voor iemand'),
            'recipient_tokens' => array('collega', 'collegas', 'vriend', 'iemand'),
            'positive_terms' => array('unisex', 'one size', 'maat vrij'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('cadeau', 'cadeautje', 'kado', 'geschenk', 'cadeau idee', 'cadeauset', 'om cadeau te geven'),
        'useful_terms' => array('handig', 'praktisch', 'nuttig', 'dagelijks handig'),
        'easy_choice_terms' => array('one size', 'unisex', 'maat vrij'),
    ),

    'flexibility_phrases' => array('als het kan', 'het liefst', 'bij voorkeur', 'idealiter', 'misschien', 'zoiets als', 'iets in de trant van', 'open voor', 'als jullie hebben'),

    'filler_phrases' => array(
        'alsjeblieft', 'alstublieft', 'bedankt', 'dank je', 'dank u', 'hallo', 'hoi', 'goedemorgen', 'goedemiddag',
        'ik zoek', 'ik ben op zoek naar', 'op zoek naar', 'ik wil', 'ik zou graag', 'ik heb nodig', 'ik heb iets nodig',
        'hebben jullie', 'heb je', 'heeft u', 'is er', 'zijn er', 'verkopen jullie', 'verkoopt u', 'voeren jullie',
        'laat me zien', 'laat eens zien', 'kun je me laten zien', 'zoek voor mij', 'help me kiezen', 'help me zoeken',
        'wat raad je aan', 'wat raadt u aan', 'heb je een aanbeveling', 'een aanbeveling', 'aanbevelingen',
        'heb je tips', 'een tip', 'suggesties', 'een suggestie', 'ideeen', 'ideeën',
        'wat is het verschil tussen', 'het verschil tussen', 'wat voor', 'wat voor soort', 'welk soort',
        'ik weet het niet zeker', 'weet niet zeker', 'ik twijfel', 'hoeveel kost', 'wat kost',
        'ik wil graag zien', 'even kijken', 'ik kijk even rond', 'op dit moment', 'deze week', 'volgende week',
        'mijn oude', 'iets nieuws', 'een nieuwe', 'iets', 'wat opties', 'opties', 'van alles', 'spullen',
        'beschikbaar', 'beschikbaarheid', 'op voorraad', 'verkocht', 'aanbevolen', 'aanbeveling', 'geinteresseerd'
    ),

    // Single junk tokens. A token that is not listed here survives as a
    // REQUIRED term, so one unrecognised word returns an empty result set
    // rather than a worse-ranked one. Nothing that could name a product goes in
    // this list -- "tas", "horloge" and "riem" are products, not filler.
    'ignore_tokens' => array(
        'wat', 'welke', 'welk', 'hoe', 'waar', 'hoeveel', 'kost', 'is', 'zijn', 'heb', 'hebt',
        'heeft', 'hebben', 'kan', 'kun', 'kunt', 'zou', 'wil', 'zoek', 'zoeken', 'nodig',
        'laat', 'zien', 'kijken', 'vinden', 'help', 'raad', 'aanraden', 'aanbevolen',
        'aanbeveling', 'aanbevelingen', 'suggestie', 'suggesties', 'idee', 'ideeen',
        'optie', 'opties', 'keuze', 'ding', 'dingen', 'spullen', 'iets', 'soort',
        'hallo', 'hoi', 'bedankt', 'dank', 'graag', 'beschikbaar', 'beschikbaarheid',
        'verkopen', 'verkocht', 'nu', 'vandaag', 'morgen', 'week', 'volgende', 'oude', 'oud',
        'ok', 'oke', 'ja', 'even', 'echt', 'best', 'heel', 'erg'
    ),
);
