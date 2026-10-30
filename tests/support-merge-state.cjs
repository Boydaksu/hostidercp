 'use strict';
const assert=require('node:assert/strict'),{Fence}=require('../public/assets/support-state.js');
const f=new Fence();f.select('source');f.accepts(f.request(),{ticket:{version:3},scope_epoch:'scope'});const before=f.request();
f.select('target');assert.equal(f.accepts(before,{ticket:{version:4},scope_epoch:'scope',merge_alias:{read_only:true}}),false);console.log('PASS F5-G late source alias cannot replace selected merge target');
assert.equal(f.accepts(f.request(),{ticket:{version:7},scope_epoch:'scope'}),true);assert.equal(f.accepts(f.request(),{ticket:{version:6},scope_epoch:'scope'}),false);console.log('PASS F5-G stale pre merge target version cannot replace merged projection');
assert.equal(f.accepts(f.request(),{ticket:{version:8},scope_epoch:'revoked'}),false);assert.equal(f.stopped,true);console.log('PASS F5-G merge projection scope revocation stops native UI state');
console.log('3 state checks, 0 failures');
