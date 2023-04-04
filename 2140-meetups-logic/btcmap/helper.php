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
function request_remote_data($city, $country)
{
	// Delete tildes to avoid empty result
	$city_without_tilde = delete_tilde($city);
	// Adapt the spaces to not break the GET request
	$formatted_city = str_replace(' ', '%20', $city_without_tilde);
	// Delete tildes to avoid empty result
	$country_without_tilde = delete_tilde($country);
	// Adapt the spaces to not break the GET request
	$formatted_country = str_replace(' ', '%20', $country_without_tilde);

	// Get the continent and the osm_id
	$URL_NOMINATIM = sprintf(NOMINATIM_OPENSTREETMAP, $city, $country);
	$continent_object = get_continent_of_city($URL_NOMINATIM);
	
	// Once we have the osm_id, request area of the city
	$geo_json_object = get_city_area($continent_object["osm_id"]);
	// We do not need anymore that field
	unset($continent_object["osm_id"]);

	// Fetch the city population
	$URL_CITY_NINJA = sprintf(CITY_NINJA, $city);
    $city_population = get_city_population($URL_CITY_NINJA);
	// Add city population data 
	$population_data = array(
		"population"		=> $city_population,
		// The city population data request time
		"population:date"	=> date("Y-m-d")
	);
	return array_merge($continent_object, $population_data, $geo_json_object);
}

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

function get_city_area($osm_id)
{
	$get_url = sprintf(POLYGONS_OPENSTREETMAP, $osm_id);
	$post_url = sprintf(POLYGONS_OPENSTREETMAP_MAP_GENERATION, $osm_id);

	// Do post request to generate area data
	$response = wp_remote_post( $post_url, array(
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
		)
	);
	
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		//echo "Something went wrong: $error_message";
	} else {
		//echo 'Response:<pre>';
		//print_r( $response );
		//echo '</pre>';
	}

	$parsed_area_result = make_get_request($get_url);
	return array(
		"geojson" => $parsed_area_result
	);
}

/**
 * Prepare the area request for nominatim
 * @param $url: The request url
 */
function get_continent_of_city($url) 
{

	// Execute the request
	$parsed_nominatim_result = make_get_request($url);
	$osm_id = null;

	// Error control if we get the right nominatim response
	if (
		!empty($parsed_nominatim_result) && 
		array_key_exists(0, $parsed_nominatim_result) &&
		property_exists($parsed_nominatim_result[0], "address") &&
		property_exists($parsed_nominatim_result[0]->address, "country_code")
	)
	{
		$country_code = $parsed_nominatim_result[0]->address->country_code;
		$osm_id = $parsed_nominatim_result[0]->osm_id;

		$url = sprintf(COUNTRY_CODE, strtoupper($country_code));
		// Execute the request
		$parsed_nominatim_result = make_get_request($url);

		if (
			!empty($parsed_nominatim_result) && 
			array_key_exists(0, $parsed_nominatim_result) &&
			property_exists($parsed_nominatim_result[0], "continent")
		) {
			return array(
				"continent" => $parsed_nominatim_result[0]->continent,
				"osm_id"	=> $osm_id
			);
		}
	}
	return array(
		"continent" => "",
		"osm_id"	=> $osm_id
	);
}

/**
 * Get the community city population
 * @param $url: Custom API ninja url
 */
function get_city_population($url) 
{
	// Define the headers
	$args = array(
		'headers' => array(
				'X-Api-Key' => NINJA_API_KEY,
			)
	);

	$parsed_result = make_get_request($url, $args);
		
	// Check if get the requested data
	if (
		!empty($parsed_result) &&
		!array_key_exists("error", $parsed_result) &&
		array_key_exists(0, $parsed_result) &&
		property_exists($parsed_result[0], "population")
	)
	{
		return $parsed_result[0]->population;
	}
	return 0;
}

/**
 * Some API endpoints does not understand tildes
 * @param chain: The word that we want to edit 
 */
function delete_tilde($chain) 
{
	// We encode the string in utf8 format in case we get errors
	//$chain = utf8_encode($chain);

	// Now we replace the letters
	$chain = str_replace(
		array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'),
		array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'),
		$chain
	);

	$chain = str_replace(
		array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'),
		array('e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'),
		$chain );

	$chain = str_replace(
		array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'),
		array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'),
		$chain );

	$chain = str_replace(
		array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'),
		array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'),
		$chain );

	$chain = str_replace(
		array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'),
		array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'),
		$chain );

	$chain = str_replace(
		array('ñ', 'Ñ', 'ç', 'Ç'),
		array('n', 'N', 'c', 'C'),
		$chain
	);

	return $chain;
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
 * Once we have all the community files created, get the file to serve BTCMap
 */
function get_community_file($file_name)
{
	$community_json = file_get_contents(BTCMAP_FOLDER . $file_name);
	// Decode the JSON file
	$community = json_decode($community_json, true);
	return $community;
}
