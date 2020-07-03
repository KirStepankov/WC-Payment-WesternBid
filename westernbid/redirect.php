<html xmlns="http://www.w3.org/1999/xhtml">
<head>
   <script type="text/javascript">
	   function closethisasap() {
		   document.forms["redirectpost"].submit();
	   }
   </script>
</head>
<body onload="closethisasap();">
<form name="redirectpost" method="post" action="https://shop.westernbid.info">
   <?php
	   foreach ($_GET as $k => $v) {
		   echo '<input type="hidden" name="' . $k . '" value="' . $v . '"> ';
	   }
   ?>
</form>
</body>
</html>