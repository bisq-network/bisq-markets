# Bisq Markets API

Visit https://markets.bisq.network/api for the current API documentation.

## Architecture

This new architecture replaces the previous Bisq node -> JSON file -> PHP frontend. We now use Risq behind a caching reverse-proxy on Google Appengine to serve Bisq Markets data.

## Proxy Caching Scheme

HTTP cache = 60 seconds
memcache = 60 seconds
datastore = forever
```
if (full query URL key exists in memcache)
    return response
else
    if (query live node succeeds)
        insert into memcache + datastore 
        return response
    else // query failed
        if (full query URL key exists in datastore)
            return response
        else // not in datastore
            return 502
```
## Demo

