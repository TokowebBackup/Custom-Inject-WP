<?php
function get_geolocation($ip) {
    $url = "http://ip-api.com/json/{$ip}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Mendapatkan IP pengunjung
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
$radius = [
    [
        "key" => "bandung",
        "lat" => "-6.921602417858295",
        "lng" => "107.60827369802438",
        "radius" => 17500
    ],
    [
        "key" => "jakarta",
        "lat" => "-6.219109243545298",
        "lng" => "106.86234063435637",
        "radius" => 20000
    ],
    [
        "key" => "bogor",
        "lat" => "106.81448710843782",
        "lng" => "-6.545657535287815",
        "radius" => 15000
    ],
    [
        "key" => "tasikmalaya",
        "lat" => "108.23413376452469",
        "lng" => "-7.330977788854294",
        "radius" => 15000
    ],
    [
        "key" => "garut",
        "lat" => "108.23413376452469",
        "lng" => "-7.330977788854294",
        "radius" => 15000
    ]
];

// Mendapatkan data geolokasi
$location_data = get_geolocation($ip);

if ($location_data['status'] == 'success') {
    $latitude = $location_data['lat'];
    $longitude = $location_data['lon'];
    $map_shortcode = "[leaflet-map lat={$latitude} lng={$longitude} zoom=8.2 height='450' zoomcontrol !show_scale]" . "<br/>";

    // Loop melalui array $radius untuk menambahkan leaflet-circle dan leaflet-marker shortcodes
    foreach ($radius as $area) {
        $map_shortcode .= "[leaflet-circle lat={$area['lat']} lng={$area['lng']} radius={$area['radius']} draggable]" . 
                          "[leaflet-marker lat={$area['lat']} lng={$area['lng']} draggable svg background='#a23a93' iconClass='fa-solid fa-building-circle-check' color='#fff']MyRepublic Area {$area['key']}[/leaflet-marker]" . "<br/>";
    }

    $map_shortcode .= "[leaflet-scale position=topright]";
} else {
    echo "Error: " . $location_data['message'] . "<br>";
}
?>

<ul class="list-group" style="list-style: none;">
  <li class="list-group-item">Ip Address : <?=$ip?></li>
  <li class="list-group-item"> <img src="https://flagsapi.com/<?=$location_data['countryCode']?>/shiny/64.png"> </li>
  <li class="list-group-item">Lokasi Kamu : <?=$location_data['city']?> - <?=$location_data['regionName']?></li>
  <li class="list-group-item">
    <?=do_shortcode($map_shortcode);?>
  </li>
</ul>
