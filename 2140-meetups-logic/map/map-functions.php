<?php

// Operation types to interact with the JSON file
const UPDATE = 'update';
const DELETE = 'delete';

const PIN_COLOR = "#FA402B";
const PIN_SIZE = "medium";

//############## PIN OBJECT CREATION
//-------------------------------------

function create_pin_style($_id, $name, $url)
{
    return array(
        "id" => $_id,
        "marker-color" => PIN_COLOR,
        "fill" => PIN_COLOR,
        "stroke" => PIN_COLOR,
        "marker-size" => PIN_SIZE,
        "name" => $name,
        "url" => $url,
    );
}

function create_pin_coordinates($latitude, $longitude)
{
    return array(
        "type" => "Point",
        "coordinates" => array($longitude, $latitude),
    );
}

//############## MAP RELATED OPERATIONS
//-------------------------------------

/**
 * The pin (meetup) does not exist in the geo.json file. Add new point to visualise
 */
function add_new_point_in_map($_id, $name, $url, $latitude, $longitude)
{
    $new_point = array(
        "type" => "Feature",
        "properties" => create_pin_style($_id, $name, $url),
        "geometry" => create_pin_coordinates($latitude, $longitude),
    );

    $map = get_map_content();
    array_push($map["features"], $new_point);
    $json_map = json_encode($map);
    update_leaflet_map($json_map);
}

/**
 * The pin already exist in the map so, we need to UPDATE the pin (meetups) properties
 */
function update_map_point($_id, $name, $url, $latitude, $longitude)
{
    $pin_to_edit = find_pin($_id);
    $pin_content = $pin_to_edit["content"];
    $pin_index = $pin_to_edit["index"];
    // Edit pin values
    $pin_content["properties"]["name"] = $name;
    $pin_content["properties"]["url"] = $url;
    // Assuming 0 index is latitude
    $pin_content["geometry"]["coordinates"][0] = $longitude;
    // Assuming 1 index is longitude
    $pin_content["geometry"]["coordinates"][1] = $latitude;
    edit_and_save_map($pin_content, $pin_index);
}

/**
 * The meetup already does not exist so, delete the pin from the geo.json file
 */
function remove_map_point($_id)
{
    $map = delete_pin_index($_id);
    $json_map = json_encode($map);
    update_leaflet_map($json_map);
}

//!!!! ############## MAIN FUNCTION TO INTERACT WITH THE APPLICATION GEO.JSON FILE
//---------------------------------------------------------------------------

/**
 * Update the new JSON file depending of action type
 * @param int $id Unique identificator of the meetup
 * @param string $action UPDATE | DELETE
 * @param string $latitude
 * @param string $longitude
 * @return null
 */
function generate_new_geo_json_map($_id, $action, $name = null, $url = null, $latitude = null, $longitude = null)
{
    if ($action == UPDATE) {
        $meetup_pin = find_pin($_id);

        if ($meetup_pin == null) {
            add_new_point_in_map($_id, $name, $url, $latitude, $longitude);
        } else {
            update_map_point($_id, $name, $url, $latitude, $longitude);
        }
    } else {
        remove_map_point($_id);
    }
}
