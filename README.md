# Bisq Markets API service

This public API serves data on Bisq offers, trades, and historical statistics.

Examples: 
* List recent trades for BTC/USD market: https://markets.bisq.network/api/trades?market=btc_usd
* List current offers for BTC/USD market: https://markets.bisq.network/api/offers?market=btc_usd

Full API documentation: https://markets.bisq.network/api/

## Local vs Cloud

There are 2 ways to get the Bisq Markets data:

1) Bisq Statsnode (generates local JSON files)

If you just want local data saved to static JSON files on-disk, you don't need this repo, you can just run the Bisq app in statsnode mode. Follow the instructions to [build Bisq from source](https://github.com/bisq-network/bisq/blob/master/docs/build.md) and run `./bisq-statsnode` - after it syncs to the network, you will get your generated JSON files in `$HOME/.local/share/Bisq/btc_mainnet/db/`

2) Risq GraphQL + Appengine Proxy (high performance cloud API service)

Since the "official" Bisq API service receives millions of requests each day, it needs to use a high availability / high performance solution. To set this up you will need:

* [Build risq from source](https://github.com/bodymindarts/risq#setup)
* Install Google Cloud and create a project on Google Appengine using Java standard runtime
* Deploy this repo to Google Appengine using the included `./markets deploy` script

The code in this repo is a smart pre-caching proxy for API requests, so data can always be served instantly from the cloud. The proxy works like this:

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
            return 503
```

The default cache times are set as:
HTTP cache = 5 minutes
memcache = 5 minutes
datastore = forever

Currently the official API service and this repo are maintained by @wiz so you will find his server hard-coded in the code, [change it to match your local setup](https://github.com/bisq-network/bisq-markets/blob/master/src/main/java/bisq/markets/api/CachingProxy.java#L88) if you want to run a pre-caching smart proxy.

### API Support

Join the [Bisq team on Keybase](https://keybase.io/team/bisq) and join #markets channel for questions about this API service
