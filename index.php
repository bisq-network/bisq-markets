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
    
    // Default to usd market.
    if( !$market || !@$markets_result[$market]) {
        $market = "usd_btc";
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
    $market_select = sprintf( "<select onclick='document.location.replace(\"?market=\" + this.options[this.selectedIndex].value+\"%s\")'>%s\n", $allparam, $market );
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
<script src="<?= $amcharts_cdn ?>/amcharts.js"></script>
<script src="<?= $amcharts_cdn ?>/serial.js"></script>
<script src="<?= $amcharts_cdn ?>/themes/light.js"></script>
<script src="<?= $amcharts_cdn ?>/amstock.js"></script>
<script src="<?= $amcharts_cdn ?>/plugins/dataloader/dataloader.min.js" type="text/javascript"></script>
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
#chartdiv {
    visibility: hidden;
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
<div id="chartdiv"></div>
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
        "title": "<?= $market_name ?>",
        "categoryField": "date",
        
        "dataLoader": {
          // we use csv instead of json because it is more compact over the wire.
          "url": "api/hloc?market=<?= $market ?>&interval=day&format=csv",
          "format": "csv",
          "delimiter": ",",       // column separator
          "useColumnNames": true, // use first row for column names
          "skip": 1,               // skip header row
          "reload": 300,           // auto reload every 5 minutes.
          "timestamp": true       // add timestamp to url, to avoid caches.
        }
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
            "dashLength": 5,
            "minPeriod": "hh",
            "parseDates": true
          },
    
          "stockGraphs": [ {
            "type": "candlestick",
            "id": "g1",
            "openField": "open",
            "closeField": "close",
            "highField": "high",
            "lowField": "low",
            "valueField": "close",
            "lineColor": "black",
            "fillColors": "lightgreen",
            "negativeLineColor": "black",
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
            "dashLength": 5,
            "parseDates": true
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
    
      "valueAxesSettings": {
        "inside": false,
        "autoMargins": true
      },
      
      "chartCursorSettings": {
        "valueLineBalloonEnabled": true,
        "valueLineEnabled": true,
      },
    
      "periodSelector": {
        "position": "bottom",
        "hideOutOfScopePeriods": true,
        "periods": [
<?php            
    /*        
        {
          "period": "mm",
          "count": 1,
          "label": "Minute"
        },
*/
?>
        {
          "period": "hh",
          "count": 1,
          "label": "Hour"
        },
        {
          "period": "DD",
          "count": 1,
          "label": "Day"
        }, {
          "period": "WW",
          "count": 1,
          "label": "Week"
        }, {
          "period": "MM",
          "selected": true,
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
      },

<?php    
      /**
       * this is an ugly hack to get the graph to display inside the div without
       * clipping left and right edges, and also keep the graph from displaying
       * on top of the left axis.  why this is necessary is beyond me.  I found
       * it in an amcharts demo though. maybe an html/JS wizard can fix things
       * so this is no longer necessary.
       */
?>      
      "listeners": [{
        "event": "init",
        "method": function(e) {
          // init
          var margins = {
            "left": 0,
            "right": 20
          };
          
          // iterate thorugh all of the panels
          for ( var p = 0; p < chart.panels.length; p++ ) {
            var panel = chart.panels[p];
            
            // iterate through all of the axis
            for ( var i = 0; i < panel.valueAxes.length; i++ ) {
              var axis = panel.valueAxes[ i ];
              if ( axis.inside !== false ) {
                continue;
              }
    
              var axisWidth = axis.getBBox().width + 10;
              if ( axisWidth > margins[ axis.position ] ) {
                margins[ axis.position ] = axisWidth;
              }
            }
            
          }
          
          // set margins
          if ( margins.left || margins.right ) {
            chart.panelsSettings.marginLeft = margins.left;
            chart.panelsSettings.marginRight = margins.right;
            chart.invalidateSize();
            // prevent redraw flashing.
            window.setTimeout( function() {document.getElementById('chartdiv').style.visibility = 'visible'}, 500);
          }
        }
      }]    
      
    } );
    
}
</script>

<?php endif; ?>


</body>
</html>
