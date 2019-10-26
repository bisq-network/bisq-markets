package bisq.markets.api;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.reflect.TypeToken;

import java.util.*;
import java.util.List;

public abstract class GraphQLQuery {
    private static final Gson gson = new GsonBuilder().serializeNulls().setPrettyPrinting().create();

    static bisq.markets.api.GraphQLQuery forRequest(String path, Map<String, String> params) {
        if (path.startsWith("/api/currencies")) {
            return new CurrenciesQuery();
        }else if (path.startsWith("/api/markets")) {
            return new MarketsQuery();
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
        private final String query;
        private CurrenciesQuery() {
            query = currenciesQuery;
        }

        @Override
        public Object translateResponse(String response) {
            GraphQLResponse<List<Currency>> currencies = gson.fromJson(response,new TypeToken<GraphQLResponse<ArrayList<Currency>>>(){}.getType());
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
        private final String query;
        private MarketsQuery() {
            query = marketsQuery;
        }

        @Override
        public Object translateResponse(String response) {
            GraphQLResponse<List<Market>> markets = gson.fromJson(response,new TypeToken<GraphQLResponse<ArrayList<Market>>>(){}.getType());
            Iterator<Market> iter = markets.getData().iterator();
            Map<String,Market> ret = new HashMap();
            while(iter.hasNext()) {
                Market market = iter.next();
                ret.put(market.pair,market);
            }
            return ret;
        }
    }
}
