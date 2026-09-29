<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/api/bootstrap.php';
header('Cache-Control: no-store');
$ticket=(string)($_GET['ticket']??'');
if($ticket===''){http_response_code(400);exit('Missing ticket');}
$secret=(string)getenv('PRIORITY_SSO_SECRET');
if(strlen($secret)<32){http_response_code(500);exit('SSO config missing');}
$body=json_encode(['ticket'=>$ticket,'app'=>'cile']);
$ts=(string)time();
$nonce=bin2hex(random_bytes(16));
$sig=hash_hmac('sha256',$ts."\n".$nonce."\n".$body,$secret);
$ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nX-SSO-Timestamp: $ts\r\nX-SSO-Nonce: $nonce\r\nX-SSO-Signature: $sig\r\n",'content'=>$body,'timeout'=>10,'ignore_errors'=>true]]);
$result=file_get_contents('https://kupsiapku.cz/api/sso/exchange.php',false,$ctx);
$data=is_string($result)?json_decode($result,true):null;
if(!is_array($data)||empty($data['ok'])||empty($data['user']['id'])){http_response_code(401);exit('SSO denied');}
$external=(int)$data['user']['id'];$email=(string)($data['user']['email']??'');$pdo=db();ensure_priority_user_schema($pdo);
$q=$pdo->prepare('SELECT id,status FROM priority_users WHERE external_user_id=? LIMIT 1');$q->execute([$external]);$row=$q->fetch();
if(!$row){$q=$pdo->prepare('INSERT INTO priority_users(external_user_id,email,status,last_login_at) VALUES(?,?,?,UTC_TIMESTAMP())');$q->execute([$external,$email,'active']);$uid=(int)$pdo->lastInsertId();$q=$pdo->prepare('INSERT INTO priority_state(user_id,version,goal_horizon) VALUES(?,?,?)');$q->execute([$uid,1,'10 let']);}
else{$uid=(int)$row['id'];if($row['status']!=='active'){http_response_code(403);exit('Account blocked');}$q=$pdo->prepare('UPDATE priority_users SET email=?,last_login_at=UTC_TIMESTAMP() WHERE id=?');$q->execute([$email,$uid]);}
session_regenerate_id(true);$_SESSION['priority_auth']=true;$_SESSION['priority_user_id']=$uid;$_SESSION['external_user_id']=$external;$_SESSION['priority_csrf']=bin2hex(random_bytes(32));
header('Location: /',true,303);exit;
