#!/usr/bin/env python3
"""End-to-end HTTP regression tests with PHP and an offline mock BBB (no real servers)."""
import hashlib
import http.cookiejar
import http.server
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
APP_ROOT = Path(os.environ.get('BAMS_TEST_APP_ROOT', ROOT)).resolve()
PHP = os.environ.get('PHP_BIN') or shutil.which('php')
if not PHP:
    print('SKIP: PHP CLI is unavailable.',file=sys.stderr)
    sys.exit(2)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

class Client:
    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())
    def request(self, path, data=None):
        data = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
        try:
            response = self.opener.open(self.base + path.lstrip('/'), data, timeout=15)
        except urllib.error.HTTPError as response_error:
            response = response_error
        return response.status, response.read().decode(), response.headers
    def token(self, page='login.php'):
        code, body, _ = self.request(page)
        assert code == 200, 'token page'
        return re.search(r'name="csrf-token" content="([^"]+)"', body).group(1)
    def login(self, username, password):
        token = self.token()
        return self.request('api/login.php', {'csrf':token,'username':username,'password':password})

def check(condition, label):
    if not condition:
        raise AssertionError(label)
    print('PASS:', label)

class MockBBB(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass
    def do_GET(self):
        parsed = urllib.parse.urlsplit(self.path)
        query, checksum = parsed.query.rsplit('&checksum=',1) if '&checksum=' in parsed.query else ('',parsed.query.removeprefix('checksum='))
        valid = hashlib.sha1(('getMeetings'+query+'fake-http-secret').encode()).hexdigest() == checksum
        if parsed.path != '/bigbluebutton/api/getMeetings' or not valid:
            self.send_response(403); self.end_headers(); return
        self.send_response(200); self.end_headers()
        self.wfile.write('<response><returncode>SUCCESS</returncode><meetings><meeting><meetingID>class-375-122[0]</meetingID><meetingName>کلاس</meetingName><moderatorPW> p&amp;+=%?#\\ </moderatorPW><attendeePW>fake</attendeePW></meeting></meetings></response>'.encode())

with tempfile.TemporaryDirectory(prefix='bams-http-') as temporary:
    private = Path(temporary)
    bootstrap = private / 'bootstrap.php'
    bootstrap.write_text("<?php return ['database'=>" + json.dumps(str(private/'bams.sqlite')) + "];\n")
    env = dict(os.environ, BAMS_BOOTSTRAP=str(bootstrap))
    unsafe_bootstrap = private/'unsafe-bootstrap.php'
    # CLI initialization runs from the source tree even when HTTP serves a package.
    forbidden = ROOT/'forbidden-test.sqlite'
    unsafe_bootstrap.write_text("<?php return ['database'=>" + json.dumps(str(forbidden)) + "];\n")
    refused = subprocess.run([PHP,str(ROOT/'bin/bams.php'),'--init','--admin=fake','--url=https://fake.example.invalid/','--password-stdin'],input=b'fake-path-test-password\n',env=dict(os.environ,BAMS_BOOTSTRAP=str(unsafe_bootstrap)),stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    check(refused.returncode != 0 and not forbidden.exists(),'database inside document root refused before creation')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}/'
    subprocess.run([PHP, str(ROOT/'bin/bams.php'),'--init','--admin=admin','--url='+base,'--password-stdin'],input=b'fake-http-admin-password\n',env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,check=True)
    mock = http.server.ThreadingHTTPServer(('127.0.0.1',0),MockBBB)
    threading.Thread(target=mock.serve_forever,daemon=True).start()
    mock_origin = f'http://127.0.0.1:{mock.server_port}'
    with (private/'php.log').open('w') as log:
        process = subprocess.Popen([PHP,'-d',f'session.save_path={private}','-S',f'127.0.0.1:{port}','-t',str(APP_ROOT)],env=env,stdout=log,stderr=log)
        try:
            anonymous = Client(base)
            for attempt in range(50):
                try:
                    anonymous.request('login.php'); break
                except OSError:
                    time.sleep(.1)
            for path in ['api/server-list.php','api/server-item.php?id=1','api/resource.php?id=1','api/join.php?serverId=1&meetingId=x','admin.php']:
                check(anonymous.request(path)[0]==401,'unauthenticated '+path.split('?')[0])
            check(anonymous.request('index.php')[0]==302,'page authentication redirect terminates')
            check('Installation is locked' in anonymous.request('install.php')[1],'installer locked for existing SQLite installation')
            check(anonymous.request('install.php',{'admin_password':'fake-injected-password'})[0]==403,'installed setup rejects reset POST')
            check(anonymous.request('api/login.php',{'username':'admin','password':'fake-http-admin-password'})[0]==403,'login CSRF rejected')
            before = next(iter(anonymous.jar)).value
            login_code, _, login_headers = anonymous.login('admin','fake-http-admin-password')
            check(login_code==200,'login succeeds')
            check(next(iter(anonymous.jar)).value != before,'login regenerates session ID')
            token = anonymous.token('admin.php')
            code, body, headers = anonymous.request('admin.php')
            check(code==200 and 'fake-http-admin-password' not in body,'administrator page contains no user password')
            check('HttpOnly' in login_headers.get('Set-Cookie','') and 'SameSite=Lax' in login_headers.get('Set-Cookie',''),'session cookie protections')
            check(anonymous.request('admin.php',{'action':'server.save'})[0]==403,'administrator CSRF rejected')
            check('name="id"' not in body and 'Server ID will be generated automatically' in body,'new server form needs no ID input')
            data = {'csrf':token,'action':'server.save','type':'B','title':'Mock','domain':mock_origin,'secret':'fake-http-secret','enabled':'1'}
            check(anonymous.request('admin.php?tab=servers',data)[0]==303,'server create via admin')
            listing = ET.fromstring(anonymous.request('api/server-list.php')[1])
            mock_id = listing.findtext('server/id')
            check(bool(re.fullmatch(r'srv-[a-f0-9]{32}',mock_id or '')),'server receives automatic ID')
            # Test an unsaved candidate without changing the saved configuration.
            data.update(action='server.test',edit_id=mock_id,title='Unsaved title',secret='')
            code,body,_ = anonymous.request('admin.php?tab=servers&edit='+mock_id,data)
            check(code==200 and 'Connection and authentication succeeded.' in body and 'fake-http-secret' not in body,'read-only connection test and secret redaction')
            code,body,_ = anonymous.request('api/server-item.php?id='+mock_id)
            root = ET.fromstring(body)
            check(code==200 and root.find('.//moderatorPW') is None,'monitoring strips raw credentials')
            url = root.findtext('.//join')
            query, checksum = urllib.parse.urlsplit(url).query.rsplit('&checksum=',1)
            values = urllib.parse.parse_qs(query,keep_blank_values=True)
            check(values['meetingID']==['class-375-122[0]'] and values['password']==[' p&+=%?#\\ '],'backend XML exact meeting and password')
            check(hashlib.sha1(('join'+query+'fake-http-secret').encode()).hexdigest()==checksum,'backend XML checksum')
            code,body,_ = anonymous.request('api/join.php?'+urllib.parse.urlencode({'serverId':mock_id,'meetingId':'class-375-122[0]','password':'tampered','name':'tampered'}))
            values=urllib.parse.parse_qs(urllib.parse.urlsplit(ET.fromstring(body).findtext('join')).query)
            check(code==200 and values['password']==[' p&+=%?#\\ '] and values['fullName']==['admin'],'join derives credentials and name server-side')
            user = {'csrf':token,'action':'user.save','username':'viewer','name':'Viewer','role':'viewer','enabled':'1','password':'fake-http-viewer-password'}
            check(anonymous.request('admin.php?tab=users',user)[0]==303,'viewer create with empty ACL')
            viewer = Client(base); check(viewer.login('viewer','fake-http-viewer-password')[0]==200,'viewer login')
            check(viewer.request('admin.php')[0]==403 and viewer.request('admin.php',{'csrf':viewer.token('index.php'),'action':'settings.save','app_url':base})[0]==403,'viewer admin read/write rejected')
            check(not ET.fromstring(viewer.request('api/server-list.php')[1]).findall('server'),'empty ACL monitoring list')
            for path in ['api/join.php?serverId='+mock_id+'&meetingId=x','api/server-item.php?id='+mock_id,'api/resource.php?id='+mock_id]:
                check(viewer.request(path)[0]==403,'ACL rejected '+path.split('?')[0])
            data.update(action='server.save',enabled='',secret='')
            check(anonymous.request('admin.php?tab=servers',data)[0]==303,'disable server')
            check(anonymous.request('api/join.php?serverId='+mock_id+'&meetingId=x')[0]==403,'disabled server join denied')
            import sqlite3
            with sqlite3.connect(private/'bams.sqlite') as db:
                viewer_id = db.execute("SELECT id FROM users WHERE username='viewer'").fetchone()[0]
            user.update(id=viewer_id,enabled='',password='')
            check(anonymous.request('admin.php?tab=users',user)[0]==303,'disable viewer')
            check(viewer.request('api/server-list.php')[0]==401,'disabled session rejected on next request')
            for path in ['../var/bams.sqlite','/var/bams.sqlite','/config/bootstrap.php','/bams.sqlite','/bootstrap.php']:
                check(anonymous.request(path)[0]==404,'private path inaccessible '+path)
            check(anonymous.request('logout.php')[0]==405,'GET logout rejected')
            check(anonymous.request('logout.php',{'csrf':token})[0]==302,'POST logout succeeds')
            check(anonymous.request('api/server-list.php')[0]==401,'logout session removed')
            print('All HTTP tests passed. No live BBB server contacted.')
        finally:
            process.terminate(); process.wait(timeout=5); mock.shutdown(); mock.server_close()
