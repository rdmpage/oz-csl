<?php

error_reporting(E_ALL);

// Get ORCIDs from DOIs


//----------------------------------------------------------------------------------------
function get($url)
{
	$data = null;
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

//----------------------------------------------------------------------------------------
function orcid_from_doi($doi)
{
	$url = 'https://enchanting-bongo.glitch.me/search?q=' . urlencode($doi);
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	if ($data != '')
	{
		$obj = json_decode($data);
		
		if (isset($obj->orcid))
		{
			foreach ($obj->orcid as $orcid)
			{
				echo "$orcid\n";
			}
		}
	}
}	




$filename = 'doi.txt';

$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
	$doi = trim(fgets($file_handle));
	
	//echo $doi . "\n";
	
	orcid_from_doi($doi);
}


?>
