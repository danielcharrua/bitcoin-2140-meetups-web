<?php

/**
 * Plugin Name: 2140 Meetups Logic
 * Plugin URI: https://2140meetups.com
 * Description: Logic for the 2140meetups.com website
 * Version: 1.0.0
 * Author: Gzuuus, Urajiro, danielpcostas
 * Author URI: https://2140meetups.com
 */

defined('ABSPATH') or die('Get out!');

/**
 * Require composer and packages
 */
require plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Download ICS
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Components\Calendar;

// External integration for maps and btcmaps
include(plugin_dir_path(__FILE__) . 'map/helper.php');
include(plugin_dir_path(__FILE__) . 'map/functions.php');
include(plugin_dir_path(__FILE__) . 'btcmap/helper.php');
include(plugin_dir_path(__FILE__) . 'btcmap/functions.php');

// Map integration constants
$upload_dir = wp_upload_dir();

define("LEAFLET_FILE", $upload_dir['basedir'] . '/map/geo.json');
const UPDATE = 'update';
const DELETE = 'delete';
const PIN_COLOR = "#FA402B";
const PIN_SIZE = "medium";

// btcmap integration constants
define("BTCMAP_FOLDER", $upload_dir['basedir'] . '/btcmap/');
const NOMINATIM_OPENSTREETMAP = "https://nominatim.openstreetmap.org/search?format=json&polygon_geojson=1&polygon_threshold=0.1&city=%s&country=%s&email=hello@2140meetups.com";
const CITY_NINJA = "https://api.api-ninjas.com/v1/city?name=%s";
// const NINJA_API_KEY defined in wp-config.php;


/* 
 * Backend - Limit users to see only posts they own 
 * 
 * @link https://www.wpbeginner.com/plugins/how-to-limit-authors-to-their-own-posts-in-wordpress-admin/
 */
function posts_for_current_author($query)
{
    global $pagenow;

    if ('edit.php' != $pagenow || !$query->is_admin)
        return $query;

    if (!current_user_can('edit_others_posts')) {
        global $user_ID;
        $query->set('author', $user_ID);
    }
    return $query;
}
add_filter('pre_get_posts', 'posts_for_current_author');

/*
 * Frontend - Populate address field only if editing (must exist the get parameter $_GET['cptid'])
 * 
 * @link https://docs.gravityforms.com/gform_field_value_parameter_name/
 */
function populate_address($value, $field, $name)
{

    if (!isset($_GET['cptid'])) {
        return;
    }

    $form = $field['formId'];

    $post = get_post($_GET['cptid']);

    if ($form === 1) {
        $address = get_post_meta($post->ID, 'direccion', true);
    } else {
        $address = get_post_meta($post->ID, 'direccion', true);
    }

    return $address;
}
add_filter('gform_field_value_address', 'populate_address', 10, 3);

/*
 * Frontend - Populate city address field only if editing (must exist the get parameter $_GET['cptid'])
 * 
 * @link https://docs.gravityforms.com/gform_field_value_parameter_name/
 */
function populate_city($value, $field, $name)
{

    if (!isset($_GET['cptid'])) {
        return;
    }

    $form = $field['formId'];

    $post = get_post($_GET['cptid']);

    if ($form === 1) {
        $city = get_post_meta($post->ID, 'ciudad', true);
    } /*else {
		$address = get_post_meta($post->ID, 'direccion', true);
	}*/

    return $city;
}
add_filter('gform_field_value_city', 'populate_city', 10, 3);

/*
 * Frontend - Populate country address field only if editing (must exist the get parameter $_GET['cptid'])
 * 
 * @link https://docs.gravityforms.com/gform_field_value_parameter_name/
 */
function populate_country($value, $field, $name)
{

    if (!isset($_GET['cptid'])) {
        return;
    }

    $form = $field['formId'];

    $post = get_post($_GET['cptid']);

    if ($form === 1) {
        $country = get_post_meta($post->ID, 'pais', true);
    } /*else {
		$address = get_post_meta($post->ID, 'direccion', true);
	}*/

    return $country;
}
add_filter('gform_field_value_country', 'populate_country', 10, 3);

/*
 * Frontend - Dynamically Populating a Field with CPT
 * 
 * @link https://docs.gravityforms.com/dynamically-populating-drop-down-or-radio-buttons-fields/
 */
function populate_communities($form)
{
    global $current_user;

    foreach ($form['fields'] as &$field) {

        if ($field->type != 'select' || strpos($field->cssClass, 'community') === false) {
            continue;
        }

        // you can add additional parameters here to alter the posts that are retrieved
        // more info: http://codex.wordpress.org/Template_Tags/get_posts
        $posts = get_posts('post_type=comunidad&author=' . $current_user->ID . '&post_status=publish');

        $choices = array();

        foreach ($posts as $post) {
            $choices[] = array('text' => $post->post_title, 'value' => $post->ID);
        }

        // update 'Select a Post' to whatever you'd like the instructive option to be
        $field->placeholder = 'Seleccionar comunidad';
        $field->choices = $choices;
    }

    return $form;
}
add_filter('gform_pre_render_2', 'populate_communities');

/*
 * Frontend - Dynamically Populating a Field with custom taxonomy
 * 
 * @link https://docs.gravityforms.com/dynamically-populating-drop-down-or-radio-buttons-fields/
 */
function populate_cats($form)
{
    global $current_user;

    foreach ($form['fields'] as &$field) {

        if ($field->type != 'select' || strpos($field->cssClass, 'category') === false) {
            continue;
        }

        $terms = get_terms(array(
            'taxonomy' => 'cat_meetup',
            'hide_empty' => false,
            'orderby'   => 'title',
            'order'   => 'ASC',
        ));

        $choices = array();

        foreach ($terms as $term) {
            $choices[] = array('text' => $term->name, 'value' => $term->term_id);
        }

        $field->choices = $choices;
        $field->placeholder = 'Seleccionar tipo';
    }

    return $form;
}
add_filter('gform_pre_render_2', 'populate_cats');

/* 
 * Frontend - Filter only future meetups and order them always by meetup date.
 * This will affect all CPTs queries in frontend but the user dashboard (it will show all meetups)
 * 
 * @link https://developer.wordpress.org/reference/hooks/pre_get_posts/
 */
function filter_future_meetups_and_order($query)
{
    if (!is_admin() && !$query->is_main_query() && in_array($query->get('post_type'), array('meetup'))) {

        $query->set('meta_key', 'fecha');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'ASC');

        //user dashboard (if not)
        if (!is_page('61')) {
            $args =    array(
                array(
                    'key'     => 'fecha',
                    'value'   => date('Y-m-d'),
                    'type'    => 'DATE',
                    'compare' => '>='
                )
            );
            $query->set('meta_query', $args);
        }

        return;
    }
}
add_action('pre_get_posts', 'filter_future_meetups_and_order');

/* 
 * Frontend - Limit users to see only posts they own. Used on https://2140meetups.com/home-usuario/ (ID 61) 
 * 
 * @link https://developer.wordpress.org/reference/hooks/pre_get_posts/
 * This will only affect the CPTs queries
 */
function posts_for_current_author_for_user_home($query)
{

    if (!is_admin() && !$query->is_main_query() && is_page('61') && in_array($query->get('post_type'), array('meetup', 'comunidad'))) {
        global $user_ID;
        $query->set('author', $user_ID);
        $query->set('post_status', array('publish', 'draft'));

        // order meetups by date DESC
        if ($query->get('post_type') == 'meetup') {
            $query->set('meta_key', 'fecha');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'DESC');
        }

        return;
    }
}
add_action('pre_get_posts', 'posts_for_current_author_for_user_home');

/* 
 * Frontend - Limit users to see only meetup the community owns. Used when showing a singe meetup community 
 * 
 * @link https://developer.wordpress.org/reference/hooks/pre_get_posts/
 * This will only affect the CPTs queries
 */
function limit_community_meetups_on_community_single($query)
{

    if (!is_admin() && !$query->is_main_query() && is_singular('comunidad') && in_array($query->get('post_type'), array('meetup'))) {

        global $post; //get community

        $query->set('meta_query', array(
            array(
                'key'     => 'comunidad',
                'compare' => '=',
                'value'   => $post->ID,
                'type'    => 'numeric',
            )
        ));

        // order meetups by date DESC
        $query->set('meta_key', 'fecha');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'DESC');

        return;
    }
}
add_action('pre_get_posts', 'limit_community_meetups_on_community_single');

/**
 * Frontend - Edit post with "pods gravity addon"
 * @link https://docs.pods.io/code-snippets/using-pods-gravity-forms-addon-to-edit-specific-id/
 * 
 * Override the item ID that is edited by the Gravity Form when using a Pods feed.
 *
 * @param int    $edit_id  Edit ID.
 * @param string $pod_name Pod name.
 * @param int    $form_id  GF Form ID.
 * @param array  $feed     GF Form feed array.
 * @param array  $form     GF Form array.
 * @param array  $options  Pods GF options.
 * @param Pods   $pod      Pods object.
 *
 * @return int The edit ID to use.
 */
function my_custom_pods_gf_edit_id($edit_id, $pod_name, $form_id, $feed, $form, $options, $pod)
{
    global $user_ID;

    // Check if the edit_id passed into the URL was set.
    if (!isset($_GET['cptid'])) {
        return $edit_id;
    }

    $author_id = get_post_field('post_author', $_GET['cptid']);

    // Only change the edit_id if this is for the form ID 1 (crear comunidad) or form ID 2 (crear meetup).
    if (1 !== (int) $form_id && 2 !== (int) $form_id) {
        return $edit_id;
    }

    // Check access rights, adjust this as needed.
    if (!is_user_logged_in() || !current_user_can('edit_posts') || $user_ID != $author_id) {
        return $edit_id;
    }

    // Force the edit_id to one from the URL.
    $edit_id = absint($_GET['cptid']);

    // Let's add the filter so we tell Pods to prepopulate the form with this item's data.
    add_filter('pods_gf_addon_prepopulate', 'my_custom_pods_gf_prepopulate', 10, 7);
    return $edit_id;
}
add_filter('pods_gf_addon_edit_id', 'my_custom_pods_gf_edit_id', 10, 7);

/**
 * Frontend - Edit post with "pods gravity addon" (populate)
 * Override whether to prepopulate the form with the item being edited by the Gravity Form when using a Pods feed.
 *
 * @param bool   $prepopulate Whether to prepopulate or not.
 * @param string $pod_name    Pod name.
 * @param int    $form_id     GF Form ID.
 * @param array  $feed        GF Form feed array.
 * @param array  $form        GF Form array.
 * @param array  $options     Pods GF options.
 * @param Pods   $pod         Pods object.
 *
 * @return Whether to prepopulate the form with data from the item being edited.
 */
function my_custom_pods_gf_prepopulate($prepopulate, $pod_name, $form_id, $feed, $form, $options, $pod)
{
    // We added this filter when checking if they can edit, so we can trust this filter context.
    // Always prepopulate the form with the item we are editing.
    // echo '<pre>'; var_dump($prepopulate); echo '</pre>';
    return true;
}

/*
 * Backend - Dont send email notifications when editing pods with gravity form
 * Also change email subject if is draft and editing for admin to acknowledge
 * 
 * @link https://docs.gravityforms.com/gform_pre_send_email/
 */
function cancel_admin_notifications_on_edit($email, $message_format, $notification, $entry)
{

    //if is editing we have a GET parameter called cptid, so if found, dont send notifications
    $source_url = rgar($entry, 'source_url');
    $has_cptid = strpos($source_url, 'cptid');

    $post_id = rgar($entry, 'post_id');
    $post_status = get_post_status($post_id);

    if ($has_cptid !== false && $post_status !== 'draft') {
        $email['abort_email'] = true;
    }

    //change email subject if is draft and user is editing
    if ($has_cptid !== false && $post_status === 'draft') {
        $email['subject'] = 'Comunidad/Meetup editado';
    }

    return $email;
}
add_filter('gform_pre_send_email', 'cancel_admin_notifications_on_edit', 10, 4);

/*
 * Frontend - Limit buttons on the rich text editor used by gravity
 * 
 * @link https://docs.gravityforms.com/gform_rich_text_editor_buttons/
 */
function limit_gravity_mce($mce_buttons)
{
    $mce_buttons = array('bold', 'italic', 'bullist', 'link', 'unlink');
    return $mce_buttons;
}
add_filter('gform_rich_text_editor_buttons', 'limit_gravity_mce', 10, 2);

/*
 * Frontend - Make all text pasted in gravity tinymce as text (delete HTML tags)
 * 
 * @link https://anythinggraphic.net/paste-as-text-by-default-in-wordpress
 */

function ag_tinymce_paste_as_text($init)
{

    if (!is_admin()) {
        $init['paste_as_text'] = true;
    }

    return $init;
}
add_filter('tiny_mce_before_init', 'ag_tinymce_paste_as_text');

/**
 * Frontend - Display button for editing communities
 */
function community_edit_link_shortcode($atts)
{
    global $post, $current_user;

    $author_id = get_post_field('post_author', $post->ID);

    if (!is_user_logged_in() || $current_user->ID != $author_id) {
        return '';
    }

    $delete_link = get_delete_post_link($post);

    $output = '<div class="wp-block-buttons">';
    $output .= '<!-- wp:button -->';
    $output .= '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://2140meetups.com/crear-comunidad/?cptid=' . $post->ID . '">Editar</a></div><!-- /wp:button -->';
    $output .= '<!-- /wp:button -->';
    $output .= '<!-- wp:button -->';
    $output .= '<div class="wp-block-button" style="padding-left: 10px;"><a class="wp-block-button__link wp-element-button" href="' . $delete_link . '" onclick="return confirm(`ADVERTENCIA\n\nSi borras la comunidad se borrarán todos los meetups relacionados.\n¿Quieres continuar?\nEsta acción no es reversible.`);">Eliminar</a></div>';
    $output .= '<!-- /wp:button -->';
    $output .= '</div>';

    return $output;
}
add_shortcode('community_edit_buttons', 'community_edit_link_shortcode');

/**
 * Frontend - Display button for editing meetups
 */
function meetup_edit_link_shortcode($atts)
{
    global $post, $current_user;

    $author_id = get_post_field('post_author', $post->ID);

    if (!is_user_logged_in() || $current_user->ID != $author_id) {
        return '';
    }

    $delete_link = get_delete_post_link($post);

    $output = '<div class="wp-block-buttons">';
    $output .= '<!-- wp:button -->';
    $output .= '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://2140meetups.com/crear-meetup/?cptid=' . $post->ID . '">Editar</a></div><!-- /wp:button -->';
    $output .= '<!-- /wp:button -->';
    $output .= '<!-- wp:button -->';
    $output .= '<div class="wp-block-button" style="padding-left: 10px;"><a class="wp-block-button__link wp-element-button" href="' . $delete_link . '" onclick="return confirm(`ADVERTENCIA\n\nEsta acción no es reversible.`);">Eliminar</a></div>';
    $output .= '<!-- /wp:button -->';
    $output .= '</div>';

    return $output;
}
add_shortcode('meetup_edit_buttons', 'meetup_edit_link_shortcode');

/**
 * Frontend - Display logout button with custom redirect
 */
function logout_button_shortcode($atts)
{

    return '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button logout_button" href="' . wp_logout_url('/') . '">Log out</a></div>';
}
add_shortcode('logout_button', 'logout_button_shortcode');

/**
 * Frontend - Redirect to frontend on delete community and meetup
 */
function wpse132196_redirect_after_trashing_get($query)
{
    if (array_key_exists('trashed', $_GET) && $_GET['trashed'] == '1') {
        if (!is_admin() && ('comunidad' == $query->query_vars['post_type'] || 'meetup' == $query->query_vars['post_type'])) {
            wp_redirect(site_url('/dashboard'));
            exit;
        }
    }
}
add_action('parse_request', 'wpse132196_redirect_after_trashing_get');


/**
 * Backend - Assign community generic thumbnail if is not uploaded by user on post creation
 * 
 * @link https://developer.wordpress.org/reference/hooks/save_post/
 */
function set_post_thumbnail_in_communities($post_id)
{

    $current_post_thumbnail = get_post_thumbnail_id($post_id);

    if (0 !== $current_post_thumbnail) {
        return;
    }

    set_post_thumbnail($post_id, 502);
}
add_action('save_post_comunidad', 'set_post_thumbnail_in_communities');

/**
 * Backend - Assign meetup generic thumbnail if is not uploaded by user on post creation
 * 
 * @link https://developer.wordpress.org/reference/hooks/save_post/
 */
function set_post_thumbnail_in_meetups($post_id)
{

    $current_post_thumbnail = get_post_thumbnail_id($post_id);

    if (0 !== $current_post_thumbnail) {
        return;
    }

    $community = get_post_meta($post_id, 'comunidad', true);
    $community_thumbnail_id = get_post_thumbnail_id($community);

    set_post_thumbnail($post_id, $community_thumbnail_id);
}
add_action('save_post_meetup', 'set_post_thumbnail_in_meetups');

/**
 * Backend - Auto approve (publish) meetups if user has one community previously published
 * 
 * @link https://developer.wordpress.org/reference/hooks/wp_insert_post/
 * @link https://developer.wordpress.org/reference/functions/wp_update_post/
 */
function auto_aprove_if_previous($post_id, $post, $update)
{

    // If this is a revision or is a post update, don't do anything
    if (wp_is_post_revision($post_id) || $update === true || $post->post_type != 'meetup')
        return;

    $args = array(
        'post_type'      => 'comunidad',
        'author'         => get_current_user_id(),
        'post_status'    => 'publish',
    );

    $wp_posts = get_posts($args);

    if (count($wp_posts)) {
        $update_args = array(
            'ID'           => $post_id,
            'post_status'  => 'publish',
        );

        // Update the post into the database
        wp_update_post($update_args);
    }
}
add_action('wp_insert_post', 'auto_aprove_if_previous', 10, 3);

/**
 * Backend - Create/update map pointer in /map/geo.json on post update (from draft to published)
 */
function create_map_pointer_geojson($post_id, $post, $update)
{

    // If this is a revision or not a community or not a delete, exit
    if (wp_is_post_revision($post_id) || $post->post_type != 'comunidad')
        return;

    // Are we deleting the post?
    if ($post->post_status === 'trash') {
        $action = 'delete';
        generate_new_geo_json_map($post_id, $action);

        return;
    }

    // Only add/update marker on post being published
    if ($post->post_status === 'publish') {

        // Generate GEOJSON for our map in home
        $action = 'update';
        $latitude = get_post_meta($post_id, 'lat', true);
        $longitude = get_post_meta($post_id, 'lon', true);
        $url = get_permalink($post_id);

        generate_new_geo_json_map($post_id, $action, $post->post_title, $url, $latitude, $longitude);
    }

    return;
}
add_action('wp_insert_post', 'create_map_pointer_geojson', 11, 3);

/**
 * Backend - Create/update btcmap area data in /btcmap/ on post update (from draft to published)
 */
function create_btcmap_area($post_id, $post, $update)
{

    // If this is a revision or not a community or not a delete, exit
    if (wp_is_post_revision($post_id) || $post->post_type != 'comunidad')
        return;

    // Are we deleting the post?
    if ($post->post_status === 'trash') {
        $action = 'delete';
        //generate_new_geo_json_map($post_id, $action);

        return;
    }

    // Only add/update btcmap area json on post being published
    if ($post->post_status === 'publish') {

        $author_id = $post->post_author;

        $data = array(
            "id"        => $post_id,
            "email"        => get_the_author_meta('email', $author_id),
            "telegram"    => get_post_meta($post_id, 'telegram', true),
            "imagen"    => get_the_post_thumbnail_url($post_id, 'full'),
            "nombre"    => $post->post_title,
            "ciudad"    => get_post_meta($post_id, 'ciudad', true),
            "pais"        => get_post_meta($post_id, 'pais', true)
        );

        generate_area_from_btcmap($data);
    }

    return;
}
add_action('wp_insert_post', 'create_btcmap_area', 12, 3);

/**
 * Backend - Create callback function that embeds our response in a WP_REST_Response
 * https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/#creating-endpoints
 */
function btcmap_get_endpoint_communities()
{
    // rest_ensure_response() wraps the data we want to return into a WP_REST_Response, and ensures it will be properly returned.
    return rest_ensure_response(btc_maps_endpoint());
}

/**
 * Backend - Create callback function that embeds our response in a WP_REST_Response
 * https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/#creating-endpoints
 */
function btcmap_get_endpoint_loop_communities()
{

    $args = array(
        'post_type'   => 'comunidad',
        'post_status' => 'publish',
        'numberposts' => 100,
    );

    $communities = get_posts($args);

    foreach ($communities as $community) {
        $author_id = $community->post_author;

        $data = array(
            "id"        => $community->ID,
            "email"        => get_the_author_meta('email', $author_id),
            "telegram"    => get_post_meta($community->ID, 'telegram', true),
            "imagen"    => get_the_post_thumbnail_url($community->ID, 'full'),
            "nombre"    => $community->post_title,
            "ciudad"    => get_post_meta($community->ID, 'ciudad', true),
            "pais"        => get_post_meta($community->ID, 'pais', true)
        );

        // Sleep for two seconds if not nominatim complains, zZzZZzzz....
        sleep(2);

        generate_area_from_btcmap($data);
    }

    // rest_ensure_response() wraps the data we want to return into a WP_REST_Response, and ensures it will be properly returned.
    return rest_ensure_response('Loop created OK');
}

/**
 * Backend - Function to register our routes for our example endpoint.
 */
function btcmap_register_routes()
{
    // register_rest_route() handles more arguments but we are going to stick to the basics for now.
    register_rest_route('btcmap/v1', '/communities', array(
        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
        'methods'  => WP_REST_Server::READABLE,
        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
        'callback' => 'btcmap_get_endpoint_communities',
    ));

    register_rest_route('btcmap/v1', '/loop', array(
        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
        'methods'  => WP_REST_Server::READABLE,
        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
        'callback' => 'btcmap_get_endpoint_loop_communities',
    ));
}

add_action('rest_api_init', 'btcmap_register_routes');

/*
 * Frontend - block all users but admin to access the backend
 * 
 * @link https://blog.hubspot.com/website/how-to-limit-wordpress-dashboard-access
 */
function blockusers_init()
{
    if (is_admin() && !current_user_can('administrator') && !(defined('DOING_AJAX') && DOING_AJAX)) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('init', 'blockusers_init');

/*
 * Backend - disable image sizes
 * 
 * @link https://perishablepress.com/disable-wordpress-generated-images/
 */
function shapeSpace_disable_thumbnail_images($sizes)
{
    unset($sizes['thumbnail']);     // disable thumbnail size
    unset($sizes['medium']);         // disable medium size
    unset($sizes['medium_large']);     // disable 768px size images
    //unset($sizes['large']); 		// disable 1024px size images
    //unset($sizes['1536x1536']); 	// disable 2x medium-large size
    unset($sizes['2048x2048']);     // disable 2x large size

    return $sizes;
}
add_action('intermediate_image_sizes_advanced', 'shapeSpace_disable_thumbnail_images');
add_filter('big_image_size_threshold', '__return_false');

/** 
 * Frontend - Removes empty paragraph tags from shortcodes in WordPress.
 * 
 * @link https://stackoverflow.com/questions/13510131/remove-empty-p-tags-from-wordpress-shortcodes-via-a-php-functon
 */
function tg_remove_empty_paragraph_tags_from_shortcodes_wordpress($content)
{
    $toFix = array(
        '<p>['    => '[',
        ']</p>'   => ']',
        ']<br />' => ']'
    );
    return strtr($content, $toFix);
}
add_filter('the_content', 'tg_remove_empty_paragraph_tags_from_shortcodes_wordpress');

/** 
 * Frontend - Redirect shortcode
 */
function redirect_shortcode($atts, $content)
{
    wp_enqueue_script('redirect-js', plugins_url('src/js/redirect.js', __FILE__), [], '1.0.0', true);

    if ($_GET["url"]) {
        $redirect = $_GET["url"];
    } else {
        $redirect = 'https://2140meetups.com';
    }
    $params = array(
        'url' => $redirect,
    );

    wp_localize_script('redirect-js', 'data', $params);

    return '<span id="timer"></span>';
}
add_shortcode('redirect', 'redirect_shortcode');

/** 
 * Backend - Trash meetups when community is trashed
 * 
 * @link https://developer.wordpress.org/reference/functions/wp_trash_post/
 */
add_action('trashed_post', 'trash_community_meetups', 10, 1);
function trash_community_meetups($post_id)
{

    $post = get_post($post_id);

    // For a specific post type
    if ('comunidad' !== $post->post_type) {
        return;
    }

    $meetups = get_posts(
        array(
            'post_type'        => 'meetup',
            'meta_key'         => 'comunidad',
            'meta_value'       => $post_id,
        )
    );

    foreach ($meetups as $meetup) {
        wp_trash_post($meetup->ID);
    }
}

/** 
 * Backend - Delete meetups when community is deleted
 * Need to specify post_status because if posts are already in trash they are not being queried
 * 
 * @link https://wordpress.org/support/article/post-status/
 * @link https://developer.wordpress.org/reference/functions/wp_delete_post/
 */
add_action('after_delete_post', 'delete_community_meetups', 10, 2);
function delete_community_meetups($post_id, $post)
{

    // For a specific post type
    if ('comunidad' !== $post->post_type) {
        return;
    }

    $meetups = get_posts(
        array(
            'post_type'        => 'meetup',
            'meta_key'         => 'comunidad',
            'meta_value'       => $post_id,
            'post_status'    => ['draft', 'publish', 'trash', 'pending', 'private', 'auto-draft'],
        )
    );

    foreach ($meetups as $meetup) {
        wp_delete_post($meetup->ID, true);
    }
}

/* 
 * Backend - Enqueue download calendar js file.
 */
function enqueue_calendar_js()
{
    wp_enqueue_script('calendar-js', plugins_url('src/js/calendar.js', __FILE__), array('jquery'), '1.0.0', true);

    wp_localize_script(
        'calendar-js',
        'data',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('download_calendar_nonce'),
        )
    );
}
add_action('wp_enqueue_scripts', 'enqueue_calendar_js');

/* 
 * Frontend - Add ICS file download to meetups.
 */
function download_meetup_ics_shortcode($atts)
{
    if (is_single() && get_post_type() == 'meetup') {

        $fecha = get_post_meta(get_the_ID(), 'fecha', true);
        $hora = get_post_meta(get_the_ID(), 'hora', true);
        $direccion = get_post_meta(get_the_ID(), 'direccion', true);
        $community_id = get_post_meta(get_the_ID(), 'comunidad', true);

        // Datetime functions
        $datetime = new DateTime($fecha . ' ' . $hora);
        $start_date = $datetime->format('Y-m-d H:i:s');
        $datetime->add(new DateInterval('PT1H'));
        $end_date = $datetime->format('Y-m-d H:i:s');

        // Display form
        $content = '<div style="margin:20px 0;">';
        $content .= '<form id="download-calendar-form" method="post">';
        $content .= '<input type="hidden" name="name" value="' . get_the_title() . '">';
        $content .= '<input type="hidden" name="starts_at" value="' . $start_date . '">';
        $content .= '<input type="hidden" name="ends_at" value="' . $end_date . '">';
        $content .= '<input type="hidden" name="address" value="' . $direccion . '">';
        $content .= '<input type="hidden" name="image" value="' . get_the_post_thumbnail_url() . '">';
        $content .= '<input type="hidden" name="community_id" value="' . $community_id . '">';
        $content .= '<input type="hidden" name="description" value="' . esc_url(get_permalink()) . '">';
        $content .= '<input type="submit" value="🗓 Agrega el meetup a tu calendario">';
        $content .= '</form>';
        $content .= '</div>';

        return $content;
    }
}
add_shortcode('download_meetup_ics', 'download_meetup_ics_shortcode');

/**
 * Ajax function to create and download ICS
 *
 */
function download_meetup_calendar_handler()
{

    // This is a secure process to validate if this request comes from a valid source.
    check_ajax_referer('download_calendar_nonce', 'security');

    // tell the browser it's going to be a calendar file
    header('Content-Type: text/calendar; charset=utf-8');

    // tell the browser we want to save it instead of displaying it
    header('Content-Disposition: attachment; filename="meetup.ics"');

    // Get community timezone
    $community_id  = $_POST['community_id'];
    $community_lat = get_post_meta($community_id, 'lat', true);
    $community_lon = get_post_meta($community_id, 'lon', true);

    $geoapify = 'https://api.geoapify.com/v1/geocode/reverse?lat=' . $community_lat . '&lon=' . $community_lon . '&format=json&apiKey=' . GEOAPIFY_API_KEY;
    $response = wp_remote_get($geoapify);
    $timezone = '';

    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        $timezone = $result['results'][0]['timezone']['name'];
    }

    // Create event
    $event = Event::create()
        ->name($_POST['name'])
        ->startsAt(new DateTime($_POST['starts_at'], new DateTimeZone($timezone)))
        ->endsAt(new DateTime($_POST['ends_at'], new DateTimeZone($timezone)))
        ->address($_POST['address'])
        ->image($_POST['image'])
        ->description($_POST['description']);

    // Create calendar and download
    $calendar = Calendar::create()->event($event);
    echo $calendar->get();

    wp_die();
}
add_action('wp_ajax_download_meetup_calendar', 'download_meetup_calendar_handler');
add_action('wp_ajax_nopriv_download_meetup_calendar', 'download_meetup_calendar_handler');
        
/* necesitamos funcion que al validar y almacenar meetup (ambas por seguridad), chequee que la comunidad que se envía pertenece al mismo autor */
