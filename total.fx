<?php 
	// get stats JSON data
	$stats_json = json_decode(file_get_contents("./get/datasource/stats.json"), TRUE);

	// display stats
	foreach ($stats_json["Stats"] as $name => $count)
	{
		echo $name . " : " . $count . "</br>";
	}
 ?>
