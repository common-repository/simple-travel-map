<?php
/**
 Plugin Name: Simple Travel Map
 Plugin URI: http://thomashoefter.com/
 Version: 0.1
 Description: Scans your posts for a custom field and creates a travel map using the Google Geocharts API from the locations found. The map can be displayed in any page or post with a shortcode.
 Author: Thomas Hoefter
 Author URI: http://thomashoefter.com/
*/

function simple_travel_map_deactivate() {
	delete_option('simple-travel-map-locations');	
	delete_option('simple-travel-map-settings');	
}
register_deactivation_hook( __FILE__, 'simple_travel_map_deactivate' );

function simple_travel_map_add_menu_pages() {
	add_options_page('Simple Travel Map', 'Simple Travel Map', 'manage_options', 'simple-travel-map-options', 'simple_travel_map_settings_page');
}
add_action('admin_menu', 'simple_travel_map_add_menu_pages');

function simple_travel_map_set_defaults() {
	$settings = array(
		"color_marker" => "#333333",
		"color_region" => "#FFFFFF",
		"color_text" => "#333333",
		"size_marker" => "4",
		"map_width" => "auto",
		"post_id" => "",
	);
	update_option("simple-travel-map-settings", $settings);
	return $settings;
}

function simple_travel_map_settings_page() {

	$settings = get_option("simple-travel-map-settings");
	
	$map_locations = get_option("simple-travel-map-locations");
	
	if(empty($settings)) {
		$settings = simple_travel_map_set_defaults();
	}
	
	if($_POST["save_options"]) {
		$settings = array(
			"color_marker" => $_POST["color_marker"],
			"color_region" => $_POST["color_region"],
			"color_text" => $_POST["color_text"],
			"size_marker" => $_POST["size_marker"],
			"map_width" => $_POST["map_width"],
			"post_id" => $_POST["post_id"],
		);
		update_option("simple-travel-map-settings", $settings);
	}
	
	if($_POST["index_locations"]) {
		if(empty($_POST["meta_value"])) {
			echo '<div class="error"><p>Please enter a custom field value to look for.</p></div>';	
		} else {
			$metas = simple_travel_map_get_meta_values($_POST["meta_value"]);
			$loc_results = "";
			
			if($_POST["reindex"] == 1) {
				$map_locations = array();
			}
			
			foreach ( $metas as $metav ) {

				if ( $metav->post_id ) {
					$pid = $metav->post_id;
					if(empty($map_locations[$pid])) {
						$permalink = get_permalink( $metav->post_id );
						$metaval = $metav->meta_value;
				
						$loc = simple_travel_map_save_lookup_lat_long($metaval); // get lat long
						$ll = array();
						if($loc !== false && !empty($loc["latitude"]) && !empty($loc["longitude"])) {
							$ll = array("lat" => $loc["latitude"], "long" => $loc["longitude"]);
							
							$thumb = wp_get_attachment_image_src( get_post_thumbnail_id($metav->post_id), 'medium' );
							$url = $thumb['0'];
		
							$map_locations[$pid] = array("location" => $metaval, "link" => $permalink, "thumbnail" => $url, "coordinates" => $ll);	
						
							$loc_results .= "Indexed: $metaval for ID $pid<br/>";
						} else {
							$loc_results .= "Location not found: $metaval for ID $pid - ".$loc["status"]."<br/>";
						}

					}
				}
			}	
			
			update_option("simple-travel-map-locations", $map_locations);	
			echo '<div class="updated"><p>'.$loc_results.'</p></div>';		
		}

	}	

?>

	<div class="wrap">
	<form method="post" name="stm_options">		
	
	<h2><?php _e("Simple Travel Map Settings","simpletravelmap") ?></h2>
	
	<p>
		<strong>Short Instructions</strong> - <a href="http://wpscoop.com/simple-wordpress-travel-map-plugin" target="_blank">For more details and help please have a look at this post!</a>
		<ol>
			<li>Add a <strong>custom field</strong> to posts you want to display on the map. Default field name is "location" (changeable below) and value is the <strong>city you want to mark</strong>, e.g. "Paris, France" or "New York City".</li>
			<li>Press the <strong>Index Post Locations</strong> button below. This looks at all your posts with the custom field, finds the exact location coordinates for them and makes them ready for the map.</li>
			<li>Parse the <strong>shortcode</strong> below into the post or page where you want to display your travel map.</li>
		</ol>
	</p>
	
	<?php if(!empty($map_locations) && is_array($map_locations)) { $mcount = count($map_locations); ?>
	<div style="background: #fff;border: 1px #ddd solid;padding: 5px;margin: 5px;">
		<p><?php echo $mcount; ?> locations indexed and ready to be displayed on your travel map! Once you add more just press the "Index" button again.</p>
		<p>Use the <strong>shortcode <code>[simple-travel-map]</code></strong> in a post or page to display the map.<br/>Alternatively you can use the <strong>code <code><?php echo htmlspecialchars('<?php simple_travel_map_shortcode(); ?>'); ?></code></strong> in your theme files.</p>
	</div>
	<?php } ?>
	
	<div style="background: #fff;border: 1px #ddd solid;padding: 5px;margin: 5px;">
	
		<p>Look for this <strong>custom field</strong> value: <input class="regular-text" type="text" name="meta_value" id="meta_value" value="location" /></p>
				
		<p>
			<input class="button-primary" type="submit" name="index_locations" value="<?php _e("Index Post Locations","simpletravelmap") ?>" />
			<input type="checkbox" name="reindex" value="1" id="reindex"> <label for="reindex">Clear current index (re-index all posts)</label>
		</p>	
	</div>
	
	<h3>Map Display Settings</h3>

		<table class="form-table">
			<tbody>			

				<tr>
					<th scope="row"><label for="map_width">Map Width (in pixels)</label></th>
					<td>
						<input class="regular-text" type="text" name="map_width" id="map_width" value="<?php echo $settings["map_width"]; ?>" />
						<br><em>e.g. '600' for a 600px wide map. 'auto' uses the width of the container the map is in.</em>
					</td>	
				</tr>				
			
				<tr>
					<th scope="row"><label for="color_text">Text Color</label></th>
					<td>
						<input class="regular-text" type="text" name="color_text" id="color_text" value="<?php echo $settings["color_text"]; ?>" />
					</td>	
				</tr>				
			
				<tr>
					<th scope="row"><label for="color_region">Region Background Color</label></th>
					<td>
						<input class="regular-text" type="text" name="color_region" id="color_region" value="<?php echo $settings["color_region"]; ?>" />
					</td>	
				</tr>			
			
				<tr>
					<th scope="row"><label for="color_marker">Marker Color</label></th>
					<td>
						<input class="regular-text" type="text" name="color_marker" id="color_marker" value="<?php echo $settings["color_marker"]; ?>" />
					</td>	
				</tr>	

				<tr>
					<th scope="row"><label for="size_marker">Marker Size</label></th>
					<td>
						<input class="regular-text" type="text" name="size_marker" id="size_marker" value="<?php echo $settings["size_marker"]; ?>" />
						<br><em>the minimum allowed by Google is '3'.</em>
					</td>	
				</tr>					
				<tr>
					<th scope="row"><label for="post_id">Post or Page ID (optional)</label></th>
					<td>
						<input class="regular-text" type="text" name="post_id" id="post_id" value="<?php echo $settings["post_id"]; ?>" />
						<br><em>the ID of the page or post you display your map in. Enter the ID so the scripts only get loaded on that specific page.</em>
					</td>	
				</tr>				
			
			</tbody>	
		</table>
		
		<p class="submit"><input class="button-primary" type="submit" name="save_options" value="<?php _e("Save Settings","simpletravelmap") ?>" /></p>	
	</form>
<?php

}

function simple_travel_map_enqueue_scripts() {

	$settings = get_option("simple-travel-map-settings");
	if(empty($settings["post_id"])) {
		wp_enqueue_script( 'google-jsapi', 'http://google.com/jsapi', array(), null );
	} elseif(is_page($settings["post_id"]) || is_single($settings["post_id"])) {
		wp_enqueue_script( 'google-jsapi', 'http://google.com/jsapi', array(), null );
	}	
}
add_action( 'wp_footer', 'simple_travel_map_enqueue_scripts' );

function simple_travel_map_load_google_apis() {

	$settings = get_option("simple-travel-map-settings");
	if(empty($settings["post_id"])) {
		$display_code = 1;
	} elseif(is_page($settings["post_id"]) || is_single($settings["post_id"])) {
		$display_code = 1;
	}	

	if($display_code == 1) {
		?>
		<script type="text/javascript">
			google.load("visualization", "1", {packages: ["geochart"]});
			google.setOnLoadCallback(simple_travel_map_draw_markers);
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'simple_travel_map_load_google_apis', 100 );

add_shortcode('simple-travel-map', 'simple_travel_map_shortcode' );
function simple_travel_map_shortcode($atts = "", $content = null) {

	$metas = get_option("simple-travel-map-locations");

	$settings = get_option("simple-travel-map-settings");
	
	if(empty($settings)) {
		$settings = simple_travel_map_set_defaults();
	}
	
	if($settings["map_width"] != "auto" && !empty($settings["map_width"])) {
		$html_style = ' style="width: '.str_replace("px", "", $settings["map_width"]).'px;"';	
	} else {
		$html_style = "";
	}
	
	?>
    <script type='text/javascript'>

    function simple_travel_map_draw_markers() {

     var data = google.visualization.arrayToDataTable([
		['Lat', 'Long', 'tooltip', 'Value', {role: 'tooltip', p:{html:true}}, 'link'],
		
	<?php
	foreach ( $metas as $metav ) {

		$permalink = $metav["link"];
		$location = $metav["location"];
		$thumbnail = $metav["thumbnail"];
		$long = $metav["coordinates"]["long"];
		$lat = $metav["coordinates"]["lat"];
		
		if(!empty($thumbnail)) {
			$tncode = '<img src="' . $thumbnail . '" />';
		} else {
			$tncode = '';
		}

		if(!empty($long) && !empty($lat)) {
			echo "[".esc_attr($long).", ".esc_attr($lat).", '".$location."', 0.1, '".$tncode."','".esc_attr($permalink)."'],";			
		}
	}
	?>
		]);

      var options = {
        tooltip: {
            isHtml: true,
			textStyle: {
				color: '<?php echo esc_attr($settings["color_text"]); ?>',
			}
        },
        displayMode: 'markers',
		legend: 'none',
		markerOpacity: 1,
		datalessRegionColor: '<?php echo esc_attr($settings["color_region"]); ?>',
		sizeAxis: {minValue: 3, maxValue:3,minSize:<?php echo esc_attr($settings["size_marker"]); ?>,  maxSize: <?php echo esc_attr($settings["size_marker"]); ?>},
		colorAxis: {minValue: 4, maxValue:4,  colors: ['<?php echo esc_attr($settings["color_marker"]); ?>','<?php echo esc_attr($settings["color_marker"]); ?>']},
      };

	var chart = new google.visualization.GeoChart(document.getElementById('chart_div'));	  
	  
	var view = new google.visualization.DataView(data); view.setColumns([0, 1, 2, 3, 4]);
	chart.draw(view, options);
	
	google.visualization.events.addListener(chart, 'select', function() {
		var selectIdx = chart.getSelection()[0].row;
		var postUrl = data.getValue(selectIdx, 5);
		window.open(postUrl, '_self');
	});

    }; 
    </script>
	<div id="chart_div"<?php echo $html_style; ?>></div>	
	<?php
	
	return $content;
}	

function simple_travel_map_get_meta_values( $key = '', $type = 'post', $status = 'publish' ) {

    global $wpdb;

    if( empty( $key ) )
        return;

    $r = $wpdb->get_results( $wpdb->prepare( "
        SELECT pm.meta_value, pm.post_id FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '%s' 
        AND p.post_status = '%s' 
        AND p.post_type = '%s'
    ", $key, $status, $type ) );

    return $r;
}

function simple_travel_map_save_lookup_lat_long($string) {
 
	$string = str_replace (" ", "+", urlencode($string));
	$details_url = "http://maps.googleapis.com/maps/api/geocode/json?address=".$string."&sensor=false";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $details_url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$response = json_decode(curl_exec($ch), true);

	if ($response['status'] != 'OK') {
		return $response;
	}

	$geometry = $response['results'][0]['geometry'];

	$array = array(
	'latitude' => $geometry['location']['lng'],
	'longitude' => $geometry['location']['lat'],
	'location_type' => $geometry['location_type'],
	);

	return $array;
}

?>