<?php

/**
 * All the important data in btc maps area has to go in tags property
 * @param $remote_data: Nominatim area and api ninja population
 * @param $local_data: Complete the tags field for the integration
 */
function merge_remote_and_local_data($remote_data, $local_data)
{
	$local_data["tags"] = array_merge($remote_data, $local_data["tags"]);
	return $local_data;
}

/**
 * Integration Complementary data
 * @param: The local row of the community
 */
function extract_local_data($community)
{
	return array(
		"id"	=> $community["id_btcmap"],
		"tags"	=> array(
			"contact:email" 	=> $community["email"],
			"contact:telegram" 	=> $community["telegram"],
			"icon:square"		=> $community["imagen"],
			"name"				=> $community["nombre"],
			"type"				=> "community",
			"organization"		=> "2140-meetups"
		)
	);
}

/**
 * Add remote content of the community to serve to btcmap request
 * @param: city
 * @param: country
 * return array
 */
function request_remote_data($osm_id)
{
	$URL_NOMINATIM = sprintf(NOMINATIM_OPENSTREETMAP, $osm_id);
	$community_metadata = get_community_metadata($URL_NOMINATIM, $osm_id);

	$geo_json_polygon = get_city_area($osm_id);

	$remote_data = array_merge($community_metadata, $geo_json_polygon);

	return $remote_data;
}

/**
 * Get the city population and continent
 * @param $url: The request url
 */
function get_community_metadata($url, $osm_id) 
{
	// Execute the request
	$location_metadata = make_get_request($url);

	// Error control if we get the right nominatim response
	if (
		!empty($location_metadata) && 
		property_exists($location_metadata, "country_code") &&
		property_exists($location_metadata, "extratags"))
	{
		// Might not have the population date
		$population_date = property_exists($location_metadata->extratags, "population:date") ? 
			$location_metadata->extratags->{'population:date'} 
			: 'na';

		$nominatim_object = array(
			"population"		=> $location_metadata->extratags->population,
			"population:date"	=> $population_date,
			"continent" 	    => "",
		);

		$country_code = $location_metadata->country_code;
		$url = sprintf(COUNTRY_CODE, strtoupper($country_code));

		// Execute the request
		$parsed_nominatim_result = make_get_request($url);
			
		if (
			!empty($parsed_nominatim_result) && 
			array_key_exists(0, $parsed_nominatim_result) &&
			property_exists($parsed_nominatim_result[0], "continent")) 
		{
			$nominatim_object["continent"] = $parsed_nominatim_result[0]->continent;
		}
		
		return $nominatim_object;
	}
	return array(
		"continent" 	=> "",
		"population"	=> ""
	);
}

/**
 * OpenStreetMap polygons are quite sharp, we want a bit more extense the area
 * @param $url: The requested API endpoint
 */
function get_city_area($osm_id)
{
	// Generate the area that we want with a POST request
	$POST_POLYGONS = sprintf(POLYGONS_OPENSTREETMAP_MAP_GENERATION, $osm_id);
	// Create the body for the POST request
	$body = array(
		'method'      => 'POST',
		'timeout'     => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking'    => true,
		'headers'     => array(),
		'body'        => array(
			'x' => '0.020000',
			'y' => '0.005000',
			'z' => '0.005000'
		),
	);
	// We do not care the response because we just want that it would be avaible that area
	// AIM: Create the need it area JSON file
	make_post_request($POST_POLYGONS, $body);

	// Once the JSON of area is generated, request it
	$GET_POLYGONS = sprintf(POLYGONS_OPENSTREETMAP, $osm_id);
	$parsed_area_result = make_get_request($GET_POLYGONS);

	return array(
		"geojson" => $parsed_area_result
	);
}

// #######################################################
// ############### HELPER FUNCTIONS ######################
// #######################################################

/**
 * Create a generic utility to make remote calls
 * @param $url: The endpoint
 * @param $header: Optional parameter
 * return array
 */
function make_get_request($url, $headers = array())
{
	if (!empty($headers))
	{
		$response = wp_remote_get( $url, $headers );
	} 
	else
	{
		$response = wp_remote_get( $url );
	}
	// Extract the body from the response
	$body = wp_remote_retrieve_body($response);
	return json_decode($body);
}

/**
 * A generic POST request
 * @param $url: API endpoint
 * @param $body: Request body
 */
function make_post_request($url, $body)
{
	$response = wp_remote_post($url, $body);
	
	if ( is_wp_error( $response ) ) 
	{
		$error_message = $response->get_error_message();
		//echo "Something went wrong: $error_message";
	}
}

/**
 * Create or update the area JSON file
 * @param $filename
 * @param $json_area: The content of the file
 */
function update_btc_map_area_file($file_name, $json_area)
{
	file_put_contents(BTCMAP_FOLDER . $file_name, $json_area);
}

/**
 * Once we have all the community files created, get the file to serve BTCMaps
 */
function get_community_file($file_name)
{
	$community_json = file_get_contents(BTCMAP_FOLDER . $file_name);
	// Decode the JSON file
	$community = json_decode($community_json, true);
	return $community;
}