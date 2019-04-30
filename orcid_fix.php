<?php

// Check ORCID works

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
// Fetch an individual work from an ORCID profile
function orcid_work_fetch_from_couch($orcid_work)
{
	global $config;	
	global $couch;
	
	$data = null;
	
	$json = $couch->send('GET', '/' . $config['couchdb_options']['database'] . '/' . urlencode($orcid_work));
			
	if ($json != '')
	{
		$data = json_decode($json);		
	}
	
	return $data;
}

//----------------------------------------------------------------------------------------
// Fetch ORCID profile
function orcid_fetch_from_couch($orcid)
{
	global $config;	
	global $couch;
	
	$data = null;
	
	$json = $couch->send('GET', '/' . $config['couchdb_options']['database'] . '/' . urlencode($orcid));
			
	if ($json != '')
	{
		$data = json_decode($json);		
	}
	
	return $data;
}



$orcid_work = '0000-0002-6671-1273/work/31481366';

$work = orcid_work_fetch_from_couch($orcid_work);

print_r($work);

$orcid = '0000-0002-6671-1273';
$profile = orcid_fetch_from_couch($orcid);

print_r($profile);



?>
