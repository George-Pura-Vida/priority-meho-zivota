<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_auth();
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { header('Allow: GET'); api_error('METHOD_NOT_ALLOWED','Only GET is allowed.',405); }
$q=db()->prepare('SELECT version,updated_at FROM priority_state WHERE user_id=?');
$q->execute([PRIORITY_USER_ID]); $r=$q->fetch();
if(!$r) api_error('STATE_NOT_FOUND','Priority state is not initialized.',500);
json_out(['ok'=>true,'version'=>(int)$r['version'],'updatedAt'=>iso($r['updated_at']),'serverTime'=>(new DateTimeImmutable())->format(DateTimeInterface::ATOM)]);
