/* Priority Life sync client v1
 * localStorage stays the offline/cache layer; server is the cross-device source.
 */
window.PrioritySync=(()=>{
 const KEY="priorityLife", META="priorityLifeSync", API="/api";
 let timer=null,busy=false,ready=false,version=null;

 const meta=()=>{try{return JSON.parse(localStorage.getItem(META)||"{}")}catch{return {}}};
 const setMeta=p=>localStorage.setItem(META,JSON.stringify({...meta(),...p}));
 const uuid=()=>crypto.randomUUID?crypto.randomUUID():"xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g,c=>{const r=Math.random()*16|0,v=c==="x"?r:(r&3|8);return v.toString(16)});
 const localDate=()=>{const d=new Date(),o=d.getTimezoneOffset();return new Date(d.getTime()-o*60000).toISOString().slice(0,10)};
 function normalize(s){
  const x=typeof structuredClone==="function"?structuredClone(s):JSON.parse(JSON.stringify(s));
  x.goalHorizon=x.goalHorizon||"10 let"; x.goals=Array.isArray(x.goals)?x.goals:[]; x.tasks=Array.isArray(x.tasks)?x.tasks:[];
  x.goals.forEach(g=>{g.id=g.id||uuid()});
  x.tasks.forEach(t=>{t.id=t.id||uuid();t.taskDate=t.taskDate||localDate()});
  return x;
 }
 function wire(s){
  return {goalHorizon:s.goalHorizon,goals:s.goals.map(g=>({id:g.id,name:g.name,area:g.area,progress:Number(g.progress)||0,horizon:g.horizon})),
   tasks:s.tasks.map(t=>({id:t.id,taskDate:t.taskDate||null,name:t.name,area:t.area,importance:Number(t.imp??t.importance)||0,urgency:Number(t.urg??t.urgency)||0,minutes:Number(t.mins??t.minutes)||0,done:!!t.done})),
   reviews:s.review?[{date:(s.review.date||localDate()).slice(0,10),score:s.review.score??null,win:s.review.win??null,waste:s.review.waste??null,tomorrow:s.review.tomorrow??null}]:[]};
 }
 function fromServer(p,current){
  const s={...current,goalHorizon:p.goalHorizon,goals:p.goals.map(g=>({id:g.id,name:g.name,area:g.area,progress:g.progress,horizon:g.horizon})),
   tasks:p.tasks.map(t=>({id:t.id,taskDate:t.taskDate,name:t.name,area:t.area,imp:t.importance,urg:t.urgency,mins:t.minutes,done:t.done}))};
  const last=p.reviews?.[p.reviews.length-1]; if(last)s.review={date:last.date,score:last.score,win:last.win,waste:last.waste,tomorrow:last.tomorrow};
  return s;
 }
 async function req(path,opt={}){const r=await fetch(API+path,{credentials:"same-origin",cache:"no-store",...opt});let j={};try{j=await r.json()}catch{};if(!r.ok){const e=new Error(j?.error?.message||("HTTP "+r.status));e.status=r.status;e.data=j;throw e}return j}
 async function csrf(){return (await req("/csrf.php")).csrfToken}
 async function put(s,v){return req("/state.php",{method:"PUT",headers:{"Content-Type":"application/json","X-CSRF-Token":await csrf()},body:JSON.stringify({version:v,state:wire(s)})})}
 async function init(getState,setState){
  let s=normalize(getState()); localStorage.setItem(KEY,JSON.stringify(s)); setState(s);
  try{
   const remote=await req("/state.php"); version=remote.version; const m=meta();
   const hasLocal=localStorage.getItem(KEY)!==null;
   const imported=!!m.imported;
   const serverEmpty=(remote.state.goals.length===0&&remote.state.tasks.length===0&&remote.state.reviews.length===0);
   if(hasLocal&&!imported&&serverEmpty){
    const saved=await put(s,version);version=saved.version;setMeta({imported:true,version,syncedAt:saved.updatedAt,status:"synced"});
   }else{
    s=normalize(fromServer(remote.state,s));localStorage.setItem(KEY,JSON.stringify(s));setState(s);
    setMeta({imported:true,version,syncedAt:remote.updatedAt,status:"synced"});
   }
   ready=true; window.dispatchEvent(new CustomEvent("priority-sync-ready"));
  }catch(e){ready=true;setMeta({status:"offline",lastError:String(e.message)});}
 }
 function changed(getState){
  if(!ready)return;clearTimeout(timer);setMeta({status:"pending"});
  timer=setTimeout(()=>sync(getState),650);
 }
 async function sync(getState){
  if(busy||version===null)return;busy=true;
  try{const s=normalize(getState());const saved=await put(s,version);version=saved.version;setMeta({version,syncedAt:saved.updatedAt,status:"synced",lastError:null})}
  catch(e){
   if(e.status===409){try{const remote=await req("/state.php");version=remote.version;setMeta({version,status:"conflict",lastError:"Novější data jsou na serveru; automatické přepsání bylo zablokováno."})}catch{}}
   else setMeta({status:navigator.onLine?"error":"offline",lastError:String(e.message)});
  }finally{busy=false}
 }
 window.addEventListener("online",()=>{if(ready)changed(()=>window.priorityState?.())});
 return {init,changed,status:meta};
})();