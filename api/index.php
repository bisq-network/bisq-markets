<?php

require_once( __DIR__ . '/../lib/strict_mode.funcs.php' );
require_once( __DIR__ . '/../lib/html_table.class.php' );

$files = rglob( __DIR__ . '/apidoc.php' );

$apis = [];
foreach( $files as $docfile ) {
    require_once( $docfile );
    $apiname = basename( dirname( $docfile ) );
    $classname = 'api_' . $apiname;
    
    $obj = new $classname;
    $apis[$apiname] = $obj;
}

$table = new html_table();
$table->table_attrs = array( 'class' => 'bordered' );

$apiversion = file_get_contents( __DIR__ . '/VERSION' );

function rglob($pattern, $flags = 0) {
    $files = glob($pattern, $flags); 
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, rglob($dir.'/'.basename($pattern), $flags));
    }
    return $files;
}

function e($buf) {
    return htmlentities( $buf );
}

?>

<html>
<head>
  <link href="../css/bitsquare/style.css" media="screen" rel="stylesheet" type="text/css">
  <link href="../css/bitsquare/css.css" id="opensans-css" media="all" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="../css/styles.css" type="text/css">
  <link href="../favicon.ico" rel="icon" type="image/x-icon">
        
<style>
h1, h2, h3, h4, h5, h6 {
    margin-top: 10px;
    margin-bottom: 10px;
}
#apiversion {
    position: absolute;
    right: 10px;
    top: 5px;
}
div.api, div.toc, div.errors {
    width: 80%;
    margin-top: 20px;
    margin-left: auto;
    margin-right: auto;
    position: relative;
}
div.method {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 10px;
}
div.description {
    margin-left: 25px;
}
div.requestexample {
    margin-left: 25px;
    margin-right: 25px;
    border: grey dashed 1px;
    background-color: white;
    padding: 10px;
}
div.responseexample {
    margin-left: 25px;
    margin-right: 25px;
    white-space: pre;
    margin-bottom: 10px;
    border: grey dashed 1px;
    background-color: white;
    padding: 10px;
}
div.params {
    margin-left: 25px;
    margin-right: 25px;
    margin-bottom: 20px;
}
</style>
</head>
<body>

<div class="toc widget">
    <h4>Bitsquare public webservice APIs:</h4>
    <p id="apiversion">Version: <?= $apiversion ?></p>
    <ul>
<?php foreach( $apis as $method => $api ): ?>
        <li><a href="#<?= e($method) ?>"><?= e($method) ?></a></li>
<?php endforeach ?>
    </ul>
    
    Error responses are documented <a href="#errors">here</a>.
    
</div>


<?php foreach( $apis as $method => $api ): ?>
<div class="api widget">
    <div class="method" id="<?= e($method) ?>">/api/<?= e($method) ?></div>
    <div class="description"><?= e($api->get_description()) ?></div>
    <div class="examples">
        <?php foreach($api->get_examples() as $example): ?>
        <div class="example">
            <h4>Sample Request</h4>
            <div class="requestexample">https://market.bitsquare.io/api<?= e($example['request']) ?></div>
            <h4>Sample Response</h4>
            <div class="responseexample"><?= e($example['response']) ?></div>
        </div>
        <?php endforeach ?>
    </div>
    
    <div class="params">
        <h4>Parameters</h4>
        <?= $table->table_with_header($api->get_params()) ?>
    </div>
    
    <div class="notes">
        <?php foreach($api->get_notes() as $note): ?>
        <div class="apinote"><?= e($note) ?></div>
        <?php endforeach ?>
    </div>
</div>
<?php endforeach ?>

<div class="errors widget" id="errors">
    <h4>Error Responses</h4>
    
    <p>Any errors such as invalid parameters will be in the following format</p>
    <div class="responseexample">{
    "success": 0,
    "error": "market parameter missing"
}</div>
    
</div>

</body>
</html>