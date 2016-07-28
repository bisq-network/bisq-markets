<?php

require_once( dirname( __FILE__) . '/lib/html_table.class.php' );
require_once( dirname( __FILE__) . '/lib/trades.class.php' );
require_once( dirname( __FILE__) . '/lib/summarize_trades.class.php' );
require_once( dirname( __FILE__) . '/lib/markets.class.php' );

ini_set('memory_limit', '5G');

$market = @$_GET['market'];
if( !$market ) {
    include(dirname(__FILE__) . '/404.html');
}
$market_name = strtoupper( str_replace( '_', '/', $market));

$max_trade_history = 100;
try {

    $marketservice = new markets();
    $markets_result = $marketservice->get_markets();
    
    $trades = new trades();
    $summarize_trades = new summarize_trades();

    $history_fields = ['volume_left', 'open', 'last', 'high', 'low', 'avg'];

    $history_result = $summarize_trades->get_trade_summaries_days( ['market' => $market,
                                                                    'datetime_from' => $start_period_time = strtotime( '4 week ago + 1 day 00:00:00' ),
                                                                    'datetime_to' => $start_period_time +  (int)floor((time() - $start_period_time)/1800)*1800,
                                                                   ] );
print_r($history_result);    
    $market_select = sprintf( "<select onclick='document.location.replace(\"?market=\" + this.options[this.selectedIndex].value)'>\n", $market );
    foreach( $markets_result as $id => $m ) {
        $market_select .= sprintf( "<option value=\"%s\"%s>%s</option>\n", $id, $id == $market ? ' selected' : '', strtoupper( str_replace('_', '/', $id )) );
    }
    $market_select .= "</select>\n";
    $market_result = ['market'=> $market_select,
                      'market_date'=> date('Y-m-d'),
                      'last'=> '--',
                      'high'=> '--',
                      'low'=> '--',
                      'avg'=> '--',
                      'volume' => '--'
                    ];
    $latest = @$history_result[count($history_result)-1];
print_r( $latest );
    if( $latest && date('Y-m-d', $latest['period_start']/1000) == date('Y-m-d') ) {
        $market_result = ['market'=> $market_select,
                          'market_date'=> date('Y-m-d'),
                          'last'=> $latest['close'],
                          'high'=> $latest['high'],
                          'low'=> $latest['low'],
                          'avg'=> $latest['avg'],
                          'volume' => $latest['volume']
                         ];
    }

    $trades_result = $trades->get_trades( [ 'market' => $market,
                                            'limit'  => $max_trade_history ] );
print_r( $trades_result[0] );
    
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
$table->timestampjs_col_names['tradeDate'] = true;
//$table->td_attrs = array( 'style' => "vertical-align: top;" );

$buttons_row = array( "<table width='100%' cellpadding='0' cellspacing='0'><tr><td valign='bottom'><h3>Sell Orders</h3></td><td valign='bottom' align='right'><a href='place_order.html?market=$market&side=BUY' class='button'>Buy <span class='curr_left'>$curr_left</span></a></td></tr></table>",
                      '&nbsp;',
                      "<table width='100%' cellpadding='0' cellspacing='0'><tr><td valign='bottom'><h3>Buy Orders</h3></td><td valign='bottom' align='right'> <a href='place_order.html?market=$market&side=SELL' class='button'>Sell <span class='curr_left'>$curr_left</span></a></td></tr></table>"
                    );

function display_crypto($val, $row) {
    return $val / 10000000;
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<?php include( dirname(__FILE__) . '/widgets/head.html' ); ?>
<script src="https://www.amcharts.com/lib/3/amcharts.js"></script>
<script src="https://www.amcharts.com/lib/3/serial.js"></script>
<script src="https://www.amcharts.com/lib/3/themes/light.js"></script>
<script src="https://www.amcharts.com/lib/3/amstock.js"></script>
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
</head>
<body>

<?php $table->table_attrs = array( 'class' => 'bordered', 'id' => 'market_info', 'style' => 'width: 800px' ); ?>
<?= $table->table_with_header( array( $market_result ),
                              array( 'Market', 'Date', "Last", "High", "Low", "Avg", "Volume" ),
                              array( 'market', 'market_date', 'last', 'high', 'low', 'avg', 'volume' ) ); ?>

<div class='widget' style="margin-top: 15px;">
<div id="chartdiv"></div>
<div style="clear: both"></div>
</div>
<script>document.getElementById('chartdiv').style.height = "500px";</script>
<?php if( !count( $trades_result ) || $trades_result[0]['tradeDate']/1000 <= strtotime( 'now - 7 day' ) ): ?>
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
                                     // 'direction' => array( 'cb_format' => function($val, $row) { return sprintf( '<a href="view_trade.html?trade=%s">%s</a>', @$row['offerId'], $val ); } ),
                                     'direction',
                                     'tradePrice' => ['cb_format' => 'display_crypto'],
                                     'tradeAmount' => ['cb_format' => 'display_crypto'],
                                     'total' => ['cb_format' => 'display_crypto'] ) ); ?>
</div>


		<script type="text/javascript">
            // generateChartData();
            createStockChart();

			function generateChartData() {
                var chartData = [];
                var data = <?=  json_encode( $history_result ) ?>;

				for (var i = 0; i < data.length; i++) {
                    var d = data[i];

					chartData.push ({
						date: new Date(d.period_start),
						open: d.open,
						close: d.close,
						high: d.high,
						low: d.low,
						volume: d.volume,
						value: d.avg
					});
                    
				}
                return chartData;
			}

            function createStockChart() {
                
var chartData = generateChartData();


var chart = AmCharts.makeChart( "chartdiv", {
  "type": "stock",
  "theme": "light",

  "dataSets": [ {
    "fieldMappings": [ {
      "fromField": "open",
      "toField": "open"
    }, {
      "fromField": "close",
      "toField": "close"
    }, {
      "fromField": "high",
      "toField": "high"
    }, {
      "fromField": "low",
      "toField": "low"
    }, {
      "fromField": "volume",
      "toField": "volume"
    }, {
      "fromField": "value",
      "toField": "value"
    } ],
    "color": "#7f8da9",
    "dataProvider": chartData,
    "title": "<?= $market_name ?>",
    "categoryField": "date"
  } ],


  "panels": [ {
      "title": "Price",
      "showCategoryAxis": false,
      "percentHeight": 70,
      "valueAxes": [ {
        "id": "v1",
        "dashLength": 5
      } ],

      "categoryAxis": {
        "dashLength": 5
      },

      "stockGraphs": [ {
        "type": "candlestick",
        "id": "g1",
        "openField": "open",
        "closeField": "close",
        "highField": "high",
        "lowField": "low",
        "valueField": "close",
        "lineColor": "#7f8da9",
        "fillColors": "#7f8da9",
        "negativeLineColor": "#db4c3c",
        "negativeFillColors": "#db4c3c",
        "fillAlphas": 1,
        "useDataSetColors": false,
        "comparable": false,
        "compareField": "value",
        "showBalloon": true,
        "balloonText" : "Open: [[open]]\nClose: [[close]]\n\nHigh: [[high]]\nLow: [[low]]\nAvg: [[value]]\n\nVolume: [[volume]]",
        "proCandlesticks": true
      } ],

      "stockLegend": {
        "valueTextRegular": undefined,
        //"periodValueTextComparing": "[[percents.value.close]]%"
      }
    },

    {
      "title": "Volume",
      "percentHeight": 30,
      "marginTop": 1,
      "showCategoryAxis": true,
      "valueAxes": [ {
        "dashLength": 5
      } ],

      "categoryAxis": {
        "dashLength": 5
      },

      "stockGraphs": [ {
        "valueField": "volume",
        "type": "column",
        "showBalloon": true,
        "fillAlphas": 1
      } ],

      "stockLegend": {
        "markerType": "none",
        "markerSize": 0,
        "labelText": "",
        "periodValueTextRegular": "[[value.close]]"
      }
    }
  ],

  "chartScrollbarSettings": {
    "graph": "g1",
    "graphType": "line",
    "usePeriod": "WW"
  },

  "chartCursorSettings": {
    "valueLineBalloonEnabled": true,
    "valueLineEnabled": true
  },

  "periodSelector": {
    "position": "bottom",
    "periods": [
    {
      "period": "mm",
      "count": 1,
      "label": "Minute"
    }, {
      "period": "hh",
      "count": 1,
      "label": "Hour"
    }, {
      "period": "DD",
      "count": 1,
      "label": "Day"
    }, {
      "period": "WW",
      "count": 1,
      "label": "Week"
    }, {
      "period": "MM",
      selected: true,
      "count": 1,
      "label": "Month"
    }, {
      "period": "YYYY",
      "count": 1,
      "label": "Year"
    }, {
      "period": "YTD",
      "label": "YTD"
    }, {
      "period": "MAX",
      "label": "MAX"
    } ]
  },
  "export": {
    "enabled": true
  }
} );
    }
			
		</script>

</body>
</html>