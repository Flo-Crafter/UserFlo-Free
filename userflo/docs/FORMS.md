# Tracking forms

UserFlo records a **form submit** event whenever a visitor submits a form on your site. It never reads what was typed into the fields: only that a form was submitted, which form, and on which page.

## Every form works without setup
A form is named after its own label (`aria-label`, `name` or `id`). This works for any form on the page, including forms from plugins and forms you wrote by hand. Because it counts the moment the browser submits the form, it means *someone tried to send it*; it cannot know whether your server accepted it.

## Naming a form yourself: `[userflo_form]` (UserFlo free and Pro)
Wrap any form, or any form plugin's shortcode, to give it a name of your choosing:

```
[userflo_form name="Quote request"]
  [your_form_plugin_shortcode id="12"]
[/userflo_form]
```

- The wrapper adds an invisible container (`display: contents`), so your layout does not change.
- `name` is up to 80 characters. Without a name the form is left untouched.
- It collects nothing itself: the normal tracker records the submit, with the usual consent, Global Privacy Control and exclusion rules applied first.
- Works in the block editor's *Shortcode* block, classic editor, page builders and theme templates (`echo do_shortcode( '[userflo_form name="Footer signup"]…[/userflo_form]' );`).
- To leave a form out of tracking completely, add `data-userflo-ignore` to it or to a parent, or list its selector under **Settings → Tracking → Exclude selectors**.

## Automatic detection (UserFlo Pro)
Pro recognises these form builders by their markup, with no shortcode and no settings:

| Builder | Named as | "Sent" is confirmed by |
|---|---|---|
| WPForms | `WPForms: <title>` | the `wpformsAjaxSubmitSuccess` event, or its confirmation message |
| Fluent Forms | `Fluent Forms #<id>` | the `fluentform_submission_success` event, or `.ff-message-success` |
| Gravity Forms | `Gravity Forms: <title>` | the `gform_confirmation_loaded` event, or the confirmation message |
| Forminator | `Forminator #<id>` | the `forminator:form:submit:success` event, or its success message |
| Formidable Forms | `Formidable Forms #<id>` | the `frmFormComplete` event, or `.frm_message` |
| Contact Form 7 | `Contact Form 7 #<id>` | the `wpcf7mailsent` event |
| Divi contact module | `Divi contact form: <title>` | the module's own message, when it is not an error list |
| Anything else | the name you give it with `[userflo_form]` | a CSS selector you give it: `[userflo_form name="Quote" success=".thank-you"]` |

- **Submissions** (the browser submitted the form) and **Confirmed sent** (the builder reported success) are separate numbers in **Statistics → WordPress → Forms**. A form that shows `—` has no way to confirm.
- Reports show the form's **current title** from the builder, so renaming a form does not split its history.
- Confirmed sends are stored as an ordinary custom event named `Form sent: <form name>`, so they also appear in the activity view and (with the GTM bridge on) as `userflo_custom_event`.
- Switch detection, or just the "sent" confirmation, off under **Settings → Advanced tracking → Form detection**.
- Field values, entries and e-mail addresses are never read or stored. Titles come from the form's own heading in the page or, in reports, from the builder's form list.

### Limits you should know about
The detection relies on each builder's markup and public events. A builder update or a heavily restyled form can stop a confirmation from being recognised; the submit is still counted. Forms rendered inside an iframe (some embeds) are not visible to any page script, including UserFlo's.

## Alerts
Pro's Alerts (see the Alerts guide in Help) can notify you when a form is sent. For the builders listed above the rule is tied to the builder's own form id (shown in the list as, for example, `wpforms:12`), so renaming the form does not break it. Forms without an id (Divi contact form, `[userflo_form]`, plain HTML forms) can only be matched by name.
