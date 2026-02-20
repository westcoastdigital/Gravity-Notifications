# Gravity Notifications

Manage notifications for Gravity Forms in one place so you can assign them to multiple forms.

## Requirements

- PHP 8.0
- WordPress 6.8
- Gravity Forms (tested with v2.9.8)

## Usage

1. Upload the zip file to your WordPress plugins folder
2. Activate plugin and add license within the plugin screen
3. Configure max width, global header and footer in Forms > Notification Settings in the admin screen
4. Configure each notification within Forms > Notifications

## Screenshots
#### Global Edit Screen
![Global Edit Screen](assets/screenshots/Global_Edit_Screen.jpg)

#### Notification Edit Screen
![Notification Edit Screen](assets/screenshots/Notification_Edit_Screen.jpg)


## FAQ

<details>

<summary>Do I have to have a license to test?</summary>

No, if you are using a local testing machine, we have included functionality to enable all settings. Once you go live you will need to add your license.

</details>

<details>

<summary>Does this override the core Gravity Forms notifications?</summary>

No, these notifications will send as normal. Deactivate or delete the default Gravity Forms notifications you do not wish to send — or use the Manage Default Notifications button on each assigned form row to toggle them on/off without leaving the screen.

</details>

<details>

<summary>Can I use conditional logic for which email to send the notification to?</summary>

No, at this stage these notifications do not support email routing like the default Gravity Forms notifications.

</details>

<details>

<summary>What does Set a Max Width do?</summary>

If you turn this setting on and set the width, it will wrap your entire email in a table with that max width, centrally aligned.
This is specifically useful for ensuring emails display correctly on mobile without overflow.

</details>

<details>

<summary>Do I have to use the global header and footer?</summary>

No, they are merely there so you do not need to add them across all notifications. If you enable the Global Header on a notification, it is concatenated to the message as part of the body — same with the footer.

</details>

<details>

<summary>How can I test the notification works?</summary>

You will need to submit the relevant form. I suggest adding a plugin to disable emails and also to log emails.
<br>
I use and recommend plugins by webaware:
<br>
[Disable Emails](https://wordpress.org/plugins/disable-emails/)
<br>
[Log Emails](https://wordpress.org/plugins/log-emails/)

</details>

<details>

<summary>Can I use shortcodes in notifications?</summary>

Yes, you can use any WordPress shortcode in the Global Notifications (header and footer) and the Notification Message body.
<br>
We have included a few built-in shortcodes to assist:

| Shortcode | Output |
| --- | --- |
| `[gnt_site_name]` | Site name (linked) |
| `[gnt_year]` | Current year |
| `[gnt_current_date]` | Current date |

Any custom shortcodes registered by your theme or other plugins (e.g. `[parrys_logo]`) will also be processed in all three areas.

</details>

<details>

<summary>Can I use field values in notifications?</summary>

Yes. Gravity Forms merge tags are supported in the Notification Message body (but not in the Global Header or Footer, since those are shared across forms).
<br>
This includes:
- **Standard field tags** — e.g. `{Email:2}`, `{Name:1.3}`
- **GF built-in tags** — e.g. `{form_title}`, `{entry_id}`, `{ip}`
- **Custom merge tags** registered via the `gform_replace_merge_tags` filter — e.g. `{brochure_link}`, `{current_year}`

There are buttons above the message editor showing all fields from assigned forms that you can click to insert tags. Use fields common to all assigned forms so the tag resolves correctly on every submission.
<br>
Alternatively, when editing a field in Gravity Forms, the Field ID is shown in a grey pill in the top right. The merge tag format is generally `{Label:id}` — for example, an email field with label "Email" and ID 2 is `{Email:2}`.
<br>
For compound fields like Name or Address, sub-field tags are used:

| Field | Tag ID | Merge Tag |
| --- | --- | --- |
| Prefix | 1.1 | `{Name:1.1}` |
| First | 1.2 | `{Name:1.2}` |
| Middle | 1.3 | `{Name:1.3}` |
| Last | 1.4 | `{Name:1.4}` |
| Suffix | 1.5 | `{Name:1.5}` |

</details>


## Changelog

**1.6.1** Bug Fix<br>
* Replaced manual merge tag regex with `GFCommon::replace_variables()` so all Gravity Forms merge tags are processed correctly in the message body — including custom tags registered via `gform_replace_merge_tags` (e.g. `{brochure_link}`, `{current_year}`)
* Shortcode rendering (`do_shortcode`) now correctly runs before merge tag replacement in the body
* Clarified that shortcodes are supported in header, footer, and body; merge tags are body-only

**1.6.0** Update<br>
* Add support for disabling default emails from within the custom notification form setting

**1.5.2** Bug Fix<br>
* Fix issue when curly braces are added to email field ID
* Make email field ID tags buttons to click and populate the email field input

**1.5.1** Bug Fix<br>
* Fix issue with email when max width set email content being repeated

**1.5.0** Update<br>
* Added max width settings and renamed Global Notifications to Notification Settings

**1.4.0** Update<br>
* Updated styles
* Added Header and Footer previews to messages

**1.3.0** Update<br>
* Added merge tags as buttons for assigned forms
* Added shortcode support and custom shortcodes

**1.2.0** Update<br>
* Added merge tag support to message

**1.1.0** Update<br>
* Added Licensing