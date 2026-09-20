/* Priority Life sync client v2 — fail closed: no silent overwrite. */
window.PrioritySync=(()=>{
 const KEY="priorityLife", META="priorityLifeSync", API="/api";
 let timer=null,busy=false,ready=false,version=null,dirty=false,conflict=false,getStateFn=null,setStateFn=null;
 const hadLocalAtBoot=localStorage.getItem(KEY)!==null;
 const meta=()=>{try{return JSON.parse(localStorage.getItem(META)||"{}")}catch{return {}}};
 const setMeta=p=>localStorage.setItem(META,JSON.stringify({...meta(),...p}));
 const uuid=()=>typeof crypto!=="undefined"&&typeof crypto.randomUUID==="function"?crypto.randomUUID():"xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g,c=>{const r=Math.random()*16|0,v=c==="x"?r:(r&3|8);return v.toString(16)});
 const localDate=()=>{const d=new Date(),o=d.getTimezoneOffset();return new Date(d.getTime()-o*60000).toISOString().slice(0,10)};
 const clone=x=>typeof structuredClone==="function"?structuredClone(x):JSON.parse(JSON.stringify(x));
 const fingerprint=s=>{const str=JSON.stringify(wire(normalize(s,{stampMissingDates:false})));let h=2166136261;for(let i=0;i<str.length;i++){h^=str.charCodeAt(i);h=Math.imul(h,16777619)}return (h>>>0).toString(16).padStart(8,"0")};
 const backupLocal=()=>{try{const raw=localStorage.getItem(KEY);if(raw)localStorage.setItem(KEY+"Backup",raw)}catch{}};
 function normalize(s,{stampMissingDates=false}={}){
  const x=clone(s||{}); x.goalHorizon=x.goalHorizon||"10 let";x.goals=Array.isArray(x.goals)?x.goals:[];x.tasks=Array.isArray(x.tasks)?x.tasks:[];
  x.goals.forEach(g=>{g.id=g.id||uuid()});
  x.tasks.forEach(t=>{t.id=t.id||uuid();if(!("taskDate" in t))t.taskDate=stampMissingDates?localDate():null});
  return x;
 }
 function wire(s){return {goalHorizon:s.goalHorizon,goals:s.goals.map(g=>({id:g.id,name:g.name,area:g.area,progress:Number(g.progress)||0,horizon:g.horizon})),tasks:s.tasks.map(t=>({id:t.id,taskDate:t.taskDate||null,name:t.name,area:t.area,importance:Number(t.imp??t.importance)||0,urgency:Number(t.urg??t.urgency)||0,minutes:Number(t.mins??t.minutes)||0,done:!!t.done})),reviews:s.review?[{date:(s.review.date||localDate()).slice(0,10),score:s.review.score??null,win:s.review.win??null,waste:s.review.waste??null,tomorrow:s.review.tomorrow??null}]:[]}}
 function fromServer(p,current){const s={...current,goalHorizon:p.goalHorizon,goals:p.goals.map(g=>({id:g.id,name:g.name,area:g.area,progress:g.progress,horizon:g.horizon})),tasks:p.tasks.map(t=>({id:t.id,taskDate:t.taskDate,name:t.name,area:t.area,imp:t.importance,urg:t.urgency,mins:t.minutes,done:t.done}))};const last=p.reviews?.[p.reviews.length-1];s.review=last?{date:last.date,score:last.score,win:last.win,waste:last.waste,tomorrow:last.tomorrow}:null;return s}
 async function req(path,opt={}){const r=await fetch(API+path,{credentials:"same-origin",cache:"no-store",...opt});let j={};try{j=await r.json()}catch{}if(!r.ok){const e=new Error(j?.error?.message||("HTTP "+r.status));e.status=r.status;e.data=j;throw e}return j}
 async function csrf(){return(await req("/csrf.php")).csrfToken}
 async function put(s,v){return req("/state.php",{method:"PUT",headers:{"Content-Type":"application/json","X-CSRF-Token":await csrf()},body:JSON.stringify({version:v,state:wire(s)})})}
 const serverEmpty=r=>r.state.goals.length===0&&r.state.tasks.length===0&&r.state.reviews.length===0;
 function applyServer(remote){const s=normalize(fromServer(remote.state,getStateFn()),{stampMissingDates:false});localStorage.setItem(KEY,JSON.stringify(s));setStateFn(s);version=remote.version;dirty=false;conflict=false;setMeta({imported:true,version,syncedAt:remote.updatedAt,lastSyncedHash:fingerprint(s),status:"synced",lastError:null})}
 function markConflict(remote,msg){conflict=true;dirty=true;setMeta({status:"conflict",serverVersion:remote?.version??null,lastError:msg||"Lokální i serverová data se liší. Automatické přepsání bylo zablokováno."});window.dispatchEvent(new CustomEvent("priority-sync-conflict",{detail:{serverVersion:remote?.version??null}}))}
 async function init(getState,setState){
  getStateFn=getState;setStateFn=setState;
  let local=normalize(getState(),{stampMissingDates:hadLocalAtBoot});localStorage.setItem(KEY,JSON.stringify(local));setState(local);
  try{
   const remote=await req("/state.php");version=remote.version;const m=meta();
   if(serverEmpty(remote)){
    if(hadLocalAtBoot){const saved=await put(local,version);version=saved.version;setMeta({imported:true,version,syncedAt:saved.updatedAt,lastSyncedHash:fingerprint(local),status:"synced",lastError:null})}
    else {applyServer(remote)}
   }else if(!hadLocalAtBoot){applyServer(remote)}
   else if(m.version===remote.version && m.lastSyncedHash && m.lastSyncedHash===fingerprint(local) && m.status!=="pending" && m.status!=="offline" && m.status!=="error" && m.status!=="conflict"){
    applyServer(remote);
   }else{
    markConflict(remote,"Na zařízení i serveru existují data a nelze bezpečně určit novější verzi. Nic nebylo přepsáno.");
   }
  }catch(e){dirty=hadLocalAtBoot;setMeta({status:navigator.onLine?"error":"offline",lastError:String(e.message)})}
  ready=true;window.dispatchEvent(new CustomEvent("priority-sync-ready"));
 }
 function changed(getState){getStateFn=getState||getStateFn;dirty=true;if(!ready)return;clearTimeout(timer);setMeta({status:conflict?"conflict":"pending"});if(!conflict)timer=setTimeout(()=>flush(),650)}
 async function flush(){
  if(!ready||busy||conflict||!dirty||version===null||!getStateFn)return;
  busy=true;dirty=false;const snapshot=normalize(getStateFn(),{stampMissingDates:false});
  try{const saved=await put(snapshot,version);version=saved.version;setMeta({version,syncedAt:saved.updatedAt,lastSyncedHash:fingerprint(snapshot),status:dirty?"pending":"synced",lastError:null})}
  catch(e){
   dirty=true;
   if(e.status===409){let remote=null;try{remote=await req("/state.php")}catch{}markConflict(remote,"Server obsahuje novější verzi. Lokální změny zůstaly zachované a synchronizace je zablokovaná.")}
   else setMeta({status:navigator.onLine?"error":"offline",lastError:String(e.message)});
  }finally{busy=false;if(dirty&&!conflict){clearTimeout(timer);timer=setTimeout(()=>flush(),650)}}
 }
 async function useServer(){
  backupLocal();const remote=await req("/state.php");applyServer(remote);return {ok:true};
 }
 async function useLocal(){
  if(!conflict)throw new Error("No conflict to resolve.");
  const remote=await req("/state.php");version=remote.version;conflict=false;dirty=true;setMeta({version,status:"pending",lastError:null});await flush();return {ok:!conflict};
 }
 window.addEventListener("online",()=>{if(!ready||conflict)return;if(version===null){init(getStateFn,setStateFn);return}if(dirty)flush()});
 return {init,changed,status:meta,flush,useServer,useLocal};
})();