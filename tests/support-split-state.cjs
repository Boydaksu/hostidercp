 'use strict';
const assert=require('node:assert/strict'),{Fence,splitSelection,splitIntentCurrent}=require('../public/assets/support-state.js');
const messages=[{id:'a',type:'customer_reply',customer_visible:true},{id:'b',type:'staff_reply',customer_visible:true},{id:'private',type:'internal_note',customer_visible:false},{id:'transferred',type:'customer_reply',customer_visible:true,transferred:true}];
assert.deepEqual(splitSelection(messages,['b'],2),['b']);console.log('PASS F5-H selected public message builds bounded intent');
for(const ids of [[],['a','a'],['a','b'],['private'],['transferred'],['foreign']])assert.throws(()=>splitSelection(messages,ids,2));console.log('PASS F5-H private foreign duplicate empty and all-public selection blocked');
assert.throws(()=>splitSelection(messages,Array.from({length:26},(_,i)=>String(i)),100));console.log('PASS F5-H selection max25 is enforced before native confirmation');
const f=new Fence();f.select('source');f.epoch='scope';const generation=f.generation;assert.equal(splitIntentCurrent(f,generation,'scope'),true);assert.equal(splitIntentCurrent(f,generation,'old'),false);f.select('other');assert.equal(splitIntentCurrent(f,generation,'scope'),false);const current=f.generation;f.stop();assert.equal(splitIntentCurrent(f,current,'scope'),false);console.log('PASS F5-H stale selection scope and stopped intent cannot display or execute preview');
console.log('4 state checks, 0 failures');
