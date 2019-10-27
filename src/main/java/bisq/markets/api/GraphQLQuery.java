package bisq.markets.api;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.reflect.TypeToken;

import java.util.*;
import java.util.List;
import java.util.logging.Logger;

public abstract class GraphQLQuery {
    private static final Logger LOG = Logger.getLogger(GraphQLQuery.class.getName());
    private static final Gson gson = new GsonBuilder().serializeNulls().setPrettyPrinting().create();

    static bisq.markets.api.GraphQLQuery forRequest(String path, Map<String, String> params) {
        if (path.startsWith("/api/currencies")) {
            return new CurrenciesQuery();
        }else if (path.startsWith("/api/markets")) {
            return new MarketsQuery();
        }else if (path.startsWith("/api/offers")) {
            return new OffersQuery(params);
        }else if (path.startsWith("/api/depth")) {
            return new DepthQuery(params);
        } else {
            return null;
        }
    }
    public abstract Object translateResponse(String response);

    private static class CurrenciesQuery extends GraphQLQuery {
        private static class Currency {
            String code;
            String name;
            String type;
            int precision;
        }
        private static final String currenciesQuery = "{ currencies { code name precision type: currencyTypeLowerCase } }";
        private final String query = currenciesQuery;

        @Override
        public Object translateResponse(String response) {
            GraphQLResponse<List<Currency>> currencies = gson.fromJson(response,new TypeToken<GraphQLResponse<List<Currency>>>(){}.getType());
            Iterator<Currency> iter = currencies.getData().iterator();
            Map<String,Currency> ret = new HashMap();
            while(iter.hasNext()) {
                Currency currency = iter.next();
                ret.put(currency.code,currency);
            }
            return ret;
        }
    }

    private static class MarketsQuery extends GraphQLQuery {
        private static class Market {
            String pair;
            String lname;
            String rname;
            String lsymbol;
            String rsymbol;
            int lprecision;
            int rprecision;
            String ltype;
            String rtype;
        }
        private static final String marketsQuery = "{ markets { pair lname: lName rname: rName lsymbol: lSymbol lprecision: lPrecision rsymbol: rSymbol rprecision: rPrecision ltype: lTypeLowerCase rtype: rTypeLowerCase } }";
        private final String query = marketsQuery;

        @Override
        public Object translateResponse(String response) {
            GraphQLResponse<List<Market>> markets = gson.fromJson(response,new TypeToken<GraphQLResponse<List<Market>>>(){}.getType());
            Iterator<Market> iter = markets.getData().iterator();
            Map<String,Market> ret = new HashMap();
            while(iter.hasNext()) {
                Market market = iter.next();
                ret.put(market.pair,market);
            }
            return ret;
        }
    }

    private static class OffersQuery extends GraphQLQuery {
        private static class OpenOffer implements Comparable<OpenOffer> {
            String offer_id;
            String market;
            long offer_date;
            String direction;
            String min_amount;
            String amount;
            String price;
            String volume;
            String payment_method;
            String offer_fee_txid;

            @Override
            public int compareTo(OpenOffer o) {
                if (price.length() - o.price.length() == 0 ){
                    return price.compareTo(o.price);
                } else {
                    return price.length() - o.price.length();
                }
            }
        }
        private static final String offersQuery = "query Offers($market: MarketPair, $direction: Direction)" +
                "{ offers(market: $market, direction: $direction) { " +
                "market: marketPair offer_id: id offer_date: offerDate " +
                "direction min_amount: formattedMinAmount " +
                "amount: formattedAmount price: formattedPrice " +
                "volume: formattedVolume payment_method: paymentMethodId " +
                "offer_fee_txid: offerFeeTxId } }";
        private final String query = offersQuery;
        private final Map<String,String> variables;

        OffersQuery(Map<String,String> params){
            variables = params;
        }

        @Override
        public Object translateResponse(String response) {
            GraphQLResponse<List<OpenOffer>> offers = gson.fromJson(response,new TypeToken<GraphQLResponse<List<OpenOffer>>>(){}.getType());

            Iterator<OpenOffer> iter = offers.getData().iterator();
            Map<String,Map<String,List<OpenOffer>>> ret = new HashMap<>();
            while(iter.hasNext()) {
                OpenOffer offer = iter.next();
                if (!ret.containsKey(offer.market)) {
                    Map<String,List<OpenOffer>> directions = new HashMap<>();
                    directions.put("buys",new ArrayList<>());
                    directions.put("sells",new ArrayList<>());
                    ret.put(offer.market, directions);
                }
                Map<String,List<OpenOffer>> directions = ret.get(offer.market);
                if (offer.direction.equals("BUY")) {
                    List<OpenOffer> buys = directions.get("buys");
                    buys.add(offer);
                    Collections.sort(buys);
                    Collections.reverse(buys);
                } else {
                    List<OpenOffer> sells = directions.get("sells");
                    sells.add(offer);
                    Collections.sort(sells);
                }
            }
            return ret;
        }
    }

    private static class DepthQuery extends GraphQLQuery {
        private static class Depth {
            List<String> buys;
            List<String> sells;
        }
        private static final String depthQuery = "query Depth($market: MarketPair!)" +
                "{ depth(market: $market) { buys: formattedBuys sells: formattedSells } }";
        private final String query = depthQuery;
        private final Map<String,String> variables;

        DepthQuery(Map<String, String> params){
            variables = params;
        }

        @Override
        public Object translateResponse(String response) {
            LOG.warning(response);
            GraphQLResponse<Depth> markets = gson.fromJson(response,new TypeToken<GraphQLResponse<Depth>>(){}.getType());
            Depth depth = markets.getData();
            Map<String,Depth> ret = new HashMap<>();
            ret.put(variables.get("market"), depth);
            return ret;
        }
    }
}
