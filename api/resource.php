<?php
require_once __DIR__ . '/initial.php';
$s = server_for($user,clean_text($_GET,'id',80));
$data = fetch_remote(address($s['domain']) . '/bams' . ($s['type'] === BIGBLUEBUTTON ? '.php' : ''));
if (!preg_match('/^\s*\d+-\d+-\d+\s*$/D',$data)) throw new RuntimeException('Invalid resource response.');
xml_response(['returncode'=>'SUCCESS','data'=>$data]);
