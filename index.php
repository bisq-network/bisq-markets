<?php

require_once( dirname( __FILE__) . '/lib/html_table.class.php' );
require_once( dirname( __FILE__) . '/lib/trades.class.php' );
require_once( dirname( __FILE__) . '/lib/summarize_trades.class.php' );
require_once( dirname( __FILE__) . '/lib/markets.class.php' );

ini_set('memory_limit', '1G');         // just in case.
date_default_timezone_set ( 'UTC' );   // all dates expressed in UTC.

$market = @$_GET['market'];
$allmarkets = @$_GET['allmarkets'];

try {

    // get list of markets.    
    $marketservice = new markets();
    $markets_result = $allmarkets ? $marketservice->get_markets() : $marketservice->get_markets_with_trades();
    
    // Default to eur market for now.
    if( !$market || !@$markets_result[$market]) {
        $market = "eur_btc";
    }
    $market_name = strtoupper( str_replace( '_', '/', $market));
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
        $market_select .= sprintf( "<option value=\"%s\"%s>%s</option>\n", $id, $id == $market ? ' selected' : '', strtoupper( str_replace('_', '/', $id )) );
    }
    $market_select .= "</select>\n";
    
    $latest = @$market_result[0];
    if( $latest ) {
        $market_result = ['choose' => $market_select, 
                          'market'=>  $currmarket['name'],
                          'market_date'=> date('Y-m-d'),
                          'last'=> $latest['close'],
                          'high'=> $latest['high'],
                          'low'=> $latest['low'],
                          'avg'=> $latest['avg'],
                          'volume' => $latest['volume']
                         ];
    }
    else {
        $market_result = ['choose' => $market_select, 
                          'market'=>  $currmarket['name'],
                          'market_date'=> date('Y-m-d'),
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
}
catch( Exception $e ) {
//  for dev/debug.
  _global_exception_handler( $e ); 
    include(dirname(__FILE__) . '/404.html');
}

list( $curr_left, $curr_right ) = explode( '/', $market_name, 2);

$table = new html_table();
$table->timestampjs_col_names['tradeDate'] = true;

function display_crypto($val, $row) {
    return $val / 100000000;
}
function display_fiat($val, $row) {
    return $val / 10000;
}
function display_cryptotimesfiat($val, $row) {
    return $val / 1000000000000;
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<?php include( dirname(__FILE__) . '/widgets/head.html' ); ?>
<script type="text/javascript" src="//code.jquery.com/jquery-1.9.1.js"></script>
<script src="https://code.highcharts.com/stock/highstock.js"></script>
<script src="https://code.highcharts.com/stock/modules/exporting.js"></script>
<?php require_once( dirname( __FILE__) . '/widgets/timezone-js.html' ); ?>
<style>
#trade_history {
    display: block;
    max-height: 30em;
    height: 30em;
    overflow-y: scroll;
}
#sell_orders td, #buy_orders td, #trade_history td {
    width: 100%;
}
#container {
    height: 500px;
}
#market_info td {
    text-align: center;
}
</style>
</head>
<body>

<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'market_info', 'style' => 'width: 800px' ); ?>
<?= $table->table_with_header( array( $market_result ),
                              array( 'Choose', 'Market', 'Date', "Last", "High", "Low", "Avg", "Volume" ),
                              array( 'choose', 'market', 'market_date', 'last', 'high', 'low', 'avg', 'volume' ) ); ?>

<?php if( !count( $trades_result ) ): ?>
<div class="widget" style="margin-top: 15px; text-align: center;">
    There have been no trades in this market recently.   You can get the ball rolling by placing an order now.
</div>
<?php else: ?>
<div class='widget' style="margin-top: 15px;">
<div id="container"></div>
</div>
                    
<table width="100%" cellpadding="0" cellspacing="0"><tr><td><h3>Trade History</h3></td><td align="right">( Last <?= count($trades_result) ?> trades )</td></tr></table>
<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'trade_history', 'style' => 'width: 800px' ); ?>
<div id="trade_history_scroll">

<?= $table->table_with_header( $trades_result,
                              array( 'Date', 'Action', 'Price', 'Size', 'Total' ),
                              array( 'tradeDate',
                                     'direction',
                                     'tradePrice' => ['cb_format' => 'display_fiat'],
                                     'tradeAmount' => ['cb_format' => 'display_crypto'],
                                     'total' => ['cb_format' => 'display_cryptotimesfiat'] ) ); ?>
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
                text: '<?= $market_name ?> Price History'
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
                    
                    txt += '<span style="font-size: 10px"><b>' + Highcharts.dateFormat( date_format, point.x) + '</b></span><br/>';
                    var empty_buf = txt + "No trades";

                    var found = false;
                    each(points, function(p, i) {
                        if(p.point && p.point.open) {
                            var curr = '<?= $curr_left ?>';
                            txt += '<b>Open</b>: ' + p.point.open + ' ' + curr +
                                   '<br/><b>High</b>: ' + p.point.high + ' ' + curr +
                                   '<br/><b>Low</b>: ' + p.point.low +' ' + curr +
                                   '<br/><b>Close</b>: ' + p.point.close + ' ' + curr +'<br/><br/>';
                            found = true;
                        } else {
                            var curr = p.series.name == 'Avg' ? '<?= $curr_left ?>' : '<?= $curr_right ?>';
                            var precision = p.series.name == 'Avg' ? 2 : 4;
                            txt +=  "<b>" + p.series.name + '</b>: ' + Highcharts.numberFormat(p.y, precision) + " " + curr +'<br/>';
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


</body>
</html>
