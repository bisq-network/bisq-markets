package bisq.markets.api;

import java.util.Map;

public class GraphQLResponse<T> {
    Map<String,T> data;

    public T getData() {
        return data.values().iterator().next();
    }
}
