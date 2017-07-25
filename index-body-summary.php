<?php
require_once( dirname( __FILE__) . '/lib/html_table.class.php' );
require_once( dirname( __FILE__) . '/lib/trades.class.php' );
require_once( dirname( __FILE__) . '/lib/summarize_trades.class.php' );
require_once( dirname( __FILE__) . '/lib/markets.class.php' );
require_once( dirname( __FILE__) . '/lib/primary_market.class.php' );
require_once( dirname( __FILE__) . '/lib/currencies.class.php' );

ini_set('memory_limit', '1G');         // just in case.
date_default_timezone_set ( 'UTC' );   // all dates expressed in UTC.

try {
    $market = 'all';
    $market_name = sprintf('All Currencies (%s Base) ', $pmarket);
    
    $currencies = new currencies($network);
    $fiats = $currencies->get_all_fiat();
    
    // Obtain market summary info for today only.
    $market_result = ['pchoose' => $pmarket_select,
                      'choose' => $market_select, 
                      'market'=>  $market_name,
                      'market_date'=> 'All Time',
                      'open'=> '--',
                      'last'=> '--',
                      'high'=> '--',
                      'low'=> '--',
                      'avg'=> '--',
                      'volume_right' => '--'
                    ];

    // get latest trades.
    $trades = new trades($network);
    $trades_result = $trades->get_trades( [ 'market' => null,
                                            'limit'  => 100 ] );
}
catch( Exception $e ) {
//  for dev/debug.
    if( @$_GET['debug'] ) {
        _global_exception_handler( $e ); 
    }
    include(dirname(__FILE__) . '/404.html');
}


$table = new html_table();
$table->htmlescape = false;
$table->timestampjs_col_names['tradeDate'] = true;


function ibs_basecurrency_meta($market, $use_basecurrency) {
    global $markets_result;
    global $pmarket;
    $market = strtolower( str_replace( '/', '_', $market));
    $currmarket = $markets_result[$market];

    $base_is_left = $pmarket == $currmarket['lsymbol'];
    $use_left = ($base_is_left && $use_basecurrency) ||
                (!$base_is_left && !$use_basecurrency);
    $symbol = $use_left ? $currmarket['lsymbol'] : $currmarket['rsymbol'];

    $key = $use_left ? 'lprecision' : 'rprecision';
    $precision = $currmarket[$key];
    
    return [
        'base_is_left' => $base_is_left,
        'use_left' => $use_left,
        'symbol' => $symbol,
        'precision' => $precision,
    ];
    
}

function ibs_display_currency_pair( $val_left, $val_right, $market, $use_basecurrency, $is_int=true ) {

    $meta = ibs_basecurrency_meta( $market, $use_basecurrency );
    extract( $meta );

    $val = $use_left ? $val_left : $val_right;
    $val = $is_int ? $val / 100000000 : $val;
    return number_format( $val, $precision ) . ' ' . $symbol;
}

function ibs_display_trade_price( $val, $row ) {

    $meta = ibs_basecurrency_meta( $row['currencyPair'], $use_basecurrency = false );
    extract( $meta );
    
    $val =  $val / 100000000;
    return number_format( $val, $precision ) . ' ' . $symbol;
}

function ibs_display_tradeamount_base( $val, $row ) {
    return ibs_display_currency_pair( $row['tradeAmount'], $row['tradeVolume'], $row['currencyPair'], true );
}

function ibs_display_tradeamount_other( $val, $row ) {
    return ibs_display_currency_pair( $row['tradeAmount'], $row['tradeVolume'], $row['currencyPair'], false );
}

?>
<link rel="stylesheet" href="css/styles.css" type="text/css">
<script type="text/javascript" src="//code.jquery.com/jquery-1.9.1.js"></script>
<script src="https://code.highcharts.com/stock/highstock.js"></script>
<script src="https://code.highcharts.com/stock/modules/exporting.js"></script>
<?php require_once( dirname( __FILE__) . '/widgets/timezone-js.html' ); ?>
<style>
#trade_history_scroll {
    display: block;
    height: 30em;
    overflow-y: auto;
    border-bottom: solid 1px #ccc;
    -webkit-box-shadow: 0 2px 2px #ccc;
    -moz-box-shadow: 0 2px 6px #ccc;
    box-shadow: 0 2px 6px #ccc;
}

.offers {
    height: 21em;
    overflow-y: auto;
}
.offers {
    text-align: right;
}


#container {
    height: 500px;
}
#market_info td {
    text-align: center;
}
</style>

<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'market_info' ); ?>
<?= $table->table_with_header( array( $market_result ),
                              array( 'Base Currency', 'Currency', 'Market', 'Market Date (UTC)', "Open", "Last", "High", "Low", "Avg", "Volume" ),
                              array( 'pchoose', 'choose', 'market', 'market_date', 'open', 'last', 'high', 'low', 'avg', 'volume_right' ) ); ?>

<?php if( !count( $trades_result ) ): ?>
<div class="widget" style="margin-top: 15px; text-align: center;">
    There have been no trades in this market recently.   You can get the ball rolling by placing an order now.
</div>
<?php else: ?>
<div class='widget' style="margin-top: 15px;">
<div id="container"></div>
</div>

<table width="100%" cellpadding="0" cellspacing="0" class="unbordered"><tr><th>Trade History</th><th align="right">( Last <?= count($trades_result) ?> trades )</th></tr></table>
<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'trade_history' ); ?>
<div id="trade_history_scroll">

<?= $table->table_with_header( $trades_result,
                              array( 'Date', 'Action', 'Price', 'Amount in ' . $pmarket, 'Amount' ),
                              array( 'tradeDate',
                                     'direction' => ['cb_format' => function($val, $r) {return $val;}],
                                     'tradePrice' => ['cb_format' => 'ibs_display_trade_price'],
                                     'tradeAmount' => ['cb_format' => 'ibs_display_tradeamount_base'],
                                     'tradeVolume' => ['cb_format' => 'ibs_display_tradeamount_other'] )
                              ); ?>
</div>

<div style="text-align: center; padding: 10px; padding-top: 25px">
    Looking for automated access to market data?  Check out the <a href="api/">API</a>.
</div>

<script>
    
createStockChart();


function createStockChart() {

function server_base_url() {
    return 'api/volumes?basecurrency=<?= urlencode($pmarket) ?>&milliseconds=true&timestamp=no&format=jscallback&fillgaps=<?= urlencode(@$_GET['fillgaps']) ?>&callback=?';
}

function server_url_args(from, to, interval) {
    return server_base_url() + '&timestamp_from=' + Math.round(from) + '&timestamp_to=' + Math.round(to) + "&interval=" + interval;
}

function server_url() {
    return server_url_args( new Date('2016-01-01').getTime(), new Date().getTime(), 'minute' );
}

function polling_time() {
    // 5 minutes.
    return 5 * 60 * 1000;
}



/**
 * Request data from the server, add it to the graph and set a timeout 
 * to request again
 */
// call it again after 5 minutes
setTimeout(requestData, polling_time());

 
function requestData() {
    $.getJSON(server_url(), function (data) {

        var chart = $('#container').highcharts();
        chart.showLoading('Loading data from server...');
        
        var m = munge_data(data);
        
        chart.series[0].setData(m.volume);
        chart.series[1].setData(m.num_trades);
        
        chart.hideLoading();
        
        // call it again after 5 minutes
        setTimeout(requestData, polling_time());
    });
}

function munge_data(data) {
    var m = {volume: [], num_trades: []};
    
    for (i = 0; i < data.length; i++) {
        m.volume.push([
            data[i][0], // the date
            data[i][1], // the volume
        ]);
        m.num_trades.push([
            data[i][0], // the date
            data[i][2], // num_trades
        ]);
    }
    return m;
}

function sum_cb(series) {
    var s = 0;
    for(var i=0; i < series.length; i++) {
        if(true) {
            s += series[i] * 100000000;
        }
    }
    return s / 100000000;
}

$(function () {
    
    Highcharts.setOptions({
        global: {
            useUTC: false   // we do not want user's localtime.
        }
    });    

    // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
    $.getJSON(server_url(), function (data) {
        
        m = munge_data(data);
        
        var groupingUnits = [
                                [
                                    'hour',                         // unit name
                                    [1]                        // allowed multiples
                                ],
                                [
                                    'day',                         // unit name
                                    [1]                             // allowed multiples
                                ],
                                [
                                    'week',                         // unit name
                                    [1]                             // allowed multiples
                                ],
                                [
                                    'month',
                                    [1]
                                ]
                            ];
               
        // create the chart
        Highcharts.stockChart('container', {
            chart: {
                alignTicks: false
            },
            
            plotOptions: {
                series: {
                    // limit the maximum column width.
                    maxPointWidth: 20
                }
            },
            
            tooltip:{
                formatter: function() {
                    var points = this.point ? Highcharts.splat(this.point) : this.points,
                        point = points[0],
                        each = Highcharts.each,
                        txt = '';
                        
                        
                    var chart = $('#container').highcharts();
                    var unit = chart.series[0].currentDataGrouping.unitName;
                    var date_format;
                    
                    switch( unit ) {
                        case 'day':    date_format = '%B %e, %Y'; break;
                        case 'week':   date_format = '%B %e, %Y'; break;
                        case 'month':   date_format = '%B %Y'; break;
                        case 'year':   date_format = '%B %Y'; break;

                        default:
                            date_format = '%B %e, %Y - %l:%M %p';
                    }
                    
                    txt += '<span style="font-size: 10px"><b>' + Highcharts.dateFormat( date_format, point.x) + '</b></span><br/><br/>\n\n';

                    var rprecision = 8;
                    each(points, function(p) {
//                        console.log(p.series.name);
console.log(p);
                        if( p.series.name == 'Volume' ) {
                            var curr = '<?= $pmarket ?>';
                            txt +=  "<b>" + p.series.name + '</b>: ' + Highcharts.numberFormat(p.y, rprecision, '.', ',') + " " + curr +'<br/>';
                        }
                        if( p.series.name == 'NumTrades' ) {
                            var curr = '<?= $pmarket ?>';
                            txt +=  "<b>" + 'Trades' + '</b>: ' + p.y  +'<br/>';
                        }
                    });
                
                    return txt;
                }
            },            
            
            rangeSelector : {
                buttons: [
                {
                    type: 'hour',
                    count: 1,
                    text: '1h'
                },
                {
                    type: 'day',
                    count: 1,
                    text: '1d'
                },
                {
                    type: 'week',
                    count: 1,
                    text: '1w'
                },
                {
                    type: 'month',
                    count: 1,
                    text: '1m'
                },
                {
                    type: 'year',
                    count: 1,
                    text: '1y'
                },
                {
                    type: 'all',
                    text: 'All'
                }],
                inputEnabled: false, // it supports only days
                selected : 3 // month
            },
    
            title: {
                text: 'Bisq : All <?= $pmarket ?> Base Markets'
            },
    
            xAxis : {
                // permit gaps in data.
                ordinal: false
            },    
            series: [
                        {
                            type: 'spline',
                            name: 'NumTrades',
                            data: m.num_trades,
                            color: 'white',
                            dataGrouping: {                        
                                units: groupingUnits,
                                approximation: sum_cb,
                                groupPixelWidth: 40,
                                enabled: true,
                                forced: true
                            }                    
                        },
                        {
                            type: 'column',
                            name: 'Volume',
                            data: m.volume,
                            dataGrouping: {
                                units: groupingUnits,
                                approximation: sum_cb,
                                groupPixelWidth: 40,
                                enabled: true,
                                forced: true                        
                            },
                        }
            ]
        });
    });
});
}

</script>

<?php endif; ?>
