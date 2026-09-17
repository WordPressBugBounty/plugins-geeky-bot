<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * English shopper-language rules used by Geeky Bot product discovery.
 *
 * Keep this focused on commerce language. These rules do not try to understand
 * every English sentence. They classify common buyer constraints, preferences,
 * shopping missions, decision styles, and recipient hints so the product ranker
 * can stay broad and useful.
 */
return array(
    'revision' => '2026.09.11.1',

    'preferences' => array(
        'comfortable' => array(
            'label' => 'Comfort',
            'weight' => 18,
            'phrases' => array('comfortable', 'comfartable', 'comfertable', 'comfy', 'comfort', 'soft', 'cushioned', 'padded', 'easy to wear', 'relaxed fit'),
        ),
        'simple' => array(
            'label' => 'Simple style',
            'weight' => 10,
            'phrases' => array('simple', 'minimal', 'minimalist', 'plain', 'clean style', 'understated'),
        ),
        'quality' => array(
            'label' => 'Quality',
            'weight' => 10,
            'phrases' => array('good quality', 'well made', 'durable', 'better made', 'quality'),
        ),
        'lightweight' => array(
            'label' => 'Lightweight',
            'weight' => 14,
            'phrases' => array('lightweight', 'light weight', 'not heavy', 'easy to carry'),
        ),
        'gift' => array(
            'label' => 'Gift',
            'weight' => 20,
            'phrases' => array('gift', 'present', 'gift idea', 'gift choice', 'as a gift'),
        ),
        'useful' => array(
            'label' => 'Useful',
            'weight' => 18,
            'phrases' => array('useful', 'practical', 'handy', 'everyday useful', 'something they can use'),
        ),
        'casual' => array(
            'label' => 'Casual',
            'weight' => 12,
            'phrases' => array('casual', 'everyday style', 'daily wear', 'regular wear', 'relaxed style'),
        ),
        'formal' => array(
            'label' => 'Formal',
            'weight' => 12,
            'phrases' => array('formal', 'professional', 'business', 'dressy', 'smart casual'),
        ),
    ),

    'use_cases' => array(
        'daily' => array(
            'label' => 'Daily use',
            'weight' => 16,
            'phrases' => array('daily use', 'everyday use', 'for everyday', 'everyday wear', 'daily wear', 'regular use'),
        ),
        'office' => array(
            'label' => 'Office use',
            'weight' => 18,
            'phrases' => array('office use', 'office work', 'daily office work', 'for office', 'at work', 'workday', 'business use', 'for work'),
        ),
        'summer' => array(
            'label' => 'Summer',
            'weight' => 16,
            'phrases' => array('summer', 'hot weather', 'warm weather', 'breathable for summer'),
        ),
        'winter' => array(
            'label' => 'Winter',
            'weight' => 16,
            'phrases' => array('winter', 'cold weather', 'warm for winter', 'cozy', 'cosy'),
        ),
        'travel' => array(
            'label' => 'Travel',
            'weight' => 16,
            'phrases' => array('travel', 'for a trip', 'on a trip', 'for travelling', 'for traveling', 'journey', 'easy to pack'),
        ),
        'sports' => array(
            'label' => 'Sports or exercise',
            'weight' => 16,
            'phrases' => array('sports', 'gym', 'training', 'workout', 'running', 'walking', 'jogging', 'exercise'),
        ),
        'school' => array(
            'label' => 'School or study',
            'weight' => 14,
            'phrases' => array('school', 'college', 'university', 'student', 'study'),
        ),
        'occasion' => array(
            'label' => 'Special occasion',
            'weight' => 12,
            'phrases' => array('birthday', 'anniversary', 'wedding', 'party', 'event', 'special occasion'),
        ),
    ),

    'decision_modes' => array(
        'budget' => array(
            'label' => 'Budget-friendly',
            'weight' => 12,
            'phrases' => array('budget friendly', 'budget-friendly', 'affordable', 'not expensive', 'not too expensive', 'not costly', 'reasonable price', 'good price', 'within my budget', 'low priced'),
        ),
        'best_value' => array(
            'label' => 'Best value',
            'weight' => 16,
            'phrases' => array('best value', 'good value', 'value for money', 'for the money', 'best for the money', 'balanced choice', 'worth buying', 'not the cheapest', 'not cheapest', 'not the lowest price', 'safe choice', 'safest choice'),
        ),
        'premium' => array(
            'label' => 'Premium',
            'weight' => 14,
            'phrases' => array('premium', 'luxury', 'high end', 'high-end', 'higher end', 'better quality', 'top quality'),
        ),
        'popular' => array(
            'label' => 'Popular',
            'weight' => 10,
            'phrases' => array('popular', 'best selling', 'best-selling', 'trending', 'most sold', 'customer favorite', 'customer favourite'),
        ),
        'top_rated' => array(
            'label' => 'Top rated',
            'weight' => 10,
            'phrases' => array('top rated', 'highest rated', 'best rated', 'best reviews', 'well reviewed'),
        ),
        'newest' => array(
            'label' => 'Newest',
            'weight' => 8,
            'phrases' => array('newest', 'latest', 'new arrivals', 'recently added', 'just added'),
        ),
    ),

    // Recipient hints are only soft preferences. They must never remove the
    // broader catalog when product audience data is missing.
    'recipients' => array(
        'men_or_unisex' => array(
            'label' => 'Men or unisex preference',
            'phrases' => array(
                'gift for my brother', 'gift for brother', 'for my brother', 'for brother',
                'gift for my husband', 'for my husband', 'gift for husband',
                'gift for my boyfriend', 'for my boyfriend', 'gift for boyfriend',
                'gift for my dad', 'gift for dad', 'for my dad', 'gift for my father', 'for my father',
                'gift for my son', 'for my son', 'gift for men', 'for men', 'mens gift', 'men gift',
                // Bare possessives. A shopper says "my son starts school next week",
                // not "for my son", and the bare form matched no rule at all.
                'my brother', 'my husband', 'my boyfriend', 'my dad', 'my father', 'my son'
            ),
            'recipient_tokens' => array('brother', 'husband', 'boyfriend', 'dad', 'father', 'son', 'men', 'mens', 'male', 'guy'),
            'positive_terms' => array('men', 'mens', 'male', 'unisex', 'for him'),
            'negative_terms' => array('women', 'womens', 'female', 'ladies', 'lady', 'dress', 'blouse', 'skirt'),
        ),
        'women_or_unisex' => array(
            'label' => 'Women or unisex preference',
            'phrases' => array(
                'gift for my sister', 'gift for sister', 'for my sister', 'for sister',
                'gift for my wife', 'for my wife', 'gift for wife',
                'gift for my girlfriend', 'for my girlfriend', 'gift for girlfriend',
                'gift for my mother', 'gift for mother', 'for my mother',
                'gift for my mom', 'for my mom', 'gift for women', 'for women', 'womens gift', 'women gift',
                'my sister', 'my wife', 'my girlfriend', 'my mother', 'my mom', 'my daughter'
            ),
            'recipient_tokens' => array('sister', 'wife', 'girlfriend', 'mother', 'mom', 'women', 'womens', 'female', 'lady'),
            'positive_terms' => array('women', 'womens', 'female', 'unisex', 'for her'),
            'negative_terms' => array('men', 'mens', 'male', 'boys', 'boy'),
        ),
        'children' => array(
            'label' => 'Child preference',
            'phrases' => array('gift for my child', 'gift for child', 'for my child', 'gift for my kid', 'gift for kid', 'for my kid', 'gift for kids', 'for kids', 'gift for toddler', 'for toddler', 'gift for boy', 'for boy', 'gift for girl', 'for girl', 'my child', 'my kid', 'my kids', 'my toddler'),
            'recipient_tokens' => array('child', 'children', 'kid', 'kids', 'toddler', 'boy', 'girl'),
            'positive_terms' => array('kid', 'kids', 'child', 'children', 'toddler', 'boy', 'girl', 'junior'),
            'negative_terms' => array('adult only', 'adults only'),
        ),
        'neutral' => array(
            'label' => 'Neutral gift preference',
            'phrases' => array('gift for coworker', 'gift for my coworker', 'for a coworker', 'for my coworker', 'gift for colleague', 'for a colleague', 'gift for friend', 'gift for my friend', 'for a friend', 'for my friend', 'gift for someone', 'for someone'),
            'recipient_tokens' => array('coworker', 'colleague', 'friend', 'someone'),
            'positive_terms' => array('unisex', 'one size'),
            'negative_terms' => array(),
        ),
    ),

    'gift_signals' => array(
        'gift_terms' => array('gift', 'present', 'gift set', 'gift idea', 'gift choice'),
        'useful_terms' => array('useful', 'practical', 'handy', 'everyday useful'),
        'easy_choice_terms' => array('one size', 'one-size', 'unisex'),
    ),

    'flexibility_phrases' => array('if possible', 'preferably', 'ideally', 'maybe', 'perhaps', 'something like', 'open to', 'if you have'),

    'filler_phrases' => array(
        'can you please', 'could you please', 'please', 'i think', 'i am looking for', 'im looking for',
        'i need', 'i want', 'do you have', 'show me', 'find me', 'find something', 'what do you recommend',
        'what would you recommend', 'which one should i buy', 'give me some options', 'give me options',
        'only show', 'show only', 'right now', 'currently', 'actually', 'something', 'option', 'options',
        // Temporal filler. "cheapest today" was searching the catalog for a
        // product called "today" and finding nothing; the single words live in
        // ignore_tokens, the phrases here.
        'this week', 'at the moment', 'these days',

        // Availability questions. This is the single most common way a shopper
        // opens, and every verb here was surviving into the term list as a
        // required term: "do you sell running shoes" searched for a product
        // called "sell" and returned nothing at all. The verbs stay as phrases
        // rather than single tokens because "carry", "stock" and "shopping" can
        // each name a product ("carry-on", "stock pot", "shopping bag").
        'do you sell', 'do you guys sell', 'do you carry', 'do you stock', 'do you guys have',
        'do you have any', 'do you have some', 'do u have', 'have you got', 'you got any',
        'what do you have', 'what have you got', 'are there any', 'is there any',
        'do you offer', 'are you selling',

        // Request framings.
        'i am searching for', 'im searching for', 'searching for', 'i am after', 'im after',
        'i am interested in', 'im interested in', 'interested in', 'shopping for',
        'looking to buy', 'want to buy', 'i want to buy', 'need to buy', 'i need to buy',
        'i would like', 'i d like', 'id like', 'i will take', 'help me find', 'help me choose',
        'can i get', 'can i see', 'may i see', 'let me see', 'let me have a look at', 'just browsing for',

        // Advice framings. "any recommendations for headphones" was searching
        // for a product called "recommendations".
        'any recommendations for', 'any recommendations', 'recommend me', 'can you recommend',
        'any suggestions for', 'any suggestions', 'suggest me', 'any ideas for', 'what should i get',

        // Category framings. "what type of" is kept as a phrase because a bare
        // "type" token belongs to real products such as a Type-C cable.
        'what kind of', 'what type of', 'what sort of', 'any kind of', 'any type of', 'some kind of',

        // Conversational framings. The shopper's own error message named the
        // culprit every time: "I couldn't find a match for OLD backpack",
        // "for GIMME sneaker", "for SEE WANNA backpack". Each is one ordinary
        // English word standing where a product name should be.
        'to carry', 'what would go well with', 'go well with', 'goes well with',
        'whats the difference between', 'what is the difference between', 'the difference between',
        'im not sure what i need', 'not sure what i need', 'im not sure', 'not sure',
        'check out', 'what can i get', 'how much is', 'how much are', 'how much does',
        'whats your', 'what is your', 'my old', 'need a new', 'get a new', 'a new one',
        'wanna see', 'want to see', 'would like to see', 'to see', 'next week', 'right away',

        // Slang. Listed in both lists -- see the ignore_tokens note -- because
        // a word left in the text still reaches core_terms.
        'gimme', 'lemme', 'wanna', 'gotta', 'gonna', 'yo', 'sup', 'cool', 'plz', 'pls', 'thx',

        // Single words that must leave the TEXT, not just the term list.
        // ignore_tokens is applied to the extracted terms, but core_terms is
        // rebuilt from the stripped query by product_phrase_profile(), which
        // applies no ignore list of its own -- so "hello i need a laptop bag"
        // still required a product called "hello" and returned nothing. Words
        // repeated here are the ones that can never form part of a product
        // name; "see", "kind", "guy" and "thing" stay in ignore_tokens only.
        'hello', 'hi there', 'good morning', 'good afternoon', 'good evening',
        'thanks', 'thank you', 'thankyou', 'anything', 'anyone', 'stuff',
        'available', 'availability', 'sell', 'sells', 'selling', 'sold',
        'searching', 'seeking', 'recommend', 'recommends', 'recommended',
        'recommendation', 'recommendations', 'suggest', 'suggested', 'suggestion',
        'suggestions', 'interested', 'wondering', 'browse', 'browsing'
    ),

    // Single junk tokens. A token that is not listed here survives as a
    // REQUIRED term, so one unrecognised word returns an empty result set
    // rather than a worse-ranked one. Both the surface and the stemmed form are
    // listed ("thanks"/"thank") because removal runs before stemming on one
    // path and after it on the other. Nothing that could name a product goes in
    // this list -- use filler_phrases for those.
    'ignore_tokens' => array(
        'what', 'would', 'will', 'should', 'could', 'if', 'one', 'ones', 'something', 'option', 'options',
        'possible', 'possibly', 'actually', 'currently', 'still', 'look', 'looks', 'nice', 'today', 'tonight',
        'sell', 'sells', 'selling', 'sold', 'searching', 'seeking', 'see', 'let',
        'recommend', 'recommends', 'recommended', 'recommendation', 'recommendations',
        'suggest', 'suggested', 'suggestion', 'suggestions', 'interested', 'wondering',
        'anything', 'anyone', 'stuff', 'thing', 'things', 'kind', 'kinds', 'sort',
        'guy', 'guys', 'hello', 'thanks', 'thank', 'available', 'availability', 'browse', 'browsing',
        // "money" is deliberately absent: a money clip is a real product. It is
        // handled by the best_value phrase "for the money" instead. query_terms()
        // does not stem these down, so "cooling", "checkered", "starter" and
        // "weekender" survive intact and their products stay findable.
        'old', 'broke', 'sure', 'well', 'go', 'difference', 'start', 'starts',
        'next', 'week', 'weeks', 'yo', 'sup', 'out', 'check', 'much', 'how', 'cool',
        'yeah', 'yep', 'ok', 'okay', 'gimme', 'lemme', 'wanna', 'gotta', 'gonna'
    ),
);
