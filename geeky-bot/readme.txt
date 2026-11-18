=== Geeky Bot – AI Chatbot & Sales Assistant for WooCommerce ===
Contributors: ahmadgb
Tags: woocommerce, product search, product recommendations, ai chatbot, ecommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chatbot for WooCommerce that answers product and policy questions, finds products in plain language, and recommends what to buy.

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

= Answers you can correct =

When the chat cannot answer a question, it appears under Needs review with the reason. Write the answer there once, and the chat sends your answer, word for word, whenever a shopper asks that question or a close rewording of it. Your answers are listed under Store Knowledge, where you can edit, delete or add more.

= Honest about being an AI =

A short notice next to the chat input tells shoppers they are chatting with an AI assistant (or an automated assistant in local mode), with a link to your privacy policy, as the EU AI Act and several US states require. It is on by default and the text can be changed. Asked "are you a bot?", the assistant always says what it is.

= Local mode and optional AI services =

Core discovery and grounded routing can run locally. AI-written answers are optional, with your own OpenAI key (stores already set up with Zywrap keep using it).

When OpenAI retires the model you use, Geeky Bot switches to OpenAI's replacement instead of stopping. If AI answers fail, for example because a key is rejected or the account has no credit, you see why on the dashboard and under Site Health, and shoppers keep getting answers from your store data.

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

No. Local grounded product discovery and policy routing can work without an external AI provider. OpenAI is optional, for AI-written answers and for the Smart Catalog and Rescue search levels; each is off until you choose it.

= Does Geeky Bot tell shoppers they are chatting with an AI? =

Yes. A notice next to the chat input says so, with a link to your privacy policy if one is set under Settings > Privacy. It reads "You are chatting with an AI assistant. Answers can contain mistakes." when AI writes the answers, and "You are chatting with an automated assistant, not a person." in local mode. Change the text, or switch it off if you show your own notice, under Geeky Bot > AI & Privacy.

= Can I fix an answer the assistant got wrong or could not give? =

Yes. Open Geeky Bot > Conversations > Needs review, choose "Write the answer" on the question, and save. From then on the chat sends your answer for that question and close rewordings of it. You can also add answers under Geeky Bot > Store Knowledge.

= What information can Geeky Bot use? =

Geeky Bot uses visible WooCommerce catalog data and published policy pages explicitly selected by an administrator. It does not crawl arbitrary URLs.

= What happens when information is missing? =

Geeky Bot does not guess. It tells the shopper when the selected product data or approved policy pages do not contain the requested information.

= What is included in the free plugin? =

The free plugin includes product discovery, grounded product and policy answers, recommendations, product cards, conversation review, and basic insights. Variation selection, cart and checkout actions, and order assistance require Commerce Pro.

= Can shoppers continue a conversation after refreshing the page? =

Yes. Browser-side conversation history can persist across page loads and tabs on the same site. Server-side storage of guest conversations depends on the privacy settings chosen by the store owner.

= Does Geeky Bot work with WPML or Polylang? =

Yes. When either plugin is active Geeky Bot takes the shopper's language from it rather than guessing from the characters they typed. Each translation of a product is its own post in WPML and Polylang, so translations are indexed and searched separately with no extra setup — a Spanish product is found by a Spanish query. Buyer intent, not just the words, is understood in Arabic, Chinese, Dutch, English, French, German, Italian, Japanese, Korean, Portuguese, Russian and Spanish; other languages still search by term and fall back to English intent. Cart and checkout commands are English-only in this release.

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

Shopper questions are sent to an external AI provider only when the administrator has selected and configured that provider. With the Rescue search level or learning from missed searches switched on, the wording of searches that found nothing is also sent to that provider, as described under External services.

== External services ==

Geeky Bot can operate in local grounded mode without sending shopper questions to an external AI provider. External requests occur only for optional services selected or used by an administrator.

= OpenAI =

When OpenAI mode is selected and an API key is saved, Geeky Bot sends the shopper's question, a grounding instruction and relevant visible-product summaries to the OpenAI API to generate the requested answer, using the model chosen under Geeky Bot → AI & Privacy (gpt-6-luna by default). Questions about store policies are answered from the selected pages without AI. Answers the store owner wrote under Needs review are sent as written, without AI.

The product search levels also use the OpenAI API with the saved key. Each is off until the administrator selects it under Geeky Bot → Product Search, and the Standard level makes no external requests.

* Smart Catalog: for each visible product, Geeky Bot sends the product title, categories, tags, attributes and a short summary of up to 240 characters taken from the product description, and asks for the words shoppers use for that product. It is sent when Smart Catalog is switched on, when a product is added or changed, and when the administrator asks for words to be rewritten. No shopper data is sent.
* Rescue (requires Commerce Pro): when a shopper's search finds nothing, Geeky Bot sends the wording of that search and a list of the store's category and product-type words, and asks which of those words fit. Each answer is stored for 30 days and reused, so the same search is not sent again during that time.
* Learning from missed searches (requires Commerce Pro, and the Smart Catalog or Rescue level): once a day, Geeky Bot sends the text of recent shopper messages that found no products, with the same list of store words, and asks for synonym suggestions. Suggestions are shown to the administrator and only used after they are accepted.

A site-wide daily and monthly limit on AI calls applies to all of these, and can be set under Geeky Bot → AI & Privacy.

Service: https://api.openai.com/

Terms: https://openai.com/policies/terms-of-use/

Privacy: https://openai.com/policies/privacy-policy/

= Zywrap =

When Zywrap mode is selected and a Zywrap key is saved, Geeky Bot sends the shopper's question, a grounding instruction and relevant visible-product context to Zywrap to generate the requested answer. The address is https://api.zywrap.com/v1/proxy unless the administrator enters another one under Geeky Bot → AI & Privacy.

Zywrap runs in the United States and passes each request to the AI model provider it uses for these answers, currently OpenAI (gpt-6-luna), which writes the reply. Zywrap's privacy policy describes what is stored and for how long.

With a Zywrap key saved, the Smart Catalog, Rescue and learning-from-missed-searches levels described under OpenAI use Zywrap instead of OpenAI when Zywrap is the answer mode or no OpenAI key is saved. They send Zywrap the same information listed there, and the same daily and monthly limit on AI calls applies.

In local mode no shopper question is sent to Zywrap for an answer; only the Rescue and learning levels, when switched on, send the wording of searches that found nothing.

Creating or connecting a Zywrap account: where Zywrap is offered as an AI connection, under Geeky Bot → AI & Privacy an administrator can create a free Zywrap account, or connect an existing one, without leaving WordPress. Nothing is sent until the administrator ticks the consent box and clicks "Email me a code" or "Sign in on zywrap.com". Geeky Bot then sends Zywrap (https://api.zywrap.com/v1/connect/wordpress/) the email address and the optional name entered there, the site's address and name, a name for the key ("Geeky Bot – " and the site's domain), and where the sign-up came from: that it is Geeky Bot, its version, and UTM tags (utm_source=geeky-bot, utm_medium=wordpress-plugin, a campaign name and the Geeky Bot screen it started on). Zywrap emails a 6-digit code, and the code is traded for an API key, which is stored encrypted on the site. "Sign in on zywrap.com" opens zywrap.com with the same details in the address. Links from Geeky Bot to zywrap.com carry the same UTM tags.

Service: https://www.zywrap.com/

Terms: https://www.zywrap.com/terms

Privacy: https://www.zywrap.com/privacy

= Geeky Bot website =

When an administrator chooses to view information about Commerce Pro or follow support, documentation, purchase, account, or upgrade links, the browser connects to geekybot.com.

Translations. Geeky Bot does not ship its translations; it downloads the one for your site's language from geekybot.com, the way WordPress installs its own language packs. This happens only on a site that is not in English (or whose users have chosen another language): when Geeky Bot is installed or updated, when the site language changes, and once a day to pick up improved translations. The request reads a public list (https://geekybot.com/translations/v2/index.json) and downloads a .zip holding a .po and a .mo file, checked against the SHA-256 the list gives. Nothing about the site is sent: no address, no key, no content, and the user-agent names only Geeky Bot and its version. Turn it off with define('GEEKYBOT_TRANSLATIONS_AUTO', false); in wp-config.php; the files can then be downloaded from Geeky Bot > Languages and installed by hand.

Commerce Pro license. The free plugin sends nothing else to geekybot.com until an administrator enters a Commerce Pro license key under Geeky Bot > License. After that:

* To activate the license, and to re-check it about once a week, Geeky Bot sends the key to geekybot.com with the site address, the WordPress, PHP and Geeky Bot versions, the site language and whether the site is a multisite. geekybot.com answers with a signed statement of what the license covers.
* When the administrator installs or updates Commerce Pro, Geeky Bot asks geekybot.com for a one-time download link and downloads the add-on from geekybot.com.
* While Commerce Pro is installed, Geeky Bot asks geekybot.com twice a day whether a newer Commerce Pro exists, sending the key, the site address and the installed Geeky Bot and Commerce Pro versions.

Deactivating the license on the License page tells geekybot.com to free this site's slot and deletes the key from the site.

Service: https://geekybot.com/

Terms: https://geekybot.com/terms-conditions/

Privacy: https://geekybot.com/privacy-policy/

== Changelog ==

= 2.2.0 =

Answers you can correct

* Write the answer from Needs review: on any unanswered question, choose "Write the answer" and save. The chat then sends your answer, word for word, in every answer mode, whenever a shopper asks that question or a close rewording of it, in any language. Saving marks the question handled.
* Store Knowledge lists the answers you wrote, how often each was sent, and lets you edit, delete or add answers.

Honest about being an AI

* A short notice next to the chat input tells shoppers they are chatting with an AI assistant, or an automated assistant in local mode, with a link to your privacy policy. The EU AI Act (Article 50, in force since 2 August 2026) and several US states require this. It is on by default; change the text or switch it off under AI & Privacy.
* "Are you a bot?" and "am I talking to a person?" always get a straight answer, in English, Spanish, French, German, Portuguese, Italian and Dutch.

AI answers that keep working

* OpenAI shuts down gpt-4o-mini, the previous default model, together with gpt-4o, gpt-4.1 and gpt-4.1-mini, on 14 January 2027, and gpt-5-nano and gpt-5-mini on 11 December 2026. New sites now default to gpt-6-luna, OpenAI's replacement, which also costs less. A store set to a retiring model is told in advance and can switch with one click; when the model stops working, Geeky Bot uses gpt-6-luna instead of failing.
* Requests now work with newer OpenAI models, including gpt-6 and reasoning models, which reject some of the settings older models used. A setting a model refuses is left out and the request retried.
* When AI answers fail, you are told why: an admin notice and a new "AI connection" line in Site Health explain whether the key was rejected, the account has no credit, the country is not served or the model is not available. Before, answers fell back to store data with no sign anything was wrong.
* After an error that would repeat, AI calls pause for 10 minutes, so shoppers get an instant answer from store data instead of waiting. Saving new AI settings lifts the pause.
* Failed AI calls no longer count against the daily and monthly AI call limits.
* The geekybot_ai_error action fires when an AI call fails, for logging or alerts.

Accessibility

* The chat window is announced as a dialog named after the assistant, the message box has a label, only new messages are read out (not the whole window), the chat button says whether the chat is open and leaves the tab order while it is, and on phones Tab stays inside the chat.

Fixes

* Needs review no longer hides real questions. Any question containing "cart" or "checkout", and a few phrases from our test store, were automatically marked "Handled now". Cart and order requests are now treated as handled only when Commerce Pro answers them.
* The dashboard's question counts now match Needs review, including questions you marked handled or ignored.
* Conversations older than the retention period are deleted by a daily background task, also when chat history is switched off. Before, they were deleted only during a shopper's chat, and never while history was off.
* Visitors behind a proxy on the same server (such as Varnish, or nginx in front of Apache) all appeared to come from 127.0.0.1 and skipped the rate limits. They are now limited like everyone else.
* The readme said AI providers receive policy-page excerpts. Policy questions are answered from your pages without AI, and External services now says so.

Also in this release

* Smart Catalog, Rescue and learning from missed searches can use a saved Zywrap key as well as an OpenAI key, on sites that use Zywrap. External services lists what they send.
* Sites whose database is SQLite, such as WordPress Playground, the WordPress.org preview and WordPress Studio: product search uses word matching straight away instead of first trying a full-text query SQLite cannot run, and the admin no longer shows a "degraded state" error there.
* Guided Demo: the two comparison examples and "what material are they?" are listed under Commerce Pro, which answers them, instead of under Free.
* Adds one database table, for the answers you write.

= 2.1.2 =

Commerce Pro license

* Commerce Pro licenses now come from geekybot.com's new license server. Keys are five groups of five characters, as shown in your account on geekybot.com.
* Commerce Pro runs only on a license statement signed by geekybot.com for this key and this site. A copied database, or a status written into the database by hand, no longer unlocks it.
* Installing and updating Commerce Pro use a one-time download link, and the download is checked against its published SHA-256 before it is installed.
* An expired license keeps the installed Commerce Pro working on a site that was already activated; updates, downloads, support and new activations need a renewal. A renewal payment on hold keeps it working too.
* If geekybot.com cannot be reached, Commerce Pro keeps running on its last confirmed check for up to 30 days.
* Staging and development copies of a site no longer use one of the license's sites.
* The License page shows dates in your site's format and says whether this site uses one of the license's sites.
* Commerce Pro update checks no longer read a release file from cdn.geekybot.com.

Languages

* Translations are no longer bundled. Geeky Bot installs the translation for your site's language from geekybot.com by itself, into wp-content/languages/plugins/ where updates cannot delete it, and keeps it up to date daily - corrected wording arrives without a plugin update. The plugin download shrinks from 4.8 MB to 0.6 MB.
* 19 languages: Arabic, Chinese (Simplified), Czech, Dutch, French, German, Greek, Hebrew, Indonesian, Italian, Japanese, Korean, Polish, Portuguese (Brazil), Romanian, Russian, Spanish, Turkish, and English. Czech, Greek, Hebrew, Indonesian, Polish, Romanian and Turkish are new. A regional variant gets the nearest translation (Swiss German gets German).
* New Geeky Bot > Languages screen: which languages are installed, Install / Update now, every translation's .po and .mo for download, and "Use it for me" to switch your own admin language.
* A translation whose download does not match its published checksum is refused. A file you placed in wp-content/languages/plugins/ yourself is kept beside the new one, never lost.

Other

* WooCommerce Subscriptions products are recognised as the simple and variable products they are built on, so Commerce Pro 2.1.2 can offer Add to cart for them in the chat.
* Commerce Pro 2.1.2 can open its contact form under a reply when a shopper asks for a person.
* The chat's saved conversation, open state and session are kept per site. Two Geeky Bot sites on one domain (localhost/site-a and localhost/site-b, or shops in a subfolder multisite network) no longer show each other's conversation; a site at the top of its domain keeps its visitors' existing conversation.
* Geeky Bot no longer rewrites errors from other plugins' REST routes that share the geekybot/v1 namespace.

= 2.1.1 =

Search

* New search levels under Product Search: Standard, Smart Catalog, and Smart Catalog + Rescue.
* Smart Catalog asks AI once per product for the words shoppers really use (trainers for sneakers, beanie for woolly hat), stores them on your server, and searches them with no AI call per search. Products are refreshed automatically when they change.
* With Commerce Pro, Rescue asks AI only when a search finds nothing, such as "something to keep warm", and remembers the answer for everyone.
* With Commerce Pro and Smart Catalog or Rescue switched on, missed searches are reviewed daily and turned into synonym suggestions you accept or dismiss, plus a list of things shoppers wanted that you don't sell.
* Typo tolerance now learns new product words as soon as products change, and repairs itself on sites where the word list was saved empty.
* Fixed a search for one thing matching a product that only mentions it in passing ("pants belt" no longer returns pants).
* Fixed saved API keys being encrypted twice by a partial settings save, and model names containing dots being rejected.
* Search results no longer change order after an index rebuild when nothing in the catalog changed.
* "Do you sell …?" questions are no longer spell-corrected ("sell" was read as "shell"), and "keep" no longer matches product names, so "something to keep warm" finds warm clothing.

Chat

* AI-written answers are now one short plain-text paragraph above the product cards. Before, shoppers could see raw formatting such as asterisks, list numbers and bracketed links.
* The two new shopper-facing messages in this release are translated into all twelve shipped languages. Other new wording shows in English until translated; the translation template is now included as languages/geeky-bot.pot.
* AI-written answers now know which products are on sale. Asked for sale products, the answer had said the store had none while showing four sale products below it.
* AI-written answers no longer end with a copy of the internal product data line ("Product #83: … | Price: … | URL: …").
* Arabic, Hebrew and other right-to-left messages now read right to left, in both the shopper's messages and the replies.
* Escape now closes the chat and returns focus to the launcher; an open menu closes first.
* Dark mode: buttons, match details, the menu and prices inside the chat were light on light or faint. They now follow the dark palette, including "Match the shopper device".
* The chat is hidden, and its catalog closed, for visitors WooCommerce "Coming soon" mode hides the store from. Guests had been able to browse product names and prices through the chat before launch. Store managers and visitors with the private preview link still see it.

Admin

* Redesigned admin screens: shorter pages, larger text, and one page per job.
* Settings and Answer Mode are merged into AI & Privacy.
* The License page walks through entering the key, installing Commerce Pro and switching it on.
* The Dashboard shows what is waiting for you and how search is doing, and collapses to one card until the first shopper conversation.
* The Storefront Widget preview now stays in view while you edit, and the image pickers are laid out correctly.
* Deactivating the plugin now stops its background jobs; they resume when it is reactivated.
* Declares compatibility with WooCommerce High-Performance Order Storage and the Cart and Checkout blocks, so stores using them no longer see an "incompatible plugins" warning for Geeky Bot.

Privacy

* The External services section now describes exactly what Smart Catalog, Rescue and learning from missed searches send to OpenAI.
* Learning from missed searches runs only when Smart Catalog or Rescue is chosen. A saved OpenAI key alone no longer starts sending shopper messages.
* Sites without Commerce Pro no longer check cdn.geekybot.com for Commerce Pro updates. The free plugin now makes no server-side requests to geekybot.com.

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

= 2.2.0 =

OpenAI shuts down gpt-4o-mini, Geeky Bot's previous default model, on 14 January 2027. Update so AI answers switch to gpt-6-luna instead of stopping. Adds a short AI notice next to the chat input (on by default; see AI & Privacy) and one database table.

= 2.1.2 =

Translations are now installed from geekybot.com instead of being bundled: a site not in English downloads its language automatically within a minute of updating (see Geeky Bot > Languages). Commerce Pro licensing moves to geekybot.com's new license server: if you use Commerce Pro, open Geeky Bot > License after updating and enter your license key again.

= 2.1.1 =

Adds AI search levels (Smart Catalog, and Rescue with Commerce Pro), learning from missed searches, and redesigned admin screens. Adds one database column to the product index. Back up and test on staging.

= 2.1.0 =

Rebuilds product search: the index rebuild no longer takes search offline, typos resolve against your own catalog instead of a fixed list, repeat searches are cached, and shoppers are understood in twelve languages with search working across them. Also includes the security and cost work — provider API keys are encrypted at rest and re-encrypted automatically on upgrade, and a site-wide AI call budget is enforced. Back up and test on staging.

= 2.0.2 =

Rebuilds the admin screens and adds natural cart and checkout commands. Fixes cheapest-first browsing, cart-position commands, and policy answers for problems described as a situation. Back up and test on staging.

= 2.0.1 =

Adds a timed shopper invitation and shopper avatar. Geeky Bot 2.x is a major WooCommerce-focused release; back up and test upgrades on staging.
