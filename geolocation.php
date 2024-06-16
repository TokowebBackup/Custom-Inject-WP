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
$ip = "103.139.10.11";
// $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
$radius = [
    [
        "key" => "Bandung",
        "lat" => "-6.921602417858295",
        "lng" => "107.60827369802438",
        "radius" => 30000
    ],
    [
        "key" => "Jakarta",
        "lat" => "-6.219109243545298",
        "lng" => "106.86234063435637",
        "radius" => 100000
    ],
    [
        "key" => "Bogor",
        "lat" => floatval(-6.545657535287815),
        "lng" => floatval(106.81448710843782),
        "radius" => 15000
    ],
    [
        "key" => "Tasikmalaya",
        "lat" => floatval(-7.330977788854294),
        "lng" => floatval(108.23413376452469),
        "radius" => 15000
    ],
    [
        "key" => "Garut",
        "lat" => floatval(-7.330977788854294),
        "lng" => floatval(108.23413376452469),
        "radius" => 15000
    ]
];

// Mendapatkan data geolokasi
$location_data = get_geolocation($ip);

if ($location_data['status'] == 'success') {
    $messageRadius;
    $latitude = $location_data['lat'];
    $longitude = $location_data['lon'];
    $map_shortcode = "[leaflet-map lat={$latitude} lng={$longitude} zoom=10 height='450' zoomcontrol !show_scale]" . "<br/>";
    // Loop melalui array $radius untuk menambahkan leaflet-circle dan leaflet-marker shortcodes
    foreach ($radius as $area) {
        if($area['lat'] <= $location_data['lat'] || $area['key'] === $location_data['city']) {
            $messageRadius = "Mendekati atau Dalam Radius yang mendekati.";
            $map_shortcode .= "[leaflet-circle lat={$area['lat']} lng={$area['lng']} radius={$area['radius']} draggable]" . 
            "[leaflet-marker lat={$area['lat']} lng={$area['lng']} draggable svg background='#a23a93' iconClass='fa-solid fa-building-circle-check' color='#fff']MyRepublic Area {$area['key']}[/leaflet-marker]" . "<br/>";     
        }
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
  <?php if($messageRadius !== "" || $messageRadius !== NULL):?>  
    <div class="alert alert-info" role="alert">
        <?=$messageRadius?>
    </div>
    <?php endif;?>
 </li>
  <li class="list-group-item">
  <?=do_shortcode($map_shortcode);?>
  </li>
</ul>
