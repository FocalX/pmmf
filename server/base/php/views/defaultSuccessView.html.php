<?php 
header('Content-type: text/html', true, $request->getHTTPReturnCode());
?>

<HTML>
<HEAD>
<TITLE>Default Success Page</TITLE>
</HEAD>
<BODY>
<H1 style='color:blue'>Default Page</H1>
For <b>developer</b>, if you see this page, you should create a specific view to handle this success call.<br><br>
For <b>general public</b>, you should not see this page. Sorry, something went wrong here.<br>
We apologize for any inconveniences. Please kindly report this problem to our staff.<br>
</BODY>
</HTML>