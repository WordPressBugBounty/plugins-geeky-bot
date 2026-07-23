=== Geeky Bot – AI Sales Assistant for WooCommerce ===
Contributors: geekybot
Tags: woocommerce, product search, product recommendations, ai chatbot, ecommerce
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce AI sales assistant for natural product discovery, grounded answers, recommendations, and guided shopping.

== Description ==

Geeky Bot turns a WooCommerce store into a guided shopping experience.

A shopper can ask "Show me wireless headphones under $100 that are in stock," follow with "Which would you recommend for daily commuting?" and then ask "Does the recommended pair support noise cancellation?"

Geeky Bot searches visible products, remembers context, explains suitable matches, and answers from WooCommerce data and approved policy pages.

It is built as a focused sales assistant—not a generic website chatbot.

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

The responsive floating widget provides product cards, suggested questions, persistent browser-side history, clear-chat control, accessible focus states, reduced-motion support, and scoped styling designed to avoid theme conflicts.

= A practical setup experience =

The guided setup checks WooCommerce, builds the product index, selects policy pages, configures the widget, and verifies readiness. Guided Demo creates realistic test questions from the store's own products.

= Conversations, insights, and privacy =

Store owners can review conversations, group unanswered questions, see products opened from chat, export data to CSV, delete stored conversations, configure guest storage and retention, and use WordPress personal-data export and erasure tools.

= Local mode and optional AI services =

Core discovery and grounded routing can run locally. Zywrap and OpenAI are optional.

API keys are not exposed to storefront JavaScript or displayed again after saving.

= Geeky Bot Commerce Pro =

The free plugin helps shoppers find and understand products.

Geeky Bot Commerce Pro is a separate paid add-on for buying actions and advanced sales assistance, including variation selection, add to cart, cart management, checkout handoff, order help, product comparison, advanced recommendations, and sales-intent analytics.

Screenshots that include Commerce Pro functionality are identified clearly in their captions.

Learn more at [geekybot.com](https://geekybot.com/).

== Installation ==

1. Install and activate Geeky Bot from Plugins > Add New.
2. Make sure WooCommerce is installed and active.
3. Open Geeky Bot > Setup Wizard.
4. Build the product index.
5. Select any public store-policy pages the assistant may use.
6. Review the widget, privacy, retention, and optional AI-provider settings.
7. Open Geeky Bot > Guided Demo and test questions generated from your own catalog.
8. Clear site or page caches before final storefront testing.

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

= 2.0.0 =

* Rebuilt Geeky Bot as a WooCommerce-native AI Sales Assistant.
* Added natural product discovery for product family, exact name, price, attributes, stock, sale status, and shopper preferences.
* Added long-form requests, follow-up refinements, synonyms, spelling recovery, negative constraints, and natural constraint removal.
* Added variation-aware matching that keeps color, size, price, and sale evidence on the same variation.
* Added grounded Product Expert answers from visible WooCommerce product data.
* Added grounded store-policy answers from explicitly selected published pages.
* Added contextual recommendations with match explanations and honest close-match behavior.
* Added a redesigned responsive widget, product cards, suggested questions, conversation persistence, and clear-chat control.
* Added the Setup Wizard and a real-catalog Guided Demo.
* Added Conversations, Needs Review grouping, product-click insight, CSV export, retention controls, and deletion tools.
* Added product-index lifecycle handling for WooCommerce activation and catalog changes.
* Added rate limiting, protected secrets, public-response allowlists, sanitized errors, and disclosure guards.
* Added support for the separate Geeky Bot Commerce Pro add-on.

== Upgrade Notice ==

= 2.0.0 =

Major release: Geeky Bot is now a WooCommerce AI Sales Assistant. Back up your site, review the 2.0 release notes, and test the upgrade on staging before updating production.
