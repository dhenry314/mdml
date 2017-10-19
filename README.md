# MDML Data Mill Aggregation System 

MDML (or MetadataMill) is an application intended to facilitate the ingestion/normalization, mapping, and publishing of data from a wide variety of sources and standards.  It relies heavily on the [ResourceSync standard](http://www.openarchives.org/rs/1.1/resourcesync) to expose records at each step.  It is also intended to allow for distributed configuration and mapping: data source configurations and data maps can be referenced via URLs.

## Motivating Use Case

MissouriHub is a collaboration of more than a dozen Missouri cultural heritage organizations interested in contributing digital collections to the DPLA.  As the technical lead for MissouriHub, the Missouri Historical Society (MHS) is responsible for coordinating the data aggregation efforts including validating data feeds; ingesting metadata records from each feed; mapping metadata records to the DPLA standards; and making mapped records available to DPLA through an aggregated OAI feed.  Since MissouriHub is an all-volunteer organization, we have to limit our data partners to those with technical staff and with data feeds implemented in the OAI-PMH standard.  While there are many cultural heritage institutions in Missouri that do not meet these qualifications, they cannot be considered for membership in MissouriHub, because we do not have the resources to troubleshoot data problems or to ingest data other than OAI-PMH.  To grow, MissouriHub needs tools to facilitate sharing the data aggregation workload and enable the aggregation of many more data formats other than OAI-PMH.

## Ingestion/Normalization

We begin with the requirement that it should be possible to ingest data from a wide variety of formats including but not limited to: OAI-PMH; Odata; CSV; XML-based REST services; JSON-based REST services; and HTML pages with embedded metadata (Note that database records would have to be exposed as a web service to be ingested into the system).   

1. The first step is to create a resourceSync sitemap and changelist for each external data source.  Each URL in the sitemap should point to a unique web resource as a record (Note that in some cases it may be necessary to cache each record when it is not possible to retrieve records individually).  A unique MD5 hash will be saved for each resource returned by each url.  When the sitemap is refreshed, the MD5 hash of the current resource will be compared to the saved MD5 hash.  When there is a difference in the hashes, the status of the resource becomes updated and a new update timestamp is saved.  A changelist will list only those resources changed within a given date range.  A data source configuration should refer to a specific web service to create and refresh the sitemap (For example, the service to generate a resourceSync sitemap from an OAI feed might be [http://data.mohistory.org/mdml/SERVICES/mdml/OAI_RSGenerator](http://data.mohistory.org/mdml/SERVICES/mdml/OAI_RSGenerator). 

2. The second step is to normalize each web resource to a [JSON-LD format](https://json-ld.org/).  JSON-LD is a flexible format that allows us to capture all of the detail on any given data record.  Since each JSON-LD document contains a context node, it is possible to save data to any data standard using namespaces for each field.  Furthermore, the JSON format allows validation with a JSON schema.  Again, the source configuration should specify the web service required to convert the origin format to JSON-LD.  

## Data Mapping

The initial use-case we considered was to aggregate data from OAI-PMH data feeds (Open Archives Initiative – Protocol for Metadata Harvesting – see [https://www.openarchives.org/pmh/](https://www.openarchives.org/pmh/).  This standard works well when metadata records are very consistent.  Unfortunately, data records are sufficiently varied across difference sources that aggregation inevitably involves data mapping to a specific standard.  

## Publishing

Once records have been ingested, normalized, and mapped, they can be made available as a new (or aggregated) resourceSync endpoint.  
Distributed Configurations
