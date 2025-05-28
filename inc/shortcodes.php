<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function gnt_site_name($atts)
{
    $atts = shortcode_atts(
		array(
			'link' => true,
		),
		$atts,
		'gnt_site_name'
	);

    $link = $atts['link'];
    $site_name = get_bloginfo('name');
    if ($link) {
        return '<a href="' . esc_url(home_url()) . '">' . esc_html($site_name) . '</a>';
    }
    return esc_html($site_name);
}
add_shortcode('gnt_site_name', 'gnt_site_name');

function gnt_year()
{
    return date('Y');
}
add_shortcode('gnt_year', 'gnt_year');

function gnt_current_date($atts)
{
    $default_format = get_option('date_format');
    $atts = shortcode_atts(
		array(
			'format' => $default_format,
		),
		$atts,
		'gnt_current_date'
	);

	$format = $atts['format'];
    return date($format);
}
add_shortcode('gnt_current_date', 'gnt_current_date');

