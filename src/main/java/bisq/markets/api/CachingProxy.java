package bisq.markets.api;

// {{{ import
import static com.google.appengine.api.urlfetch.FetchOptions.Builder.*;

//import bisq.markets.api.beans.*;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.InputStream;
import java.io.IOException;
import java.io.PrintWriter;
import java.io.UnsupportedEncodingException;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.MalformedURLException;
import java.util.Arrays;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import java.util.logging.Level;
import java.util.logging.Logger;
import java.util.HashMap;
import java.util.Map;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import com.google.appengine.api.datastore.DatastoreService;
import com.google.appengine.api.datastore.DatastoreServiceFactory;
import com.google.appengine.api.datastore.Entity;
import com.google.appengine.api.datastore.EntityNotFoundException;
import com.google.appengine.api.datastore.FetchOptions;
import com.google.appengine.api.datastore.Key;
import com.google.appengine.api.datastore.KeyFactory;
import com.google.appengine.api.datastore.Query;
import com.google.appengine.api.datastore.Query.Filter;
import com.google.appengine.api.datastore.Query.FilterPredicate;
import com.google.appengine.api.datastore.Query.FilterOperator;
import com.google.appengine.api.datastore.Query.CompositeFilter;
import com.google.appengine.api.datastore.Query.CompositeFilterOperator;
import com.google.appengine.api.datastore.Query.SortDirection;
import com.google.appengine.api.datastore.Query;
import com.google.appengine.api.datastore.PreparedQuery;
import com.google.appengine.api.datastore.Text;
import com.google.appengine.api.datastore.Transaction;

import com.google.appengine.api.memcache.Expiration;
import com.google.appengine.api.memcache.MemcacheService;
import com.google.appengine.api.memcache.MemcacheService.SetPolicy;
import com.google.appengine.api.memcache.MemcacheServiceFactory;

import com.google.appengine.api.utils.SystemProperty;
import com.google.appengine.api.urlfetch.HTTPHeader;
import com.google.appengine.api.urlfetch.HTTPMethod;
import com.google.appengine.api.urlfetch.HTTPRequest;
import com.google.appengine.api.urlfetch.HTTPResponse;
import com.google.appengine.api.urlfetch.URLFetchService;
import com.google.appengine.api.urlfetch.URLFetchServiceFactory;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.JsonSyntaxException;
import com.google.gson.JsonParseException;
import org.graalvm.compiler.graph.Graph;

// }}}

@SuppressWarnings("serial")
public class CachingProxy extends HttpServlet
{
    // {{{ get appengine API instances
    private static final DatastoreService DS = DatastoreServiceFactory.getDatastoreService();
    private static final MemcacheService mc = MemcacheServiceFactory.getMemcacheService();
    private static final Logger LOG = Logger.getLogger(CachingProxy.class.getName());
    // }}}
    // {{{ static constants
    // google cache should be >61 seconds and < 1 week
    private static final int API_CACHE_SECONDS = 69;
    // bisq markets node can sometimes be slow to respond
    private static final double REQUEST_DEADLINE = 30.0;

    // hostnames used by this CDN app
    private static final String FRONTEND_HOSTNAME_PRODUCTION = "api.wiz.biz";
    private static final String FRONTEND_HOSTNAME_DEVELOPMENT = "dev.wiz.biz";

    // hostnames of bisq markets node to source data from
    private static final String BISQNODE_HOSTNAME_PRODUCTION = "https://markets.bisq.network";
    private static final String BISQNODE_HOSTNAME_DEVELOPMENT = "https://markets.bisq.network";
    private static final String BISQNODE_HOSTNAME_DEVSERVER = "http://127.0.0.1:7477";

    // init gson
    public static final Gson gson = new GsonBuilder().serializeNulls().setPrettyPrinting().create();
    // }}}

    private static final class MarketsApiResponseCache // {{{
    {
        private static final String KIND = "MarketsApiResponseCache";
        private static final String BISQNODE_URL = "marketsapi_url";
        private static final String BISQNODE_RESPONSE = "marketsapi_response";
    } // }}}

    @Override
    public void doGet(HttpServletRequest req, HttpServletResponse res)
    throws IOException
    {
        // {{{ init
        // autodetect locale from Accept-Language http request header
        Locale langLocale = req.getLocale();

        // get request source IP
        String reqIP = req.getRemoteAddr();

        // set default cache headers
        setResponsePrivateCacheHeaders(res, 0);

        // set content type
        res.setContentType("application/json; charset=UTF-8");

        // get request URL
        String reqURI = req.getRequestURI().toString();

        // get query string
        String queryString = req.getQueryString();

        // settings to use datastore
        boolean useCacheForMarketsApi = true;
        boolean isDevelopment = isDevelopmentMode(req);
        if (isDevelopment)
        {
            useCacheForMarketsApi = false;
        }

        // flag to force update of datastore/memcache
        boolean forceUpdate = false;
        if (req.getParameter("forceUpdate") != null)
            forceUpdate = true;

        // get URI after /api for apiPath
        String apiPath = reqURI.substring( "/api".length(), reqURI.length() );
        //LOG.log(Level.WARNING, "reqURI is "+reqURI);
        //LOG.log(Level.WARNING, "apiPath is "+apiPath);

        // request URL
        String url = "";
        // response nugget
        Object responseData = new HashMap<String, Object>();
        // }}}
        if (apiPath.equals("/ping")) // {{{
        {
            res.getWriter().println("pong");
            return;
        } // }}}
        else if ( // {{{
            apiPath.startsWith("/currencies") ||
            apiPath.startsWith("/depth") ||
            apiPath.startsWith("/hloc") ||
            apiPath.startsWith("/markets") ||
            apiPath.startsWith("/offers") ||
            apiPath.startsWith("/ticker") ||
            apiPath.startsWith("/trades") ||
            apiPath.startsWith("/volumes")
        )
        {
            url = reqURI + "?" + queryString;
        } // }}}
        else // {{{ 404
        {
            res.sendError(404);
            return;
        } // }}}
        //{{{ send response
        // fetch data, pass forceUpdate arg if requested
        if (forceUpdate) url = url + "&forceUpdate=1";
        responseData = getCachedMarketsApiData(req, url, API_CACHE_SECONDS, useCacheForMarketsApi, forceUpdate);

        // return 503 if unable to get data
        if (responseData == null)
        {
            res.sendError(503);
            return;
        }

        // set CORS headers
        setResponseCORS(res);

        // set cache header if not a forced update
        if (!forceUpdate)
            setResponsePublicCacheHeaders(res, API_CACHE_SECONDS);

        // create result objects
        String responseJsonString = gson.toJson(responseData);

        // send on wire
        res.getWriter().println(responseJsonString);
        // }}}
    }

    private boolean isDevelopmentMode(HttpServletRequest req) // {{{
    {
        boolean dev = false;

        if (SystemProperty.environment.value() == SystemProperty.Environment.Value.Development)
            dev = true;
        if (req.getServerName().equals(FRONTEND_HOSTNAME_DEVELOPMENT))
            dev = true;

        return dev;
    } // }}}

    private void proxyRequestToMarketsApi(HttpServletRequest req, HttpServletResponse res, HTTPMethod reqMethod, String bisqMarketsURI, Class incomingRequestBean, Class outgoingRequestResponseBean) // {{{
    throws IOException
    {
        // sanitize json bodies by parsing to JSON bean objects
        Object incomingRequestData = null;
        Object outgoingRequestResponseData = null;
        String sanitizedReqBody = null;
        String queryString = req.getQueryString();
        String reqBody = null;

        if (incomingRequestBean != null)
        {
            try // parse json body of incoming request
            {
                reqBody = getBodyFromRequest(req);
                LOG.log(Level.WARNING, "Got body: "+reqBody);
                incomingRequestData = gson.fromJson(reqBody, incomingRequestBean);
            }
            catch (Exception e)
            {
                LOG.log(Level.WARNING, "Unable to parse body of incoming request");
                e.printStackTrace();
                res.sendError(400);
                // TODO: send json body as error response
                return;
            }

            // convert sanitized request body back to json and send in outgoing request to backend
            sanitizedReqBody = gson.toJson(incomingRequestData);
        }

        if (reqMethod == HTTPMethod.POST && incomingRequestBean != null && incomingRequestData == null)
        {
            if (req.getHeader("Content-Encoding") != null)
                LOG.log(Level.WARNING, "Content-Encoding is "+req.getHeader("Content-Encoding"));
            if (req.getHeader("Content-Type") != null)
                LOG.log(Level.WARNING, "Content-Type is "+req.getHeader("Content-Type"));
            if (req.getHeader("Content-Length") != null)
                LOG.log(Level.WARNING, "Content-Length is "+req.getHeader("Content-Length"));
            LOG.log(Level.WARNING, "incomingRequestData is null!");
            res.sendError(400);
            return;
        }

        String reqURI = bisqMarketsURI;

        HTTPResponse bisqMarketsResponse = null;
        if (incomingRequestBean == Map.class)
        {
            // pass original raw body to outgoing request
            bisqMarketsResponse = requestData(reqMethod, buildMarketsApiURL(req, reqURI), getSafeHeadersFromRequest(req), reqBody);
        }
        else
        {
            // pass sanitized body to outgoing request
            bisqMarketsResponse = requestData(reqMethod, buildMarketsApiURL(req, reqURI), getSafeHeadersFromRequest(req), sanitizedReqBody);
        }

        if (bisqMarketsResponse == null) // request failed
        {
            res.sendError(503);
            return;
        }

        // get response code of outgoing request, set on incoming request response
        Integer resCode = bisqMarketsResponse.getResponseCode();
        res.setStatus(resCode);

        // pass outgoing request's response's headers
        setResponseHeadersFromBackendResponse(res, bisqMarketsResponse);

        String bisqMarketsResponseBody = null;
        try // parse json body of outgoing request's response
        {
            if (resCode >= 400)
            {
                bisqMarketsResponseBody = getBodyFromResponse(bisqMarketsResponse);
                //outgoingRequestResponseData = new MarketsApiErrorResponse(resCode, null, bisqMarketsResponseBody);
            }
            else if (outgoingRequestResponseBean != null)
            {
                bisqMarketsResponseBody = getBodyFromResponse(bisqMarketsResponse);
                outgoingRequestResponseData = gson.fromJson(bisqMarketsResponseBody, outgoingRequestResponseBean);
            }
        }
        catch (Exception e)
        {
            LOG.log(Level.WARNING, "Unable to parse outgoing request's response json body: "+e.toString());
            LOG.log(Level.WARNING, "Response body: "+bisqMarketsResponseBody);
            e.printStackTrace();
            res.sendError(500);
            // TODO: send json body as error response
            return;
        }

        // send 503 or default empty response object if no body
        if (outgoingRequestResponseData == null)
        {
            //outgoingRequestResponseData = new MarketsApiErrorResponse(resCode);
            res.sendError(503);
            return;
        }

        // pass sanitized body to incoming request's response
        sendResponse(res, outgoingRequestResponseData);
    } //}}}

    private void sendResponse(HttpServletResponse res, Object nugget) // {{{
    throws IOException
    {
        String jsonString = gson.toJson(nugget);
        res.getWriter().println(jsonString);
        // FIXME unescape JSON encoded HTML entities? ie. & is getting sent as \u0026
    } // }}}
    private void sendResponse(HttpServletResponse res, String str) // {{{
    throws IOException
    {
        res.getWriter().println(str);
    } // }}}

    private Map<String, String> getQueryMap(String query) // {{{
    {
        String[] params = query.split("&");
        Map<String, String> map = new HashMap<String, String>();
        for (String param : params)
        {
            String name = param.split("=")[0];
            String value = param.split("=")[1];
            map.put(name, value);
        }
        return map;
    } // }}}
    private String getBodyFromRequest(HttpServletRequest req) // {{{
    {
        String contentType = req.getHeader("Content-Type");

        if (contentType.equals("application/x-www-form-urlencoded"))
        {
            LOG.log(Level.WARNING, "Parsing as application/x-www-form-urlencoded");
            try // convert to json string
            {
                Map<String, String> map = new HashMap<String,String>();
                Map<String, String[]> parameters = req.getParameterMap();
                for (String key : parameters.keySet())
                {
                    String[] values = req.getParameterValues(key);
                    if (values != null)
                    map.put(key, values[0]);
                }
                String jsonString = gson.toJson(map);
                LOG.log(Level.INFO, "Got json string: "+jsonString);
                return jsonString;
            }
            catch (Exception e)
            {
                e.printStackTrace();
                return null;
            }
        }

        // read request body
        StringBuffer sb = new StringBuffer();
        InputStream inputStream = null;
        BufferedReader reader = null;

        String line = null;
        try
        {
            inputStream = req.getInputStream();
            if (inputStream != null)
            {
                reader = new BufferedReader(new InputStreamReader(inputStream));
                char[] charBuffer = new char[65535];
                int bytesRead = -1;
                while ((bytesRead = reader.read(charBuffer)) > 0)
                {
                    sb.append(charBuffer, 0, bytesRead);
                }
            }
            else
            {
                sb.append("");
            }
        }
        catch (Exception e)
        {
            e.printStackTrace();
        }
        finally
        {
            if (reader != null)
            {
                try
                {
                    reader.close();
                }
                catch (IOException e)
                {
                    LOG.log(Level.WARNING, "Unable to read request body");
                    e.printStackTrace();
                    return null;
                }
            }
        }

        // convert payload bytes to string
        String reqBody = sb.toString();
        //if (reqBody != null && !reqBody.isEmpty())
        //LOG.log(Level.WARNING, "got payload: "+reqBody);

        return reqBody;
    } // }}}
    private String getBodyFromResponse(HTTPResponse response) // {{{
    {
        String str = null;

        try // if we got response, convert to UTF-8 string
        {
            str = new String(response.getContent(), "UTF-8");
        }
        catch (UnsupportedEncodingException e)
        {
            LOG.log(Level.WARNING, e.toString(), e);
            str = null;
        }
        return str;
    } // }}}

    private List<HTTPHeader> getSafeHeadersFromRequest(HttpServletRequest req) // {{{
    {
        List<HTTPHeader> headers = new ArrayList<HTTPHeader>();
        String safeHeaders[] = {
            "Cookie"
            ,"User-Agent"
        };

        // copy headers in "safe" list above
        for (String headerName : safeHeaders)
            if (req.getHeader(headerName) != null)
                headers.add(new HTTPHeader(headerName, req.getHeader(headerName)));

        // add original IP header
        /*
        String reqIP = req.getRemoteAddr();
        HTTPHeader headerOriginalIP = new HTTPHeader("X-Original-IP", reqIP);
        headers.add(headerOriginalIP);
        */

        return headers;
    } // }}}
    private void setResponseHeadersFromBackendResponse(HttpServletResponse res1, HTTPResponse res2) // {{{
    {
        List<HTTPHeader> headers = res2.getHeaders();
        String safeHeaders[] = {
            "Set-Cookie"
        };

        for (HTTPHeader header : headers)
            if (Arrays.asList(safeHeaders).contains(header.getName()))
                res1.setHeader(header.getName(), header.getValue());
    } // }}}

    private void setResponseCORS(HttpServletResponse res) // {{{
    {
        res.setHeader("Access-Control-Allow-Headers", "Access-Control-Allow-Headers, Origin, Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers");
        res.setHeader("Access-Control-Allow-Method", "GET,HEAD,OPTIONS,POST,PUT");
        res.setHeader("Access-Control-Allow-Origin", "*");
    } // }}}
    private void setResponsePublicCacheHeaders(HttpServletResponse res, int seconds) // {{{
    {
        res.setDateHeader("Expires", System.currentTimeMillis() + (1000 * seconds) );
        res.setHeader("Cache-Control", "public, max-age="+seconds);
    } // }}}
    private void setResponsePrivateCacheHeaders(HttpServletResponse res, int seconds) // {{{
    {
        res.setDateHeader("Expires", System.currentTimeMillis() + (1000 * seconds) );
        res.setHeader("Cache-Control", "private, max-age="+seconds);
    } // }}}

    private URL buildMarketsApiURL(HttpServletRequest req, String bisqMarketsURI) // {{{
    {
        URL bisqMarketsURL = null;

        try // build bisqMarketsURL
        {
            // determine API endpoint
            String bisqMarketsBaseURL = null;

            if (SystemProperty.environment.value() == SystemProperty.Environment.Value.Development)
            {
                // for local devserver - don't use TLS
                bisqMarketsBaseURL = BISQNODE_HOSTNAME_DEVSERVER;
            }
            else if (req.getServerName().equals(FRONTEND_HOSTNAME_DEVELOPMENT)) // cloud development
            {
                bisqMarketsBaseURL = BISQNODE_HOSTNAME_DEVELOPMENT;
            }
            else // cloud production
            {
                bisqMarketsBaseURL = BISQNODE_HOSTNAME_PRODUCTION;
            }

            bisqMarketsURL = new URL(bisqMarketsBaseURL + "/graphql");
        }
        catch (MalformedURLException e)
        {
            bisqMarketsURL = null;
        }
        return bisqMarketsURL;
    } // }}}

    private Object getCachedMarketsApiData(HttpServletRequest req, String bisqMarketsURI, int secondsToMemcache, boolean useCache, boolean forceUpdate) // {{{
    {
        URL bisqMarketsURL = buildMarketsApiURL(req, bisqMarketsURI);
        Map<String,String> params = new HashMap();
        if (req.getQueryString() != null) {
          params = getQueryMap(req.getQueryString());
        }
        return getCachedData(bisqMarketsURL.toString(), bisqMarketsURI, params, secondsToMemcache, useCache, forceUpdate);
    } // }}}
    private Object getCachedData(String apiURL, String bisqMarketsURI, Map<String,String> queryMap, int secondsToMemcache, boolean useCache, boolean forceUpdate) // {{{
    {
        String response = null;
        Object responseData = null;
        boolean inMemcache = false;
        boolean inDatastore = false;

        // strip forceUpdate=1 if present
        String apiURLstripped = apiURL.replaceAll("&forceUpdate=1", "");

        // {{{ first check memcache, use apiURL as key
        if (useCache)
            response = (String)mc.get(apiURLstripped);

        if (!forceUpdate && response != null)
        {
            responseData = parseJsonData(response);
            if (responseData == null)
                LOG.log(Level.WARNING, "Failed parsing memcache bisqMarkets response for "+apiURL);
            else
                inMemcache = true;
        }
        // }}}
        // {{{ if not in memcache, try request it from backend node
        if (responseData == null)
        {
            LOG.log(Level.WARNING, "Fetching data from bisqMarkets for "+apiURL);

            GraphQLQuery query = GraphQLQuery.forRequest(bisqMarketsURI, queryMap);
            try
            {
                response = requestDataAsString(HTTPMethod.POST, new URL(apiURL), null, gson.toJson(query));
            }
            catch (Exception e)
            {
                response = null;
            }
            if (response != null)
            {
                responseData = query.translateResponse(response);
                if (responseData == null)
                    LOG.log(Level.WARNING, "Failed parsing requested bisqMarkets response for "+apiURL);
            }
        }
        // }}}
        // {{{ if successful, save response in memcache/datastore for next time
        if (response != null && responseData != null)
        {
            if (useCache && !inMemcache)
            {
                if (forceUpdate)
                    mc.put(apiURLstripped, response, Expiration.byDeltaSeconds(secondsToMemcache), SetPolicy.SET_ALWAYS);
                else
                    mc.put(apiURLstripped, response, Expiration.byDeltaSeconds(secondsToMemcache), SetPolicy.ADD_ONLY_IF_NOT_PRESENT);
            }
            if (useCache && !inDatastore)
            {
                Key cacheKey = KeyFactory.createKey(MarketsApiResponseCache.KIND, apiURLstripped);
                Transaction tx = DS.beginTransaction();
                Entity cache = new Entity(cacheKey);
                try
                {
                    cache.setProperty(MarketsApiResponseCache.BISQNODE_URL, apiURLstripped);
                    Text responseText = new Text(response);
                    cache.setProperty(MarketsApiResponseCache.BISQNODE_RESPONSE, responseText);
                    DS.put(tx, cache);
                    tx.commit();
                }
                catch (Exception e)
                {
                    LOG.log(Level.WARNING, e.toString(), e);
                }
                finally
                {
                    if (tx.isActive())
                    {
                        tx.rollback();
                    }
                }
            }
        }
        // }}}
        // {{{ if backend node not available, try querying the datastore
        if (useCache && !forceUpdate && responseData == null)
        {
            Key entityKey = KeyFactory.createKey(MarketsApiResponseCache.KIND, apiURLstripped);
            Filter bisqMarketsURLFilter = new FilterPredicate(Entity.KEY_RESERVED_PROPERTY, FilterOperator.EQUAL, entityKey);
            response = null;
            Entity result = null;
            try
            {
                Query query = new Query(MarketsApiResponseCache.KIND)
                    .setFilter(bisqMarketsURLFilter);
                PreparedQuery pq = DS.prepare(query);
                result = pq.asSingleEntity();
            }
            catch (Exception e)
            {
                LOG.log(Level.WARNING, e.toString(), e);
                return null;
            }
            if (result != null)
            {
                Text responseText = (Text)result.getProperty(MarketsApiResponseCache.BISQNODE_RESPONSE);
                response = (String)responseText.getValue();
                responseData = parseJsonData(response);
                if (responseData == null)
                    LOG.log(Level.WARNING, "Failed parsing datastore bisqMarkets response for "+apiURL);
                else
                    inDatastore = true;
            }
        }
        //}}}

        return responseData;
    } // }}}

    private Object parseJsonData(String jsonRaw) // {{{
    {
        Object jsonData = null;
        try // {{{ parse as JSON object
        {
            jsonData = new Gson().fromJson(jsonRaw, Map.class);
        }
        catch (JsonSyntaxException e1)
        {
            try // parse as JSON array
            {
                jsonData = new Gson().fromJson(jsonRaw, ArrayList.class);
            }
            catch (JsonSyntaxException e2)
            {
                LOG.log(Level.WARNING, e2.toString(), e2);
                jsonData = null;
            }
        } // }}}
        return jsonData;
    } // }}}

    private String requestMarketsApiData(HttpServletRequest req, HTTPMethod requestMethod, String bisqMarketsURI, List<HTTPHeader> headers, String body) // {{{
    {
        URL bisqMarketsURL = buildMarketsApiURL(req, bisqMarketsURI);
        return requestDataAsString(requestMethod, bisqMarketsURL, headers, body);
    } // }}}

    private String requestDataAsString(HTTPMethod requestMethod, URL bisqMarketsURL, List<HTTPHeader> headers, String body) // {{{
    {
        String bisqMarketsResponse = null;
        HTTPResponse response = requestData(requestMethod, bisqMarketsURL, headers, body);
        if (response != null && (response.getResponseCode() == HttpURLConnection.HTTP_OK || response.getResponseCode() == HttpURLConnection.HTTP_CREATED))
            bisqMarketsResponse = getBodyFromResponse(response);
        return bisqMarketsResponse;
    } // }}}

    private HTTPResponse requestData(HTTPMethod requestMethod, URL bisqMarketsURL, List<HTTPHeader> headers, String body) // {{{
    {
        HTTPRequest request = new HTTPRequest(bisqMarketsURL, requestMethod, withDefaults().setDeadline(REQUEST_DEADLINE));
        HTTPResponse response = null;

        try
        {
            response = requestDataFetch(request, headers, body);
        }
        catch (IOException e)
        {
            // TODO log error requesting
            LOG.log(Level.WARNING, e.toString(), e);
            try
            {
                response = requestDataFetch(request, headers, body);
            }
            catch (Exception ee)
            {
                // TODO log error requesting
                response = null;
                LOG.log(Level.WARNING, ee.toString(), ee);
            }
        }
        catch (Exception e)
        {
            // TODO log error requesting
            response = null;
            LOG.log(Level.WARNING, e.toString(), e);
        }
        return response;
    } // }}}
    private HTTPResponse requestDataFetch(HTTPRequest request, List<HTTPHeader> headers, String body) // {{{
    throws IOException, UnsupportedEncodingException
    {
        URLFetchService urlFetchService = URLFetchServiceFactory.getURLFetchService();

        if (headers != null)
            for (HTTPHeader header : headers)
                request.setHeader(header);

        if (body != null)
        {
            request.setHeader(new HTTPHeader("Content-type", "application/json"));
            request.setPayload(body.getBytes("UTF-8"));
        }

        return urlFetchService.fetch(request);
    } // }}}
}

// vim: ts=4:expandtab:foldmethod=marker wrap
