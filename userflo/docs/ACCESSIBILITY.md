# Accessibility checks in the website audit

UserFlo's website audit (Page Intelligence) also looks for some **accessibility** problems on your pages. This guide explains what it checks, what it cannot know, and how to read the results.

> **Automated checks cannot establish WCAG conformance.** UserFlo finds some problems and flags others for review. A page with no findings is not shown to be accessible, and no result here says that your site "complies" with anything.

## How it works

The audit reads each page the way a visitor's browser would receive it: the HTML your server sends. It does not run JavaScript, load style sheets, fill in or submit forms, or press keys. It never changes your site, and it reads only pages of your own site.

That makes it light and safe, and it also sets the limits:

- **Seen:** what is written in the HTML (text, images and their alt text, labels, ARIA attributes, the language, headings, landmarks, inline styles and `<style>` blocks, video and iframe tags).
- **Not seen:** anything JavaScript adds after the page loads, colours and focus styles that live in external style sheets, the real background behind text, keyboard behaviour, what a screen reader announces, the quality of alt text or captions.

The reference is [WCAG 2.2](https://www.w3.org/TR/WCAG22/), levels A and AA. Each finding names the success criterion it relates to, with a link to W3C's explanation.

## What a result means

| Result | Meaning |
|---|---|
| **Issue detected** | A specific, reproducible problem was read from the page (for example an image with no alt attribute). It counts towards the page's health. |
| **Potential issue** | A pattern that often causes a problem but cannot be confirmed from HTML alone (for example a positive tabindex). Worth reviewing. It counts as a warning. |
| **Manual review required** | Only a person can judge it (for example whether captions exist and are accurate). Never counted as an issue. |
| **Passed automated check** | Something was tested and the condition was met. This says nothing about overall conformance. |
| **Not tested** | The check could not be made on this page (for example no text colours were set in the HTML). It is **not** a pass. |
| **Nothing to test** | The page has nothing of that kind (for example no buttons). |

UserFlo does not give an accessibility score or percentage. A number made from incomplete information would be misleading; counts by certainty are shown instead.

Severity (how serious a finding is) and traffic (how many people visit the page) are kept apart. Pro can list pages that need attention using visits as context: a page with a detected accessibility issue that is busy, or that people use to complete a task (checkout, login, contact, booking), however few visit it. Traffic only orders that list; it never changes a finding's severity.

## Free and Pro

- **UserFlo (free):** the basic accessibility checks that need nothing but the page's own HTML and rarely give false alarms: image alt text, links and buttons without a name, form fields without labels and the page language (plus the page title). The Page health screen shows an Accessibility overview with WCAG references, how many findings of each kind there are, and the list of things automated checks cannot decide.
- **UserFlo Pro:** the broader checks listed below, an accessibility card on each page's report (findings by certainty, passed and untested checks), accessibility counts in the Audit table and its CSV export, accessibility issues in the "pages that need attention" list, and the accessibility counts in Copy for AI's audit data.

If a licence ends, nothing is deleted. Findings only Pro shows are hidden until Pro is active again.

## The checks

| Check | What it looks for | WCAG 2.2 | Result type | Edition | Can be wrong when |
|---|---|---|---|---|---|
| `title_missing` | No page title | 2.4.2 (A) | Issue detected | Free | Reads the title tag of the HTML sent by the server; a title set by JavaScript after load is not seen. |
| `html_lang_missing` | No page language | 3.1.1 (A) | Issue detected | Free | Reads the lang attribute of the html tag in the HTML sent by the server. |
| `html_lang_invalid` | Page language may be invalid | 3.1.1 (A) | Potential issue | Pro | Checks only the shape of the code, not that it is the right language for the page. |
| `img_alt_missing` | Image without alt text | 1.1.1 (A) | Issue detected | Free | Finds img elements without an alt attribute. Images set as CSS backgrounds are not images in HTML and are not seen. |
| `img_alt_filename` | Image text looks like a file name | 1.1.1 (A) | Potential issue | Pro | Matches file-name-like and placeholder alt text only. It cannot judge whether other alt text describes the image well. |
| `img_empty_alt_title` | Image marked decorative but has a title | 1.1.1 (A) | Potential issue | Pro | An empty alt is correct for decoration; this only flags images that also carry a title. It cannot see what the image shows. |
| `link_empty` | Link without a name | 2.4.4 (A), 4.1.2 (A) | Issue detected | Free | Looks for text, an image with alt text, aria-label, aria-labelledby or title inside the link. A name created by CSS or JavaScript is not seen. |
| `link_generic` | Link text says little | 2.4.4 (A) | Potential issue | Pro | Matches a short list of generic phrases. Whether the surrounding text makes the link clear is a human judgement. |
| `link_acts_as_button` | Link that acts as a button | 4.1.2 (A) | Potential issue | Pro | Needs a placeholder address plus an inline handler or ARIA state. Handlers attached by scripts are invisible to this check. |
| `button_no_name` | Button without a name | 4.1.2 (A) | Issue detected | Free | Looks for text, an image with alt text, a value, aria-label, aria-labelledby or title. A name created by CSS or JavaScript is not seen. |
| `form_field_no_label` | Form fields without labels | 1.3.1 (A), 3.3.2 (A), 4.1.2 (A) | Issue detected | Free | Recognises label for=, wrapping labels, aria-label, aria-labelledby and title. Fields outside a form element are not checked. A placeholder is not a label. |
| `form_required_unmarked` | Required field may not be marked | 3.3.2 (A), 1.3.1 (A) | Potential issue | Pro | Looks for an asterisk in the label text. It does not know what the asterisk means on that form. |
| `input_no_autocomplete` | Personal field without autocomplete | 1.3.5 (AA) | Potential issue | Pro | Looks at e-mail and telephone fields only, and cannot tell whether a field asks for the visitor’s own details. |
| `heading_empty` | Empty heading | 1.3.1 (A), 2.4.6 (AA) | Issue detected | Pro | Finds heading elements with no text, image text or label. Headings filled by JavaScript are not seen. |
| `main_landmark_missing` | No main content landmark | 1.3.1 (A), 2.4.1 (A) | Potential issue | Pro | Looks for a main element or role="main" in the HTML sent by the server. Other bypass mechanisms (such as a skip link) are not assessed. |
| `id_duplicate_referenced` | Duplicate ID used by a label or ARIA reference | 1.3.1 (A), 4.1.2 (A) | Potential issue | Pro | Reports duplicates only when a label or an ARIA attribute points to the ID. Duplicate IDs elsewhere are not reported. |
| `aria_ref_missing` | Label or ARIA reference points to nothing | 1.3.1 (A), 4.1.2 (A) | Potential issue | Pro | The target may be created by JavaScript after the page loads. |
| `aria_role_invalid` | Unknown ARIA role | 4.1.2 (A) | Potential issue | Pro | Compares role values with the WAI-ARIA 1.2 role list. It does not check that a valid role suits the element. |
| `aria_hidden_focusable` | Hidden from screen readers but focusable | 4.1.2 (A), 1.3.1 (A) | Potential issue | Pro | Cannot see styles from external style sheets or scripts that make the content unfocusable. |
| `tabindex_positive` | Positive tabindex | 2.4.3 (A) | Potential issue | Pro | Finds the attribute only; whether the resulting order is a problem needs a person to tab through the page. |
| `focus_outline_removed` | Focus outline may be removed | 2.4.7 (AA) | Potential issue | Pro | Reads style attributes and style blocks in the page only. External style sheets are not fetched, and a visible focus style cannot be confirmed without a browser. |
| `viewport_zoom_blocked` | Zooming is switched off | 1.4.4 (AA) | Issue detected | Pro | Reads the viewport meta tag only. |
| `iframe_no_title` | Frame without a title | 4.1.2 (A), 2.4.1 (A) | Issue detected | Pro | Skips hidden and 1-pixel frames. Titles added by JavaScript are not seen. |
| `custom_control_no_role` | Clickable element that is not a control | 2.1.1 (A), 4.1.2 (A) | Potential issue | Pro | Sees only onclick attributes written in the HTML. Handlers added by scripts are invisible to it. |
| `media_no_captions` | Video or audio may need captions or a transcript | 1.2.1 (A), 1.2.2 (A) | Manual review | Pro | Looks for a captions or subtitles track element. Captions inside the video, or a transcript on the page, cannot be seen. |
| `media_embed_manual` | Embedded video needs a manual check | 1.2.1 (A), 1.2.2 (A), 1.2.3 (A) | Manual review | Pro | Recognises embeds from common video and audio services only. What they offer cannot be seen from the page. |
| `media_autoplay` | Media starts by itself without controls | 1.4.2 (A) | Potential issue | Pro | Reads the autoplay, muted and controls attributes. Playback started by scripts is not seen. |
| `contrast_inline_low` | Text colour may have low contrast | 1.4.3 (AA) | Potential issue | Pro | Calculated from solid colours written in inline style attributes only. Text sized in em, rem or % is judged by the lower large-text threshold. Style sheets, images, gradients, transparency and overlays make a result unknown, and then nothing is reported. |

"Free" checks run in UserFlo without a licence. Excluded findings (Exclusions → audit findings) are hidden from the counts but kept and listed separately.

## Things only a person can check

The Accessibility panels list these as **Manual review required**. Automated checks cannot decide them, so UserFlo never reports them as passed:

- Colour contrast of text from style sheets, over images or on gradients (1.4.3), and of controls and graphics (1.4.11).
- Reflow and text spacing (1.4.10, 1.4.12).
- That everything works with the keyboard (2.1.1), that there are no keyboard traps (2.1.2), that the focus order is sensible (2.4.3), that focus is visible (2.4.7) and not hidden behind sticky bars (2.4.11).
- Target size (2.5.8).
- Error messages after a mistake in a form (3.3.1). UserFlo never submits a form.
- Whether alt text describes the image well (1.1.1) and whether captions and transcripts are correct (1.2.x).

A good routine: fix what is detected, review the potential issues, then tab through the page with the keyboard and try it with a screen reader (NVDA, VoiceOver) and at 200% zoom.

## Contrast

Contrast is calculated only when the text colour **and** its background are both written as solid colours in style attributes on the element or its parents (`style="color:#777;background:#fff"`). Everything else, which is most pages because colours come from style sheets, is **Not tested**. Backgrounds that are images, gradients, translucent or faded make the result unknown, and then nothing is reported. A low-contrast result is always a **Potential issue**: the real result depends on style sheets and overlays UserFlo does not see. The thresholds are 4.5:1 for normal text and 3:1 for large text (24 px, or 18.66 px bold). When the size is written in em, rem or %, only results below 3:1 are reported.

## Common fixes

- **Image without alt:** describe what the image shows or does. If it is decoration, use `alt=""` (an empty alt is correct and is not a finding).
- **Link or button without a name:** add visible text, or an `aria-label`, or alt text on the image inside it.
- **Form field without a label:** add a `<label for="id">` (or wrap the field in a label). A placeholder is not a label.
- **No page language:** set the site language in Settings → General; the theme should print `language_attributes()`.
- **No main landmark:** wrap the main content in `<main>`.
- **Positive tabindex, aria-hidden on focusable content, unknown ARIA roles:** use native HTML controls where you can; they come with the right role, name and keyboard behaviour.

## Privacy, performance and safety

- The audit only reads pages of your own site, a few at a time in the background, and never on a visitor's page view. Tracking is unaffected if a scan fails.
- Nothing from your pages is sent to another service. Findings store short evidence such as an element's tag and class, never what a visitor typed.
- Pages scanned before this feature existed, or before you upgraded to Pro, are read again once.
- Copy for AI includes counts and finding titles. An AI can explain a finding, but it cannot confirm one: use the "Potential issue" and "Manual review" wording as it is.
