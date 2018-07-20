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
