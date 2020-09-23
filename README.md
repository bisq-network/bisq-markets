# Bisq Markets API+Website

A website to visualize and explore Bisq Markets data, powered by the Mempool backend which can serve Bisq Markets API data.

Bisq Markets API documentation: https://bisq.markets/api

## API Backend

First, build [Bisq](https://github.com/bisq-network/bisq) from source as normal, and run the `./bisq-statsnode` entrypoint with `--dumpStatistics=true --dumpBlockchainData=true` - after it finishes syncing the Bisq data, it should create some JSON files in your `./btc_mainnet/db/json/` folder such as `trade_statistics.json`.

Then, install the [Mempool Explorer](https://github.com/mempool/mempool)'s production configuration as normal, and assuming your Bisq home directory is `/bisq`, enable the Bisq Markets API in your backend `mempool-config.json` as follows:

```
  "BISQ_ENABLED": true,
  "BISQ_BLOCKS_DATA_PATH": "/bisq/statsnode-data/btc_mainnet/db/json",
  "BISQ_MARKET_ENABLED": true,
  "BISQ_MARKETS_DATA_PATH": "/bisq/statsnode-data",
```

Mempool will get the Bisq Markets data from your bisq-statsnode in the JSON files and serve it over the API. You should be able to access `/api/offers?market=bsq_btc` or other Bisq Market APIs.

## Angular web frontend

TBD as part of https://github.com/bisq-network/projects/issues/41

