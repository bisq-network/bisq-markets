package bisq.markets.api;

import com.sun.xml.internal.xsom.impl.scd.Iterators;

import java.awt.*;
import java.util.HashMap;
import java.util.Iterator;
import java.util.List;
import java.util.Map;

public abstract class GraphQLQuery {
    static public bisq.markets.api.GraphQLQuery forRequest(String path, Map<String,String> params) {
        if (path.startsWith("/api/currencies")) {
          return new CurrenciesQuery();
        } else {
            return null;
        }
    }
    public abstract Object translateResponse(Object response);


    private static class CurrenciesQuery extends GraphQLQuery {
        private static final String currenciesQuery = "{ currencies { code name precision type: currencyTypeLowerCase } }";
        private final String query;
        private CurrenciesQuery() {
            query = currenciesQuery;
        }

        @Override
        public Object translateResponse(Object response) {
            List<Map<String,Object>> currencies = (List<Map<String, Object>>) GraphQLQuery.extractFromJson(GraphQLQuery.extractFromJson(response,"data"),"currencies");
            Map<String,Object> ret = new HashMap();
            Iterator iterator = currencies.iterator();
            while(iterator.hasNext()) {
                Map<String,Object> currency = (Map<String, Object>) iterator.next();
                String code = (String) currency.get("code");
                ret.put(code,currency);
            }
            return ret;
        }
    }

    private static Object extractFromJson(Object json, String key) {
        return ((Map<String,Object>)json).get(key);
    }
}
