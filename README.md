# Gravity Notifications

Mangage notifications for Gravity Forms in one place so can assign to multiple forms.

## Requirements

- php 8.0
- WordPress 6.8
- Gravity Forms (tested with v 2.9.8)

## Usage

1. Upload the zip file to your WordPress plugins folder
2. Activate plugin and add license within the plugin screen
3. Configure global header and footer in Forms > Global Notifcations in the admin screen
4. Configure each notification within Forms > Notifications

## FAQ

<details>

<summary>Does this override the core Gravity Forms notifications?</summary>

No, these notifications will send as normal, so deactivate or delete the notifications you do not wish to send

</details>

<details>

<summary>Can I use conditional logic for wich email to send the notification to?</summary>

No, At this stage these notifications do not support email routing like the default Gravity Forms notifications.

</details>

<details>

<summary>Do I have to use the global header and footer?</summary>

No, they are merely there so you do not need to add them across all notifications. If you enable the Global Header on a notification, it concatenates it to the message as part of the body, same with the footer.

</details>

<details>

<summary>How can I test the notification works?</summary>

You will need to submit the relevant form, I suggest adding a plugin to disable emails and also to log emails.
<br>
I use and recommend plugins by webaware:
<br>
[Disable Emails](https://wordpress.org/plugins/disable-emails/)
<br>
[Log Emails](https://wordpress.org/plugins/log-emails/)

</details>

<details>

<summary>Can I use field values in notifications?</summary>

Yes, you can use form fields (merge tags) in the To Email and and also the Message.
<br>
When you edit a field in the Gravity Form, in the top right you will see the Field ID (in a grey pill shaped element).
<br>
Then your merge tag will generally be label:id, so if you have an email field with label Email and an ID of 2, then the merge tag is Email:2.
<br>
If you have a field which is actually a group of fields, like Name or Address, then the value may be different.
<br>
For example, if you have a Name field with an ID of 1, then the fields would be more like:
<br>
| Field | Tag ID | Merge Tag |
| ------------- | ------------- | ------------- |
| Prefix | 1.1 | Name:1.1 |
| First | 1.2 | Name:1.2 |
| Middle |1.3 | Name:1.3 |
| Suffix | 1.4 | Name:1.4 |

</details>
