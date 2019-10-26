package bisq.markets.api;

import java.util.HashMap;
import java.util.Map;
import java.util.logging.Level;
import java.util.logging.Logger;

public class GraphQLQuery {
    private String query;
    private Map<String,String> variables;
    private static final Logger LOG = Logger.getLogger(GraphQLQuery.class.getName());

    static public bisq.markets.api.GraphQLQuery forRequest(String path, Map<String,String> params) {
        LOG.log(Level.WARNING, "PATH: " + path);
        if (path == "/currencies") {
          return new GraphQLQuery(currenciesQuery,new HashMap());
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
