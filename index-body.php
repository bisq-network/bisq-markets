<?php

require_once( dirname( __FILE__) . '/lib/html_table.class.php' );
require_once( dirname( __FILE__) . '/lib/trades.class.php' );
require_once( dirname( __FILE__) . '/lib/summarize_trades.class.php' );
require_once( dirname( __FILE__) . '/lib/markets.class.php' );
require_once( dirname( __FILE__) . '/lib/offers.class.php' );
require_once( dirname( __FILE__) . '/lib/primary_market.class.php' );

ini_set('memory_limit', '1G');         // just in case.
date_default_timezone_set ( 'UTC' );   // all dates expressed in UTC.

$market = @$_GET['market'];
$allmarkets = @$_GET['allmarkets'];
$pmarket = @$_GET['pmarket'];

// normalize market=all to null.
if( $market == 'all') {
    $market = null;
}

try {
    $pmarket = $market ? primary_market::determine_primary_market_symbol_from_market($market) :
                         primary_market::get_normalized_primary_market_symbol($pmarket);
                         
    $network = primary_market::get_network($pmarket);
    
    // get list of primary markets.
    $primary_markets = primary_market::get_primary_market_list();

    // get list of markets.    
    $marketservice = new markets($network);
    $markets_result = $allmarkets ? null : $marketservice->get_markets_with_trades_or_offers($pmarket);

    // in case no markets have trades, we get all markets then.
    if( !$markets_result ) {
        $markets_result = $marketservice->get_markets($pmarket);
    }

    // Sort by currency name.  ( where currency is non-btc side of market )
    uasort( $markets_result, function( $a, $b ) use($pmarket) {
        $aname = $a['lsymbol'] == $pmarket ? $a['rname'] : $a['lname'];
        $bname = $b['lsymbol'] == $pmarket ? $b['rname'] : $b['lname'];
        return strcmp( $aname, $bname );
    });

    // create primary market select control.
    $allparam = $allmarkets ? '&allmarkets=1' : '';
    $pmarket_select = sprintf( "<select onchange='document.location.replace(\"?pmarket=\" + this.options[this.selectedIndex].value+\"%s\")'>%s\n", $allparam, $pmarket );
    foreach( $primary_markets as $pm ) {
        $pmarket_select .= sprintf( "<option value=\"%s\"%s>%s</option>\n", $pm, $pm == $pmarket ? ' selected' : '', $pm );
    }
    $pmarket_select .= "</select>\n";

    
    // create market select control.
    $market_select = sprintf( "<select onchange='document.location.replace(\"?market=\" + this.options[this.selectedIndex].value+\"%s\")'>%s\n", $allparam, $market );
    $market_select .= sprintf( "<option value=\"all\"%s>All Currency Summary</option>\n", $market == 'all' ? ' selected' : '' );
    foreach( $markets_result as $id => $m ) {
        $symbol = $m['lsymbol'] == $pmarket ? $m['rsymbol'] : $m['lsymbol'];
        $name = $m['lsymbol'] == $pmarket ? $m['rname'] : $m['lname'];
        $market_select .= sprintf( "<option value=\"%s\"%s>%s (%s)</option>\n", $id, $id == $market ? ' selected' : '', $name, $symbol );
    }
    $market_select .= "</select>\n";
    
    
    // Display all markets summary if market not found.
    if( !@$markets_result[$market]) {
        require_once( 'index-body-summary.php' );
        return;
    }
    
    $market_name = strtoupper( str_replace( '_', '/', $market));
    list( $curr_left, $curr_right ) = explode( '/', $market_name, 2);
    $currmarket = $markets_result[$market];
    
    // Obtain market summary info for today only.
    $summarize_trades = new summarize_trades($network);
    $market_result = $summarize_trades->get_trade_summaries_days( ['market' => $market,
                                                                    'datetime_from' => strtotime( 'today 00:00:00' ),
                                                                    'datetime_to' => strtotime( 'today 23:59:00' ),
                                                                    'limit' => 1
                                                                   ] );
    
    $latest = @$market_result[0];

    if( $latest ) {
        $market_result = ['pchoose' => $pmarket_select,
                          'choose' => $market_select, 
                          'market'=>  $market_name,
                          'market_date'=> date('Y-m-d'),
                          'open'=> display_currency( $latest['open'], $curr_right ),
                          'last'=> display_currency( $latest['close'], $curr_right ),
                          'high'=> display_currency( $latest['high'], $curr_right ),
                          'low'=> display_currency( $latest['low'], $curr_right ),
                          'avg'=> display_currency( $latest['avg'], $curr_right ),
                          'volume_right' => display_currency( $latest['volume_right'], $curr_right ) . " " . $curr_right
                         ];
    }
    else {
        $market_result = ['pchoose' => $pmarket_select,
                          'choose' => $market_select, 
                          'market'=>  $market_name,
                          'market_date'=> date('Y-m-d'),
                          'open'=> '--',
                          'last'=> '--',
                          'high'=> '--',
                          'low'=> '--',
                          'avg'=> '--',
                          'volume_right' => '--'
                        ];
    }

    // get latest trades.
    $trades = new trades($network);
    $trades_result = $trades->get_trades( [ 'market' => $market,
                                            'limit'  => 100 ] );
    
    // get latest buy offers.
    $offers = new offers($network);
    
    $offers_buy_result = $offers->get_offers( [ 'market' => $market,
                                                'direction' => 'BUY',
                                                'sort' => 'desc',
                                                'limit'  => 100 ] );
        
    $offers_sell_result = $offers->get_offers( [ 'market' => $market,
                                                 'direction' => 'SELL',
                                                 'sort' => 'asc',
                                                 'limit'  => 100 ] );
    
    // add running totals for primary market.
    foreach( [&$offers_buy_result, &$offers_sell_result] as &$results ) {
        $sum = 0;
        foreach( $results as &$row ) {
            $sum += $row['volume'];
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
$table->htmlescape = false;
$table->timestampjs_col_names['tradeDate'] = true;

function display_currency( $val, $symbol, $is_int=true ) {
    global $currmarket, $curr_left;
    $key = $symbol == $curr_left ? 'lprecision' : 'rprecision';
    $precision = $currmarket[$key];
    $val = $is_int ? $val / 100000000 : $val;
    return number_format( $val, $precision );    
}

function display_currency_leftside( $val, $row ) {
    list($left, $right) = explode( '/', $row['currencyPair'] );
    return display_currency( $val, $left );
}

function display_currency_rightside( $val, $row ) {
    list($left, $right) = explode( '/', $row['currencyPair'] );
    return display_currency( $val, $right);
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
    border: solid 1px #ccc;
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

<?php if( count( $trades_result )): ?>
<div class='widget' style="margin-top: 15px;">
<div id="container"></div>
</div>
<?php endif ?>

<?php if( !count($trades_result) && !count( $offers_buy_result ) && !count($offers_sell_result)): ?>
<?php else: ?>

<?php $table->table_attrs = array( 'class' => 'unbordered' ); ?>
<table width="100%" cellpadding="0" class="unbordered" style="margin-top: 20px">
<tr><th style="padding-right: 10px">Buy <?= $curr_left ?> Offers</th>
    <th style="padding-left: 10px">Sell <?= $curr_left ?> Offers</th></tr>
<tr>
    <td style="padding-right: 10px; width: 50%">
        <div class="offers widget">
<?= $table->table_with_header( $offers_buy_result,
                               array( 'Price', $curr_left, $curr_right, "Sum($curr_right)" ),
                               [ 'price' => ['cb_format' => 'display_currency_rightside'],
                                 'amount' => ['cb_format' => 'display_currency_leftside'],
                                 'volume' => ['cb_format' => 'display_currency_rightside'],
                                 'sum' => ['cb_format' => 'display_currency_rightside']
                               ] );
                               
?>
        </div>
    </td>
    <td style="padding-left: 10px">
        <div class="offers widget">
<?= $table->table_with_header( $offers_sell_result,
                               array( 'Price', $curr_left, $curr_right, "Sum($curr_right)" ),
                               [ 'price' => ['cb_format' => 'display_currency_rightside'],
                                 'amount' => ['cb_format' => 'display_currency_leftside'],
                                 'volume' => ['cb_format' => 'display_currency_rightside'],
                                 'sum' => ['cb_format' => 'display_currency_rightside']
                               ] );
?>
        </div>
    </td>
</tr>

</table>
<?php endif ?>

                   
<?php if( !count( $trades_result ) ): ?>
<div class="widget" style="margin-top: 0px; text-align: center;">
    There have been no trades in this market recently.
</div>
<?php else: ?>

<table width="100%" cellpadding="0" cellspacing="0" class="unbordered" style="margin-top: 0px"><tr><th>Trade History</th><th align="right">( Last <?= count($trades_result) ?> trades )</th></tr></table>
<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'trade_history', 'style'=>"border: none; box-shadow: none" ); ?>
<div id="trade_history_scroll">

<?= $table->table_with_header( $trades_result,
                              array( 'Date', 'Action', 'Price', "$curr_left", "$curr_right" ),
                              array( 'tradeDate',
                                     'direction' => ['cb_format' => function($val, $r) {$curr_left; return $val . ' ' .  $GLOBALS['curr_left'];}],
                                     'tradePrice' => ['cb_format' => 'display_currency_rightside'],
                                     'tradeAmount' => ['cb_format' => 'display_currency_leftside'],
                                     'tradeVolume' => ['cb_format' => 'display_currency_rightside'] )
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
                data[i][7]  // the average
            ]);
            volume.push([
                data[i][0], // the date
                data[i][6]  // the volume_right
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

function ucfirst(s) {
    return s && s[0].toUpperCase() + s.slice(1);
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
                    data[i][4]  // close
                ]);
                avg.push([
                    data[i][0], // the date
                    data[i][7]  // the average
                ]);
                volume.push([
                    data[i][0], // the date
                    data[i][6]  // the volume_right
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
                text: 'Bisq : <?= $market_name ?> Market'
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
                    
                    var emptyline = '<span style="visibility: hidden;">-</span><br/>';
                    txt += '<span style="font-size: 10px"><b>' + Highcharts.dateFormat( date_format, point.x) + '</b></span><br/>';
                    txt += emptyline;
                    var empty_buf = txt + "No trades";

                    var found = false;
                    var rprecision = <?= $currmarket['rprecision'] ?>;
                    each(points, function(p, i) {
                        if(p.point && p.point.open) {
                            var curr = '<?= $curr_right ?>';
                            txt +=      '<b>Open</b>: '  + Highcharts.numberFormat( p.point.open, rprecision, '.', ',' ) +
                                   '<br/><b>High</b>: '  + Highcharts.numberFormat( p.point.high, rprecision, '.', ',' ) +
                                   '<br/><b>Low</b>: '   + Highcharts.numberFormat( p.point.low, rprecision, '.', ',' ) +
                                   '<br/><b>Close</b>: ' + Highcharts.numberFormat( p.point.close, rprecision, '.', ',' ) +'<br/><br/>';
                            found = true;
                        }
<?php /*                        
                        note: disabling display of average in tooltip because highcharts grouping uses a simple
                        average function that does not take into account quantity traded at each price. So the grouped
                        average would be different from what is displayed in the daily market summary table and the java app.
                        also the dataGrouping.approximation function does not accept additional params that would
                        enable us to calculate ourselves, even if we had necessary input data from server.
*/?>
                        else if( p.series.name == 'Vol' ) {
                            var curr = '<?= $curr_right ?>';
                            txt +=  "<b>" + p.series.name + '</b>: ' + Highcharts.numberFormat(p.y, rprecision, '.', ',') + " " + curr +'<br/>';
                        }
                    });
                    txt += emptyline;
                    txt += '<b>Period</b>: ' + ucfirst(unit) + '<br/>';
                
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
