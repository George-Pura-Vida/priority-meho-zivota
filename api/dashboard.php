<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_auth();
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { header('Allow: GET'); api_error('METHOD_NOT_ALLOWED','Only GET is allowed.',405); }
$pdo=db(); $uid=PRIORITY_USER_ID;
$q=$pdo->prepare('SELECT id,name,area,importance,urgency,minutes,done FROM priority_tasks WHERE user_id=? AND task_date=?');
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->format('Y-m-d');
$q->execute([$uid,$today]); $tasks=$q->fetchAll();
$total=count($tasks); $completed=0; $open=[]; $quads=[1=>0,2=>0,3=>0,4=>0];
foreach($tasks as $t){ if((bool)$t['done']){$completed++;continue;} $imp=(int)$t['importance'];$urg=(int)$t['urgency'];$quad=$imp>=6?($urg>=6?1:2):($urg>=6?3:4);$quads[$quad]++;$t['_q']=$quad;$t['_score']=$imp*3+$urg+($quad===2?8:0);$open[]=$t; }
usort($open,fn($a,$b)=>$b['_score']<=>$a['_score']);
$focus=$open[0]??null;
if($focus)$focus=['id'=>$focus['id'],'name'=>$focus['name'],'area'=>$focus['area'],'importance'=>(int)$focus['importance'],'urgency'=>(int)$focus['urgency'],'minutes'=>(int)$focus['minutes'],'quadrant'=>$focus['_q']];
$q=$pdo->prepare('SELECT updated_at FROM priority_state WHERE user_id=?');$q->execute([$uid]);$updated=$q->fetchColumn();
json_out(['ok'=>true,'priority'=>['completed'=>$completed,'total'=>$total,'completionPercent'=>$total?round($completed*100/$total):0,'focus'=>$focus,'openTasks'=>count($open),'quadrants'=>['q1'=>$quads[1],'q2'=>$quads[2],'q3'=>$quads[3],'q4'=>$quads[4]]],'updatedAt'=>iso($updated?:null)]);
