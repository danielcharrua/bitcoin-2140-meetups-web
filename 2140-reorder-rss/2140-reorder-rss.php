<?php
/**
 * Plugin Name: 2140 Reorder meetups RSS
 * Plugin URI: https://2140meetups.com
 * Description: Ordena los resultados meetups en el bloque RSS de Gutenberg en WordPress.
 * Version: 0.1.0
 * Author: danielpcostas
 * Author URI: https://2140meetups.com
 */

function b2140_meetups_reorder_feed( $feed, $url ){
    if ($url === 'https://2140meetups.com/feed/?post_type=meetup'){
    	$feed->enable_order_by_date(false);
    }
}
add_action( 'wp_feed_options', 'b2140_meetups_reorder_feed', 10, 2 );