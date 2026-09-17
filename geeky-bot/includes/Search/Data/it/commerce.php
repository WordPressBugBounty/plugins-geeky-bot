<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Italian shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, the labels' role and the weights
 * stay aligned with English so ranking behaves identically across languages.
 *
 * Two things about Italian shape the lists below.
 *
 * 1. Accents. Phrases are matched after normalize_text(), which runs
 *    remove_accents(), so "qualità" and "qualita" collapse to one string.
 *    Accents are written properly here so the pack reads correctly.
 *
 * 2. Gender and number agreement. Adjectives agree with their noun, and many
 *    product nouns are feminine (scarpa, borsa, giacca, sciarpa, cintura), so
 *    -o/-a/-i/-e endings are all listed: "scarpa comoda" and "maglione comodo"
 *    are both ordinary ways to ask.
 *
 * positive_terms and negative_terms are matched against PRODUCT text rather
 * than the query, so the English equivalents are listed alongside the Italian.
 */
return array(
    'revision' => '2026.09.13.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => 'Comfort',
            'weight' => 18,
            'phrases' => array('comodo', 'comoda', 'comodi', 'comode', 'comfortevole', 'confortevole', 'confortevoli', 'morbido', 'morbida', 'morbidi', 'morbide', 'imbottito', 'imbottita', 'felpato', 'facile da indossare', 'vestibilità comoda', 'vestibilita comoda', 'che non stringe', 'traspirante'),
        ),
        'simple' => array(
            'label' => 'Stile semplice',
            'weight' => 10,
            'phrases' => array('semplice', 'semplici', 'minimal', 'minimalista', 'sobrio', 'sobria', 'tinta unita', 'senza fantasia', 'essenziale', 'classico', 'classica'),
        ),
        'quality' => array(
            'label' => 'Qualità',
            'weight' => 10,
            'phrases' => array('buona qualità', 'buona qualita', 'di qualità', 'qualità', 'qualita', 'ben fatto', 'ben fatta', 'ben fatti', 'fatto bene', 'resistente', 'resistenti', 'robusto', 'robusta', 'robusti', 'durevole', 'duraturo', 'che dura', 'che dura nel tempo', 'materiali buoni', 'alta qualità', 'alta qualita'),
        ),
        'lightweight' => array(
            'label' => 'Leggero',
            'weight' => 14,
            'phrases' => array('leggero', 'leggera', 'leggeri', 'leggere', 'poco peso', 'peso leggero', 'non pesante', 'non pesa', 'facile da portare', 'comodo da portare', 'leggerissimo'),
        ),
        'gift' => array(
            'label' => 'Regalo',
            'weight' => 20,
            'phrases' => array('regalo', 'regali', 'come regalo', 'per regalo', 'da regalare', 'idea regalo', 'idee regalo'),
        ),
        'useful' => array(
            'label' => 'Utile',
            'weight' => 18,
            'phrases' => array('utile', 'utili', 'pratico', 'pratica', 'pratici', 'pratiche', 'funzionale', 'funzionali', 'che si usa', 'che si può usare', 'utile ogni giorno', 'utile tutti i giorni', 'comodo da usare'),
        ),
        'casual' => array(
            'label' => 'Casual',
            'weight' => 12,
            'phrases' => array('casual', 'sportivo casual', 'di tutti i giorni', 'per tutti i giorni', 'quotidiano', 'informale', 'stile rilassato'),
        ),
        'formal' => array(
            'label' => 'Elegante',
            'weight' => 12,
            'phrases' => array('elegante', 'eleganti', 'formale', 'formali', 'da cerimonia', 'per ufficio', 'da ufficio', 'professionale', 'raffinato', 'raffinata', 'curato', 'chic', 'da sera'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => 'Uso quotidiano',
            'weight' => 16,
            'phrases' => array('tutti i giorni', 'ogni giorno', 'uso quotidiano', 'per tutti i giorni', 'di uso comune', 'uso regolare'),
        ),
        'office' => array(
            'label' => 'Ufficio',
            'weight' => 18,
            'phrases' => array('ufficio', 'in ufficio', 'per l ufficio', 'al lavoro', 'per il lavoro', 'per andare a lavoro', 'uso professionale'),
        ),
        'summer' => array(
            'label' => 'Estate',
            'weight' => 16,
            'phrases' => array('estate', 'per l estate', 'in estate', 'quando fa caldo', 'clima caldo', 'traspirante', 'fresco', 'fresca'),
        ),
        'winter' => array(
            'label' => 'Inverno',
            'weight' => 16,
            'phrases' => array('inverno', 'per l inverno', 'in inverno', 'quando fa freddo', 'clima freddo', 'caldo per l inverno', 'accogliente'),
        ),
        'travel' => array(
            'label' => 'Viaggio',
            'weight' => 16,
            'phrases' => array('viaggio', 'in viaggio', 'per viaggiare', 'per un viaggio', 'per le vacanze', 'bagaglio a mano', 'facile da mettere in valigia'),
        ),
        'sports' => array(
            'label' => 'Sport',
            'weight' => 16,
            'phrases' => array('sport', 'sportivo', 'sportiva', 'sportivi', 'palestra', 'in palestra', 'allenamento', 'allenarsi', 'corsa', 'correre', 'running', 'camminata', 'camminare', 'jogging', 'esercizio', 'fitness', 'trekking', 'attività fisica'),
        ),
        'school' => array(
            'label' => 'Scuola o studio',
            'weight' => 14,
            'phrases' => array('scuola', 'per la scuola', 'università', 'universita', 'liceo', 'studente', 'studentessa', 'per studiare', 'per le lezioni'),
        ),
        'occasion' => array(
            'label' => 'Occasione speciale',
            'weight' => 12,
            'phrases' => array('compleanno', 'anniversario', 'matrimonio', 'festa', 'evento', 'occasione speciale', 'natale', 'laurea'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => 'Economico',
            'weight' => 12,
            'phrases' => array('economico', 'economica', 'economici', 'poco costoso', 'non costoso', 'non troppo caro', 'a buon mercato', 'conveniente', 'prezzo basso', 'budget basso', 'nel mio budget', 'prezzo ragionevole', 'buon prezzo'),
        ),
        'best_value' => array(
            'label' => 'Miglior rapporto qualità-prezzo',
            'weight' => 16,
            'phrases' => array('rapporto qualità prezzo', 'rapporto qualita prezzo', 'qualità prezzo', 'per il prezzo', 'ne vale la pena', 'vale la spesa', 'scelta sicura', 'la scelta migliore', 'non il più economico'),
        ),
        'premium' => array(
            'label' => 'Premium',
            'weight' => 14,
            'phrases' => array('premium', 'lusso', 'di lusso', 'fascia alta', 'alta gamma', 'qualità superiore', 'qualita superiore', 'migliore qualità'),
        ),
        'popular' => array(
            'label' => 'Popolare',
            'weight' => 10,
            'phrases' => array('popolare', 'popolari', 'più venduto', 'piu venduto', 'i più venduti', 'best seller', 'di tendenza', 'preferito dai clienti'),
        ),
        'top_rated' => array(
            'label' => 'Più votati',
            'weight' => 10,
            'phrases' => array('più votati', 'piu votati', 'meglio recensito', 'migliori recensioni', 'ottime recensioni', 'valutazione alta', 'ben recensito'),
        ),
        'newest' => array(
            'label' => 'Novità',
            'weight' => 8,
            'phrases' => array('nuovo', 'nuova', 'nuovi', 'novità', 'novita', 'ultimo arrivo', 'ultimi arrivi', 'appena arrivato', 'appena aggiunto'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => 'Preferenza uomo o unisex',
            'phrases' => array(
                'regalo per mio fratello', 'per mio fratello', 'regalo per mio marito', 'per mio marito',
                'regalo per il mio ragazzo', 'per il mio ragazzo', 'regalo per mio padre', 'per mio padre',
                'regalo per mio papà', 'per mio papa', 'regalo per mio figlio', 'per mio figlio',
                'regalo per uomo', 'per uomo', 'per uomini', 'regalo da uomo',
                'mio fratello', 'mio marito', 'mio padre', 'mio papà', 'mio figlio'
            ),
            'recipient_tokens' => array('fratello', 'marito', 'ragazzo', 'padre', 'papà', 'papa', 'figlio', 'uomo', 'uomini'),
            'positive_terms' => array('uomo', 'uomini', 'maschile', 'da uomo', 'unisex', 'per lui', 'men', 'mens', 'male'),
            'negative_terms' => array('donna', 'donne', 'femminile', 'da donna', 'vestito', 'gonna', 'camicetta', 'women', 'womens', 'female', 'dress', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => 'Preferenza donna o unisex',
            'phrases' => array(
                'regalo per mia sorella', 'per mia sorella', 'regalo per mia moglie', 'per mia moglie',
                'regalo per la mia ragazza', 'per la mia ragazza', 'regalo per mia madre', 'per mia madre',
                'regalo per mia mamma', 'per mia mamma', 'regalo per mia figlia', 'per mia figlia',
                'regalo per donna', 'per donna', 'per donne', 'regalo da donna',
                'mia sorella', 'mia moglie', 'mia madre', 'mia mamma', 'mia figlia'
            ),
            'recipient_tokens' => array('sorella', 'moglie', 'ragazza', 'madre', 'mamma', 'figlia', 'donna', 'donne'),
            'positive_terms' => array('donna', 'donne', 'femminile', 'da donna', 'unisex', 'per lei', 'women', 'womens', 'female'),
            'negative_terms' => array('uomo', 'uomini', 'maschile', 'bambini maschi', 'men', 'mens', 'male', 'boys'),
        ),
        'children' => array(
            'label' => 'Preferenza bambini',
            'phrases' => array('regalo per bambino', 'per bambino', 'regalo per bambina', 'per bambina', 'regalo per bambini', 'per bambini', 'regalo per mio figlio piccolo', 'per un neonato', 'per il neonato', 'mio bambino', 'i miei bambini', 'mio piccolo', 'mia piccola'),
            'recipient_tokens' => array('bambino', 'bambina', 'bambini', 'neonato', 'piccolo', 'piccola', 'ragazzino'),
            'positive_terms' => array('bambino', 'bambini', 'junior', 'neonato', 'scuola', 'kid', 'kids', 'child', 'children'),
            'negative_terms' => array('solo per adulti', 'adults only'),
        ),
        'neutral' => array(
            'label' => 'Regalo neutro',
            'phrases' => array('regalo per un collega', 'per un collega', 'per una collega', 'regalo per colleghi', 'regalo per un amico', 'per un amico', 'per un amica', 'regalo per qualcuno', 'per qualcuno'),
            'recipient_tokens' => array('collega', 'colleghi', 'amico', 'amica', 'qualcuno'),
            'positive_terms' => array('unisex', 'taglia unica', 'one size'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('regalo', 'regali', 'idea regalo', 'confezione regalo', 'da regalare', 'come regalo'),
        'useful_terms' => array('utile', 'pratico', 'funzionale', 'utile ogni giorno'),
        'easy_choice_terms' => array('taglia unica', 'unisex', 'one size'),
    ),

    'flexibility_phrases' => array('se possibile', 'preferibilmente', 'idealmente', 'magari', 'forse', 'qualcosa tipo', 'qualcosa come', 'sono aperto a', 'se avete'),

    'filler_phrases' => array(
        'per favore', 'per piacere', 'grazie', 'grazie mille', 'ciao', 'buongiorno', 'buonasera', 'salve',
        'scusa', 'scusate', 'senti', 'senta', 'volevo chiedere', 'una domanda', 'avrei una domanda',
        'sto dando un occhiata', 'sto guardando', 'mi piacerebbe', 'mi piacerebbe vedere',
        'che ne pensi', 'secondo te', 'secondo voi', 'mi puoi aiutare', 'mi potete aiutare',
        'ho bisogno di qualcosa', 'sto cercando qualcosa', 'niente di troppo', 'niente di speciale',
        'una cosa semplice', 'qualcosa di bello', 'qualcosa di utile', 'per fare un regalo',
        'sto cercando', 'cerco', 'vorrei', 'voglio', 'mi serve', 'ho bisogno di', 'avrei bisogno di',
        'avete', 'ci sono', 'c è', 'esiste', 'vendete', 'trattate', 'tenete',
        'mostrami', 'fammi vedere', 'mi fai vedere', 'mi mostri', 'fatemi vedere',
        'trovami', 'aiutami a trovare', 'aiutami a scegliere', 'cosa mi consigli',
        'cosa consigliate', 'cosa mi consigliate', 'hai qualche consiglio', 'qualche consiglio',
        'dei consigli', 'qualche suggerimento', 'dei suggerimenti', 'qualche idea',
        'qual è la differenza tra', 'la differenza tra', 'che tipo di', 'che genere di',
        'un tipo di', 'non so bene', 'non sono sicuro', 'non sono sicura', 'quanto costa',
        'quanto costano', 'vorrei vedere', 'voglio vedere', 'do un occhiata', 'sto solo guardando',
        'in questo momento', 'questa settimana', 'la prossima settimana', 'subito',
        'il mio vecchio', 'la mia vecchia', 'uno nuovo', 'una nuova', 'qualcosa', 'qualche opzione',
        'delle opzioni', 'qualsiasi cosa', 'roba', 'cose', 'disponibile', 'disponibilità',
        'vendete', 'venduto', 'consigliato', 'consiglio', 'suggerimento', 'interessato', 'mi chiedevo'
    ),

    // Single junk tokens. A token that is not listed here survives as a
    // REQUIRED term, so one unrecognised word returns an empty result set
    // rather than a worse-ranked one. Nothing that could name a product goes in
    // this list -- "borsa", "orologio" and "cintura" are products, not filler.
    'ignore_tokens' => array(
        'cosa', 'quale', 'quali', 'come', 'dove', 'quanto', 'costa', 'costano',
        'avete', 'avere', 'posso', 'potrei', 'vorrei', 'voglio', 'serve', 'bisogno',
        'cerco', 'cercando', 'mostrami', 'vedere', 'guardare', 'trovare', 'trovami',
        'aiutami', 'consiglio', 'consigli', 'consigliate', 'consigliato', 'suggerimento',
        'suggerimenti', 'idea', 'idee', 'opzione', 'opzioni', 'scelta', 'roba', 'cose',
        'qualcosa', 'qualsiasi', 'tipo', 'genere', 'ciao', 'grazie', 'favore', 'scusa', 'scusate',
        'penso', 'credo', 'magari', 'insomma', 'praticamente', 'davvero', 'proprio', 'piuttosto',
        'niente', 'nulla', 'solo', 'soltanto', 'ancora', 'gia', 'già', 'poi', 'quindi', 'allora',
        'disponibile', 'disponibilita', 'vendete', 'venduto', 'adesso', 'ora', 'oggi',
        'settimana', 'prossima', 'vecchio', 'vecchia', 'ok', 'okay', 'si'
    ),
);
