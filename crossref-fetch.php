<?php

error_reporting(E_ALL);

require_once('couchsimple.php');

$force = true;

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
// CrossRef API
function get_work($doi)
{
	global $couch;

	$data = null;
	
	$url = 'https://api.crossref.org/v1/works/http://dx.doi.org/' . $doi;
	
	$json = get($url);
	
	if ($json != '')
	{
		$obj = json_decode($json);
		if ($obj)
		{
			$data = new stdclass;
			
			$data->_id = 'https://doi.org/' . $doi;
			
			// https://github.com/CrossRef/rest-api-doc#result-types
			$data->{'message-format'} = 'application/vnd.crossref-api-message+json';
			
			// Set URL we got data from
			$data->{'message-source'} = $url;
						
			$data->message = $obj->message;
			
			// authors
			if (isset($data->message->author))
			{
				foreach ($data->message->author as $author)
				{
					if (isset($author->ORCID))
					{
						$data->links[] = str_replace('http:', 'https:', $author->ORCID);
					}
				}
			}
					
			/*
			// cited literature (ensure we use same logic when naming these as in CouchDB view)
			// see http://data.crossref.org/schemas/common4.3.7.xsd
			if (isset($data->message->reference))
			{
				// extract and add cited literature to database
				
				foreach ($data->message->reference as $cited) {
				
					// If reference has a DOI we simply add that to our list of links,
					// and these may be added to the queue 
					if (isset($cited->DOI))
					{
						$data->links[] = 'https://doi.org/' . strtolower(trim($cited->DOI));
					} 
					else 
					{
						// Handle citations that lack a DOI
						
						$doc = new stdclass;
						$doc->message = $reference;

						$doc->{'message-format'} = 'application/vnd.crossref-citation+json'; // made up by rdmp	
						$doc->{'message-timestamp'} = date("c", time());
						$doc->{'message-modified'} 	= $doc->{'message-timestamp'};
					
						// need consistent way of identifying these references,
						// note that the "key" used by CrossRef or in HTML version of article
						// is not always compatible with a URI :(
						
						$id = $cited->key;
						
						// clean 						
						$id = preg_replace('/[^a-zA-Z0-9_]/', '', $id);
												
						// make hash id 
						$doc->_id = 'https://doi.org/' . $doi . '#' . $id;
						$doc->cluster_id = $doc->_id;
						
						
						// attempt to parse unstructured string
						if (isset($cited->unstructured) && !isset($cited->{'article-title'}))
						{
							$cited = parse_reference($cited);
						}
						
						$doc->message = $cited;
						
						//print_r($cited);
						
						// Add directly to database
						if (1)
						{
							$exists = $couch->exists($doc->_id);
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
			}
			*/


			
		}
	}
	
	return $data;
}


//----------------------------------------------------------------------------------------
function crossref_fetch($doi)
{
	global $couch;
	global $force;
	
	$id = 'https://doi.org/' . $doi;
	
	$exists = $couch->exists($id);

	$go = true;
	if ($exists && !$force)
	{
		echo "Have already\n";
		$go = false;
	}

	if ($go)
	{
		$doc = get_work($doi);
		
		//print_r($doc);
		
		
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
if (0)
{

	$dois=array(
	"10.11646/zootaxa.3926.2.6",
	"10.7717/peerj.3463",
	"10.11646/zootaxa.3827.3.5",
	"10.11646/zootaxa.4189.2.11",
	"10.1111/aen.12317",
	"10.11646/zootaxa.3873.5.7",
	"10.11646/zootaxa.4000.5.1",
	);
	
	$force = true;
	$force = false;
	
	foreach ($dois as $doi)
	{
		crossref_fetch($doi);
	}
}

// from file
if (1)
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

?>
