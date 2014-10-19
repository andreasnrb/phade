<?php
define('PHADE_TEST_DEBUG', true);
include "vendor/autoload.php";
include "vendor/cyonite/underscore.php/lib/skillshare/underscore.php";

register_shutdown_function(function(){
    if (file_exists(__DIR__ . '/tmp/result.html')) {
        $result = file_get_contents(__DIR__ . '/tmp/result.html');
        $result = str_replace('<ul>', '<ul class="results">', $result);
        $result = str_replace('<html>', '<html><head><title>Phade - Jade to PHP compiler</title><meta name="description" content="Phade is a Jade to PHP compiler.">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="style.css" />
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
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>

  </body>        
        
        ', $result);
        file_put_contents(__DIR__ . '/tmp/index.html', $result);
    }
});
