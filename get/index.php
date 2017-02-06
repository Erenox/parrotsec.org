<?php

/*
* func : error_recovery
* do somethings if an error happened instead of a blank page.
*/
function error_recovery($message, $redirect)
{
	// new date
	$date = new DateTime();
	$date = $date->format("y:m:d h:i:s");

	// append in error.log - server must have write permissions to file.
	$result = file_put_contents('error.log',"[" . $date . "] - " . $message . "\n", FILE_APPEND);

	if($redirect) //if notified, redirect back to download page
	{
		header('Location: /download.fx', true, 307);
		exit;
	}
}

/*
* func : mirror_selector
* return a mirror alias by using client country code.
*/
function mirror_selector($req_country_code)
{
	// get geo JSON data
	$country_code_json = json_decode(file_get_contents("./datasource/geo.json"), TRUE);

	if(!empty($country_code_json["Geo"][$req_country_code])) // ok
	{
		return $country_code_json["Geo"][$req_country_code][array_rand($country_code_json["Geo"][$req_country_code])];
	}
	else // possibly empty country code : SS, UM, VG ?
	{
		// log it, continue
		error_recovery("No case found for country code : " . $req_country_code . ".", false);
		return "rwth-aachen";  // return the main mirror
	}

	return $default_alias; // country code not found in JSON data
}


/*
* func : update_stats
* update the stats table
*/
function update_stats($mirror_name)
{
	// get stats JSON data
	$stats_json = json_decode(file_get_contents("./datasource/stats.json"), TRUE);

	// add in stats
	if(!is_null($stats_json["Stats"][$mirror_name])) // for existing mirror name
	{
		$stats_json["Stats"][$mirror_name] += 1;
	}
	else // for new mirror
	{
		$stats_json["Stats"][$mirror_name] = 1;
	}

	// increment the total
	$stats_json["Stats"]["total"] += 1;

	// save JSON stats
	file_put_contents("./datasource/stats.json", json_encode($stats_json, JSON_PRETTY_PRINT));

}

/*
* func : check_mirror
* check if the destination mirror is up 
*/
function check_mirror($mirror_url) 
{
	// send an head request to download url
	stream_context_set_default(array('http' => array('method' => 'HEAD')));
	$headers = get_headers($mirror_url)[0];
	
	// 200, 301, 302 accepted
	if(substr($headers[0], 9, 3) == 200 || substr($headers[0], 9, 3) == 301 || substr($headers[0], 9, 3) == 302)
	{
		return true; // mirroir is up
	}
	else if(preg_match('/sourceforge.net/',$mirror_url) && substr($headers[0], 9, 3) == 404) // allow '404' for sourceforge
	{
		return true; //  mirror is up
	}
	else // 4xx,5xx, and others
	{
		return false; // mirror is down
	}

}

/*
* func : mirror_redirector
* redirect client to selected mirror download link
*/
function mirror_redirector($req_mirror, $req_file)
{
	// get mirrors JSON data
	$mirrors_json = json_decode(file_get_contents("./datasource/mirrors.json"), TRUE);

	// get an download link from mirror file
	foreach ($mirrors_json["Mirrors"] as $continent => $mirror)
	{
		foreach ($mirror as $parameters)
		{
			if(in_array($req_mirror, $parameters["alias"]))
			{
				// build the download link
				$download_link = $parameters["link"] . "/" .$req_file;		
				
				// check the mirror status
				if(check_mirror($download_link)) // mirror is up
				{
					// update the stats 
					update_stats($parameters["name"]);
				
					// redirect the client.
					header("Location: ".$download_link, true, 307);
					exit;
				}
				else // mirror is down
				{
					// log it, continue
					error_recovery("An error occurred with download url : " . $download_link, false);

					// use the main mirror
					mirror_redirector("rwth-aachen", $req_file);
				}
			}
		}
	}

	// log it, redirect to download page
	error_recovery("Mirror alias " . $req_mirror . " not found in mirrors.json.", true);
}


/*
* entry point
* get, check the parameters, and processing
*/
if(isset($_GET["mirror"]) && isset($_GET["file"]))
{
	// get the parameters
	$req_mirror = htmlentities($_GET["mirror"]);
	$req_file = htmlentities($_GET["file"]);

	if($req_mirror == "auto")
	{
		$req_mirror = mirror_selector(htmlentities($_SERVER["HTTP_CF_IPCOUNTRY"]));
	}
	
	// select the mirror and redirect the client.
	mirror_redirector($req_mirror, $req_file);
}
else // bad parameters
{
	// log it, redirect to download page
	error_recovery("Bad request parameters.", true);
}

?>
