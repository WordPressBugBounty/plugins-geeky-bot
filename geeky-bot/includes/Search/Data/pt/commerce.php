<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Portuguese shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, the labels' role and the weights
 * stay aligned with English so ranking behaves identically across languages.
 *
 * Written for Brazilian Portuguese first, because pt-BR is the larger shopper
 * population, but European forms are listed alongside wherever the two diverge
 * in ordinary shopping language: "tênis"/"ténis", "celular"/"telemóvel",
 * "legal"/"fixe". BuyerIntentLibrary resolves both pt-BR and pt-PT to the same
 * two-letter `pt` pack, so a phrase omitted here is simply not understood for
 * half the speakers.
 *
 * Accents are matched after normalize_text(), which runs remove_accents(), so
 * "tênis" and "tenis" collapse to one string; both are written where a shopper
 * types either. Adjectives agree with their noun, so -o/-a/-os/-as endings are
 * all listed: "bolsa confortável" and "tênis confortável" are both ordinary.
 *
 * positive_terms and negative_terms are matched against PRODUCT text rather
 * than the query, so the English equivalents are listed alongside.
 */
return array(
    'revision' => '2026.09.13.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => 'Conforto',
            'weight' => 18,
            'phrases' => array('confortável', 'confortavel', 'confortáveis', 'conforto', 'macio', 'macia', 'acolchoado', 'acolchoada', 'fácil de usar', 'facil de usar', 'modelagem solta', 'folgado'),
        ),
        'simple' => array(
            'label' => 'Estilo simples',
            'weight' => 10,
            'phrases' => array('simples', 'minimalista', 'básico', 'basico', 'básica', 'liso', 'lisa', 'sem estampa', 'discreto', 'discreta', 'clássico', 'classico'),
        ),
        'quality' => array(
            'label' => 'Qualidade',
            'weight' => 10,
            'phrases' => array('boa qualidade', 'de qualidade', 'qualidade', 'bem feito', 'bem feita', 'resistente', 'durável', 'duravel', 'que dura', 'reforçado', 'reforcado'),
        ),
        'lightweight' => array(
            'label' => 'Leve',
            'weight' => 14,
            'phrases' => array('leve', 'leves', 'peso leve', 'não pesado', 'nao pesado', 'não é pesado', 'fácil de carregar', 'facil de carregar'),
        ),
        'gift' => array(
            'label' => 'Presente',
            'weight' => 20,
            'phrases' => array('presente', 'presentes', 'de presente', 'para presente', 'para presentear', 'ideia de presente', 'prenda'),
        ),
        'useful' => array(
            'label' => 'Útil',
            'weight' => 18,
            'phrases' => array('útil', 'util', 'prático', 'pratico', 'prática', 'funcional', 'que dá para usar', 'útil no dia a dia'),
        ),
        'casual' => array(
            'label' => 'Casual',
            'weight' => 12,
            'phrases' => array('casual', 'do dia a dia', 'para o dia a dia', 'dia a dia', 'informal', 'despojado', 'estilo relaxado'),
        ),
        'formal' => array(
            'label' => 'Social',
            'weight' => 12,
            'phrases' => array('social', 'formal', 'elegante', 'chique', 'para trabalho', 'profissional', 'para o escritório', 'de festa'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => 'Uso diário',
            'weight' => 16,
            'phrases' => array('dia a dia', 'todo dia', 'uso diário', 'uso diario', 'para o dia a dia', 'uso frequente', 'no dia a dia'),
        ),
        'office' => array(
            'label' => 'Escritório',
            'weight' => 18,
            'phrases' => array('escritório', 'escritorio', 'para o escritório', 'no trabalho', 'para trabalhar', 'para o trabalho', 'uso profissional'),
        ),
        'summer' => array(
            'label' => 'Verão',
            'weight' => 16,
            'phrases' => array('verão', 'verao', 'para o verão', 'no verão', 'calor', 'quando faz calor', 'tempo quente', 'respirável', 'respiravel', 'fresco'),
        ),
        'winter' => array(
            'label' => 'Inverno',
            'weight' => 16,
            'phrases' => array('inverno', 'para o inverno', 'no inverno', 'frio', 'quando faz frio', 'tempo frio', 'quentinho', 'para o frio'),
        ),
        'travel' => array(
            'label' => 'Viagem',
            'weight' => 16,
            'phrases' => array('viagem', 'para viagem', 'para viajar', 'em viagem', 'para as férias', 'para as ferias', 'bagagem de mão', 'fácil de guardar'),
        ),
        'sports' => array(
            'label' => 'Esporte ou exercício',
            'weight' => 16,
            'phrases' => array('esporte', 'esportivo', 'esportiva', 'desporto', 'academia', 'ginásio', 'treino', 'corrida', 'correr', 'caminhada', 'exercício', 'exercicio', 'malhar'),
        ),
        'school' => array(
            'label' => 'Escola ou estudo',
            'weight' => 14,
            'phrases' => array('escola', 'para escola', 'faculdade', 'universidade', 'colégio', 'colegio', 'estudante', 'aluno', 'para estudar', 'para as aulas'),
        ),
        'occasion' => array(
            'label' => 'Ocasião especial',
            'weight' => 12,
            'phrases' => array('aniversário', 'aniversario', 'casamento', 'festa', 'evento', 'ocasião especial', 'ocasiao especial', 'natal', 'formatura', 'dia das mães'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => 'Em conta',
            'weight' => 12,
            'phrases' => array('barato', 'barata', 'baratos', 'em conta', 'não caro', 'nao caro', 'não muito caro', 'econômico', 'economico', 'acessível', 'acessivel', 'preço baixo', 'preco baixo', 'dentro do meu orçamento', 'bom preço', 'bom preco', 'custo baixo'),
        ),
        'best_value' => array(
            'label' => 'Melhor custo-benefício',
            'weight' => 16,
            'phrases' => array('custo benefício', 'custo beneficio', 'melhor custo benefício', 'vale a pena', 'compensa', 'pelo preço', 'pelo preco', 'escolha segura', 'a melhor escolha', 'não o mais barato', 'nao o mais barato'),
        ),
        'premium' => array(
            'label' => 'Premium',
            'weight' => 14,
            'phrases' => array('premium', 'luxo', 'de luxo', 'top de linha', 'alta qualidade', 'melhor qualidade', 'sofisticado', 'sofisticada'),
        ),
        'popular' => array(
            'label' => 'Popular',
            'weight' => 10,
            'phrases' => array('popular', 'populares', 'mais vendido', 'mais vendidos', 'best seller', 'em alta', 'tendência', 'tendencia', 'queridinho'),
        ),
        'top_rated' => array(
            'label' => 'Mais bem avaliados',
            'weight' => 10,
            'phrases' => array('mais bem avaliado', 'melhor avaliado', 'melhores avaliações', 'melhores avaliacoes', 'boas avaliações', 'bem avaliado', 'melhores notas', 'boas críticas'),
        ),
        'newest' => array(
            'label' => 'Novidades',
            'weight' => 8,
            'phrases' => array('novo', 'nova', 'novos', 'novidade', 'novidades', 'lançamento', 'lancamento', 'mais recente', 'recém chegado', 'acabou de chegar'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => 'Preferência masculina ou unissex',
            'phrases' => array(
                'presente para meu irmão', 'presente para meu irmao', 'para meu irmão', 'para meu irmao',
                'presente para meu marido', 'para meu marido', 'presente para meu namorado', 'para meu namorado',
                'presente para meu pai', 'para meu pai', 'presente para meu filho', 'para meu filho',
                'presente para homem', 'para homem', 'para homens', 'presente masculino',
                'meu irmão', 'meu irmao', 'meu marido', 'meu namorado', 'meu pai', 'meu filho'
            ),
            'recipient_tokens' => array('irmão', 'irmao', 'marido', 'namorado', 'pai', 'filho', 'homem', 'homens'),
            'positive_terms' => array('masculino', 'masculina', 'homem', 'homens', 'unissex', 'para ele', 'men', 'mens', 'male', 'unisex'),
            'negative_terms' => array('feminino', 'feminina', 'mulher', 'mulheres', 'vestido', 'saia', 'blusa', 'women', 'womens', 'female', 'dress', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => 'Preferência feminina ou unissex',
            'phrases' => array(
                'presente para minha irmã', 'presente para minha irma', 'para minha irmã', 'para minha irma',
                'presente para minha esposa', 'para minha esposa', 'presente para minha namorada', 'para minha namorada',
                'presente para minha mãe', 'presente para minha mae', 'para minha mãe', 'para minha mae',
                'presente para minha filha', 'para minha filha', 'presente para mulher', 'para mulher',
                'para mulheres', 'presente feminino',
                'minha irmã', 'minha irma', 'minha esposa', 'minha namorada', 'minha mãe', 'minha mae', 'minha filha'
            ),
            'recipient_tokens' => array('irmã', 'irma', 'esposa', 'namorada', 'mãe', 'mae', 'filha', 'mulher', 'mulheres'),
            'positive_terms' => array('feminino', 'feminina', 'mulher', 'mulheres', 'unissex', 'para ela', 'women', 'womens', 'female', 'unisex'),
            'negative_terms' => array('masculino', 'masculina', 'homem', 'homens', 'menino', 'men', 'mens', 'male', 'boys'),
        ),
        'children' => array(
            'label' => 'Preferência infantil',
            'phrases' => array('presente para criança', 'presente para crianca', 'para criança', 'para crianças', 'presente para meu filho pequeno', 'para menino', 'presente para menino', 'para menina', 'presente para menina', 'para bebê', 'para bebe', 'meu filho', 'meus filhos', 'minha criança'),
            'recipient_tokens' => array('criança', 'crianca', 'crianças', 'menino', 'menina', 'bebê', 'bebe', 'pequeno', 'pequena'),
            'positive_terms' => array('infantil', 'criança', 'crianca', 'junior', 'bebê', 'bebe', 'menino', 'menina', 'escolar', 'kid', 'kids', 'child', 'children'),
            'negative_terms' => array('apenas para adultos', 'adults only'),
        ),
        'neutral' => array(
            'label' => 'Presente neutro',
            'phrases' => array('presente para colega', 'para um colega', 'para uma colega', 'presente para colega de trabalho', 'presente para amigo', 'presente para amiga', 'para um amigo', 'para uma amiga', 'presente para alguém', 'para alguem'),
            'recipient_tokens' => array('colega', 'amigo', 'amiga', 'alguém', 'alguem'),
            'positive_terms' => array('unissex', 'tamanho único', 'tamanho unico', 'unisex', 'one size'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('presente', 'presentes', 'ideia de presente', 'kit presente', 'para presentear', 'de presente', 'prenda'),
        'useful_terms' => array('útil', 'util', 'prático', 'pratico', 'funcional', 'útil no dia a dia'),
        'easy_choice_terms' => array('tamanho único', 'tamanho unico', 'unissex', 'one size'),
    ),

    'flexibility_phrases' => array('se possível', 'se possivel', 'de preferência', 'de preferencia', 'idealmente', 'talvez', 'algo como', 'algo tipo', 'aberto a', 'se tiver', 'se vocês tiverem'),

    'filler_phrases' => array(
        'por favor', 'obrigado', 'obrigada', 'oi', 'olá', 'ola', 'bom dia', 'boa tarde', 'boa noite',
        'estou procurando', 'to procurando', 'procuro', 'quero', 'queria', 'gostaria de', 'eu preciso de',
        'preciso de', 'estou atrás de', 'vocês têm', 'voces tem', 'você tem', 'voce tem', 'tem algum',
        'tem alguma', 'tem aí', 'existe', 'vocês vendem', 'voces vendem', 'você vende', 'vendem',
        'me mostra', 'me mostre', 'mostra pra mim', 'me mostrem', 'me ajuda a achar',
        'me ajuda a escolher', 'o que você recomenda', 'o que voce recomenda', 'o que vocês recomendam',
        'o que me recomenda', 'alguma recomendação', 'alguma recomendacao', 'alguma sugestão',
        'alguma sugestao', 'alguma ideia', 'tem alguma dica', 'qual a diferença entre',
        'qual e a diferenca entre', 'a diferença entre', 'que tipo de', 'que tipo', 'qual tipo de',
        'não sei bem', 'nao sei bem', 'não tenho certeza', 'nao tenho certeza', 'quanto custa',
        'quanto é', 'quanto e', 'queria ver', 'quero ver', 'dar uma olhada', 'só olhando',
        'so olhando', 'agora', 'nesta semana', 'semana que vem', 'o meu antigo', 'a minha antiga',
        'um novo', 'uma nova', 'alguma coisa', 'algumas opções', 'algumas opcoes', 'qualquer coisa',
        'coisa', 'coisas', 'disponível', 'disponivel', 'disponibilidade', 'em estoque',
        'vendido', 'recomendado', 'recomendação', 'sugestão', 'interessado', 'fiquei pensando'
    ),

    // Single junk tokens. A token that is not listed here survives as a
    // REQUIRED term, so one unrecognised word returns an empty result set
    // rather than a worse-ranked one. Nothing that could name a product goes in
    // this list -- "bolsa", "relógio" and "cinto" are products, not filler.
    'ignore_tokens' => array(
        'que', 'qual', 'quais', 'como', 'onde', 'quanto', 'custa', 'tem', 'têm', 'ter',
        'posso', 'poderia', 'queria', 'quero', 'preciso', 'procuro', 'procurando',
        'mostra', 'mostre', 'ver', 'olhar', 'olhada', 'achar', 'encontrar', 'ajuda',
        'recomenda', 'recomendam', 'recomendado', 'recomendação', 'recomendacao',
        'recomendações', 'sugestão', 'sugestao', 'sugestões', 'sugere', 'ideia', 'ideias',
        'opção', 'opcao', 'opções', 'opcoes', 'escolha', 'coisa', 'coisas', 'treco',
        'alguma', 'algum', 'qualquer', 'tipo', 'oi', 'olá', 'ola', 'obrigado', 'obrigada',
        'favor', 'disponível', 'disponivel', 'disponibilidade', 'vendem', 'vendido',
        'agora', 'hoje', 'amanhã', 'semana', 'antigo', 'antiga', 'ok', 'okay', 'sim'
    ),
);
