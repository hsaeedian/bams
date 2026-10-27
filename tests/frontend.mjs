// Unit tests for the frontend helpers. Real XML/anchor coverage is in browser.mjs.
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../js/node.js',import.meta.url),'utf8');
const {default:Node}=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
for(const value of ['normal','class-375-122[0]','class-375-122[12]',' نام فارسی \\ ','&+=%?#']) {
 const model=new Node({getElementsByTagName:()=>[{localName:'meetingID',textContent:value}]});
 assert.equal(model._find('meetingid'),value);
}
console.log('PASS: case-insensitive extraction preserves all raw values and whitespace');
const model=new Node(null);
globalThis.document={createElement:tag=>({tag,children:[],appendChild(child){this.children.push(child);}})};
const badge=model._createBadgeItem('title','<img onerror=unsafe()>','test');
assert.equal(badge.children[1].innerHTML,undefined);
assert.equal(badge.children[1].textContent,'<img onerror=unsafe()>');
console.log('PASS: untrusted badge values use text rendering');
const unsafe=model._createLinkItem('Join','javascript:unsafe()','test');
assert.equal(unsafe.href,undefined);
console.log('PASS: unsafe link schemes rejected');
const expected='<response><join>https://example.invalid/?x=%5B0%5D&amp;y=%26</join></response>';
const parsed={querySelector:()=>null};
globalThis.DOMParser=class {parseFromString(body,type){assert.equal(type,'application/xml');assert.equal(body,expected);return parsed;}};
globalThis.XMLHttpRequest=class {
 open(method,url){assert.equal(method,'GET');assert.equal(url,'/fake');}
 send(){this.readyState=4;this.status=200;this.responseText=expected;this.onreadystatechange();}
};
let called=false;model.request('/fake',value=>{called=true;assert.equal(value,parsed);});assert.equal(called,true);
console.log('PASS: request passes unchanged response bytes to the XML parser');
console.log('All frontend helper unit tests passed (DOM and transport mocks; no browser XML integration).');
