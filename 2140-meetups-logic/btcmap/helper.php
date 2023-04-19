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
	$location = encode_community_location($city, $country);

	$URL_NOMINATIM = sprintf(NOMINATIM_OPENSTREETMAP, $location["city"], $location["country"]);
	$community_metadata = get_community_metadata($URL_NOMINATIM, $city);
	
	// Once we have the osm_id, request area of the city
	// IMPORTANT step that one because the first query to nominatim will be using city and country, 
	// After that, all the queries will use the osm_id not city name
	$geo_json_polygon = get_city_area($community_metadata["osm_id"]);

	$remote_data = array_merge($community_metadata, $geo_json_polygon);

	return $remote_data;
}

/**
 * Prepare the area request for nominatim
 * @param $url: The request url
 */
function get_community_metadata($url, $city) 
{
	// Execute the request
	$location_metadata = make_get_request($url);
	$osm_id = null;
	
	$nominatim_key = has_default_index($city);

	// Error control if we get the right nominatim response
	if (
		!empty($location_metadata) && 
		array_key_exists($nominatim_key, $location_metadata) &&
		property_exists($location_metadata[$nominatim_key], "address") &&
		property_exists($location_metadata[$nominatim_key]->address, "country_code"))
	{
		// Important variable for community area
		$osm_id = $location_metadata[$nominatim_key]->osm_id;

		$country_code = $location_metadata[$nominatim_key]->address->country_code;
		$url = sprintf(COUNTRY_CODE, strtoupper($country_code));
		// Execute the request
		$parsed_nominatim_result = make_get_request($url);
		
		$city = get_city($location_metadata[$nominatim_key]->address);
		
		$population_date = property_exists($location_metadata[$nominatim_key]->extratags, 'population:date') ?
			$location_metadata[$nominatim_key]->extratags->{'population:date'} : 
			'na';
		
		$nominatim_object = array(
			"osm_id"	 		=> $osm_id,
			"population"		=> $location_metadata[$nominatim_key]->extratags->population,
			"population:date"	=> $population_date,
			// Extra data
			"address"	 		=> $city . ", " . $location_metadata[$nominatim_key]->address->country,
		);
			
		if (
			!empty($parsed_nominatim_result) && 
			array_key_exists(0, $parsed_nominatim_result) &&
			property_exists($parsed_nominatim_result[0], "continent")) 
		{
			$nominatim_object["continent"] = $parsed_nominatim_result[$nominatim_key]->continent;
		}
		
		return $nominatim_object;
	}
	return array(
		"osm_id"	=> $osm_id,
		"continent" => "",
		"address"	=> ""
	);
}

/**
 * Not all the request has just one element. Some has more than one and are not in index 0
 * @param $city
 */
function has_default_index($city)
{
	if ($city === "Retamar")
	{
		return 2;
	}
	return 0;
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

/**
 * In nominatim not all the addresses has city. They might have instead village or town
 * @param $address: The object that has the location metadata
 */
function get_city($address)
{
	if (property_exists($address, "city"))
	{
		return $address->city;
	}
	else if (property_exists($address, "village"))
	{
		return $address->village;
	} 
	else if (property_exists($address, "town"))
	{
		return $address->town;
	}
	return "";
}

/**
 * If we have composed location format spaces and tildes in case it has
 * @param $city
 * @param $country
 */
function encode_community_location($city, $country)
{
	//print_r("DB => City: ". $city . ", Country: " . $country . "\n");
	// Delete tildes to avoid empty result
	$city_without_tilde = delete_tilde($city);
	// Adapt the spaces to avoid empty result
	$formatted_city = str_replace(' ', '%20', $city_without_tilde);
	// Delete tildes to avoid empty result
	$country_without_tilde = delete_tilde($country);
	// Adapt the spaces to avoid empty result
	$formatted_country = str_replace(' ', '%20', $country_without_tilde);
	
	return array(
		"city"		=> $formatted_city,
		"country"	=> $formatted_country
	);
}

// #######################################################
// ############### HELPER FUNCTIONS ######################
// #######################################################

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