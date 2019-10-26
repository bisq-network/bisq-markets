package bisq.markets.api;

import java.util.Map;

public class GraphQLQuery {
    private String query;
    private Map<String,String> variables;

    static public bisq.markets.api.GraphQLQuery forRequest(String path, Map<String,String> params) {
        if (path == "/api/currencies") {
          return new GraphQLQuery(currenciesQuery, null);
        } else {
            return null;
        }
    }

    private GraphQLQuery(String query, Map<String,String> variables){
        this.query = query;
        this.variables = variables;
    }

    private static String currenciesQuery = "{ currencies { code name precision type: currencyTypeLowerCase } }";
}
