<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Spanish shopper-language rules used by Geeky Bot product discovery.
 *
 * Mirrors the structure of Data/en/commerce.php exactly, so the compiler in
 * BuyerIntentLibrary needs no per-language branching. Only the shopper-facing
 * phrases are translated; the canonical keys, labels' role and weights stay
 * aligned with English so ranking behaves identically across languages.
 *
 * Phrases are matched after normalisation, which strips accents, so both
 * "cómodo" and "comodo" are written here where a shopper may type either.
 */
return array(
    'revision' => '2026.09.11.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => 'Comodidad',
            'weight' => 18,
            'phrases' => array('comodo', 'cómodo', 'comoda', 'cómoda', 'comodos', 'cómodos', 'comodas', 'cómodas', 'confortable', 'suave', 'acolchado', 'acolchada', 'facil de llevar', 'fácil de llevar', 'holgado'),
        ),
        'simple' => array(
            'label' => 'Estilo sencillo',
            'weight' => 10,
            'phrases' => array('sencillo', 'sencilla', 'simple', 'minimalista', 'liso', 'lisa', 'basico', 'básico', 'basica', 'básica', 'discreto'),
        ),
        'quality' => array(
            'label' => 'Calidad',
            'weight' => 10,
            'phrases' => array('buena calidad', 'de calidad', 'bien hecho', 'duradero', 'duradera', 'resistente', 'calidad'),
        ),
        'lightweight' => array(
            'label' => 'Ligero',
            'weight' => 14,
            'phrases' => array('ligero', 'ligera', 'liviano', 'no pesa', 'poco peso', 'facil de llevar'),
        ),
        'gift' => array(
            'label' => 'Regalo',
            'weight' => 20,
            'phrases' => array('regalo', 'para regalar', 'de regalo', 'idea de regalo', 'como regalo'),
        ),
        'useful' => array(
            'label' => 'Util',
            'weight' => 18,
            'phrases' => array('util', 'útil', 'practico', 'práctico', 'practica', 'práctica', 'que se pueda usar', 'para el dia a dia'),
        ),
        'casual' => array(
            'label' => 'Casual',
            'weight' => 12,
            'phrases' => array('casual', 'informal', 'para el dia a dia', 'para el día a día', 'uso diario', 'de diario'),
        ),
        'formal' => array(
            'label' => 'Formal',
            'weight' => 12,
            'phrases' => array('formal', 'elegante', 'de vestir', 'profesional', 'para la oficina'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => 'Uso diario',
            'weight' => 16,
            'phrases' => array('uso diario', 'para el dia a dia', 'para el día a día', 'de diario', 'todos los dias', 'todos los días', 'uso habitual'),
        ),
        'office' => array(
            'label' => 'Oficina',
            'weight' => 18,
            'phrases' => array('para la oficina', 'para el trabajo', 'en el trabajo', 'uso profesional', 'para trabajar'),
        ),
        'summer' => array(
            'label' => 'Verano',
            'weight' => 16,
            'phrases' => array('verano', 'para el verano', 'calor', 'transpirable', 'fresco para el verano'),
        ),
        'winter' => array(
            'label' => 'Invierno',
            'weight' => 16,
            'phrases' => array('invierno', 'para el invierno', 'frio', 'frío', 'abrigado', 'que abrigue', 'calentito'),
        ),
        'travel' => array(
            'label' => 'Viaje',
            'weight' => 16,
            'phrases' => array('viaje', 'para viajar', 'de viaje', 'para un viaje', 'facil de guardar'),
        ),
        'sports' => array(
            'label' => 'Deporte',
            'weight' => 16,
            'phrases' => array('deporte', 'gimnasio', 'entrenamiento', 'entrenar', 'correr', 'running', 'caminar', 'ejercicio'),
        ),
        'school' => array(
            'label' => 'Colegio o estudios',
            'weight' => 14,
            'phrases' => array('colegio', 'escuela', 'universidad', 'estudiante', 'para estudiar', 'clase'),
        ),
        'occasion' => array(
            'label' => 'Ocasion especial',
            'weight' => 12,
            'phrases' => array('cumpleanos', 'cumpleaños', 'aniversario', 'boda', 'fiesta', 'evento', 'ocasion especial', 'ocasión especial'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => 'Economico',
            'weight' => 12,
            'phrases' => array('barato', 'barata', 'baratos', 'baratas', 'economico', 'económico', 'economica', 'económica', 'no muy caro', 'no demasiado caro', 'precio razonable', 'buen precio', 'ajustado de precio', 'sin gastar mucho'),
        ),
        'best_value' => array(
            'label' => 'Mejor relacion calidad-precio',
            'weight' => 16,
            'phrases' => array('mejor relacion calidad precio', 'mejor relación calidad precio', 'relacion calidad precio', 'buena relacion calidad precio', 'por lo que cuesta', 'merece la pena', 'vale la pena', 'la mejor opcion', 'la mejor opción', 'no el mas barato', 'no el más barato'),
        ),
        'premium' => array(
            'label' => 'Premium',
            'weight' => 14,
            'phrases' => array('premium', 'lujo', 'de lujo', 'gama alta', 'mejor calidad', 'alta calidad'),
        ),
        'popular' => array(
            'label' => 'Popular',
            'weight' => 10,
            'phrases' => array('popular', 'mas vendido', 'más vendido', 'mas vendidos', 'más vendidos', 'mas vendida', 'más vendida', 'mas vendidas', 'más vendidas', 'lo que mas se vende', 'tendencia', 'favorito de los clientes'),
        ),
        'top_rated' => array(
            'label' => 'Mejor valorado',
            'weight' => 10,
            'phrases' => array('mejor valorado', 'mejor valorados', 'mejor valorada', 'mejor valoradas', 'mejor puntuado', 'mejor puntuados', 'mejor puntuada', 'mejor puntuadas', 'mejores opiniones', 'mejores resenas', 'mejores reseñas'),
        ),
        'newest' => array(
            'label' => 'Novedades',
            'weight' => 8,
            'phrases' => array('nuevo', 'nuevos', 'nueva', 'nuevas', 'novedades', 'ultimo', 'último', 'recien anadido', 'recién añadido', 'lo mas nuevo'),
        ),
    ),

    // Las pistas de destinatario son solo preferencias suaves. Nunca deben
    // eliminar el catalogo mas amplio cuando falta el dato de audiencia.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => 'Preferencia hombre o unisex',
            'phrases' => array(
                'regalo para mi hermano', 'para mi hermano', 'regalo para hermano',
                'regalo para mi marido', 'para mi marido', 'regalo para mi esposo', 'para mi esposo',
                'regalo para mi novio', 'para mi novio',
                'regalo para mi padre', 'para mi padre', 'regalo para mi papa', 'para mi papá',
                'regalo para mi hijo', 'para mi hijo', 'regalo para hombre', 'para hombre', 'para hombres',
                'mi hermano', 'mi marido', 'mi esposo', 'mi novio', 'mi padre', 'mi papa', 'mi papá', 'mi hijo'
            ),
            'recipient_tokens' => array('hermano', 'marido', 'esposo', 'novio', 'padre', 'papa', 'papá', 'hijo', 'hombre', 'hombres', 'chico'),
            'positive_terms' => array('hombre', 'hombres', 'masculino', 'unisex', 'para el', 'para él'),
            'negative_terms' => array('mujer', 'mujeres', 'femenino', 'senora', 'señora', 'vestido', 'blusa', 'falda'),
        ),
        'women_or_unisex' => array(
            'label' => 'Preferencia mujer o unisex',
            'phrases' => array(
                'regalo para mi hermana', 'para mi hermana', 'regalo para hermana',
                'regalo para mi mujer', 'para mi mujer', 'regalo para mi esposa', 'para mi esposa',
                'regalo para mi novia', 'para mi novia',
                'regalo para mi madre', 'para mi madre', 'regalo para mi mama', 'para mi mamá',
                'regalo para mi hija', 'para mi hija', 'regalo para mujer', 'para mujer', 'para mujeres',
                'mi hermana', 'mi mujer', 'mi esposa', 'mi novia', 'mi madre', 'mi mama', 'mi mamá', 'mi hija'
            ),
            'recipient_tokens' => array('hermana', 'mujer', 'esposa', 'novia', 'madre', 'mama', 'mamá', 'hija', 'mujeres', 'chica'),
            'positive_terms' => array('mujer', 'mujeres', 'femenino', 'unisex', 'para ella'),
            'negative_terms' => array('hombre', 'hombres', 'masculino', 'nino', 'niño'),
        ),
        'children' => array(
            'label' => 'Preferencia infantil',
            'phrases' => array('regalo para mi hijo pequeno', 'regalo para un nino', 'regalo para un niño', 'para un nino', 'para un niño', 'regalo para una nina', 'regalo para una niña', 'para una nina', 'para ninos', 'para niños', 'regalo para ninos', 'regalo para niños', 'para bebe', 'para bebé'),
            'recipient_tokens' => array('nino', 'niño', 'nina', 'niña', 'ninos', 'niños', 'bebe', 'bebé', 'infantil'),
            'positive_terms' => array('nino', 'niño', 'nina', 'niña', 'infantil', 'junior', 'kids'),
            'negative_terms' => array('solo adultos'),
        ),
        'neutral' => array(
            'label' => 'Regalo neutro',
            'phrases' => array('regalo para un companero', 'regalo para un compañero', 'para un companero de trabajo', 'regalo para un colega', 'regalo para un amigo', 'regalo para una amiga', 'para un amigo', 'para una amiga', 'regalo para alguien', 'para alguien'),
            'recipient_tokens' => array('companero', 'compañero', 'colega', 'amigo', 'amiga', 'alguien'),
            'positive_terms' => array('unisex', 'talla unica', 'talla única'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('regalo', 'para regalar', 'de regalo', 'idea de regalo', 'set de regalo'),
        'useful_terms' => array('util', 'útil', 'practico', 'práctico', 'que se pueda usar'),
        'easy_choice_terms' => array('talla unica', 'talla única', 'unisex'),
    ),

    'flexibility_phrases' => array('si es posible', 'preferiblemente', 'idealmente', 'quiza', 'quizá', 'tal vez', 'algo como', 'abierto a', 'si tienes'),

    'filler_phrases' => array(
        'me puedes', 'podrias', 'podrías', 'por favor', 'creo que', 'estoy buscando', 'busco',
        'necesito', 'quiero', 'tienes', 'tienen', 'muestrame', 'muéstrame', 'ensename', 'enséñame',
        'que me recomiendas', 'qué me recomiendas', 'cual me recomiendas', 'cuál me recomiendas',
        'cual deberia comprar', 'cuál debería comprar', 'dame opciones', 'dame algunas opciones',
        'solo muestra', 'muestra solo', 'ahora mismo', 'actualmente', 'en realidad', 'algo', 'opcion', 'opción', 'opciones',
        'esta semana', 'en este momento', 'estos dias', 'estos días',

        // Preguntas de disponibilidad, la forma mas comun de empezar. Cada
        // verbo que falte aqui sobrevive como termino OBLIGATORIO y devuelve
        // cero productos: "vendes zapatillas" buscaba un producto llamado
        // "vendes". Se listan como frases y no como tokens sueltos porque
        // "bolsa de compras" y "cable tipo c" son productos reales.
        'vendes', 'venden', 'vendeis', 'vendéis', 'teneis', 'tenéis',
        'tienes algun', 'tienes algún', 'tienen algun', 'tienen algún',
        'hay algun', 'hay algún', 'hay alguna', 'hay algunas', 'hay algo de',
        'que tienen en', 'qué tienen en', 'que tienes en', 'qué tienes en',
        'disponen de', 'trabajan con', 'venden ustedes',

        // Formas de pedir.
        'ando buscando', 'estoy buscando algo', 'quisiera', 'me gustaria', 'me gustaría',
        'quiero comprar', 'necesito comprar', 'estoy interesado en', 'estoy interesada en',
        'ayudame a encontrar', 'ayúdame a encontrar', 'puedo ver', 'puedes mostrarme',
        'me puedes mostrar', 'me puedes ensenar', 'me puedes enseñar', 'dejame ver', 'déjame ver',

        // Peticiones de consejo.
        'alguna recomendacion para', 'alguna recomendación para', 'alguna recomendacion',
        'alguna recomendación', 'alguna sugerencia', 'algun consejo', 'algún consejo',
        'me recomiendas', 'que me sugieres', 'qué me sugieres',

        // Preguntas por categoria.
        'que tipo de', 'qué tipo de', 'que clase de', 'qué clase de', 'algun tipo de', 'algún tipo de',

        // Formas conversacionales. Cada una es una palabra corriente ocupando
        // el lugar del nombre del producto.
        'para llevar', 'que va bien con', 'qué va bien con', 'va bien con',
        'cual es la diferencia entre', 'cuál es la diferencia entre', 'la diferencia entre',
        'no estoy seguro', 'no estoy segura', 'no se que necesito', 'no sé qué necesito',
        'cuanto cuesta', 'cuánto cuesta', 'cuanto vale', 'cuánto vale', 'cuanto valen', 'cuánto valen',
        'cual es tu', 'cuál es tu', 'mi viejo', 'mi vieja', 'necesito uno nuevo', 'necesito una nueva',
        'quiero ver', 'me gustaria ver', 'me gustaría ver', 'la semana que viene',

        // Coloquial.
        'porfa', 'porfis', 'oye', 'genial', 'guay',

        // Palabras sueltas que deben salir del TEXTO y no solo de la lista de
        // terminos: core_terms se reconstruye desde la consulta ya limpia en
        // product_phrase_profile(), que no aplica ninguna lista de ignorados.
        'hola', 'buenos dias', 'buenos días', 'buenas tardes', 'buenas noches',
        'gracias', 'muchas gracias', 'cualquier cosa', 'cosa', 'cosas',
        'disponible', 'disponibles', 'disponibilidad', 'vender', 'vendiendo', 'buscando',
        'recomendacion', 'recomendación', 'recomendaciones', 'sugerencia', 'sugerencias',
        'interesado', 'interesada'
    ),

    // Tokens sueltos de relleno. Un token que no este aqui sobrevive como
    // termino OBLIGATORIO. No se incluye nada que pueda nombrar un producto
    // ("tipo" esta solo en las frases por el cable tipo C).
    'ignore_tokens' => array(
        'que', 'qué', 'cual', 'cuál', 'seria', 'sería', 'deberia', 'debería', 'podria', 'podría',
        'si', 'uno', 'una', 'unos', 'unas', 'algo', 'opcion', 'opción', 'opciones', 'posible',
        'posiblemente', 'realmente', 'actualmente', 'todavia', 'todavía', 'ver', 'bonito', 'bonita',
        'hoy', 'esta', 'este',
        'vendes', 'venden', 'vender', 'vendeis', 'vendéis', 'vendiendo', 'buscando', 'busco',
        'recomendacion', 'recomendación', 'recomendaciones', 'recomiendas', 'sugerencia', 'sugerencias',
        'sugieres', 'consejo', 'interesado', 'interesada', 'clase', 'cosa', 'cosas', 'cualquier',
        'alguno', 'alguna', 'algunos', 'algunas', 'hola', 'gracias', 'disponible', 'disponibles',
        'disponibilidad', 'tienda',
        'viejo', 'vieja', 'roto', 'rota', 'seguro', 'segura', 'diferencia', 'bien', 'va',
        'semana', 'semanas', 'proxima', 'próxima', 'viene', 'oye', 'cuanto', 'cuánto',
        'cuesta', 'cuestan', 'genial', 'guay', 'vale'
    ),
);
