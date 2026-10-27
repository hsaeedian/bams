// Dependency-free Firefox smoke test for XML -> models -> anchors. No PHP required.
import {createServer} from 'node:http';
import {readFileSync,mkdtempSync,rmSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {tmpdir} from 'node:os';
import {resolve,extname} from 'node:path';
import {spawn} from 'node:child_process';
const root=resolve(import.meta.dirname,'..');
const encode=v=>encodeURIComponent(v).replace(/[!'()*]/g,c=>'%'+c.charCodeAt(0).toString(16).toUpperCase());
const xml=v=>v.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
const fixtures=['normal','class-375-122[0]','class-375-122[12]',' space فارسی \\ ','&+=%?#'].map(id=>{
 const name=' علی &+ =%?# \\ ',password=' pass&+=%?#\\ ';
 const query=Object.entries({fullName:name,meetingID:id,password}).map(([k,v])=>k+'='+encode(v)).join('&');
 const url='https://bbb.example.invalid/bigbluebutton/api/join?'+query+'&checksum='+createHash('sha1').update('join'+query+'fake-secret').digest('hex');
 return {id,name,password,url};
});
let complete;
const done=new Promise(resolve=>{complete=resolve;});
const server=createServer((req,res)=>{
 if (req.url==='/result' && req.method==='POST') {
  let body='';req.on('data',c=>body+=c);req.on('end',()=>{res.end('ok');complete(JSON.parse(body));});return;
 }
 if(req.url==='/fixtures.json'){res.setHeader('Content-Type','application/json');res.end(JSON.stringify(fixtures));return;}
 const match=/^\/fixture-(\d+)\.xml$/.exec(req.url);
 if(match){const f=fixtures[+match[1]];res.setHeader('Content-Type','application/xml');res.end('<response><meeting><meetingID>'+xml(f.id)+'</meetingID><meetingName>کلاس</meetingName><join>'+xml(f.url)+'</join></meeting></response>');return;}
 const path=req.url==='/'?'tests/browser.html':req.url.slice(1);
 if(!/^(js\/[a-z]+\.js|tests\/browser\.html)$/.test(path)){res.writeHead(404);res.end();return;}
 res.setHeader('Content-Type',extname(path)==='.js'?'text/javascript':'text/html');res.end(readFileSync(resolve(root,path)));
});
try { await new Promise((resolve,reject)=>{ server.once('error',reject);server.listen(0,'127.0.0.1',resolve); }); }
catch(e) { console.error('Browser test could not start its local HTTP server: '+e.message);process.exit(2); }
const profile=mkdtempSync(resolve(tmpdir(),'bams-firefox-'));
const browser=spawn(process.env.FIREFOX_BIN || 'firefox',['--headless','--no-remote','--profile',profile,`http://127.0.0.1:${server.address().port}/`],{stdio:['ignore','ignore','pipe']});
let diagnostic='';browser.stderr.on('data',c=>diagnostic+=c);
browser.on('error',e=>complete({ok:false,error:e.message}));
const timeout=setTimeout(()=>complete({ok:false,error:'Firefox timed out. '+diagnostic.slice(0,1000)}),25000);
try {const result=await done; if(!result.ok)throw new Error(result.error); for(const test of result.results)console.log('PASS: '+test);console.log('All frontend browser tests passed.');}
catch(e){console.error(e.message);process.exitCode=1;}
finally {clearTimeout(timeout);browser.kill('SIGTERM');server.closeAllConnections();server.close();if(browser.exitCode===null && browser.pid) await new Promise(r=>browser.once('exit',r));rmSync(profile,{recursive:true,force:true});}
