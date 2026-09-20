<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_auth();
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { header('Allow: GET'); api_error('METHOD_NOT_ALLOWED','Only GET is allowed.',405); }
json_out(['ok'=>true,'csrfToken'=>csrf_token()]);
