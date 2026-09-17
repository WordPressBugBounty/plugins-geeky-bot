<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * French shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, the labels' role and the weights
 * stay aligned with English so ranking behaves identically across languages.
 *
 * Two things about French shape the lists below.
 *
 * 1. Accents. Phrases are matched after SearchLanguageService::normalize_text(),
 *    which runs remove_accents() -- so "léger" and "leger" collapse to the same
 *    string. Accents are still written properly here because the pack has to
 *    read correctly to whoever maintains it; the duplicates cost nothing.
 *
 * 2. Gender and number agreement. An adjective agrees with its noun, and many
 *    product nouns are feminine (chaussure, veste, montre, ceinture, écharpe),
 *    so the feminine form is not an optional extra -- "chaussure confortable"
 *    and "pull confortable" are both ordinary. Masculine, feminine and plural
 *    are listed wherever a shopper would naturally use them.
 *
 * positive_terms and negative_terms are matched against PRODUCT text rather
 * than the query, so the English equivalents are listed alongside the French:
 * a French shopper browsing an English catalog must still have "women" read as
 * a women's product.
 */
return array(
    'revision' => '2026.09.13.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => 'Confort',
            'weight' => 18,
            'phrases' => array('confortable', 'comfortable', 'confort', 'doux', 'douce', 'moelleux', 'rembourré', 'rembourre', 'agréable à porter', 'agreable a porter', 'facile à porter', 'coupe décontractée'),
        ),
        'simple' => array(
            'label' => 'Style simple',
            'weight' => 10,
            'phrases' => array('simple', 'minimaliste', 'minimal', 'sobre', 'uni', 'unie', 'sans motif', 'discret', 'discrète', 'classique'),
        ),
        'quality' => array(
            'label' => 'Qualité',
            'weight' => 10,
            'phrases' => array('bonne qualité', 'bonne qualite', 'de qualité', 'qualité', 'qualite', 'bien fait', 'bien fabriqué', 'solide', 'résistant', 'resistant', 'durable', 'qui dure'),
        ),
        'lightweight' => array(
            'label' => 'Léger',
            'weight' => 14,
            'phrases' => array('léger', 'leger', 'légère', 'legere', 'poids léger', 'pas lourd', 'pas lourde', 'facile à porter', 'facile a porter'),
        ),
        'gift' => array(
            'label' => 'Cadeau',
            'weight' => 20,
            'phrases' => array('cadeau', 'un cadeau', 'comme cadeau', 'en cadeau', 'idée cadeau', 'idee cadeau', 'pour offrir', 'à offrir', 'a offrir'),
        ),
        'useful' => array(
            'label' => 'Utile',
            'weight' => 18,
            'phrases' => array('utile', 'pratique', 'fonctionnel', 'fonctionnelle', 'qui sert', 'qu on peut utiliser', 'utile au quotidien'),
        ),
        'casual' => array(
            'label' => 'Décontracté',
            'weight' => 12,
            'phrases' => array('décontracté', 'decontracte', 'décontractée', 'casual', 'de tous les jours', 'au quotidien', 'style décontracté'),
        ),
        'formal' => array(
            'label' => 'Habillé',
            'weight' => 12,
            'phrases' => array('habillé', 'habille', 'formel', 'formelle', 'chic', 'élégant', 'elegant', 'élégante', 'professionnel', 'pour le bureau', 'tenue de ville'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => 'Usage quotidien',
            'weight' => 16,
            'phrases' => array('tous les jours', 'au quotidien', 'usage quotidien', 'de tous les jours', 'pour tous les jours', 'usage régulier'),
        ),
        'office' => array(
            'label' => 'Bureau',
            'weight' => 18,
            'phrases' => array('bureau', 'au bureau', 'pour le bureau', 'au travail', 'pour le travail', 'pour aller travailler', 'usage professionnel'),
        ),
        'summer' => array(
            'label' => 'Été',
            'weight' => 16,
            'phrases' => array('été', 'ete', 'pour l été', 'temps chaud', 'quand il fait chaud', 'respirant', 'respirante', 'léger pour l été'),
        ),
        'winter' => array(
            'label' => 'Hiver',
            'weight' => 16,
            'phrases' => array('hiver', 'pour l hiver', 'temps froid', 'quand il fait froid', 'chaud pour l hiver', 'bien chaud', 'douillet'),
        ),
        'travel' => array(
            'label' => 'Voyage',
            'weight' => 16,
            'phrases' => array('voyage', 'en voyage', 'pour voyager', 'pour un voyage', 'pour partir', 'facile à ranger', 'cabine'),
        ),
        'sports' => array(
            'label' => 'Sport',
            'weight' => 16,
            'phrases' => array('sport', 'sportif', 'sportive', 'salle de sport', 'gym', 'entraînement', 'entrainement', 'course', 'courir', 'running', 'marche', 'jogging', 'exercice'),
        ),
        'school' => array(
            'label' => 'École ou études',
            'weight' => 14,
            'phrases' => array('école', 'ecole', 'collège', 'lycée', 'fac', 'faculté', 'université', 'universite', 'étudiant', 'etudiant', 'étudiante', 'pour les cours', 'rentrée'),
        ),
        'occasion' => array(
            'label' => 'Occasion spéciale',
            'weight' => 12,
            'phrases' => array('anniversaire', 'mariage', 'fête', 'fete', 'soirée', 'soiree', 'événement', 'evenement', 'occasion spéciale', 'noël', 'noel'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => 'Petit budget',
            'weight' => 12,
            'phrases' => array('pas cher', 'pas chère', 'bon marché', 'bon marche', 'économique', 'economique', 'abordable', 'petit budget', 'dans mon budget', 'prix raisonnable', 'bon prix', 'pas trop cher'),
        ),
        'best_value' => array(
            'label' => 'Meilleur rapport qualité-prix',
            'weight' => 16,
            'phrases' => array('rapport qualité prix', 'rapport qualite prix', 'bon rapport qualité prix', 'qualité prix', 'pour le prix', 'ça vaut le coup', 'ca vaut le coup', 'vaut le coup', 'le meilleur choix', 'choix sûr', 'pas le moins cher'),
        ),
        'premium' => array(
            'label' => 'Haut de gamme',
            'weight' => 14,
            'phrases' => array('haut de gamme', 'premium', 'luxe', 'de luxe', 'meilleure qualité', 'meilleure qualite', 'qualité supérieure', 'plus haut de gamme'),
        ),
        'popular' => array(
            'label' => 'Populaire',
            'weight' => 10,
            'phrases' => array('populaire', 'le plus vendu', 'les plus vendus', 'meilleures ventes', 'best seller', 'tendance', 'à la mode', 'préféré des clients'),
        ),
        'top_rated' => array(
            'label' => 'Les mieux notés',
            'weight' => 10,
            'phrases' => array('les mieux notés', 'les mieux notes', 'mieux noté', 'meilleures notes', 'meilleurs avis', 'bien noté', 'bonnes critiques'),
        ),
        'newest' => array(
            'label' => 'Nouveautés',
            'weight' => 8,
            'phrases' => array('nouveau', 'nouvelle', 'nouveautés', 'nouveautes', 'dernier', 'dernière', 'les plus récents', 'récemment ajouté', 'qui vient de sortir'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => 'Préférence homme ou mixte',
            'phrases' => array(
                'cadeau pour mon frère', 'cadeau pour mon frere', 'pour mon frère', 'pour mon frere',
                'cadeau pour mon mari', 'pour mon mari', 'cadeau pour mon copain', 'pour mon copain',
                'cadeau pour mon père', 'cadeau pour mon pere', 'pour mon père', 'pour mon pere', 'pour mon papa',
                'cadeau pour mon fils', 'pour mon fils', 'cadeau pour homme', 'pour homme', 'pour hommes',
                'mon frère', 'mon frere', 'mon mari', 'mon copain', 'mon père', 'mon pere', 'mon papa', 'mon fils'
            ),
            'recipient_tokens' => array('frère', 'frere', 'mari', 'copain', 'père', 'pere', 'papa', 'fils', 'homme', 'hommes', 'monsieur'),
            'positive_terms' => array('homme', 'hommes', 'masculin', 'mixte', 'unisexe', 'pour lui', 'men', 'mens', 'male', 'unisex'),
            'negative_terms' => array('femme', 'femmes', 'féminin', 'feminin', 'dame', 'robe', 'jupe', 'chemisier', 'women', 'womens', 'female', 'dress', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => 'Préférence femme ou mixte',
            'phrases' => array(
                'cadeau pour ma soeur', 'cadeau pour ma sœur', 'pour ma soeur', 'pour ma sœur',
                'cadeau pour ma femme', 'pour ma femme', 'cadeau pour ma copine', 'pour ma copine',
                'cadeau pour ma mère', 'cadeau pour ma mere', 'pour ma mère', 'pour ma mere', 'pour ma maman',
                'cadeau pour ma fille', 'pour ma fille', 'cadeau pour femme', 'pour femme', 'pour femmes',
                'ma soeur', 'ma sœur', 'ma femme', 'ma copine', 'ma mère', 'ma mere', 'ma maman', 'ma fille'
            ),
            'recipient_tokens' => array('soeur', 'sœur', 'femme', 'copine', 'mère', 'mere', 'maman', 'fille', 'femmes', 'dame'),
            'positive_terms' => array('femme', 'femmes', 'féminin', 'feminin', 'mixte', 'unisexe', 'pour elle', 'women', 'womens', 'female', 'unisex'),
            'negative_terms' => array('homme', 'hommes', 'masculin', 'garçon', 'garcon', 'men', 'mens', 'male', 'boys'),
        ),
        'children' => array(
            'label' => 'Préférence enfant',
            'phrases' => array('cadeau pour enfant', 'pour enfant', 'pour les enfants', 'cadeau pour mon enfant', 'pour mon enfant', 'cadeau pour un garçon', 'pour un garçon', 'cadeau pour une fille', 'pour une fille', 'pour un bébé', 'pour bébé', 'mon enfant', 'mes enfants', 'mon petit', 'ma petite'),
            'recipient_tokens' => array('enfant', 'enfants', 'garçon', 'garcon', 'fillette', 'bébé', 'bebe', 'petit', 'petite'),
            'positive_terms' => array('enfant', 'enfants', 'junior', 'bébé', 'bebe', 'garçon', 'fille', 'scolaire', 'kid', 'kids', 'child', 'children'),
            'negative_terms' => array('adulte seulement', 'pour adultes', 'adults only'),
        ),
        'neutral' => array(
            'label' => 'Cadeau neutre',
            'phrases' => array('cadeau pour un collègue', 'cadeau pour un collegue', 'pour un collègue', 'pour une collègue', 'cadeau pour un ami', 'cadeau pour une amie', 'pour un ami', 'pour une amie', 'cadeau pour quelqu un', 'pour quelqu un'),
            'recipient_tokens' => array('collègue', 'collegue', 'ami', 'amie', 'quelqu un'),
            'positive_terms' => array('unisexe', 'mixte', 'taille unique', 'unisex', 'one size'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('cadeau', 'cadeaux', 'idée cadeau', 'idee cadeau', 'coffret cadeau', 'à offrir', 'pour offrir'),
        'useful_terms' => array('utile', 'pratique', 'fonctionnel', 'utile au quotidien'),
        'easy_choice_terms' => array('taille unique', 'unisexe', 'mixte'),
    ),

    'flexibility_phrases' => array('si possible', 'de préférence', 'de preference', 'idéalement', 'idealement', 'peut être', 'peut etre', 'quelque chose comme', 'ouvert à', 'dans le genre', 'si vous avez'),

    'filler_phrases' => array(
        's il vous plaît', 's il vous plait', 's il te plaît', 'svp', 'merci', 'bonjour', 'salut', 'bonsoir',
        'je cherche', 'je recherche', 'je voudrais', 'je veux', 'j aimerais', 'il me faut', 'j ai besoin de',
        'est ce que vous avez', 'est ce qu il y a', 'avez vous', 'vous avez', 'auriez vous', 'y a t il',
        'vous vendez', 'est ce que vous vendez', 'vous faites', 'montrez moi', 'montre moi', 'affiche moi',
        'trouve moi', 'trouvez moi', 'aidez moi à trouver', 'aide moi à choisir',
        'que me conseillez vous', 'que conseillez vous', 'qu est ce que vous recommandez',
        'vous me conseillez quoi', 'des recommandations', 'une recommandation', 'des suggestions',
        'une suggestion', 'des idées', 'des idees', 'une idée', 'quelle est la différence entre',
        'la différence entre', 'quel type de', 'quelle sorte de', 'quel genre de', 'un genre de',
        'je ne sais pas trop', 'je sais pas', 'je suis pas sûr', 'combien coûte', 'combien coute',
        'combien ça coûte', 'ça coûte combien', 'je voudrais voir', 'je veux voir', 'faire voir',
        'jeter un oeil', 'en ce moment', 'cette semaine', 'la semaine prochaine', 'tout de suite',
        'mon ancien', 'mon vieux', 'un nouveau', 'une nouvelle', 'quelque chose', 'quelques options',
        'des options', 'n importe quoi', 'truc', 'trucs', 'disponible', 'disponibilité', 'en stock',
        'vend', 'vendez', 'vendu', 'cherche', 'recherche', 'conseiller', 'conseille', 'recommande',
        'recommandé', 'suggère', 'suggéré', 'intéressé', 'interesse', 'je me demande'
    ),

    // Single junk tokens. A token that is not listed here survives as a
    // REQUIRED term, so one unrecognised word returns an empty result set
    // rather than a worse-ranked one. Nothing that could name a product goes in
    // this list -- use filler_phrases for those.
    'ignore_tokens' => array(
        'quoi', 'quel', 'quelle', 'quels', 'quelles', 'est', 'sont', 'avez', 'avoir', 'faut',
        'peux', 'peut', 'pourrais', 'voudrais', 'veux', 'cherche', 'recherche', 'besoin',
        // "montre" (a watch), "affiche" (a poster) and "type" (Type-C) are
        // deliberately absent: each names a real product. Their verb forms are
        // safe, and the request framings live in filler_phrases instead.
        'montrez', 'trouve', 'trouvez', 'donne', 'donnez', 'aide', 'aidez',
        'conseil', 'conseils', 'conseille', 'conseillez', 'recommande', 'recommandez',
        'recommandation', 'recommandations', 'suggestion', 'suggestions', 'suggère', 'suggerez',
        'idée', 'idee', 'idées', 'idees', 'option', 'options', 'choix', 'chose', 'choses',
        'truc', 'trucs', 'machin', 'quelque', 'quelques', 'genre', 'sorte',
        'bonjour', 'salut', 'bonsoir', 'merci', 'svp', 'disponible', 'disponibilite',
        'vend', 'vendez', 'vendu', 'vends', 'actuellement', 'maintenant', 'vraiment',
        'aujourd hui', 'demain', 'semaine', 'prochaine', 'ancien', 'vieux', 'nouveau',
        'ok', 'okay', 'ouais', 'oui', 'voir', 'regarder', 'combien', 'coute', 'coûte'
    ),
);
