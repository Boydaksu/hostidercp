'use strict';
(function(root){
 class Fence {
  constructor(){this.generation=0;this.sequence=0;this.applied=0;this.ticket=null;this.version=0;this.epoch=null;this.stopped=false;this.busy=false;}
  select(id){this.generation++;this.ticket=id;this.version=0;this.applied=0;return this.generation;}
  request(){return {generation:this.generation,ticket:this.ticket,sequence:++this.sequence};}
  accepts(token,data){if(this.stopped||token.generation!==this.generation||token.ticket!==this.ticket||token.sequence<this.applied)return false;const v=Number(data.ticket?.version??data.version??0);if(v<this.version)return false;if(this.epoch&&data.scope_epoch&&this.epoch!==data.scope_epoch){this.stop();return false;}this.epoch=data.scope_epoch||this.epoch;this.version=v;this.applied=token.sequence;return true;}
  stop(){this.stopped=true;this.generation++;}
 }
 if(typeof module!=='undefined'&&module.exports)module.exports={Fence};else root.SupportState={Fence};
})(globalThis);
