=== Geeky Bot – AI Sales Assistant for WooCommerce ===
Contributors: ahmadgb
Tags: woocommerce, product search, product recommendations, ai chatbot, ecommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce AI sales assistant for natural product discovery, grounded answers, recommendations, and guided shopping.

== Description ==

Geeky Bot turns a WooCommerce store into a guided shopping experience.

A shopper can ask "Show me wireless headphones under $100 that are in stock," follow with "Which would you recommend for daily commuting?" and then ask "Does the recommended pair support noise cancellation?"

Geeky Bot searches visible products, remembers context, explains suitable matches, and answers from WooCommerce data and approved policy pages.

It is built as a focused sales assistant—not a generic website chatbot.

= See Geeky Bot in action =

Watch Geeky Bot turn natural shopper questions into product discovery, comparisons, grounded answers, and buying assistance inside WooCommerce.

https://www.youtube.com/watch?v=KwlQ4gNXr8w

= Natural WooCommerce product discovery =

Help shoppers discover products by:

* Product name, family, category, price, color, size, stock, and sale status.
* Use case, shopper preference, and natural long-form requests.
* Follow-up refinements, synonyms, spelling mistakes, and close matches.

Search results remain grounded in products that are visible in the WooCommerce catalog.

= Product answers shoppers can trust =

Geeky Bot answers from available WooCommerce descriptions, attributes, variations, prices, and stock.

When information is missing, the assistant says so instead of inventing details.

= Helpful recommendations =

Geeky Bot can recommend suitable products and explain why they match the shopper's request. If no exact result exists, it can present clearly labeled close matches or suggest a sensible constraint to remove.

= Store policy answers =

Choose which published pages Geeky Bot may use for shipping, refunds, returns, exchanges, warranty, payment, privacy, and other store-policy questions.

Policy answers stay isolated to approved sources. Geeky Bot does not crawl arbitrary pages.

= Storefront shopping assistant =

The responsive floating widget provides product cards, suggested questions, a configurable timed shopper invitation, clear shopper and assistant message identity, persistent browser-side history, clear-chat control, accessible focus states, reduced-motion support, and scoped styling designed to avoid theme conflicts.

= A practical setup experience =

The guided setup checks WooCommerce, builds the product index, selects policy pages, configures the widget, and verifies readiness. Guided Demo creates realistic test questions from the store's own products, including short conversations that show a follow-up question being answered in context.

= Conversations, insights, and privacy =

Store owners can review conversations, group unanswered questions, see products opened from chat, export data to CSV, delete stored conversations, configure guest storage and retention, and use WordPress personal-data export and erasure tools.

= Local mode and optional AI services =

Core discovery and grounded routing can run locally. Zywrap and OpenAI are optional.

API keys are not exposed to storefront JavaScript or displayed again after saving.

= Geeky Bot Commerce Pro =

The free plugin helps shoppers find and understand products.

Geeky Bot Commerce Pro adds variation selection, cart and checkout actions, order help, product comparison, advanced recommendations, and sales-intent analytics.

Learn more at [geekybot.com](https://geekybot.com/).

== Installation ==

1. Install and activate Geeky Bot, with WooCommerce active.
2. Open Geeky Bot > Setup Wizard.
3. Build the product index and select approved public policy pages.
4. Review widget, privacy, retention, and optional AI-provider settings.
5. Use Guided Demo to test questions generated from your catalog.
6. Clear site or page caches before final storefront testing.

== Frequently Asked Questions ==

= Is Geeky Bot a general-purpose chatbot? =

No. Geeky Bot 2.0 is focused on WooCommerce shopping: finding products, answering product and policy questions, explaining choices, and guiding shoppers toward a purchase.

= Does Geeky Bot require WooCommerce? =

WooCommerce is required for product discovery and shopping assistance. Geeky Bot handles an inactive or not-yet-installed WooCommerce setup gracefully and shows the required next step.

= Does Geeky Bot require an external AI service? =

No. Local grounded product discovery and policy routing can work without an external AI provider. Zywrap and OpenAI are optional for supported AI-assisted answer modes.

= What information can Geeky Bot use? =

Geeky Bot uses visible WooCommerce catalog data and published policy pages explicitly selected by an administrator. It does not crawl arbitrary URLs.

= What happens when information is missing? =

Geeky Bot does not guess. It tells the shopper when the selected product data or approved policy pages do not contain the requested information.

= What is included in the free plugin? =

The free plugin includes product discovery, grounded product and policy answers, recommendations, product cards, conversation review, and basic insights. Variation selection, cart and checkout actions, and order assistance require Commerce Pro.

= Can shoppers continue a conversation after refreshing the page? =

Yes. Browser-side conversation history can persist across page loads and tabs on the same site. Server-side storage of guest conversations depends on the privacy settings chosen by the store owner.

= Does Geeky Bot work with WPML or Polylang? =

Yes. When either plugin is active Geeky Bot takes the shopper's language from it rather than guessing from the characters they typed. Each translation of a product is its own post in WPML and Polylang, so translations are indexed and searched separately with no extra setup — a Spanish product is found by a Spanish query. Buyer intent, not just the words, is understood in English, Spanish and Arabic; other languages still search by term and fall back to English intent. Cart and checkout commands are English-only in this release.

= Can Geeky Bot run on a WordPress multisite network? =

Yes, activated per site or across the network. Each site keeps its own product index, settings, conversations and AI budget, and a site installs its own tables the first time it loads — including sites added to the network later. Build the product index once per site from that site's Geeky Bot > Setup Wizard.

== Screenshots ==

1. Natural product discovery by budget, with grounded prices, stock, match details, and product cards. Commerce Pro variation selection is shown.
2. Need-based recommendations explain why products match the shopper's request. Commerce Pro add-to-cart actions are shown.
3. Commerce Pro compares selected products side by side using confirmed WooCommerce catalog data.
4. The dashboard highlights setup readiness, product-index health, shopper activity, and the next highest-impact actions.
5. The Setup Wizard checks the store in dependency order, from WooCommerce and indexing to knowledge and storefront launch.
6. Widget Builder combines shopper-facing controls with a live preview for branding, wording, placement, and product cards.

== Privacy ==

Depending on the settings chosen by the store owner, Geeky Bot may store conversation messages, session identifiers, response intent, product references, policy references, and product-click events in the WordPress database.

The plugin provides retention settings, conversation deletion tools, and integration with the WordPress personal-data export and erasure system. Store owners are responsible for describing their configuration and data practices in their own privacy policy.

Shopper questions are sent to an external AI provider only when the administrator has selected and configured that provider.

== External services ==

Geeky Bot can operate in local grounded mode without sending shopper questions to an external AI provider. External requests occur only for optional services selected or used by an administrator.

= OpenAI =

When OpenAI mode is selected and an API key is saved, Geeky Bot sends the shopper's question, a grounding instruction, relevant visible-product summaries, and relevant excerpts from approved policy pages to the OpenAI API to generate the requested answer.

Service: https://api.openai.com/

Terms: https://openai.com/policies/terms-of-use/

Privacy: https://openai.com/policies/privacy-policy/

= Zywrap =

When Zywrap mode is selected, Geeky Bot sends the shopper's question, a grounding instruction, relevant visible-product context, and relevant approved policy-page context to the configured Zywrap HTTPS endpoint to generate the requested answer.

No shopper question is sent to Zywrap when local mode is selected.

Service: https://www.zywrap.com/

Terms: https://www.zywrap.com/terms

Privacy: https://www.zywrap.com/privacy

= Geeky Bot website =

When an administrator chooses to view information about Commerce Pro or follow support, documentation, purchase, account, or upgrade links, the browser connects to geekybot.com.

Service: https://geekybot.com/

Terms: https://geekybot.com/terms-conditions/

Privacy: https://geekybot.com/privacy-policy/

== Changelog ==

= 2.1.0 =

Search

* Rebuilding the product index no longer takes search offline, and an interrupted rebuild resumes instead of starting over.
* Typos are resolved against your own catalog: "sunglases", "beenie" and "belst" now find the product, and the reply says which spelling was searched.
* Repeat searches are cached, and the cache clears itself on product edits, rebuilds and search-setting changes.
* Product tags now count towards what a product is, so a heel tagged "Party Shoes" is found by a search for shoes.
* Fixed size codes being read out of ordinary words — "muslin scarf" is no longer restricted to size L.
* Fixed synonyms narrowing a search instead of widening it.
* Excluding something — "shoe not black" — is around 25 times faster.
* Faster similar-product lookups, and repeated AI answers are no longer re-billed.

Multilingual

* Shoppers are understood in their own language rather than only term-matched. Packs ship for English, Arabic, Spanish, French, German, Italian, Portuguese, Dutch, Russian, Japanese, Korean and Chinese.
* Search works across languages: an English shopper finds Arabic products, an Arabic shopper finds English ones, and the same holds for every shipped language.
* Colour, size, price, stock and "not this" filters work in all twelve languages.
* Sale, newest, popular and top-rated requests are understood in all twelve languages.
* Around 2,000 product-word translations are built in, so most stores need no setup. Words specific to your catalog go under Settings → Custom synonyms.
* The shopper's language is read from the question itself, and from WPML or Polylang when either is active.
* Arabic now works the way Arabic is written: the definite article, broken plurals, Arabic-Indic digits, stretched letters and follow-up questions. Replies are shown in Arabic letters.
* Added an Arabic translation of the shopper-facing replies, and a translation template for everything else.
* Cart and checkout commands remain English-only in this release.

Multisite

* On a network-wide activation, every site now prompts its owner to finish setup instead of sitting with an unbuilt index.

Security and cost control

* Provider API keys are encrypted at rest, and existing keys are re-encrypted automatically on upgrade.
* Saving a key is refused, with a clear message, on servers that cannot encrypt it — rather than storing it in plaintext.
* Added a site-wide daily budget and monthly cap for AI calls, enforced server-side. Past the cap, shoppers still get local answers.
* Added a trusted-proxy setting, so forwarded IP addresses are only trusted when you say how many proxies you run.
* Contributors and authors are now subject to public rate limits.
* Merchant catalog and policy text is filtered for prompt injection before it reaches a language model.
* Onboarding and Answer Mode state plainly that the shipped local mode calls no language model.

= 2.0.2 =

Admin

* Rebuilt every admin screen on a single `gb2-` design system, replacing six generations of stylesheet that had been shipping at once.
* Reduced admin CSS from 306 KB to 55 KB, 322 `!important` rules to 2, 150 hex colours to 44, and 70 font sizes to 13.
* Added opt-in dark mode for the admin screens, so WordPress chrome is never left mismatched.
* Replaced the full-bleed page heroes with compact headers, and removed the duplicated status facts that appeared twice on several pages.

Conversation

* Added natural cart, checkout and deals commands: add, remove, change quantity, view cart, go to checkout, ask for a code, or ask for a person.
* Products in a command can be named, pointed at by position ("add the second one"), or referred to as "it" after a previous turn.
* A colour or size choice and the add can now arrive in one sentence, such as "Choose Blue, Logo Yes for Hoodie and add it to my cart".
* Positions in a cart command are counted in the cart. "Remove the first product from my cart" no longer acts on the last search results.
* Comparisons accept a description instead of a name, such as "compare the cheaper one and the second".
* Added answers for questions about the assistant itself, such as "What can you do?", built from the capabilities actually enabled.
* Problems described as a situation, such as "What if my item arrives damaged?", now reach the store's policy pages instead of product results.
* Requests to place an order, complain, or reach a person are handed to the store rather than answered with products.
* Recognised "my latest order" alongside last, previous, recent, past and earlier.

Answers

* Improved policy excerpt selection so an answer matches the question's polarity, and page furniture such as navigation labels and quoted questions is no longer quotable as an answer.
* Fixed indexed page text losing the word boundaries the markup implied, which joined the last word of one block to the first word of the next.

Catalog

* Fixed "what is cheapest" and in-stock browsing returning the newest products instead of the cheapest or best selling. Both sort orders are catalog-only in WooCommerce and were being dropped without warning.

Guided Demo

* Expanded the board from 7 examples to 15, and led with the prompts a search box cannot answer.
* Added multi-turn examples. The storefront sends the opening question, then hands over each follow-up once the previous answer arrives.
* Removed a product-details prompt that never resolved, and stopped building product questions from titles too ambiguous to identify one product.
* Fixed the currency symbol rendering as `&#036;` in generated budget prompts.

Widget

* Hardened the widget's close button and form controls against theme styles that were overriding them.


= 2.0.1 =

* Added a configurable 3–60 second shopper invitation, shown once per session and hidden after interaction.
* Added a polished, privacy-friendly shopper avatar in live and restored conversations.
* Added natural "difference between," "vs," and "which is better" comparison routing.
* Polished admin hero density, storefront preview states, and Commerce Pro update notices.

= 2.0.0 =

* Rebuilt Geeky Bot as a WooCommerce-native AI Sales Assistant.
* Added natural and variation-aware product discovery for names, families, price, attributes, stock, sale status, preferences, follow-ups, synonyms, and spelling recovery.
* Added grounded product and selected store-policy answers.
* Added contextual recommendations with match explanations and honest close matches.
* Redesigned the responsive widget with product cards, suggested questions, conversation persistence, and clear-chat control.
* Added Setup Wizard, real-catalog Guided Demo, Conversations, Needs Review, product-click insight, CSV export, retention, and deletion tools.
* Added product-index lifecycle handling, rate limiting, protected secrets, public-response allowlists, sanitized errors, and disclosure guards.
* Added support for the separate Geeky Bot Commerce Pro add-on.

== Upgrade Notice ==

= 2.1.0 =

Rebuilds product search: the index rebuild no longer takes search offline, typos resolve against your own catalog instead of a fixed list, repeat searches are cached, and shoppers are understood in twelve languages with search working across them. Also includes the security and cost work — provider API keys are encrypted at rest and re-encrypted automatically on upgrade, and a site-wide AI call budget is enforced. Back up and test on staging.

= 2.0.2 =

Rebuilds the admin screens and adds natural cart and checkout commands. Fixes cheapest-first browsing, cart-position commands, and policy answers for problems described as a situation. Back up and test on staging.

= 2.0.1 =

Adds a timed shopper invitation and shopper avatar. Geeky Bot 2.x is a major WooCommerce-focused release; back up and test upgrades on staging.
