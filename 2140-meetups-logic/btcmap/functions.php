<?php

/**
 * Create or update the btc map area JSON
 * @param int $_id Unique identificator of the meetup
 * @param string $country
 * @param string $city
 * @return null
 */
function generate_area_from_btcmap($data)
{
    // Set the file name
    $file_name = "btc_map_area_" . $data["id"] . ".json";
    // Extract the info from different APIs
    $remote_data = request_remote_data($data["ciudad"], $data["pais"]);
    // Extract from local data the missing attributes
    $local_data = extract_local_data($data);
    $btc_maps_community = merge_remote_and_local_data($remote_data, $local_data);
    // Before create the JSON file, clear up some attributes
    unset($btc_maps_community["tags"]["osm_id"]);

    // Decode the JSON file
    $btc_maps_json = json_encode($btc_maps_community, JSON_UNESCAPED_SLASHES);
    // Create or update the file
    update_btc_map_area_file($file_name, $btc_maps_json);
}

/**
 * Endpoint to fetch all communities
 */
function btc_maps_endpoint()
{
    // Ignore the first two indexes because are the actual folder and the parent one
    $btc_maps_library = array_slice(scandir(BTCMAP_FOLDER), 2);
    $communities = array();
    foreach ($btc_maps_library as $file_name) 
    {
        $community = get_community_file($file_name);
        array_push($communities, $community);
    }
    
    return $communities;
}
