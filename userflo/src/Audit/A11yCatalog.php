<?php
namespace UserFlo\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * The accessibility findings of the website audit: what each one means, how to fix it, which WCAG 2.2 success criterion it relates to and how
 * sure the check can be. Data only; the logic is in `A11yBasic` (Lite) and UserFlo Pro's `A11yChecks`.
 *
 * Detection (how sure the check is):
 *  - `issue`     a specific problem read from the page's HTML (shown as "Issue detected", stored as class "verified");
 *  - `potential` a pattern that often causes a problem but cannot be confirmed from HTML alone ("Potential issue", class "warning");
 *  - `manual`    something only a person can judge ("Manual review required", class "recommendation": never counted as an issue).
 * "Passed automated check" and "Not tested" are not findings: they are worked out per page from what the scan tested (see A11yReport).
 *
 * Automated checks cannot establish WCAG conformance. Nothing here says a page conforms; a passed check only says the condition tested was met.
 */
final class A11yCatalog {

	const DETECT_ISSUE     = 'issue';
	const DETECT_POTENTIAL = 'potential';
	const DETECT_MANUAL    = 'manual';

	/** Findings only the accessibility audit makes (same layout as Catalog::all()). */
	public static function entries() {
		return array(
			'html_lang_missing'      => array( 'verified', 2, 'page', __( 'No page language', 'userflo' ), __( 'The html element has no lang attribute, so screen readers may read the page with the wrong voice and pronunciation.', 'userflo' ), __( 'Set the site language in WordPress (Settings → General) and make sure the theme prints language_attributes() on the html tag.', 'userflo' ) ),
			'html_lang_invalid'      => array( 'warning', 1, 'page', __( 'Page language may be invalid', 'userflo' ), __( 'The lang attribute does not look like a language code (such as “en” or “en-GB”), so assistive technology may ignore it.', 'userflo' ), __( 'Use a valid language code on the html tag.', 'userflo' ) ),
			'img_alt_filename'       => array( 'warning', 1, 'page', __( 'Image text looks like a file name', 'userflo' ), __( 'The alt text of an image looks like a file name or a placeholder (for example “IMG_2041.jpg” or “image”), which says nothing about what the picture shows.', 'userflo' ), __( 'Describe what the image shows or what it does. If it is only decoration, use an empty alt (alt="").', 'userflo' ) ),
			'img_empty_alt_title'    => array( 'warning', 1, 'page', __( 'Image marked decorative but has a title', 'userflo' ), __( 'The image has an empty alt text (so it is skipped by screen readers) but also a title, which suggests it may carry meaning.', 'userflo' ), __( 'If the picture communicates something, describe it in the alt text. If it is decoration, remove the title.', 'userflo' ) ),
			'heading_empty'          => array( 'verified', 2, 'page', __( 'Empty heading', 'userflo' ), __( 'A heading element has no text, no image text and no label. Screen reader users who browse by headings meet an empty entry.', 'userflo' ), __( 'Add text to the heading or remove the heading element (use a styled paragraph for spacing).', 'userflo' ) ),
			'main_landmark_missing'  => array( 'warning', 1, 'page', __( 'No main content landmark', 'userflo' ), __( 'The page has no main element (or role="main"). Without it, keyboard and screen reader users have no direct way to skip to the content.', 'userflo' ), __( 'Wrap the page’s main content in a main element. Most modern themes do this; a page builder template may have left it out.', 'userflo' ) ),
			'id_duplicate_referenced' => array( 'warning', 1, 'page', __( 'Duplicate ID used by a label or ARIA reference', 'userflo' ), __( 'Two elements share an ID that a label or an ARIA attribute points to, so the browser may connect the label or description to the wrong element.', 'userflo' ), __( 'Make every ID unique. Duplicates often come from the same widget or form being added to a page twice.', 'userflo' ) ),
			'aria_ref_missing'       => array( 'warning', 1, 'page', __( 'Label or ARIA reference points to nothing', 'userflo' ), __( 'A label (for=…) or an aria-labelledby / aria-describedby attribute names an ID that is not in the page HTML. If JavaScript creates that element later, this is a false alarm.', 'userflo' ), __( 'Check that the target element exists, with exactly that ID, when the page has loaded.', 'userflo' ) ),
			'aria_role_invalid'      => array( 'warning', 1, 'page', __( 'Unknown ARIA role', 'userflo' ), __( 'A role attribute uses a value that is not a defined WAI-ARIA role, so assistive technology will ignore it.', 'userflo' ), __( 'Use a defined role (or better, a native HTML element that already has the meaning).', 'userflo' ) ),
			'aria_hidden_focusable'  => array( 'warning', 1, 'page', __( 'Hidden from screen readers but focusable', 'userflo' ), __( 'An element with aria-hidden="true" contains something a keyboard user can focus, so focus can land on an element that screen readers do not announce.', 'userflo' ), __( 'Remove aria-hidden, or make the content unfocusable (hidden, inert or tabindex="-1") while it is hidden.', 'userflo' ) ),
			'tabindex_positive'      => array( 'warning', 1, 'page', __( 'Positive tabindex', 'userflo' ), __( 'A tabindex above zero forces an element to the front of the keyboard order, which usually makes the order confusing.', 'userflo' ), __( 'Use tabindex="0" or none, and arrange the HTML in the order people should reach it.', 'userflo' ) ),
			'focus_outline_removed'  => array( 'warning', 1, 'page', __( 'Focus outline may be removed', 'userflo' ), __( 'The page’s own CSS (a style attribute or a style block) turns off the focus outline without adding another visible focus style. Style sheets that UserFlo does not read may fix this.', 'userflo' ), __( 'Keep the browser outline or add a clearly visible :focus-visible style. Tab through the page to check.', 'userflo' ) ),
			'viewport_zoom_blocked'  => array( 'verified', 2, 'page', __( 'Zooming is switched off', 'userflo' ), __( 'The viewport meta tag disables pinch zoom (user-scalable=no) or limits it below 2×, which stops many people enlarging the page.', 'userflo' ), __( 'Remove user-scalable=no and maximum-scale from the viewport tag.', 'userflo' ) ),
			'iframe_no_title'        => array( 'verified', 2, 'page', __( 'Frame without a title', 'userflo' ), __( 'A visible iframe has no title or label, so it is announced only as “frame”.', 'userflo' ), __( 'Add a short title attribute that says what the frame contains.', 'userflo' ) ),
			'custom_control_no_role' => array( 'warning', 1, 'page', __( 'Clickable element that is not a control', 'userflo' ), __( 'A div, span or similar element has an onclick handler but no role and is not focusable, so keyboard and screen reader users may not be able to use it. Click handlers added by scripts are not visible to this check.', 'userflo' ), __( 'Use a real button or link. If that is impossible, add role, tabindex="0" and keyboard handling.', 'userflo' ) ),
			'link_acts_as_button'    => array( 'warning', 1, 'page', __( 'Link that acts as a button', 'userflo' ), __( 'A link without a real address (# or javascript:) is wired to an action, so assistive technology announces “link” where the user expects a button.', 'userflo' ), __( 'Use a button element, or add role="button" and keyboard handling.', 'userflo' ) ),
			'form_required_unmarked' => array( 'warning', 1, 'page', __( 'Required field may not be marked', 'userflo' ), __( 'A label shows an asterisk but the field is not marked required (required or aria-required), so assistive technology may not say it is required.', 'userflo' ), __( 'Add the required attribute (or aria-required="true") and say in text what the asterisk means.', 'userflo' ) ),
			'input_no_autocomplete'  => array( 'warning', 1, 'page', __( 'Personal field without autocomplete', 'userflo' ), __( 'An e-mail or telephone field has no autocomplete attribute, so browsers and assistive tools cannot fill in or identify its purpose.', 'userflo' ), __( 'Add autocomplete="email" or autocomplete="tel" where the field asks for the visitor’s own details.', 'userflo' ) ),
			'media_no_captions'      => array( 'recommendation', 1, 'page', __( 'Video or audio may need captions or a transcript', 'userflo' ), __( 'A video or audio element has no captions track. The captions or a transcript may be provided another way (burned into the video, or on the page), which this check cannot see.', 'userflo' ), __( 'Check that people who cannot hear the audio have captions or a transcript. Add a track element if the file has a captions file.', 'userflo' ) ),
			'media_embed_manual'     => array( 'recommendation', 1, 'page', __( 'Embedded video needs a manual check', 'userflo' ), __( 'The page embeds a video or audio player from another service. Whether it has captions or a transcript depends on that service and the file.', 'userflo' ), __( 'Play it and check that captions are available, and that audio-only content has a transcript.', 'userflo' ) ),
			'media_autoplay'         => array( 'warning', 1, 'page', __( 'Media starts by itself without controls', 'userflo' ), __( 'A video or audio element autoplays with sound and has no visible controls, so people may not be able to pause or silence it.', 'userflo' ), __( 'Remove autoplay, mute the media, or show controls.', 'userflo' ) ),
			'contrast_inline_low'    => array( 'warning', 1, 'page', __( 'Text colour may have low contrast', 'userflo' ), __( 'Text whose colour and background are both set in the element’s own style attributes has a contrast below the WCAG minimum. Only these inline colours are read; style sheets, images and overlays are not, so this is a calculation, not a measurement of what visitors see.', 'userflo' ), __( 'Check the real colours in a browser and use a contrast checker. WCAG asks for at least 4.5:1 for normal text and 3:1 for large text.', 'userflo' ) ),
		);
	}

	/**
	 * WCAG meta for every accessibility finding (new ones and the existing ones the accessibility audit also uses):
	 * code => array( success criteria (number => level), detection, limits ).
	 *
	 * @return array<string,array{0:array<string,string>,1:string,2:string}>
	 */
	public static function meta() {
		$I = self::DETECT_ISSUE;
		$P = self::DETECT_POTENTIAL;
		$M = self::DETECT_MANUAL;
		return array(
			'title_missing'          => array( array( '2.4.2' => 'A' ), $I, __( 'Reads the title tag of the HTML sent by the server; a title set by JavaScript after load is not seen.', 'userflo' ) ),
			'html_lang_missing'      => array( array( '3.1.1' => 'A' ), $I, __( 'Reads the lang attribute of the html tag in the HTML sent by the server.', 'userflo' ) ),
			'html_lang_invalid'      => array( array( '3.1.1' => 'A' ), $P, __( 'Checks only the shape of the code, not that it is the right language for the page.', 'userflo' ) ),
			'img_alt_missing'        => array( array( '1.1.1' => 'A' ), $I, __( 'Finds img elements without an alt attribute. Images set as CSS backgrounds are not images in HTML and are not seen.', 'userflo' ) ),
			'img_alt_filename'       => array( array( '1.1.1' => 'A' ), $P, __( 'Matches file-name-like and placeholder alt text only. It cannot judge whether other alt text describes the image well.', 'userflo' ) ),
			'img_empty_alt_title'    => array( array( '1.1.1' => 'A' ), $P, __( 'An empty alt is correct for decoration; this only flags images that also carry a title. It cannot see what the image shows.', 'userflo' ) ),
			'link_empty'             => array( array( '2.4.4' => 'A', '4.1.2' => 'A' ), $I, __( 'Looks for text, an image with alt text, aria-label, aria-labelledby or title inside the link. A name created by CSS or JavaScript is not seen.', 'userflo' ) ),
			'link_generic'           => array( array( '2.4.4' => 'A' ), $P, __( 'Matches a short list of generic phrases. Whether the surrounding text makes the link clear is a human judgement.', 'userflo' ) ),
			'link_acts_as_button'    => array( array( '4.1.2' => 'A' ), $P, __( 'Needs a placeholder address plus an inline handler or ARIA state. Handlers attached by scripts are invisible to this check.', 'userflo' ) ),
			'button_no_name'         => array( array( '4.1.2' => 'A' ), $I, __( 'Looks for text, an image with alt text, a value, aria-label, aria-labelledby or title. A name created by CSS or JavaScript is not seen.', 'userflo' ) ),
			'form_field_no_label'    => array( array( '1.3.1' => 'A', '3.3.2' => 'A', '4.1.2' => 'A' ), $I, __( 'Recognises label for=, wrapping labels, aria-label, aria-labelledby and title. Fields outside a form element are not checked. A placeholder is not a label.', 'userflo' ) ),
			'form_required_unmarked' => array( array( '3.3.2' => 'A', '1.3.1' => 'A' ), $P, __( 'Looks for an asterisk in the label text. It does not know what the asterisk means on that form.', 'userflo' ) ),
			'input_no_autocomplete'  => array( array( '1.3.5' => 'AA' ), $P, __( 'Looks at e-mail and telephone fields only, and cannot tell whether a field asks for the visitor’s own details.', 'userflo' ) ),
			'heading_empty'          => array( array( '1.3.1' => 'A', '2.4.6' => 'AA' ), $I, __( 'Finds heading elements with no text, image text or label. Headings filled by JavaScript are not seen.', 'userflo' ) ),
			'main_landmark_missing'  => array( array( '1.3.1' => 'A', '2.4.1' => 'A' ), $P, __( 'Looks for a main element or role="main" in the HTML sent by the server. Other bypass mechanisms (such as a skip link) are not assessed.', 'userflo' ) ),
			'id_duplicate_referenced' => array( array( '1.3.1' => 'A', '4.1.2' => 'A' ), $P, __( 'Reports duplicates only when a label or an ARIA attribute points to the ID. Duplicate IDs elsewhere are not reported.', 'userflo' ) ),
			'aria_ref_missing'       => array( array( '1.3.1' => 'A', '4.1.2' => 'A' ), $P, __( 'The target may be created by JavaScript after the page loads.', 'userflo' ) ),
			'aria_role_invalid'      => array( array( '4.1.2' => 'A' ), $P, __( 'Compares role values with the WAI-ARIA 1.2 role list. It does not check that a valid role suits the element.', 'userflo' ) ),
			'aria_hidden_focusable'  => array( array( '4.1.2' => 'A', '1.3.1' => 'A' ), $P, __( 'Cannot see styles from external style sheets or scripts that make the content unfocusable.', 'userflo' ) ),
			'tabindex_positive'      => array( array( '2.4.3' => 'A' ), $P, __( 'Finds the attribute only; whether the resulting order is a problem needs a person to tab through the page.', 'userflo' ) ),
			'focus_outline_removed'  => array( array( '2.4.7' => 'AA' ), $P, __( 'Reads style attributes and style blocks in the page only, and only rules for links, buttons, form fields or all elements. External style sheets are not fetched, and a visible focus style cannot be confirmed without a browser.', 'userflo' ) ),
			'viewport_zoom_blocked'  => array( array( '1.4.4' => 'AA' ), $I, __( 'Reads the viewport meta tag only.', 'userflo' ) ),
			'iframe_no_title'        => array( array( '4.1.2' => 'A', '2.4.1' => 'A' ), $I, __( 'Skips hidden and 1-pixel frames. Titles added by JavaScript are not seen.', 'userflo' ) ),
			'custom_control_no_role' => array( array( '2.1.1' => 'A', '4.1.2' => 'A' ), $P, __( 'Sees only onclick attributes written in the HTML. Handlers added by scripts are invisible to it.', 'userflo' ) ),
			'media_no_captions'      => array( array( '1.2.1' => 'A', '1.2.2' => 'A' ), $M, __( 'Looks for a captions or subtitles track element. Captions inside the video, or a transcript on the page, cannot be seen.', 'userflo' ) ),
			'media_embed_manual'     => array( array( '1.2.1' => 'A', '1.2.2' => 'A', '1.2.3' => 'A' ), $M, __( 'Recognises embeds from common video and audio services only. What they offer cannot be seen from the page.', 'userflo' ) ),
			'media_autoplay'         => array( array( '1.4.2' => 'A' ), $P, __( 'Reads the autoplay, muted and controls attributes. Playback started by scripts is not seen.', 'userflo' ) ),
			'contrast_inline_low'    => array( array( '1.4.3' => 'AA' ), $P, __( 'Calculated from solid colours written in inline style attributes only. Text sized in em, rem or % is judged by the lower large-text threshold. Style sheets, images, gradients, transparency and overlays make a result unknown, and then nothing is reported.', 'userflo' ) ),
		);
	}

	/** Findings the Lite scanner decides (they need nothing but the page's own HTML and are reliable enough to report without a licence). */
	const BASIC = array( 'html_lang_missing', 'img_alt_missing', 'link_empty', 'button_no_name', 'form_field_no_label' );

	/**
	 * What no automated check can decide. Shown as "Manual review required", never as passed. criterion => array( level, title, what to check ).
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function manual_topics() {
		return array(
			'1.4.3'  => array( 'AA', __( 'Colour contrast of text', 'userflo' ), __( 'Contrast of text as styled by style sheets, over images or on gradients. Check with a contrast checker in the browser.', 'userflo' ) ),
			'1.4.11' => array( 'AA', __( 'Contrast of controls and graphics', 'userflo' ), __( 'Borders of fields, icons and chart parts need at least 3:1 against their surroundings. This cannot be calculated from HTML.', 'userflo' ) ),
			'1.4.10' => array( 'AA', __( 'Reflow', 'userflo' ), __( 'Zoom to 400% (or a 320 pixel wide window) and check that content stays usable without scrolling in two directions. Needs a browser.', 'userflo' ) ),
			'1.4.12' => array( 'AA', __( 'Text spacing', 'userflo' ), __( 'Widen line, paragraph, letter and word spacing and check that nothing is cut off or overlaps. Needs a browser.', 'userflo' ) ),
			'2.1.1'  => array( 'A', __( 'Everything works with the keyboard', 'userflo' ), __( 'Tab through the page and use every menu, slider, dialog and form with the keyboard alone.', 'userflo' ) ),
			'2.1.2'  => array( 'A', __( 'No keyboard traps', 'userflo' ), __( 'Check that focus can always move away from every widget, including pop-ups and embedded players.', 'userflo' ) ),
			'2.4.3'  => array( 'A', __( 'Sensible focus order', 'userflo' ), __( 'The order focus moves in should follow the meaning of the page.', 'userflo' ) ),
			'2.4.7'  => array( 'AA', __( 'Focus is visible', 'userflo' ), __( 'Tab through the page and confirm that you can always see where focus is.', 'userflo' ) ),
			'2.4.11' => array( 'AA', __( 'Focus is not hidden', 'userflo' ), __( 'Sticky headers, cookie banners and chat widgets must not cover the focused element completely.', 'userflo' ) ),
			'2.5.8'  => array( 'AA', __( 'Target size', 'userflo' ), __( 'Clickable targets should be at least 24 by 24 CSS pixels (WCAG 2.2). Needs rendered sizes.', 'userflo' ) ),
			'3.3.1'  => array( 'A', __( 'Error messages', 'userflo' ), __( 'Submit a form with mistakes and check that errors are identified in text and linked to the fields. UserFlo never submits forms.', 'userflo' ) ),
			'1.2.2'  => array( 'A', __( 'Captions are accurate', 'userflo' ), __( 'Whether captions and transcripts exist and are correct can only be judged by watching and listening.', 'userflo' ) ),
			'1.1.1'  => array( 'A', __( 'Image descriptions are accurate', 'userflo' ), __( 'UserFlo can see that alt text exists, not that it describes the image well or that decorative images are marked as such.', 'userflo' ) ),
		);
	}
}
