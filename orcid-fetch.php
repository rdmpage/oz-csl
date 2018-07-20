<?php

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/couchsimple.php');
require_once (dirname(__FILE__) . '/fingerprint.php');


$force = true;

//----------------------------------------------------------------------------------------
function get($url, $accept = 'application/json')
{
	$data = null;
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_HTTPHEADER => array('Accept: ' . $accept) 
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
// Fetch an individual work from an ORCID profile
function orcid_work_fetch($orcid_work, $lookup_works = false)
{
	$data = null;
		
	$url = 'https://pub.orcid.org/v2.1/' . $orcid_work;	
	$json = get($url, 'application/vnd.citationstyles.csl+json');
			
	if ($json != '')
	{
		$data = json_decode($json);		
	}
	
	return $data;
}

//----------------------------------------------------------------------------------------
function orcid_fetch($orcid, $lookup_works = false)
{
	global $force;
	global $couch;
	
	$data = null;
		
	$url = 'https://pub.orcid.org/v2.1/' . $orcid;	
	$json = get($url, 'application/orcid+json');
	
	// file_put_contents($orcid . '.json', $json);
		
	if ($json != '')
	{
		$data = json_decode($json);		
		
		
		// get author details
		$person = new stdclass;
		
		$person->orcid = $orcid;
		
		$parts = array();
		
		if (isset($data->person))
		{
			if (isset($data->person->name->{'given-names'}))
			{
				$person->given = $data->person->name->{'given-names'}->value;
				
				$parts[] = $person->given;
			}
			if (isset($data->person->name->{'family-name'}))
			{
				$person->family = $data->person->name->{'family-name'}->value;
				$parts[] =  $person->family;
			}
			
		}
		
		$person->literal = join(' ', $parts);
		//print_r($parts);
		
		print_r($person);
		//exit();
		
		
		
		
		// API 2.1 has API to access individual works via "putcode"
		if (isset($data->{'activities-summary'}))
		{
			if (isset($data->{'activities-summary'}->{'works'}))
			{
				foreach ($data->{'activities-summary'}->{'works'}->{'group'} as $work)
				{
					
					foreach ($work->{'work-summary'} as $summary)
					{
						
						$doi = '';
						
						if (isset($work->{'external-ids'}))
						{
							if (isset($work->{'external-ids'}->{'external-id'}))
							{
								foreach ($work->{'external-ids'}->{'external-id'} as $external_id)
								{
									if ($external_id->{'external-id-type'} == 'doi')
									{
										$doi = $external_id->{'external-id-value'};
									}
								}
							}
						}
						
						
						// fetch individual works
						
						$work = orcid_work_fetch($orcid . '/work/' . $summary->{'put-code'});

						
						
						// cleaning...
						
						if (isset($work->title))
						{
							//$work->title = preg_replace('/\\\less/', '', $work->title);
							//$work->title = preg_replace('/\\\greater/', '', $work->title);
							
							/*
							$work->title = str_replace('$i$', '<i>', $work->title);
							$work->title = str_replace('$/i$', '</i>', $work->title);
							$work->title = str_replace('$em$', '<em>', $work->title);
							$work->title = str_replace('$/em$', '</em>', $work->title);
							$work->title = str_replace('$strong$', '<strong>', $work->title);
							$work->title = str_replace('$/strong$', '</strong>', $work->title);
							*/
							
							$work->title = str_replace('\less', '<', $work->title);
							$work->title = str_replace('\greater', '>', $work->title);
							
							$work->title = str_replace('{\&}amp\mathsemicolon', '&', $work->title);
							$work->title = str_replace('{HeadingRunIn}', '"HeadingRunIn"', $work->title);
							
							
							$work->title = str_replace('$', '', $work->title);
							
						}
						
						
						// do we need to look for a DOI?
						
						if (!isset($work->DOI))
						{
							$terms = array();
						
							if (isset($work->author))
							{
								foreach ($work->author as $author)
								{
									if (isset($author->family))
									{
										$terms[] = $author->family;
									}							
								}					
							}
					
							if (isset($work->issued))
							{
								if (isset($work->issued->{'date-parts'}))
								{
									$terms[] = $work->issued->{'date-parts'}[0][0];
								}
							}
											
							if (isset($work->title))
							{
								$terms[] = strip_tags($work->title);
							}
							if (isset($work->{'container-title'}))
							{
								$terms[] = $work->{'container-title'};
							}
							if (isset($work->volume))
							{
								$terms[] = $work->volume;
							}
							if (isset($work->page))
							{
								$terms[] = $work->page;
							}
						
						
							echo join(' ', $terms);
							
							$doi = find_doi(join(' ', $terms));
							if ($doi != '')
							{
								$work->DOI = strtolower($doi);
							}							
							
						}
						
						// figure out which author gets ORCID
						if (isset($work->author))
						{
							$n = count($work->author);
							
							if ($n == 1)
							{
								$work->author[0]->ORCID = $orcid;
							}
							else
							{
								$min_d = 100;
								$hit = -1;
								
								for ($i = 0; $i < $n; $i++)
								{
									$parts = array();
									
									if (isset($work->author[$i]->given))
									{
										$parts[] = $work->author[$i]->given;
									}
									if (isset($work->author[$i]->family))
									{
										$parts[] = $work->author[$i]->family;
									}
									
									$work->author[$i]->literal = join(' ', $parts);
									
									
									$d = levenshtein(finger_print($person->literal), finger_print($work->author[$i]->literal));

									if ($d < $min_d)
									{
										$min_d = $d;
										$hit = $i;
									}
								}
								
								if ($hit != -1)
								{
									$work->author[$hit]->ORCID = $orcid;
								}
										
							}
						
						
						}
						
						
						//print_r($work);
						
						// Store work as a message
						$work_doc = new stdclass;						
						$work_doc->_id = $orcid . '/work/' . $summary->{'put-code'};
						$work_doc->{'message-format'} = 'application/vnd.citationstyles.csl+json';
						$work_doc->message = $work;
						
						// store work in CouchDB
						$exists = $couch->exists($work_doc->_id);

						// add to database
						if (!$exists)
						{
							$couch->add_update_or_delete_document($work_doc, $work_doc->_id, 'add');	
						}
						else
						{
							if ($force)
							{
								$couch->add_update_or_delete_document($work_doc, $work_doc->_id, 'update');
							}
						}
						
						// generate triples work -> author -> name + position so we can start to merge authors...
		
					}
				}
			}
		
		}
	}
	
	return $data;
}


$orcids = array('0000-0001-7150-6509');


$orcids = array('0000-0002-9994-6423'); // Jane Melville
$orcids = array('0000-0003-4244-2942'); // Jeremy Austin
//$orcids = array('0000-0001-8630-3114'); // Lindsay Popple
//$orcids = array('0000-0003-2722-6854'); // david emery (one work)

$orcids=array(
'0000-0002-0981-4647',
'0000-0002-8428-3495',
'0000-0003-0944-3003',
'0000-0003-1175-1152'
);

$orcids=array(
'0000-0001-8641-4412'
);

$orcids = array('0000-0001-8630-3114');

$doi_lookup = false;
$dois = array();

$force = true;

$count = 1;

foreach ($orcids as $orcid)
{

	$exists = $couch->exists($orcid);

	$go = true;
	if ($exists && !$force)
	{
		echo "Have $orcid already\n";
		$go = false;
	}

	if ($go)
	{
		$data = orcid_fetch($orcid);
		
		$doc = new stdclass;
		$doc->_id = $orcid;		
		$doc->{'message-format'} = 'application/orcid+json';
		$doc->message = $data;		

		// add to database
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
		
		
	
		if (($count++ % 10) == 0)
		{
			$rand = rand(1000000, 3000000);
			echo '...sleeping for ' . round(($rand / 1000000),2) . ' seconds' . "\n";
			usleep($rand);
		}
	}	
}

print_r($dois);

echo "'" . join("',\n'", $dois) . "'\n";

?>
