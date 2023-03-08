<?php

// ################## WRITE DATE IN THE GEO.JSON FILE
// ----------------------------------------------------

/**
 * Reads the geo.json file content and return a populated array with map points
 */
function get_map_content()
{
    // Read the JSON file 
    $geo_json = file_get_contents(LEAFLET_FILE);
    // Decode the JSON file
    $map = json_decode($geo_json, true);
    // Display data
    return $map;
}

/**
 * Once we have ready map json, overwrite the actual geo.json
 */
function update_leaflet_map($json_map)
{
    file_put_contents(LEAFLET_FILE, $json_map);
}

/**
 * Construct the map object
 */
function edit_and_save_map($pin_content, $pin_index)
{
    $map = get_map_content();
    $map["features"][$pin_index] = $pin_content;
    update_leaflet_map(json_encode($map));
}

// ################## SEARCH OPERATIONS IN GEO.JSON FILE
// ----------------------------------------------------

/**
 * Find the pin object in the map (geo.json)
 */
function find_pin($id_to_find)
{
    $map = get_map_content();

    foreach ($map["features"] as $key => $pin) {
        $id = $pin["properties"]["id"];
        if ($id === $id_to_find) {
            return array(
                "content" => $pin,
                "index" => $key
            );
        }
    }
    return null;
}

/**
 * Find the pin in the map (geo.json) list and if it exists, delete from the map
 */
function delete_pin_index($id_to_delete)
{
    $map = get_map_content();
    $index_to_delete = null;

    foreach ($map["features"] as $key => $pin) {
        $id = $pin["properties"]["id"];
        if ($id === $id_to_delete) {
            $index_to_delete = $key;
            break;
        }
    }
    // If we find the index, delete the pin object
    if ($index_to_delete != null) {
        unset($map["features"][$index_to_delete]);
        // reordenar index array, sino rompe el json
        $map["features"] = array_values($map["features"]);
    }
    return $map;
}
