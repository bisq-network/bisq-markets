<?php

require_once( dirname( __FILE__) . '/lib/html_table.class.php' );
require_once( dirname( __FILE__) . '/lib/trades.class.php' );
require_once( dirname( __FILE__) . '/lib/summarize_trades.class.php' );

ini_set('memory_limit', '5G');

$market = @$_GET['market'];
if( !$market ) {
    include(dirname(__FILE__) . '/404.html');
}

$max_trade_history = 100;
try {

    $market_result = [['market'=> $market, 'market_date'=> date('c'), 'last'=> 0, 'high'=> 0, 'low'=> 0, 'avg'=> 0, 'volume_left', 'volume_right' => 0]];
/*
    $market_result = $api->call( 'get_market', array( 'api_key' => api_key(), 'market' => $market ) );
    if( !$market_result ) {
        throw new Exception( "Not found" );
    }
*/
    
    $trades = new trades();
    $summarize_trades = new summarize_trades();

    $history_fields = ['volume_left', 'open', 'last', 'high', 'low', 'avg'];

    $history_result = $summarize_trades->get_trade_summaries_days( ['market' => $market,
                                                                    'datetime_from' => $start_period_time = strtotime( '4 week ago + 1 day 00:00:00' ),
                                                                    'datetime_to' => $start_period_time +  (int)floor((time() - $start_period_time)/1800)*1800,
                                                                   ] );
    $trades_result = $trades->get_trades( [ 'market' => $market,
                                            'limit'  => $max_trade_history ] );
    
}
catch( Exception $e ) {
    _global_exception_handler( $e );
    include(dirname(__FILE__) . '/404.html');
}

list( $curr_left, $curr_right ) = explode( '_', $market, 2);

$table = new html_table();
$table->right_align_numeric = true;
$table->table_attrs = array( 'class' => 'bordered', 'id' => 'sell_orders' );

/*
$sells = $table->table_with_header( $result->sell,
                                    array( 'Price', $curr_left, $curr_right, 'Orders' ),
                                    array( 'price', 'size', 'total_price', 'count' ) );

$table->table_attrs = array( 'class' => 'bordered', 'id' => 'buy_orders' );
$buys = $table->table_with_header( $result->buy,
                                    array( 'Price', $curr_left, $curr_right, 'Orders' ),
                                    array( 'price', 'size', 'total_price', 'count' ) );
*/

$table = new html_table();
//$table->td_attrs = array( 'style' => "vertical-align: top;" );

$buttons_row = array( "<table width='100%' cellpadding='0' cellspacing='0'><tr><td valign='bottom'><h3>Sell Orders</h3></td><td valign='bottom' align='right'><a href='place_order.html?market=$market&side=BUY' class='button'>Buy <span class='curr_left'>$curr_left</span></a></td></tr></table>",
                      '&nbsp;',
                      "<table width='100%' cellpadding='0' cellspacing='0'><tr><td valign='bottom'><h3>Buy Orders</h3></td><td valign='bottom' align='right'> <a href='place_order.html?market=$market&side=SELL' class='button'>Sell <span class='curr_left'>$curr_left</span></a></td></tr></table>"
                    );

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
#buy_sell_orders {
    margin-top: 0px;
    margin-bottom: 15px;
    width: 100%;
}

#buy_sell_orders .td-0,  #buy_sell_orders .td-2 {
    width: 380px;
}

#buy_sell_orders .td-1 {
    width: 10px;
}

#sell_orders, #buy_orders {
    width: 100%;
}
#sell_orders {
}
#buy_orders {
}

#sell_orders, #buy_orders, #trade_history {
    display: block;
    max-height: 30em;
    height: 30em;
    overflow-y: scroll;
}
#sell_orders td, #buy_orders td, #trade_history td {
    width: 100%;
}
</style>

</style>
</head>
<body>

<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'market_info', 'style' => 'width: 800px' ); ?>

<?= $table->table_with_header( array( $market_result ),
                              array( 'Market', 'Date', "Last", "High", "Low", "Avg", "Volume ($curr_left)", "Volume ($curr_right)" ),
                              array( 'market', 'market_date', 'last', 'high', 'low', 'avg', 'volume_left', 'volume_right' ) ); ?>

<?php if( count( $trades_result ) && $trades_result[0]['tradeDate'] >= strtotime( 'now - 7 day' ) ): ?>
<div class='widget' style="margin-top: 15px;">
<div id="container"></div>
<div style="clear: both"></div>
</div>
<script>document.getElementById('container').style.height = "400px";</script>
<?php else: ?>
<div class="widget" style="margin-top: 15px; text-align: center;">
    There have been no trades in this market recently.   You can get the ball rolling by placing an order now.
</div>
<?php endif; ?>
                    
<table width="100%" cellpadding="0" cellspacing="0"><tr><td><h3>Trade History</h3></td><td align="right">( Last <?= count($trades_result) ?> trades )</td></tr></table>
<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'trade_history', 'style' => 'width: 800px' ); ?>
<div id="trade_history_scroll">

<?= $table->table_with_header( $trades_result,
                              array( 'Date', 'Action', 'Price', 'Size', 'Total' ),
                              array( 'tradeDate',
                                     'direction' => array( 'cb_format' => function($val, $row) { return sprintf( '<a href="view_trade.html?trade=%s">%s</a>', @$row['offerId'], $val ); } ),
                                     'tradePrice', 'tradeAmount', 'total' ) ); ?>
</div>


		<script type="text/javascript">
            // generateChartData();
            createStockChart2();

			function generateChartData() {
                var data = <?=  json_encode( $history_result ) ?>;

				for (var i = 0; i < data.length; i++) {
                    var d = data[i];

					chartData.push ({
						date: new Date(d.period_start*1000),
						open: d.open,
						close: d.close,
						high: d.high,
						low: d.low,
						volume: d.volume,
						value: d.avg
					});
                    
				}
			}

			function createStockChart2() {
                
$(function () {
//    $.getJSON('https://www.highcharts.com/samples/data/jsonp.php?filename=aapl-ohlcv.json&callback=?', function (data) {
    $.getJSON('api/hloc/?market=dash_btc&callback=?', function (data) {

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
                data[i][4] // close
            ]);

            volume.push([
                data[i][0], // the date
                data[i][5] // the volume
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
return;                
                
$(function () {
    /**
     * Load new data depending on the selected min and max
     */
    function afterSetExtremes(e) {

        var chart = $('#container').highcharts();

        chart.showLoading('Loading data from server...');
        $.getJSON('api/hloc/?market=dash_btc&start=' + Math.round(e.min) +
                '&end=' + Math.round(e.max) + '&callback=?', function (data) {

            chart.series[0].setData(data);
            chart.hideLoading();
        });
    }

    // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
    $.getJSON('api/hloc/?market=dash_btc&callback=?', function (data) {

        // Add a null value for the end date
        data = [].concat(data, [[Date.UTC(2011, 9, 14, 19, 59), null, null, null, null]]);

        // create the chart
        $('#container').highcharts('StockChart', {
            chart : {
                type: 'candlestick',
                zoomType: 'x'
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
                text: 'AAPL history by the minute from 1998 to 2011'
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
                selected : 4 // all
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

</body>
</html>