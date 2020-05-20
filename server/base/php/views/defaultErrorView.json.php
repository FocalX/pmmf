<?php
// A trick where PHP/Apache 2.2.x does not output 429 error properly
if($request->getHTTPReturnCode()) {
	header('HTTP/1.1 429 Too Many Requests');
}
header('Content-type: application/json', true, $request->getHTTPReturnCode());

print '{
"code" : '.$request->getHTTPReturnCode(). ',
"message" : "'.$request->getLastError().'"
}';

?>