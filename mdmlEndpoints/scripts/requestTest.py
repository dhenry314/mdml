#!/usr/bin/env python

import time
import json
import requests

endpointServiceURL = "http://localhost/mdml/SERVICES/mdml/EndpointProcessService"
jwt = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9kYXRhLm1vaGlzdG9yeS5vcmdcL21kbWwiLCJpYXQiOjE0OTg0MjEyNjMsImV4cCI6MTQ5ODQ1NzI2MywiYXVkIjoiXC8ifQ.9EEkxJvAF9Z5pdCxDYGX3LCQIpaboJtdHhlKjEc8MBM"
job_info = {
  "@context": {
	"mdml": "http://data.mohistory.org/mdml/",
	"schema": "http://schema.org/" 
  },
  "@id":"mohub_frbstl_fraser_incoming",
  "@type": ["mdml:EndpointDef"],
  "mdml:endpointNS": "MOHUB",
  "mdml:endpointPath":"/FRBSTL/FRASER/INCOMING/",
  "schema:name":"Federal Reserve Bank of St. Louis - FRASER",
  "mdml:allowableMethods":["getRecord","listing","sitemap","changelist"],
  "mdml:processes": [
		{
			"schema:name": "generateRS",
			"mdml:ServicePath":"http://localhost/mdml/SERVICES/mdml/OAI_RSGenerator",
			"mdml:ServiceParams": {
				"path": "https://fraser.stlouisfed.org/oai",
				"metadataPrefix":"mods"
			}
		}
	]
}

headers = {
	"Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9kYXRhLm1vaGlzdG9yeS5vcmdcL21kbWwiLCJpYXQiOjE0OTg0MjEyNjMsImV4cCI6MTQ5ODQ1NzI2MywiYXVkIjoiXC8ifQ.9EEkxJvAF9Z5pdCxDYGX3LCQIpaboJtdHhlKjEc8MBM"
}

def json_loads_byteified(json_text):
    return _byteify(
        json.loads(json_text, object_hook=_byteify),
        ignore_dicts=True
    )

def _byteify(data, ignore_dicts = False):
    # if this is a unicode string, return its string representation
    if isinstance(data, unicode):
        return data.encode('utf-8')
    # if this is a list of values, return list of byteified values
    if isinstance(data, list):
        return [ _byteify(item, ignore_dicts=True) for item in data ]
    # if this is a dictionary, return dictionary of byteified keys and values
    # but only if we haven't already byteified it
    if isinstance(data, dict) and not ignore_dicts:
        return {
            _byteify(key, ignore_dicts=True): _byteify(value, ignore_dicts=True)
            for key, value in data.iteritems()
        }
    # if it's anything else, return it in its original form
    return data

#job_info = json.loads(job_contents)
print job_info['schema:name']
resp = requests.post(endpointServiceURL,data=json.dumps(job_info),headers=headers)
print resp.text



