<?php
function get_refresh_interval() {
  
  static $refresh = null;
  if( $refresh ) {
    return $refresh;
  }
  
  $get_refresh = @$_GET['refresh'];
  $cookie_refresh = @$_COOKIE['refresh'];
  if( (int)$get_refresh && $get_refresh != $cookie_refresh ) {
    setcookie("refresh", $get_refresh, strtotime('2032-01-01') );
  }
  $refresh = (int)$get_refresh ?: (int)$cookie_refresh;
  return $refresh && $refresh >= 60 ? $refresh : 60;    // default to 60 secs.  enforce 60 sec min.
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta content="text/html; charset=utf-8" http-equiv="content-type">
  <meta charset="UTF-8">
<?php if(get_refresh_interval() != 'no'): ?>
  <meta http-equiv="refresh" content="<?= get_refresh_interval() ?>">
<?php endif ?>  

  <title>Bisq Markets</title>
  <link href="css/bitsquare/style.css" media="screen" rel="stylesheet" type=
  "text/css">
  <link href="favicon.ico" rel="icon" type="image/x-icon">
  <link href="css/bitsquare/css.css" id="opensans-css" media="all" rel=
  "stylesheet" type="text/css">
</head>

<body class="home page page-id-6 page-template-default custom-background">
  <div class="center" id="wrapper_border" style="margin-top: 50px;">
    <div class="center" id="wrapper_bg">
      <div id="navwrap">
        <div id="navwrap2">
          <nav>
            <ul id="menuUl" class="menu"><li class="tnskip" >&nbsp;</li><li id="menu-item-40" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-40"><a href="https://bisq.io/">Home</a></li>
            <li id="menu-item-1427" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-1427 menu-item menu-item-type-post_type menu-item-object-page current_page_item current-menu-item-38"><a href="https://market.bisq.io">Markets</a></li>
            <li id="menu-item-38" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-38"><a href="https://bisq.io/philosophy/">Philosophy</a></li>
            <li id="menu-item-36" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-36"><a href="https://bisq.io/community/">Community</a></li>
            <li id="menu-item-468" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-468"><a href="https://bisq.io/faq/">FAQ</a></li>
            <li id="menu-item-597" class="menu-item menu-item-type-taxonomy menu-item-object-category menu-item-597"><a href="https://bisq.io/blog/category/news/">Blog</a></li>
            <li id="menu-item-1271" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-1271"><a href="https://forum.bisq.io">Forum</a></li>
            <li id="menu-item-1602" class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://bisq.io/downloads/">Downloads</a></li>
            <li class="tnskip">&nbsp;</li><li class="tnskip filler"></li>
            </ul>
          </nav>
        </div>
      </div>
    </div>


    <div style="margin-top: 20px; margin-bottom: 20px">
     <?php include( __DIR__ . '/index-body.php' ) ?>
<?php if( get_refresh_interval() != 1956556800 ): ?>
     <div style="text-align: center" title="This page automatically reloads every 60 seconds.  Click this link to disable it."><a href="?refresh=1956556800">Disable auto page reload.</a></div>
<?php else: ?>
     <div style="text-align: center" title="This page can automatically reload every 60 seconds.  Click to enable it."><a href="?refresh=60">Enable auto page reload.</a></div>
<?php endif ?>
    </div>

    <div class="center" id="wrapper_bg">

      <footer class="center copy">

        <nav class="nav-footer">
          <ul class="menu sf-js-enabled sf-shadow" id="menuUIfooter">
            <li class="tnskip">&nbsp;</li><li class=
            "menu-item menu-item-type-post_type menu-item-object-page menu-item-42"
            id="menu-item-42"><a href="https://bisq.io/press/">Press</a></li>

            <li class=
            "menu-item menu-item-type-post_type menu-item-object-page menu-item-453"
            id="menu-item-453"><a href="https://bisq.io/roadmap/">Roadmap</a></li>


            <li class=
            "menu-item menu-item-type-post_type menu-item-object-page menu-item-451"
            id="menu-item-451">
              <a href="https://bisq.io/dao/">DAO</a>
            </li>


            <li class=
            "menu-item menu-item-type-post_type menu-item-object-page menu-item-44"
            id="menu-item-44">
              <a href="https://bisq.io/contact/">Contact</a>
            </li>


            <li class="tnskip">&nbsp;</li>


            <li class="tnskip filler">
            </li>
          </ul>
        </nav>
        <br>


        <p>© <?= date('Y') ?> Bisq</p>
      </footer>
    </div>
  </div>
</body>
</html>
