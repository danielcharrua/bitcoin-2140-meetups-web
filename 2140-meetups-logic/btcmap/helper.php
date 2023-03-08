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
		"id"	=> "2140_meetups_" . $community["id"],
		"tags"	=> array(
			"contact:email" 	=> $community["email"],
			"contact:telegram" 	=> $community["telegram"],
			"continent"			=> "???",
			"icon:square"		=> $community["imagen"],
			"name"				=> $community["nombre"],
			"type"				=> "community"
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
	// Create the URL to make the request to get the area
	$URL_NOMINATIM = sprintf(NOMINATIM_OPENSTREETMAP, $city, $country);
	$nominatim_object = get_area_geo_json($URL_NOMINATIM);
	// Create the URL to make the request to get the city population
	$URL_CITY_NINJA = sprintf(CITY_NINJA, $city);
    $city_population = get_city_population($URL_CITY_NINJA);
	// Create an array to merge with the nominatim data
	$population_data = array(
		"population"		=> $city_population,
		// The city population data request time
		"population:date"	=> date("Y-m-d")
	);
	return array_merge($nominatim_object, $population_data);
}

/**
 * Prepare the area request for nominatim
 * @param $url: The request url
 */
function get_area_geo_json($url) 
{
	$response = wp_remote_get( $url );
	$body = wp_remote_retrieve_body( $response );
	$parsed_nominatim_result = json_decode($body);

	// Error control if we get the right nominatim response
	if (!empty($parsed_nominatim_result))
	{
		$nominatim_object = $parsed_nominatim_result[0];
		return array(
			"geo_json" => $nominatim_object->geojson,
			// https://wiki.openstreetmap.org/wiki/Bounding_Box
			"box:south" 	=> $nominatim_object->boundingbox[0],
			"box:north" 	=> $nominatim_object->boundingbox[1],
			"box:west" 		=> $nominatim_object->boundingbox[2],
			"box:east" 		=> $nominatim_object->boundingbox[3]
		);
	} else 
	{
		return array(
			"geo_json" => array(),
			"box:south" 	=> 0,
			"box:north" 	=> 0,
			"box:west" 		=> 0,
			"box:east" 		=> 0
		);
	}
}

/**
 * Get the community city population
 * @param $url: Custom API ninja url
 */
function get_city_population($url) 
{
	$args = array(
		'headers' => array(
				'X-Api-Key' => NINJA_API_KEY,
			)
	);
	
	$response = wp_remote_get( $url, $args );
	$body = wp_remote_retrieve_body( $response );
	$parsed_result = json_decode($body);
		
	// Check if get the requested data
	if (!empty($parsed_result))
	{
		return $parsed_result[0]->population;
	}
	
	return 0;
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
