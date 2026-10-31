'use strict';
(function(root){
 class Fence {
  constructor(){this.generation=0;this.sequence=0;this.applied=0;this.ticket=null;this.version=0;this.epoch=null;this.stopped=false;this.busy=false;}
  select(id){this.generation++;this.ticket=id;this.version=0;this.applied=0;return this.generation;}
  request(){return {generation:this.generation,ticket:this.ticket,sequence:++this.sequence};}
  accepts(token,data){if(this.stopped||token.generation!==this.generation||token.ticket!==this.ticket||token.sequence<this.applied)return false;const v=Number(data.ticket?.version??data.version??0);if(v<this.version)return false;if(this.epoch&&data.scope_epoch&&this.epoch!==data.scope_epoch){this.stop();return false;}this.epoch=data.scope_epoch||this.epoch;this.version=v;this.applied=token.sequence;return true;}
  stop(){this.stopped=true;this.generation++;}
 }
 function splitSelection(messages,ids,total){if(!Array.isArray(ids)||ids.length<1||ids.length>25||new Set(ids).size!==ids.length||!Number.isInteger(total)||ids.length>=total)throw new Error('En az bir public mesaj source üzerinde kalmalı; 1–25 farklı mesaj seçin.');const allowed=new Map(messages.filter(m=>m.customer_visible===true&&['customer_reply','staff_reply'].includes(m.type)&&!m.transferred&&!m.split_id).map(m=>[m.id,m]));for(const id of ids)if(typeof id!=='string'||!allowed.has(id))throw new Error('Mesaj seçimi güncel public kapsamda değil.');return [...ids].sort();}
 function splitIntentCurrent(fence,generation,epoch){return !fence.stopped&&generation===fence.generation&&epoch===fence.epoch;}
 if(typeof module!=='undefined'&&module.exports)module.exports={Fence,splitSelection,splitIntentCurrent};else root.SupportState={Fence,splitSelection,splitIntentCurrent};
})(globalThis);
