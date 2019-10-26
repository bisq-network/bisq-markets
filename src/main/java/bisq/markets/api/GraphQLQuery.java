package bisq.markets.api;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.reflect.TypeToken;

import java.util.*;
import java.util.List;

public abstract class GraphQLQuery {
    public static final Gson gson = new GsonBuilder().serializeNulls().setPrettyPrinting().create();

    static public bisq.markets.api.GraphQLQuery forRequest(String path, Map<String,String> params) {
        if (path.startsWith("/api/currencies")) {
          return new CurrenciesQuery();
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
}
