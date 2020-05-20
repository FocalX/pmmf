<?php 
header('Content-type: text/html', true, $request->getHTTPReturnCode());
?>

<HTML>
<HEAD>
<TITLE>Application Error</TITLE>
</HEAD>
<BODY>
<H1 style='color:blue'>Application Error</H1>
<H3 style='color:red'><?php echo 'Code: '.$request->getHTTPReturnCode() ?></H3>

<?php 
foreach ($request->getAllErrors() as $error) {?>
	<span style='color:red'>- <?php echo $error; ?></span><br>
<?php } ?>
<br>
Sorry, something went wrong here...<br>
We apologize for any inconveniences. Please report this problem to our staff.<br>
</BODY>
</HTML>