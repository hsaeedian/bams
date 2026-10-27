<?php
require_once __DIR__ . '/initial.php';
require_once __DIR__ . '/../lib/adobeconnect.php';
$s = server_for($user,clean_text($_GET,'id',80));
if ($s['type'] === BIGBLUEBUTTON) {
    $doc = bbb_meetings($s);
    sanitize_meetings($doc,$s,$user['name']);
} else {
    $ac = new AdobeConnectClient($s['domain'],$s['login'],$s['password']);
    $data = $ac->makeAuth()->getActiveMeetings();
    $doc = new DOMDocument('1.0','UTF-8'); $root = $doc->createElement('response'); $doc->appendChild($root); xml_text($root,'returncode','SUCCESS');
    $meetings = $data['report-active-meetings']['row'] ?? $data['report-active-meetings']['meeting'] ?? $data['report-active-meetings'] ?? [];
    if (isset($meetings['@attributes'])) $meetings = [$meetings];
    foreach ($meetings as $am) {
        if (!is_array($am) || !isset($am['@attributes'])) continue;
        $path = $am['url-path'] ?? '/';
        if (!is_string($path) || !str_starts_with($path,'/') || str_starts_with($path,'//')) throw new RuntimeException('Invalid meeting path.');
        $row = $doc->createElement('meeting'); $root->appendChild($row);
        foreach (['sco-id','active-participants','length-minutes'] as $key) xml_text($row,$key,$am['@attributes'][$key] ?? '');
        xml_text($row,'name',$am['name'] ?? ''); xml_text($row,'url-path',address($s['domain']) . $path); xml_text($row,'date-begin',substr($am['date-begin'] ?? '',0,19));
    }
}
echo $doc->saveXML();
