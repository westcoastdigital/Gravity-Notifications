<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class GNT_SEND_EMAILS
{
    public function __construct()
    {
        add_action('init', array($this, 'setup_hooks'));
    }

    public function setup_hooks()
    {
        // Hook into all Gravity Forms submissions
        add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);
    }

    public function handle_form_submission($entry, $form)
    {
        $form_id = $form['id'];

        // Get all notification posts
        $args = array(
            'post_type' => 'gf-notifications',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );
        $notifications = get_posts($args);

        if (!$notifications) {
            return;
        }

        foreach ($notifications as $notification) {
            $id = $notification->ID;

            $all_forms = get_post_meta($id, '_gnt_use_all_forms', true);
            $assigned_forms = get_post_meta($id, '_gnt_assigned_forms', true);

            $should_trigger = false;

            if ($all_forms) {
                $should_trigger = true;
            } else {
                if (is_array($assigned_forms)) {
                    foreach ($assigned_forms as $assigned) {
                        if ((int) $assigned['form_id'] === (int) $form_id) {
                            $should_trigger = true;

                            // Optional: check conditional logic here
                            if (!empty($assigned['conditional_logic'])) {
                                $should_trigger = $this->evaluate_conditional_logic($entry, $form, $assigned['conditional_logic']);
                            }
                            break;
                        }
                    }
                }
            }

            if ($should_trigger) {
                $this->send_email($notification, $entry, $form);
            }
        }
    }

    public function send_email($notification, $entry, $form)
    {
        $id = $notification->ID;

        $type = get_post_meta($id, '_gnt_to_email_type', true) ?? 'enter_email';
        $email = '';
        if ($type === 'enter_email') {
            $email = get_post_meta($id, '_gnt_to_email', true);
        } else {
            // Get the field value (e.g., 'Email:2')
            $field = get_post_meta($id, '_gnt_to_email_field_id', true);

            // Ensure the field is not empty
            if (!empty($field)) {
                // Extract the numeric ID from 'Email:2' to get '2'
                $field_id = explode(":", $field)[1];
                var_dump($field_id); // This should output '2'

                // Use rgar() to get the value for the extracted field ID
                $email = rgar($entry, $field_id);
            }
        }

        // Continue with the rest of your code
        $from = get_post_meta($id, '_gnt_from_name', true) ?? get_bloginfo('name');
        $from_email = get_post_meta($id, '_gnt_from_email', true) ?? get_bloginfo('admin_email');
        $reply_to = get_post_meta($id, '_gnt_reply_to', true) ?? get_bloginfo('admin_email');
        $bcc = get_post_meta($id, '_gnt_bcc', true) ?? '';
        $subject = get_post_meta($id, '_gnt_subject', true) ?? 'New Form Submission';

        $global_header = get_post_meta($id, '_gnt_use_global_header', true);
        $global_footer = get_post_meta($id, '_gnt_use_global_footer', true);

        $header = '';
        if ($global_header) {
            $header = get_option('gnt_global_header_content') ?? '';
        }

        $footer = '';
        if ($global_footer) {
            $footer = get_option('gnt_global_footer_content') ?? '';
        }

        $body = get_post_meta($id, '_gnt_message', true) ?? '';

        // Replace merge tags like {Label:1.3} or {Email:2}
        preg_match_all('/{([^:}]+):([\d.]+)}/', $body, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $full_tag = $match[0];       // e.g., {First Name:1.3}
            $label = $match[1];          // e.g., First Name (not used here)
            $field_id = $match[2];       // e.g., 1.3 or 2

            $replacement = rgar($entry, $field_id) ?? '';  // Use rgar to get nested keys safely
            $body = str_replace($full_tag, $replacement, $body);
        }

        $content = $header . $body . $footer;

        // Replace merge tags like {Name:1} with actual field values
        foreach ($entry as $key => $value) {
            if (is_numeric($key)) {
                $field_label = rgar($form['fields'][$key] ?? [], 'label') ?: $key;
                $content = str_replace('{' . $field_label . ':' . $key . '}', $value, $content);
            }
        }

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $headers[] = 'From: ' . $from . ' <' . $from_email . '>';
        $headers[] = 'Reply-To: ' . $reply_to;

        if (!empty($bcc)) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        wp_mail($email, $subject, $content, $headers);
    }


    private function evaluate_conditional_logic($entry, $form, $logic)
    {
        if (empty($logic['conditions']) || !is_array($logic['conditions'])) {
            return true; // No conditions to evaluate = pass
        }

        $results = [];

        foreach ($logic['conditions'] as $condition) {
            $field_id = $condition['field_id'];
            $operator = $condition['operator'];
            $expected = $condition['value'];

            $actual = rgar($entry, $field_id);

            $result = false;

            switch ($operator) {
                case 'is':
                    $result = $actual == $expected;
                    break;
                case 'isnot':
                    $result = $actual != $expected;
                    break;
                case 'greater_than':
                    $result = floatval($actual) > floatval($expected);
                    break;
                case 'less_than':
                    $result = floatval($actual) < floatval($expected);
                    break;
                case 'contains':
                    $result = stripos($actual, $expected) !== false;
                    break;
                case 'starts_with':
                    $result = stripos($actual, $expected) === 0;
                    break;
                case 'ends_with':
                    $result = str_ends_with(strtolower($actual), strtolower($expected));
                    break;
            }

            $results[] = $result;
        }

        // Logic type determines overall outcome
        if ($logic['logic_type'] === 'any') {
            return in_array(true, $results, true);
        }

        // Default to 'all'
        return !in_array(false, $results, true);
    }
}

new GNT_SEND_EMAILS();
