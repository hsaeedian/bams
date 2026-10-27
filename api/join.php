<?php
require_once __DIR__ . '/initial.php';
$server = server_for($user,clean_text($_GET,'serverId',80));
if ($server['type'] !== BIGBLUEBUTTON) throw new HttpError(400,'BBB server required.');
$id = clean_text($_GET,'meetingId',4096);
$doc = bbb_meetings($server);
foreach ($doc->getElementsByTagName('meeting') as $m) {
    if (getChildNodeValue($m->childNodes,'meetingID') === $id) {
        // Preserve the existing moderator join role; ignore browser-supplied password/name.
        xml_response(['returncode'=>'SUCCESS','join'=>meeting_join($server,$m,$user['name'])]); exit;
    }
}
throw new HttpError(404,'Meeting not found.');
