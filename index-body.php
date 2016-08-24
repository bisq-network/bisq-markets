<?php

require_once( dirname( __FILE__) . '/lib/html_table.class.php' );
require_once( dirname( __FILE__) . '/lib/trades.class.php' );
require_once( dirname( __FILE__) . '/lib/summarize_trades.class.php' );
require_once( dirname( __FILE__) . '/lib/markets.class.php' );
require_once( dirname( __FILE__) . '/lib/offers.class.php' );

ini_set('memory_limit', '1G');         // just in case.
date_default_timezone_set ( 'UTC' );   // all dates expressed in UTC.

$market = @$_GET['market'];
$allmarkets = @$_GET['allmarkets'];

try {

    // get list of markets.    
    $marketservice = new markets();
    $markets_result = $allmarkets ? $marketservice->get_markets() : $marketservice->get_markets_with_trades();
    
    // Sort by currency name.  ( where currency is non-btc side of market )
    uasort( $markets_result, function( $a, $b ) {
        $aname = $a['lsymbol'] == 'BTC' ? $a['rname'] : $a['lname'];
        $bname = $b['lsymbol'] == 'BTC' ? $b['rname'] : $b['lname'];
        return strcmp( $aname, $bname );
    });

    // Default to eur market for now.
    if( !$market || !@$markets_result[$market]) {
        $market = "btc_eur";
    }
    $market_name = strtoupper( str_replace( '_', '/', $market));
    list( $curr_left, $curr_right ) = explode( '/', $market_name, 2);
    $currmarket = $markets_result[$market];
    
    // Obtain market summary info for today only.
    $summarize_trades = new summarize_trades();
    $market_result = $summarize_trades->get_trade_summaries_days( ['market' => $market,
                                                                    'datetime_from' => strtotime( 'today 00:00:00' ),
                                                                    'datetime_to' => strtotime( 'today 23:59:00' ),
                                                                    'limit' => 1
                                                                   ] );
    
    // create market select control.
    $allparam = $allmarkets ? '&allmarkets=1' : '';
    $market_select = sprintf( "<select onchange='document.location.replace(\"?market=\" + this.options[this.selectedIndex].value+\"%s\")'>%s\n", $allparam, $market );
    foreach( $markets_result as $id => $m ) {
        $symbol = $m['lsymbol'] == 'BTC' ? $m['rsymbol'] : $m['lsymbol'];
        $name = $m['lsymbol'] == 'BTC' ? $m['rname'] : $m['lname'];
        $market_select .= sprintf( "<option value=\"%s\"%s>%s (%s)</option>\n", $id, $id == $market ? ' selected' : '', $name, $symbol );
    }
    $market_select .= "</select>\n";
    
    $latest = @$market_result[0];

    if( $latest ) {
        $market_result = ['choose' => $market_select, 
                          'market'=>  $market_name,
                          'market_date'=> date('Y-m-d'),
                          'open'=> display_btc( $latest['open'] ),
                          'last'=> display_btc( $latest['close'] ),
                          'high'=> display_btc( $latest['high'] ),
                          'low'=> display_btc( $latest['low'] ),
                          'avg'=> display_btc( $latest['avg'] ),
                          'volume' => display_btc( $latest['volume'] ) . " " . $curr_right
                         ];
    }
    else {
        $market_result = ['choose' => $market_select, 
                          'market'=>  $market_name,
                          'market_date'=> date('Y-m-d'),
                          'open'=> '--',
                          'last'=> '--',
                          'high'=> '--',
                          'low'=> '--',
                          'avg'=> '--',
                          'volume' => '--'
                        ];
    }

    // get latest trades.
    $trades = new trades();
    $trades_result = $trades->get_trades( [ 'market' => $market,
                                            'limit'  => 100 ] );
    
    // get latest buy offers.
    $offers = new offers();
    
    // kludge: the bitsquare app defines direction based on buy/sell of bitcoin, not secondary market.
    // if/when bitsquare provides direction based on left-side (secondary) then this kludge can be removed.
    $offers_buy_result = $offers->get_offers( [ 'market' => $market,
                                                'direction' => $curr_left == 'BTC' ? 'BUY' : 'SELL',
                                                'limit'  => 100 ] );
    usort( $offers_buy_result, function($a, $b) {
        if( $b['price'] == $a['price'] ) {
            return 0;
        }
        return $b['price'] < $a['price'] ? -1 : 1;
    });
        
    $offers_sell_result = $offers->get_offers( [ 'market' => $market,
                                                 'direction' => $curr_left == 'BTC' ? 'SELL' : 'BUY',
                                                 'limit'  => 100 ] );
    usort( $offers_sell_result, function($a, $b) {
        if( $b['price'] == $a['price'] ) {
            return 0;
        }
        return $b['price'] > $a['price'] ? -1 : 1;
    });
    
    // add running totals for primary market.
    foreach( [&$offers_buy_result, &$offers_sell_result] as &$results ) {
        $sum = 0;
        foreach( $results as &$row ) {
            $sum += $row['total'];
            $row['sum'] = $sum;
        }
    }
}
catch( Exception $e ) {
//  for dev/debug.
    if( @$_GET['debug'] ) {
        _global_exception_handler( $e ); 
    }
    include(dirname(__FILE__) . '/404.html');
}


$table = new html_table();
$table->timestampjs_col_names['tradeDate'] = true;

function display_btc($val, $row = null) {
    return number_format( $val, 8 );
}
function display_crypto($val, $row = null) {
    return number_format( $val / 100000000, 8 );
}
function display_fiat($val, $row = null) {
    return number_format( $val / 10000, 8 );
}
function display_cryptotimesfiat($val, $row = null) {
    return number_format( $val / 1000000000000, 8 );
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
                              array( 'Currency', 'Bitsquare Market', 'Market Date (UTC)', "Open", "Last", "High", "Low", "Avg", "Volume" ),
                              array( 'choose', 'market', 'market_date', 'open', 'last', 'high', 'low', 'avg', 'volume' ) ); ?>

<?php if( !count( $trades_result ) ): ?>
<div class="widget" style="margin-top: 15px; text-align: center;">
    There have been no trades in this market recently.   You can get the ball rolling by placing an order now.
</div>
<?php else: ?>
<div class='widget' style="margin-top: 15px;">
<div id="container"></div>
</div>

<?php $table->table_attrs = array( 'class' => 'unbordered' ); ?>
<table width="100%" cellpadding="0" class="unbordered" style="margin-bottom: 20px">
<tr><th style="padding-right: 10px">Buy <?= $curr_left ?> Offers</th>
    <th style="padding-left: 10px">Sell <?= $curr_left ?> Offers</th></tr>
<tr>
    <td style="padding-right: 10px">
        <div class="offers widget">
<?= $table->table_with_header( $offers_buy_result,
                               array( 'Price', $curr_left, $curr_right, "Sum($curr_right)" ),
                               [ 'price' => ['cb_format' => 'display_fiat'],
                                 'amount' => ['cb_format' => 'display_crypto'],
                                 'total' => ['cb_format' => 'display_cryptotimesfiat'],
                                 'sum' => ['cb_format' => 'display_cryptotimesfiat']
                               ] );
                               
?>
        </div>
    </td>
    <td style="padding-left: 10px">
        <div class="offers widget">
<?= $table->table_with_header( $offers_sell_result,
                               array( 'Price', $curr_left, $curr_right, "Sum($curr_right)" ),
                               [ 'price' => ['cb_format' => 'display_fiat'],
                                 'amount' => ['cb_format' => 'display_crypto'],
                                 'total' => ['cb_format' => 'display_cryptotimesfiat'],
                                 'sum' => ['cb_format' => 'display_cryptotimesfiat']
                               ] );
?>
        </div>
    </td>
</tr>

</table>
                    
<table width="100%" cellpadding="0" cellspacing="0" class="unbordered"><tr><th>Trade History</th><th align="right">( Last <?= count($trades_result) ?> trades )</th></tr></table>
<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'trade_history' ); ?>
<div id="trade_history_scroll">

<?= $table->table_with_header( $trades_result,
                              array( 'Date', 'Action', 'Price', "$curr_left", "$curr_right" ),
                              array( 'tradeDate',
                                     'direction',
                                     'tradePrice' => ['cb_format' => 'display_fiat'],
                                     'tradeAmount' => ['cb_format' => 'display_crypto'],
                                     'total' => ['cb_format' => 'display_cryptotimesfiat'] )
                              ); ?>
</div>

<script type="text/javascript">
createStockChart();


function createStockChart() {

function server_base_url(args) {
    return 'api/hloc?market=<?= $market ?>&milliseconds=true&timestamp=no&format=jscallback&fillgaps=<?= @$_GET['fillgaps'] ?>&callback=?';
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
        
        var ohlc = [],
            volume = [],
            avg = [],
            dataLength = data.length;
            
        for (i = 0; i < dataLength; i++) {
            ohlc.push([
                data[i][0], // the date
                data[i][1], // open
                data[i][2], // high
                data[i][3], // low
                data[i][4] // close
            ]);
            avg.push([
                data[i][0], // the date
                data[i][6]  // the average
            ]);
            volume.push([
                data[i][0], // the date
                data[i][5] // the volume
            ]);
        }                

        chart.series[0].setData(ohlc);
        chart.series[1].setData(avg);
        chart.series[2].setData(volume);
        
        chart.hideLoading();
        
        // call it again after 5 minutes
        setTimeout(requestData, polling_time());
    });
}

$(function () {
    
    Highcharts.setOptions({
        global: {
            useUTC: false   // we want user's localtime.
        }
    });    

    // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
    $.getJSON(server_url(), function (data) {

            var ohlc = [],
                volume = [],
                avg = [],
                dataLength = data.length;
                
            var groupingUnits = [
                [
                    'hour',                         // unit name
                    [1,6,12]                        // allowed multiples
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
                ]];
                
                
            for (i = 0; i < dataLength; i++) {
                ohlc.push([
                    data[i][0], // the date
                    data[i][1], // open
                    data[i][2], // high
                    data[i][3], // low
                    data[i][4] // close
                ]);
                avg.push([
                    data[i][0], // the date
                    data[i][6]  // the average
                ]);
                volume.push([
                    data[i][0], // the date
                    data[i][5] // the volume
                ]);
            }                


        // create the chart
        $('#container').highcharts('StockChart', {
            chart : {
                type: 'candlestick',
                zoomType: 'x',
            },
            
            plotOptions: {
                series: {
                    // limit the maximum column width.
                    maxPointWidth: 20
                },
                candlestick: {
                    color: 'red',
                    upColor: 'green'
                },
                column: {
                }
            },

            yAxis: [{
                title: {
                    text: '<b>Price (<?= $market_name ?>)</b>'
                },
                height: 200,
                lineWidth: 2
            }, {
                title: {
                    text: '<b>Volume (<?= $curr_right ?>)</b>'
                },
                top: 290,
                height: 95,
                offset: 0,
                lineWidth: 2
            }],
            
            navigator : {
                series : {
                    data : data
                }
            },

            scrollbar: {
                liveRedraw: true
            },

            title: {
                text: 'Bitsquare : <?= $market_name ?> Market'
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
                    
                    txt += '<span style="font-size: 10px"><b>' + Highcharts.dateFormat( date_format, point.x) + ' - <?= $curr_right ?></b></span><br/>';
                    var empty_buf = txt + "No trades";

                    var found = false;
                    each(points, function(p, i) {
                        if(p.point && p.point.open) {
                            var curr = '<?= $curr_left ?>';
                            txt += '<b>Open</b>: ' + Highcharts.numberFormat( p.point.open, 8 ) +
                                   '<br/><b>High</b>: ' + Highcharts.numberFormat( p.point.high, 8 ) +
                                   '<br/><b>Low</b>: ' + Highcharts.numberFormat( p.point.low, 8 ) +
                                   '<br/><b>Close</b>: ' + Highcharts.numberFormat( p.point.close, 8 ) +'<br/><br/>';
                            found = true;
                        }
<?php /*                        
                        note: disabling display of average in tooltip because highcharts grouping uses a simple
                        average function that does not take into account quantity traded at each price. So the grouped
                        average would be different from what is displayed in the daily market summary table and the java app.
                        also the dataGrouping.approximation function does not accept additional params that would
                        enable us to calculate ourselves, even if we had necessary input data from server.
*/?>
                        else if( p.series.name != 'Avg' ) {
                            var curr = p.series.name == 'Avg' ? '<?= $curr_left ?>' : '<?= $curr_right ?>';
                            txt +=  "<b>" + p.series.name + '</b>: ' + Highcharts.numberFormat(p.y, 8) + '<br/>';
                        }
                    });
                
                    return found ? txt : empty_buf;
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

            xAxis : {
                // permit gaps in data.
                ordinal: false
            },

            series: [
                {
                    type: 'candlestick',
                    name: 'ohlc',
                    data: ohlc,
                    dataGrouping: {
                        units: groupingUnits,
                        groupPixelWidth: 40,
                        enabled: true,
                        forced: true                        
                    }                    
                },
                {
                    type: 'spline',
                    name: 'Avg',
                    data: avg,
                    dataGrouping: {                        
                        units: groupingUnits,
                        groupPixelWidth: 40,
                        enabled: true,
                        forced: true
                    }                    
                },
                {
                    type: 'column',
                    name: 'Vol',
                    data: volume,
                    yAxis: 1,
                    dataGrouping: {
                        units: groupingUnits,
                        groupPixelWidth: 40,
                        enabled: true,
                        forced: true                        
                    }                    
                }
            ]
        });
    });
});
}

</script>

<?php endif; ?>


