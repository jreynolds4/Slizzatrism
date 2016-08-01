<?php

    if(isset($_POST['submit'])){
      $to = "jon.rey22@gmail.com";
      $from = $_POST['email'];
      $name = $_POST['name'];
      $subject = "Form submission";
      $subject2 = "Copy of your form submission";
      $message = $name . " wrote the following:" . "\n\n" . $_POST['message'];
      $message2 = "Here is a copy of your message " . $name . "\n\n" . $_POST['message'];

      $headers = "From:" . $from;
      $headers2 = "To:" . $to;
      mail($to,$subject,$message,$headers);
      //mail($from,$subject2,$message2,$headers2); // sends a copy of the message to the sender
      echo "Mail Sent. Thank you " . $name . ", we will contact you shortly.";
      // You can also use header('Location: thank_you.php'); to redirect to another page.
    }
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../media/slizzatrism.ico">

    <title>$lizzatri$m</title>

    <!-- Bootstrap core CSS -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">

    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="../css/ie10-viewport-bug-workaround.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <!-- link href="../css/temp.css" rel="stylesheet" -->

    <!-- Just for debugging purposes. Don't actually copy these 2 lines! -->
    <!--[if lt IE 9]><script src="../../assets/js/ie8-responsive-file-warning.js"></script><![endif]-->
    <script src="../js/ie-emulation-modes-warning.js"></script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/font-awesome.css">

    <script src="https://use.fontawesome.com/b026176100.js"></script>

    <link href="../css/slizzatrism.css" rel="stylesheet">
  </head>

  <body>

    <div class="masthead container centered ">
      <div class="inner">
        <h3 class="masthead-brand">
          <a href="http://www.instagram.com/rasnebyu" target="_blank"><span><i class="fa fa-instagram"></i></span></a>
          <a href="http://www.soundcloud.com/rasnebyu" target="_blank"><span><i class="fa fa-soundcloud"></i></span></a>
          <a href="https://www.youtube.com/user/LexThunderTV" target="_blank"><span><i class="fa fa-youtube-play"></i></span></a>
          <a href="http://www.twitter.com/rasnebyu" target="_blank"><span><i class="fa fa-twitter"></i></span></a>
          <a href="http://www.facebook.com/rasnebyu" target="_blank"><span><i class="fa fa-facebook"></i></span></a>
        </h3>

        <nav>
          <ul class="nav masthead-nav">
            <li class="active"><a href="home.php">Home</a></li>
            <li><a href="media.php">Media</a></li>
            <li><a href="roster.php">Roster</a></li>
            <li data-toggle="modal" data-target="#contactForm"><a href="#">Contact</a></li>
          </ul>
        </nav>
      </div>
    </div>

    <div class="container">
      <!-- Modal -->
      <div class="modal fade" id="contactForm" role="dialog">
        <div class="modal-dialog">

          <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal">&times;</button>
              <h4 class="modal-title">Contact Form</h4>
            </div>
            <div class="modal-body">
              <form action="" method="post" enctype="text/plain">
                <div class="modal-label">Name:<br></div>
                <input type="text" name="name"><br>
                <div class="modal-label">E-mail:<br></div>
                <input type="text" name="email"><br>
                <div class="modal-label">Message:<br></div>
                <textarea name="message" rows="3" cols="25"></textarea><br><br>
                <input class="modal-label" type="submit" value="Send">
                <input class="modal-label" type="reset" value="Reset">
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
          </div>

        </div>
      </div>

    </div>

    <div class="container main-content centered" role="main">

      <div class="home-heading">SLIZZATRISM</div>
      <p class="lead">
        The Official Website of Ras Nebyu
      </p>

      <div class="row">
        <div class="col-sm-3"></div>
        <div class="col-sm-6">
          <iframe width="100%" height="315" src="https://www.youtube.com/embed/JxQFQUvHFGE" frameborder="0" allowfullscreen></iframe>
        </div>
        <div class="col-sm-3"></div>
      </div>

      <footer> Webmaster: <a href="MAILTO:jon.rey22@gmail.com">Dr. Jrey</a> </footer>


    </div>

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <script>window.jQuery || document.write('<script src="../js/vendor/jquery.min.js"><\/script>')</script>
    <script src="../js/bootstrap.min.js"></script>
    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <script src="../js/ie10-viewport-bug-workaround.js"></script>
  </body>
</html>
