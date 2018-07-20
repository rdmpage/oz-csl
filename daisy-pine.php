<?php

// daisy-pine.glitch.me metadata extraction

error_reporting(E_ALL);

require_once('couchsimple.php');

$force = true;
$doi_lookup = false;

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
function find_doi($string)
{
	$doi = '';
	
	$url = 'https://mesquite-tongue.glitch.me/search?q=' . urlencode($string);
	
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
		
		//print_r($obj);
		
		if (count($obj) == 1)
		{
			if ($obj[0]->match)
			{
				$doi = $obj[0]->id;
			}
		}
		
	}
	
	return $doi;
			
}	

//----------------------------------------------------------------------------------------
// daisy-pine API
function get_work($url)
{
	global $couch;

	$data = null;
	
	$url = 'https://daisy-pine.glitch.me/summary?q=' . urlencode($url);
	
	$json = get($url);
		
	if ($json != '')
	{
		$obj = json_decode($json);
		if ($obj)
		{
			if ($doi_lookup)
			{
				// DOIs?
				foreach ($obj->reference as $i => $reference)
				{
					if (!isset($reference->DOI))
					{
						$doi = find_doi($reference->unstructured);
						if ($doi != '')
						{
							echo $doi . "\n";
							$obj->reference[$i]->DOI = $doi;
						}
					}			
				}
			}		
		
			$data = new stdclass;
			
			$data->_id = $url;
			
			// Same as ORCID
			$data->{'message-format'} = 'application/vnd.citationstyles.csl+json';
			
			// Set URL we got data from
			$data->{'message-source'} = $url;
						
			$data->message = $obj;
		}
	}
	
	return $data;
}


//----------------------------------------------------------------------------------------
function daisy_fetch($url)
{
	global $couch;
	global $force;
	global $doi_lookup;
	
	$id = 'https://daisy-pine.glitch.me/summary?q=' . urlencode($url);
	
	$exists = $couch->exists($id);

	$go = true;
	if ($exists && !$force)
	{
		echo "Have already\n";
		$go = false;
	}

	if ($go)
	{
		$doc = get_work($url);
		
		print_r($doc);
		
		exit();
		
		
		if ($doc)
		{
		
			if (!$exists)
			{
				$couch->add_update_or_delete_document($doc, $doc->_id, 'add');	
			}
			else
			{
				if ($force)
				{
					$couch->add_update_or_delete_document($doc, $doc->_id, 'update');
				}
			}
		}
		
	}	
}


// test cases
if (1)
{

	$urls = array(
//	"https://doi.org/10.1071/is13027",
//	"http://www.mapress.com/j/zt/article/view/zootaxa.1570.1.1"
	"https://doi.org/10.11646/zootaxa.4137.4.9"
	);
	
	$force = true;
	$force = false;
	
	foreach ($urls as $url)
	{
		echo $url . "\n";
		daisy_fetch($url);
	}
}

/*
// from file
if (0)
{
	$force = false;

	$filename = 'doi.txt';
	
	$file_handle = fopen($filename, "r");
	while (!feof($file_handle)) 
	{
		$doi = trim(fgets($file_handle));
		
		crossref_fetch($doi);
	}
}
*/

?>
