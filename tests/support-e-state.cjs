'use strict';
const assert=require('node:assert/strict'),{Fence}=require('../public/assets/support-state.js');
const f=new Fence();f.select('A');const a=f.request();f.select('B');assert.equal(f.accepts(a,{version:9}),false);console.log('PASS F5-E selection generation rejects a late response for another ticket');
const b=f.request(),c=f.request();assert.equal(f.accepts(c,{version:3,scope_epoch:'one'}),true);assert.equal(f.accepts(b,{version:2,scope_epoch:'one'}),false);assert.equal(f.accepts(f.request(),{version:1,scope_epoch:'one'}),false);console.log('PASS F5-E response sequence and ticket version prevent backward projection');
assert.equal(f.accepts(f.request(),{version:4,scope_epoch:'two'}),false);assert.equal(f.stopped,true);assert.equal(f.accepts(f.request(),{version:5,scope_epoch:'two'}),false);console.log('PASS F5-E changed scope epoch stops projection until native reload');
console.log('3 state checks, 0 failures');
