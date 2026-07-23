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
    'revision' => '2026.07.12.2',

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
            'phrases' => array('best value', 'good value', 'value for money', 'balanced choice', 'worth buying', 'not the cheapest', 'not cheapest', 'not the lowest price', 'safe choice', 'safest choice'),
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
                'gift for my son', 'for my son', 'gift for men', 'for men', 'mens gift', 'men gift'
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
                'gift for my mom', 'for my mom', 'gift for women', 'for women', 'womens gift', 'women gift'
            ),
            'recipient_tokens' => array('sister', 'wife', 'girlfriend', 'mother', 'mom', 'women', 'womens', 'female', 'lady'),
            'positive_terms' => array('women', 'womens', 'female', 'unisex', 'for her'),
            'negative_terms' => array('men', 'mens', 'male', 'boys', 'boy'),
        ),
        'children' => array(
            'label' => 'Child preference',
            'phrases' => array('gift for my child', 'gift for child', 'for my child', 'gift for my kid', 'gift for kid', 'for my kid', 'gift for kids', 'for kids', 'gift for toddler', 'for toddler', 'gift for boy', 'for boy', 'gift for girl', 'for girl'),
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
        'only show', 'show only', 'right now', 'currently', 'actually', 'something', 'option', 'options'
    ),

    'ignore_tokens' => array('what', 'would', 'will', 'should', 'could', 'if', 'one', 'ones', 'something', 'option', 'options', 'possible', 'possibly', 'actually', 'currently', 'still', 'look', 'looks', 'nice'),
);
