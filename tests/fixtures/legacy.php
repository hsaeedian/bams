<?php
// Fictitious offline fixture. Never use these credentials in a deployment.
require_once __DIR__ . '/../../meta.php';
define('URL','https://bams.example.invalid/');
const SERVERS = [
 ['id'=>'1','type'=>BIGBLUEBUTTON,'domain'=>'bbb.example.invalid','title'=>'Test BBB','secret'=>'fake-bbb-secret'],
 ['id'=>'node-2','type'=>ADOBE_CONNECT,'domain'=>'http://192.168.20.2:8080','title'=>'Test Adobe','login'=>'fake-login','password'=>'fake-adobe-password'],
];
const USERS = [
 ['username'=>'owner','password'=>'fake-owner-password','name'=>'مدیر آزمایشی'],
 ['username'=>'limited','password'=>'fake-viewer-password','name'=>'Viewer','acl'=>['node-2']],
 ['username'=>'legacy-empty','password'=>'fake-empty-password','name'=>'Legacy empty','acl'=>[]],
];
