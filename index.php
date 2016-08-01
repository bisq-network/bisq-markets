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

function get_interval(start, end) {
    // Note: this logic mirrors the logic in api/hloc/index.php
    var range = (end - start) / 1000;   // in seconds.
    console.log(range);
    if(range <= 3600) {
        // up to one hour range loads minutely data  ( 60 / hour )
        return 'minutes';
    }
    else if(range <= 1 * 24 * 3600) {
        // up to one day range loads half-hourly data  ( 48 / day )
        return 'half_hours';
    }
    else if(range <= 3 * 24 * 3600) {
        // up to 3 day range loads hourly data  ( 72 / 3 days)
        return 'hours';
    }
    else if(range <= 7 * 24 * 3600) {
        // up to 7 day range loads half-daily data  ( 84 / week )
        return 'half_days';
    }
    else if(range <= 60 * 24 * 3600) {
        // up to 2 month range loads daily data  ( 48 / 2 months )
        return 'days';
    }
    else if(range <= 12 * 31 * 24 * 3600) {
        // up to one year range loads weekly data ( 52 / year )
        return 'weeks';
    }
    else if(range <= 12 * 31 * 24 * 3600) {
        // up to 5 year range loads monthly data ( 60 / 5 years )
        return 'months';
    }
    else {
        // greater range loads yearly data
        return 'years';
    }
}

function get_point_interval(start, end) {
    var interval = get_interval(start, end);
    switch( interval ) {
        case 'minutes':      return 60 * 1000;
        case 'half_hours':   return 1800 * 1000;
        case 'hours':        return 3600 * 1000;
        case 'half_days':    return 3600 * 12 * 1000;
        case 'days':         return 24 * 3600 * 1000;
        case 'weeks':        return 24 * 7 * 3600 * 1000;
        case 'months':       return 24 * 3600 * 30 *1000;
        case 'years':        return 86400 * 365 * 1000;
        default: return null;
    }
}

function server_base_url(args) {
    return 'api/hloc?market=<?= $market ?>&milliseconds=true&timestamp=no&format=jscallback&callback=?';
}

function server_url(from, to, interval) {
    return server_base_url() + '&timestamp_from=' + Math.round(from) + '&timestamp_to=' + Math.round(to) + "&interval=" + interval;
}


Highcharts.setOptions({
   plotOptions: {
      series: {
         animation: false
      }
   }
});

$(function () {
    /**
     * Load new data depending on the selected min and max
     */
    function afterSetExtremes(e) {
        

        var chart = $('#container').highcharts();
        
        chart.showLoading('Loading data from server...');
        
        $.getJSON( server_url( e.min, e.max, ''), function (data) {

            var ohlc = [],
                volume = [],
                avg = [],
                dataLength = data.length,
                pointInterval;
                
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
                ])
            }                
                
            chart.series[0].setData(ohlc);
            chart.series[1].setData(avg);
            chart.series[2].setData(volume);
            
            chart.hideLoading();
        });
    }

    // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
    var url = server_url( new Date('2016-01-01').getTime(), new Date().getTime(), 'day' );
    $.getJSON(url, function (data) {

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


        // create the chart
        $('#container').highcharts('StockChart', {
            chart : {
                type: 'candlestick',
                zoomType: 'x',
            },
            
            plotOptions: {
                candlestick: {
                    color: 'red',
                    upColor: 'green',
                    pointWidth: 10,
                },
                column: {
                    pointWidth: 10,
                }
                
                /*,
                spline: {
                    connectNulls: false
//                  pointWidth: 10,
                } */
            },

            yAxis: [{
                title: {
                    text: 'Price (<?= $market_name ?>)'
                },
                height: 200,
                lineWidth: 2
            }, {
                title: {
                    text: 'Volume'
                },
                top: 290,
                height: 95,
                offset: 0,
                lineWidth: 2
            }],
            
            navigator : {
                adaptToUpdatedData: false,
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
                    var date_format;
                    var interval = get_interval( chart.xAxis[0].min, chart.xAxis[0].max );
                    console.log( interval, chart.xAxis[0].min, chart.xAxis[0].max  );
                    switch( interval ) {
                        case 'minutes':    date_format = '%B %e, %Y - %l:%M %p'; break;
                        case 'half_hours': date_format = '%B %e, %Y - %l:%M %p'; break;
                        case 'hours':      date_format = '%B %e, %Y - %l %p';    break;
                        case 'half_days':  date_format = '%B %e, %Y - %l %p';    break;
                        case 'days':       date_format = '%B %e, %Y';            break;
                        case 'weeks':      date_format = 'Week of %B %e, %Y';    break;
                        case 'months':     date_format = '%B %Y';                break;
                        case 'years':     date_format = '%Y';                    break;
                    }
                    
                    txt += '<span style="font-size: 10px"><b>' + Highcharts.dateFormat( date_format, point.x) + '</b></span><br/>';
                    var empty_buf = txt + "No trades";

                    var found = false;
                    each(points, function(p, i) {
                        if(p.point && p.point.open) {
                            txt += 'Open: ' + p.point.open + '<br/>High: ' + p.point.high + '<br/>Low: ' + p.point.low + '<br/>Close: ' + p.point.close + '<br/><br/>';
                            found = true;
                        } else {
                            txt +=  p.series.name + ': ' + p.y + '<br/>';
                        }
                    });
                
                    return found ? txt : empty_buf;
                }
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
                ordinal: false,
                events : {
                    afterSetExtremes : afterSetExtremes
                },
                minRange: 3600 * 1000, // one hour
            },

            series: [
                {
                    type: 'candlestick',
                    name: 'ohlc',
                    data: ohlc,
                    dataGrouping: {
                        enabled: false
                    }
                },
                {
                    type: 'spline',
                    name: 'Average',
                    data: avg,
                    dataGrouping: {
                        enabled: false
                    }
                },
                {
                    type: 'column',
                    name: 'Volume',
                    data: volume,
                    yAxis: 1,
                    dataGrouping: {
                        enabled: false
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
