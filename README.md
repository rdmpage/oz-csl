# OZ-CSL

Fetch bibliographic data from various sources in CSL-JSON, store in CouchDB, and post process so it can be used by OZ.

## CrossRef

Fetch using DOI, extract ORCIDs and citations. Triples can include:
- DOI, author, ORCID, position
- DOI - DOI citation
- DOI - SICI citation

## ORCID

Fetch using ORCID, add any missing DOIs, generate DOI-ORCID tuples.

## Daisy

Fetch references from article web page.


## CouchDB

This query generates list of ORCIDs that occur in CrossRef DOI metadata:

```
http://127.0.0.1:5984/oz-csl/_design/crossref/_list/values/orchid
```

We can then input this list into ```orcid-fetch.php``` to populate CouchDB with ORCID data.

To fetch triples for citation data from CrossRef:

```
curl http://127.0.0.1:5984/oz-csl/_design/crossref/_list/triples/citation-nt > crossref-citation.nt
```

Then upload this to triple store:

```
curl http://130.209.46.63/blazegraph/sparql?context-uri=https://crossref.org -H 'Content-Type: text/rdf+n3' --data-binary '@crossref-citation.nt'  --progress-bar | tee /dev/null
```

## ORCID

```
curl http://127.0.0.1:5984/oz-csl/_design/orcid/_list/triples/doi-orcid-nt > orcid.nt
```

```
curl http://130.209.46.63/blazegraph/sparql?context-uri=https://orcid.org -H 'Content-Type: text/rdf+n3' --data-binary '@orcid.nt'  --progress-bar | tee /dev/null
```

## Problem

Need some careful filtering :O

http://localhost/~rpage/ozymandias-demo/?uri=https://biodiversity.org.au/afd/publication/%23creator/p-kolesik

http://127.0.0.1:5984/_utils/#/database/oz-csl/0000-0002-6671-1273%2Fwork%2F31481366

http://localhost/~rpage/ozymandias-demo/?uri=https://biodiversity.org.au/afd/publication/%23creator/c-l-lambkin

Partly a bug in ORCID where works may have more than one author but CSL returns just the author with an ORCID, hence we always assign ORCID to author position 1. Compare for example:

https://pub.orcid.org/v2.1/0000-0002-6671-1273/work/31481366 accept: application/orcid+json versus accept: application/vnd.citationstyles.csl+json


## SPARQL queries

Need to think about this carefully, especially whether we want to use named graphs to make sure we know where the source of the inference comes from.

```
filter and match using graph to isolate just orcid records

select distinct ?orcid
where 
{
 ?work_doi <http://schema.org/value> "10.11646/zootaxa.4001.1.1" . 
 ?work <http://schema.org/identifier> ?work_doi . 
 ?work <http://schema.org/creator> ?role .
 ?role <http://schema.org/roleName> "2" . 
 ?role <http://schema.org/creator> ?work_creator .
 ?work_creator  <http://schema.org/name> ?name . 
  
  graph ?orcid_creator {
  
 ?orcid_doi <http://schema.org/value> "10.11646/zootaxa.4001.1.1" . 
 ?orcid_work <http://schema.org/identifier> ?orcid_doi . 
 ?orcid_work <http://schema.org/creator> ?orcid_role .
 ?orcid_role <http://schema.org/roleName> "2" . 
 ?orcid_role <http://schema.org/creator> ?orcid_creator .
 ?orcid_creator  <http://schema.org/name> ?orcid_name . 
  
  ?orcid_creator <http://schema.org/identifier> ?orcid_id .
  ?orcid_id <http://schema.org/propertyID> "orcid" . 
  ?orcid_id <http://schema.org/value> ?orcid .
 
  
 }
        
 FILTER regex(STR(?work), "https://biodiversity.org.au/")
 
}

```


### Using named graphs

Link author to ORCID via works using named graphs.

```
SELECT ?name ?doi ?orcid_name
WHERE
{
  GRAPH <https://biodiversity.org.au/afd/publication> {
  <https://biodiversity.org.au/afd/publication/#creator/l-w-popple> <http://schema.org/name> ?name .
?role <http://schema.org/creator> <https://biodiversity.org.au/afd/publication/#creator/l-w-popple>  .
?role <http://schema.org/roleName> ?roleName  .

?work <http://schema.org/creator> ?role  .

?work <http://schema.org/identifier> ?identifier .
?identifier <http://schema.org/propertyID> "doi" .
?identifier <http://schema.org/value> ?doi .
}
  
  GRAPH <https://orcid.org>
  {
    ?orcid_identifier <http://schema.org/value> ?doi .
    ?orcid_work <http://schema.org/identifier> ?orcid_identifier .
    
	?orcid_work <http://schema.org/creator> ?orcid_role  . 
    ?orcid_role <http://schema.org/roleName> ?orcid_roleName  .
    
    ?orcid_role <http://schema.org/creator> ?orcid_creator  .
    
    ?orcid_creator <http://schema.org/name> ?orcid_name .
  } 
  
  FILTER(?roleName = ?orcid_roleName)
}
```
