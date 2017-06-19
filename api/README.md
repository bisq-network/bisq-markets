# bitsquare_market APIs

These APIs are open to the public with no authorization required.

# API

## /api/hloc

Retrieves high-low-open-close data according to criteria.

Params:
* market: eg 'dash_btc'.  required.
* format: json | jsonpretty | csv.  default: jsonpretty.
* timestamp: yes | no. return date as a timestamp.  default: yes
* interval: minute|hour|day|month.
   default: choose based on from/to.
* timestamp_from: start of date range. unix timestamp.  default: 1970.
* timestamp_to: end of date range. unix timestamp.  default: now.
* endcaps: bool. add a fake row at start and end of data.  default: 0
* fillgaps: bool. add a fake row for each interval with no trades. default: 0

Sample Call and Result:

```
curl "http://market.bisq.io/api/hloc/?market=eur_btc&interval=day"
[
    {
        "period_start": "2016-04-20T00:00:00+00:00",
        "open": 374.61,
        "high": 374.61,
        "low": 374.61,
        "close": 374.61,
        "volume": 0.01,
        "avg": 374.61
    },
...
    {
        "period_start": "2016-07-28T00:00:00+00:00",
        "open": 605.31,
        "high": 623.18,
        "low": 605.31,
        "close": 623.18,
        "volume": 1.25,
        "avg": 614.245
    }
]
```



## /api/trades

Retrieves trades for a given market according to criteria.

Params:
* market: eg 'dash_btc'.  omit for all markets.
* format: json | jsonpretty | csv.  default: jsonpretty.
* timestamp_from: start of date range.  unix timestamp. default: 0.
* timestamp_to: end of date range. unix timestamp.  default: now.
* direction: 'buy', 'sell'.  omit for both.
* limit: max trades.  optional.
* sort: asc | desc.  default = asc
* integeramounts: bool. return amounts as integer.  default = true.
* fields: array -- specify which fields to return.
*   available:  "currency", "direction", "tradePrice", "tradeAmount",
*               "tradeDate", "paymentMethod", "offerDate",
*               "useMarketBasedPrice", "marketPriceMargin",
*               "offerAmount", "offerMinAmount", "offerId",
*               "depositTxId"

Sample Call and Result:

```
curl "http://market.bisq.io/api/trades/?market=eur_btc&limit=1"
[
    {
        "currency": "EUR",
        "direction": "SELL",
        "tradePrice": 6231800,
        "tradeAmount": 75000000,
        "tradeDate": 1469740097748,
        "paymentMethod": "SEPA",
        "offerDate": 1469088232392,
        "useMarketBasedPrice": true,
        "marketPriceMargin": 0.05,
        "offerAmount": 75000000,
        "offerMinAmount": 10000000,
        "offerId": "c9775e65-e1d4-41c6-b40b-d9c12a9575fb",
        "depositTxId": "6af26d0dbbf0cd7bfffa3ee9fbab0177b418fbf2f0cfcbe8d5a9f36ca080dd3a",
        "market": "eur_btc",
        "total": 467385000000000
    }
]
```
