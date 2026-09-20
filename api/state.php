<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_auth();

$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method,['GET','PUT'],true)) {
    header('Allow: GET, PUT');
    api_error('METHOD_NOT_ALLOWED','Only GET and PUT are allowed.',405);
}

$pdo=db();
$uid=PRIORITY_USER_ID;

function get_state(PDO $pdo,int $uid): array {
    $s=$pdo->prepare('SELECT version,goal_horizon,updated_at FROM priority_state WHERE user_id=?');
    $s->execute([$uid]); $meta=$s->fetch();
    if (!$meta) api_error('STATE_NOT_FOUND','Priority state is not initialized.',500);

    $q=$pdo->prepare('SELECT id,name,area,progress,horizon,updated_at FROM priority_goals WHERE user_id=? ORDER BY created_at,id');
    $q->execute([$uid]);
    $goals=array_map(fn($r)=>[
        'id'=>$r['id'],'name'=>$r['name'],'area'=>$r['area'],'progress'=>(int)$r['progress'],
        'horizon'=>$r['horizon'],'updatedAt'=>iso($r['updated_at'])
    ],$q->fetchAll());

    $q=$pdo->prepare("SELECT id,task_date,name,area,importance,urgency,minutes,done,completed_at,updated_at FROM priority_tasks WHERE user_id=? ORDER BY COALESCE(task_date,'9999-12-31'),created_at,id");
    $q->execute([$uid]);
    $tasks=array_map(fn($r)=>[
        'id'=>$r['id'],'taskDate'=>$r['task_date'],'name'=>$r['name'],'area'=>$r['area'],
        'importance'=>(int)$r['importance'],'urgency'=>(int)$r['urgency'],'minutes'=>(int)$r['minutes'],
        'done'=>(bool)$r['done'],'completedAt'=>iso($r['completed_at']),'updatedAt'=>iso($r['updated_at'])
    ],$q->fetchAll());

    $q=$pdo->prepare('SELECT review_date,score,win,waste,tomorrow FROM priority_reviews WHERE user_id=? AND review_date>=? ORDER BY review_date');
    $reviewsFrom=(new DateTimeImmutable('today',new DateTimeZone('Europe/Prague')))->modify('-90 days')->format('Y-m-d');
    $q->execute([$uid,$reviewsFrom]);
    $reviews=array_map(fn($r)=>[
        'date'=>$r['review_date'],'score'=>$r['score']===null?null:(int)$r['score'],
        'win'=>$r['win'],'waste'=>$r['waste'],'tomorrow'=>$r['tomorrow']
    ],$q->fetchAll());

    return ['ok'=>true,'version'=>(int)$meta['version'],'updatedAt'=>iso($meta['updated_at']),
        'state'=>['goalHorizon'=>$meta['goal_horizon'],'goals'=>$goals,'tasks'=>$tasks,'reviews'=>$reviews]];
}

if ($method==='GET') json_out(get_state($pdo,$uid));

require_csrf();
if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''),'application/json')!==0) {
    api_error('UNSUPPORTED_MEDIA_TYPE','Content-Type must be application/json.',415);
}
$body=read_json_body();
if (!isset($body['version'],$body['state']) || !is_int($body['version']) || !is_array($body['state'])) {
    api_error('VALIDATION_ERROR','version and state are required.',422);
}
$state=$body['state'];
$allowedState=['goalHorizon','goals','tasks','reviews'];
if (array_diff(array_keys($state),$allowedState)) api_error('VALIDATION_ERROR','Unknown state field.',422);
$horizons=['10 let','5 let','1 rok','Měsíc']; $areas=['Zdraví','Finance','Vztahy','Rozvoj'];
if (!isset($state['goalHorizon'],$state['goals'],$state['tasks'],$state['reviews']) ||
    !in_array($state['goalHorizon'],$horizons,true) || !is_array($state['goals']) || !is_array($state['tasks']) || !is_array($state['reviews'])) {
    api_error('VALIDATION_ERROR','Invalid state structure.',422);
}

foreach ($state['goals'] as $i=>$g) {
    if (!is_array($g) || array_diff(array_keys($g),['id','name','area','progress','horizon']) ||
        !isset($g['id'],$g['name'],$g['area'],$g['progress'],$g['horizon']) || !valid_uuid((string)$g['id']) ||
        !is_string($g['name']) || trim($g['name'])==='' || mb_strlen($g['name'])>255 ||
        !in_array($g['area'],$areas,true) || !is_int($g['progress']) || $g['progress']<0 || $g['progress']>100 ||
        !in_array($g['horizon'],$horizons,true)) api_error('VALIDATION_ERROR',"Invalid goal at index $i.",422);
}
foreach ($state['tasks'] as $i=>$t) {
    if (!is_array($t) || array_diff(array_keys($t),['id','taskDate','name','area','importance','urgency','minutes','done']) ||
        !isset($t['id'],$t['name'],$t['area'],$t['importance'],$t['urgency'],$t['minutes'],$t['done']) ||
        !valid_uuid((string)$t['id']) || !is_string($t['name']) || trim($t['name'])==='' || mb_strlen($t['name'])>255 ||
        !in_array($t['area'],$areas,true) || !is_int($t['importance']) || $t['importance']<0 || $t['importance']>10 ||
        !is_int($t['urgency']) || $t['urgency']<0 || $t['urgency']>10 || !is_int($t['minutes']) || $t['minutes']<0 || $t['minutes']>1440 ||
        !is_bool($t['done'])) api_error('VALIDATION_ERROR',"Invalid task at index $i.",422);
    if (array_key_exists('taskDate',$t) && $t['taskDate']!==null && (!is_string($t['taskDate']) || !valid_date($t['taskDate']))) api_error('VALIDATION_ERROR',"Invalid taskDate at index $i.",422);
}
foreach ($state['reviews'] as $i=>$r) {
    if (!is_array($r) || array_diff(array_keys($r),['date','score','win','waste','tomorrow']) || !isset($r['date']) ||
        !is_string($r['date']) || !valid_date($r['date']) ||
        (isset($r['score']) && (!is_int($r['score']) || $r['score']<1 || $r['score']>10))) api_error('VALIDATION_ERROR',"Invalid review at index $i.",422);
}

try {
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT version FROM priority_state WHERE user_id=? FOR UPDATE'); $q->execute([$uid]);
    $serverVersion=(int)$q->fetchColumn();
    if ($serverVersion!==$body['version']) {
        $pdo->rollBack();
        api_error('VERSION_CONFLICT','Server contains a newer state.',409,['serverVersion'=>$serverVersion]);
    }

    $pdo->prepare('UPDATE priority_state SET goal_horizon=?,version=version+1 WHERE user_id=?')->execute([$state['goalHorizon'],$uid]);

    $pdo->prepare('DELETE FROM priority_goals WHERE user_id=?')->execute([$uid]);
    $ins=$pdo->prepare('INSERT INTO priority_goals(id,user_id,name,area,progress,horizon) VALUES(?,?,?,?,?,?)');
    foreach($state['goals'] as $g) $ins->execute([$g['id'],$uid,trim($g['name']),$g['area'],$g['progress'],$g['horizon']]);

    $old=$pdo->prepare('SELECT id,done,completed_at FROM priority_tasks WHERE user_id=?'); $old->execute([$uid]);
    $oldMap=[]; foreach($old->fetchAll() as $r) $oldMap[$r['id']]=$r;
    $pdo->prepare('DELETE FROM priority_tasks WHERE user_id=?')->execute([$uid]);
    $ins=$pdo->prepare('INSERT INTO priority_tasks(id,user_id,task_date,name,area,importance,urgency,minutes,done,completed_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
    foreach($state['tasks'] as $t) {
        $prev=$oldMap[$t['id']]??null; $completed=null;
        if ($t['done']) $completed=($prev && (bool)$prev['done'] && $prev['completed_at'])?$prev['completed_at']:date('Y-m-d H:i:s');
        $ins->execute([$t['id'],$uid,$t['taskDate']??null,trim($t['name']),$t['area'],$t['importance'],$t['urgency'],$t['minutes'],$t['done']?1:0,$completed]);
    }

    $ins=$pdo->prepare('INSERT INTO priority_reviews(user_id,review_date,score,win,waste,tomorrow) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),win=VALUES(win),waste=VALUES(waste),tomorrow=VALUES(tomorrow)');
    foreach($state['reviews'] as $r) $ins->execute([$uid,$r['date'],$r['score']??null,$r['win']??null,$r['waste']??null,$r['tomorrow']??null]);

    $q=$pdo->prepare('SELECT version,updated_at FROM priority_state WHERE user_id=?'); $q->execute([$uid]); $meta=$q->fetch();
    $pdo->commit();
    json_out(['ok'=>true,'version'=>(int)$meta['version'],'updatedAt'=>iso($meta['updated_at'])]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_error('INTERNAL_ERROR','State could not be saved.',500);
}
