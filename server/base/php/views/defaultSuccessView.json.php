<?php
header('Content-type: application/json', true, $request->getHTTPReturnCode());

echo json_encode($request->getJsonReturnData());

