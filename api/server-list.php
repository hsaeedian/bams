<?php
require_once __DIR__ . '/initial.php';
$doc = new DOMDocument('1.0','UTF-8'); $root = $doc->createElement('response'); $doc->appendChild($root);
xml_text($root,'returncode','SUCCESS');
foreach (sql('SELECT * FROM servers WHERE enabled=1 ORDER BY id')->fetchAll() as $s) {
    if (!can_access($user,$s)) continue;
    $node = $doc->createElement('server'); $root->appendChild($node);
    foreach (['id','type','domain'] as $k) xml_text($node,$k,$s[$k]);
    xml_text($node,'serverTitle',$s['title']);
}
echo $doc->saveXML();
