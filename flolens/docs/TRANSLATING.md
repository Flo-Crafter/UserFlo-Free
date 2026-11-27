# Translating FloLens

FloLens uses the standard WordPress text domain `userflo`. Bundled languages: **Spanish (es_ES)**, **French (fr_FR)** and **Hungarian (hu_HU)**.

## Editing translations in the admin (Settings → Translations)
The *Languages* list shows English, Spanish, Hungarian and any language you add, with how much is translated and how many strings you changed. **Edit** opens the editor: strings are grouped in collapsible sections with progress bars, you can search (key, English text or your translation) and filter to *Only what is missing*. **+ New language** creates an empty language from a locale code such as `de_DE`; untranslated text falls back to English. **Save** stores your edits in the database: they sit on top of the shipped translations, survive plugin updates, and can reword the English text too. **Reset my edits** removes them. The ↓ button downloads the effective translations as a `.po` file (for Loco Translate, Poedit or sharing).

Safety: a translation must keep the same placeholders (`%s`, `%d`, `%1$d`) as the English text, otherwise it is rejected (a wrong placeholder would break the page); only simple formatting tags (`a`, `strong`, `em`, `code`, `br`) are kept.

## Report language
Reports can use a language other than the dashboard's: Settings → Translations → Report language (default) or Reports → Report language (per report). Any installed or custom language is offered.

## For users
Settings → **Plugin language**: *Automatic* follows the WordPress site language (or the user's own profile language in the admin); any `es_*` locale falls back to Spanish and any `hu_*` to Hungarian. You can also force English, Español or Magyar.
Other languages: drop `userflo-<locale>.mo` (and, for the dashboard screens, the `userflo-<locale>-<handle>.json` files) into `wp-content/plugins/flolens/languages/` or `wp-content/languages/plugins/`, or use Loco Translate / translate.wordpress.org once the plugin is published.

## For maintainers
1. `python3 bin/make-pot.py` scans PHP and the React/JS sources for literal strings (`__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_n()`) and writes `flolens/languages/userflo.pot`.
2. Source translations live in `i18n/<locale>.json` (English string -> translation). Add a new language by creating that file (and adding the locale to `I18n::BUNDLED`, `Settings::sanitize` and the settings dropdown).
3. `python3 bin/compile-i18n.py` produces `userflo-<locale>.po`, `.mo` and one `.json` per admin script handle (`userflo-admin-app`, `-pro-app`, `-tools`, `-settings`, `-translations`, `-setup`, `-heatmap-admin`, `-integrations`, `-mail`) for `wp.i18n`. Each file holds only the strings that appear as literals in that handle's own scripts (the mapping is `HANDLE_FILES` in the script), and `python3 bin/check-i18n.py` verifies that none is missing; the package build runs it. Set `SOURCE_DATE_EPOCH` to get byte-identical files on any day.
4. Untranslated strings fall back to English.

Rules: always wrap UI text in `__('...', 'userflo')` with a literal string (no variables or concatenation); keep placeholders (`%s`, `%d`, `%1$d`) intact; never put HTML in translatable strings except through `sprintf` placeholders.

## Limits
The Spanish and Hungarian translations were produced by an AI model and have **not been reviewed by native speakers**; expect wording to be improved. A few strings built from several parts (for example, labels assembled in JavaScript) may read slightly awkwardly in some languages.
