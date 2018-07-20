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