<?php

require_once( dirname( __FILE__) . '/lib/html_table.class.php' );
require_once( dirname( __FILE__) . '/lib/trades.class.php' );
require_once( dirname( __FILE__) . '/lib/summarize_trades.class.php' );
require_once( dirname( __FILE__) . '/lib/markets.class.php' );

ini_set('memory_limit', '1G');         // just in case.
date_default_timezone_set ( 'UTC' );   // all dates expressed in UTC.

$market = @$_GET['market'];
$allmarkets = @$_GET['allmarkets'];
$market_name = strtoupper( str_replace( '_', '/', $market));

try {

    // get list of markets.    
    $marketservice = new markets();
    $markets_result = $allmarkets ? $marketservice->get_markets() : $marketservice->get_markets_with_trades();
    
    // Default to eur market for now.
    if( !$market || !@$markets_result[$market]) {
        $market = "eur_btc";
    }
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

list( $curr_left, $curr_right ) = explode( '_', $market, 2);

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

$amcharts_cdn = 'https://www.amcharts.com/lib/3';
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

/*                
$(function () {
//    $.getJSON('https://www.highcharts.com/samples/data/jsonp.php?filename=aapl-ohlcv.json&callback=?', function (data) {
    $.getJSON('api/hloc?market=<?= $market ?>&timestamp=no&format=jscallback&callback=?', function (data) {

//    $.getJSON('api/hloc/?market=dash_btc&callback=?', function (data) {

        // split the data set into ohlc and volume
        var ohlc = [],
            volume = [],
            dataLength = data.length,
            // set the allowed units for data grouping
            groupingUnits = [[
                'week',                         // unit name
                [1]                             // allowed multiples
            ], [
                'month',
                [1, 2, 3, 4, 6]
            ]],

            i = 0;

        for (i; i < dataLength; i += 1) {
            ohlc.push([
                data[i][0], // the date
                data[i][1], // open
                data[i][2], // high
                data[i][3], // low
                data[i][4]  // close
            ]);

            volume.push([
                data[i][0], // the date
                data[i][5]  // the volume
            ]);
        }


        // create the chart
        $('#container').highcharts('StockChart', {

            rangeSelector: {
                selected: 1
            },

            title: {
                text: 'AAPL Historical'
            },

            yAxis: [{
                labels: {
                    align: 'right',
                    x: -3
                },
                title: {
                    text: 'OHLC'
                },
                height: '60%',
                lineWidth: 2
            }, {
                labels: {
                    align: 'right',
                    x: -3
                },
                title: {
                    text: 'Volume'
                },
                top: '65%',
                height: '35%',
                offset: 0,
                lineWidth: 2
            }],

            series: [{
                type: 'candlestick',
                name: 'AAPL',
                data: ohlc,
                dataGrouping: {
                    units: groupingUnits
                }
            }, {
                type: 'column',
                name: 'Volume',
                data: volume,
                yAxis: 1,
                dataGrouping: {
                    units: groupingUnits
                }
            }]
        });
    });
});                
*/
                
$(function () {
    /**
     * Load new data depending on the selected min and max
     */
    function afterSetExtremes(e) {

        var chart = $('#container').highcharts();

        chart.showLoading('Loading data from server...');
        $.getJSON('api/hloc?market=<?= $market ?>&milliseconds=true&timestamp=no&format=jscallback&callback=?&timestamp_from=' + Math.round(e.min) +
                '&timestamp_to=' + Math.round(e.max), function (data) {

            chart.series[0].setData(data);
            chart.hideLoading();
        });
    }

    // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
    $.getJSON('api/hloc?market=<?= $market ?>&milliseconds=true&timestamp=no&format=jscallback&callback=?', function (data) {

        // Add a null value for the end date
        // data = [].concat(data, [[Date.UTC(2011, 9, 14, 19, 59), null, null, null, null]]);

        // create the chart
        $('#container').highcharts('StockChart', {
            chart : {
                type: 'candlestick',
                zoomType: 'x'
            },
            
            plotOptions: {
             candlestick: {
                        color: 'red',
                        upColor: 'green'
                    }
                },            

            yAxis: {
                min: 0,
                title: {
                    text: 'Price (<?= $market_name ?>)'
                }
            },
            dataLabels: {
                enabled: true,
                rotation: -90,
                color: 'black',
                align: 'right',
                format: '{point.y:.1f}', // one decimal
                y: 10, // 10 pixels down from the top
                style: {
                    fontSize: '13px',
                    fontFamily: 'Verdana, sans-serif'
                }
            },
            navigator : {
                adaptToUpdatedData: false,
                series : {
                    data : data
                }
            },

            scrollbar: {
                liveRedraw: false
            },

            title: {
                text: '<?= $market_name ?> history'
            },

            subtitle: {
                text: 'Displaying 1.7 million data points in Highcharts Stock by async server loading'
            },

            rangeSelector : {
                buttons: [{
                    type: 'hour',
                    count: 1,
                    text: '1h'
                }, {
                    type: 'day',
                    count: 1,
                    text: '1d'
                }, {
                    type: 'month',
                    count: 1,
                    text: '1m'
                }, {
                    type: 'year',
                    count: 1,
                    text: '1y'
                }, {
                    type: 'all',
                    text: 'All'
                }],
                inputEnabled: false, // it supports only days
                selected : 2 // month
            },

            xAxis : {
                events : {
                    afterSetExtremes : afterSetExtremes
                },
                minRange: 3600 * 1000 // one hour
            },

            yAxis: {
                floor: 0
            },

            series : [{
                data : data,
                dataGrouping: {
                    enabled: false
                }
            }]
        });
    });
});
}

</script>

<?php endif; ?>


</body>
</html>
