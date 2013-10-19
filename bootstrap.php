<?php
include "vendor/autoload.php";

register_shutdown_function(function(){
    if (file_exists(__DIR__ . '/tmp/result.html')) {
        $result = file_get_contents(__DIR__ . '/tmp/result.html');
        $result = str_replace('<ul>', '<ul class="results">', $result);
        $result = str_replace('<html>', '<html><head><title>Phade - Jade to PHP compiler</title><meta name="description" content="Phade is a Jade to PHP compiler."><link rel="stylesheet" type="text/css" href="style.css" />    <!-- Le styles -->
    <link href="assets/css/bootstrap.css" rel="stylesheet">
    <style type="text/css">
      body {
        padding-top: 20px;
        padding-bottom: 40px;
      }

      /* Custom container */
      .container-narrow {
        margin: 0 auto;
        max-width: 700px;
      }
      .container-narrow > hr {
        margin: 30px 0;
      }

      /* Main marketing message and sign up button */
      .jumbotron {
        margin: 60px 0;
        text-align: center;
      }
      .jumbotron h1 {
        font-size: 72px;
        line-height: 1;
      }
      .jumbotron .btn {
        font-size: 21px;
        padding: 14px 24px;
      }

      /* Supporting marketing content */
      .marketing {
        margin: 60px 0;
      }
      .marketing p + h4 {
        margin-top: 28px;
      }
    </style>
    <link href="assets/css/bootstrap-responsive.css" rel="stylesheet">

    <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="assets/js/html5shiv.js"></script>
    <![endif]-->

    <!-- Fav and touch icons -->
    <link rel="apple-touch-icon-precomposed" sizes="144x144" href="assets/ico/apple-touch-icon-144-precomposed.png">
    <link rel="apple-touch-icon-precomposed" sizes="114x114" href="assets/ico/apple-touch-icon-114-precomposed.png">
      <link rel="apple-touch-icon-precomposed" sizes="72x72" href="assets/ico/apple-touch-icon-72-precomposed.png">
                    <link rel="apple-touch-icon-precomposed" href="assets/ico/apple-touch-icon-57-precomposed.png">
                                   <link rel="shortcut icon" href="assets/ico/favicon.png">
  </head>', $result);
        $result = str_replace('<body>', '<body>
<div class="container-narrow">

      <div class="masthead">
        <ul class="nav nav-pills pull-right">
          <li class="active"><a href="/">Home</a></li>
        </ul>
        <h3 class="muted">Phade - Jade to PHP compiler</h3>
      </div>

      <hr>

      <div class="jumbotron">
        <h1>The number one Jade to PHP compiler!<span style="font-size: 12pt"> (eventually)</span></h1>
        <p class="lead">This site shows the current progress on the Phade project. Phade is a Jade to PHP compiler, parser. This is currently just a plain HTML site but on the todo list is make this a nodejs site and have a demo site that displays the same content using the Phade compiler. This to better display the progress made with the project. Will start out with lots of errors though as can be seen from the data below.</p>
      </div>

      <hr>

      <div class="row-fluid marketing">
', $result);
        $result = str_replace('<li><strike>', '<li class="failed"><strike>', $result);
        $result = str_replace('</body>', '
      <div class="footer">
        <p>&copy; Cyonite Systems 2013</p>
      </div>

    </div> <!-- /container -->

    <!-- Le javascript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="assets/js/jquery.js"></script>
    <script src="assets/js/bootstrap-transition.js"></script>
    <script src="assets/js/bootstrap-alert.js"></script>
    <script src="assets/js/bootstrap-modal.js"></script>
    <script src="assets/js/bootstrap-dropdown.js"></script>
    <script src="assets/js/bootstrap-scrollspy.js"></script>
    <script src="assets/js/bootstrap-tab.js"></script>
    <script src="assets/js/bootstrap-tooltip.js"></script>
    <script src="assets/js/bootstrap-popover.js"></script>
    <script src="assets/js/bootstrap-button.js"></script>
    <script src="assets/js/bootstrap-collapse.js"></script>
    <script src="assets/js/bootstrap-carousel.js"></script>
    <script src="assets/js/bootstrap-typeahead.js"></script>

  </body>        
        
        ', $result);
        file_put_contents(__DIR__ . '/tmp/index.html', $result);
    }
});