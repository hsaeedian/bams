<?php
class HttpError extends RuntimeException
{
    public int $status;
    public function __construct(int $status, string $message) { parent::__construct($message); $this->status = $status; }
}
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
// Compatibility helper: validation and rendering are deliberately separate.
function test_input($data) { return is_string($data) ? $data : null; }
function completeUrl(array $server, string $callName, array $params = []): string
{
    if (!preg_match('/^[A-Za-z]+$/D', $callName)) throw new InvalidArgumentException('Invalid BBB method.');
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return address($server['domain']) . ($server['api_path'] ?? '/bigbluebutton/api/') . $callName . '?' . ($query === '' ? '' : $query . '&') . 'checksum=' . sha1($callName . $query . $server['secret']);
}
function xml_text(DOMNode $parent, string $name, $value): DOMElement
{
    $doc = $parent instanceof DOMDocument ? $parent : $parent->ownerDocument;
    $node = $doc->createElement($name); $node->appendChild($doc->createTextNode((string)$value)); $parent->appendChild($node); return $node;
}
function xml_response(array $values, int $status = 200): void
{
    http_response_code($status); header('Content-Type: application/xml; charset=UTF-8');
    $doc = new DOMDocument('1.0','UTF-8'); $root = $doc->createElement('response'); $doc->appendChild($root);
    foreach ($values as $key=>$value) xml_text($root,$key,$value);
    echo $doc->saveXML();
}
function parse_xml(string $xml): DOMDocument
{
    if (stripos($xml,'<!DOCTYPE') !== false || stripos($xml,'<!ENTITY') !== false) throw new RuntimeException('Unsafe XML response.');
    $old = libxml_use_internal_errors(true);
    try {
        $doc = new DOMDocument();
        if (!$doc->loadXML($xml, LIBXML_NONET)) throw new RuntimeException('Invalid XML response.');
        return $doc;
    } finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
}
function getChildNodeValue($arr, $name)
{
    foreach ($arr as $n) if (strcasecmp($n->nodeName,$name) === 0) return $n->textContent;
    return null;
}
function csrf_token(): string { return $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(32))); }
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { if (PHP_SAPI !== 'cli') header('Allow: POST'); throw new HttpError(405,'POST required.'); }
    $value = $_POST['csrf'] ?? '';
    if (!is_string($value) || !hash_equals(csrf_token(),$value)) throw new HttpError(403,'Invalid CSRF token. Reload the page.');
}
function require_user(bool $admin = false): array
{
    $u = current_user();
    if (!$u) throw new HttpError(401,'Authentication required.');
    if ($admin && $u['role'] !== 'admin') throw new HttpError(403,'Administrator access required.');
    return $u;
}
function login_require(): void
{
    $login = basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'login.php';
    $u = current_user();
    if ($login && $u) { header('Location: ' . URL); exit; }
    if (!$login && !$u) { header('Location: ' . URL . 'login.php'); exit; }
}
function getUser($username) { return sql('SELECT * FROM users WHERE username=?',[$username])->fetch() ?: null; }
function fetch_remote(string $url): string
{
    $ch = curl_init($url); $body = '';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>10, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_WRITEFUNCTION=>function ($ch,$chunk) use (&$body) { if (strlen($body)+strlen($chunk)>8*1024*1024) return 0; $body .= $chunk; return strlen($chunk); }]);
    $ok = curl_exec($ch); $errno = curl_errno($ch); $code = curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($ok === false) {
        $reason = match ($errno) { CURLE_OPERATION_TIMEDOUT=>'Connection timed out.', CURLE_COULDNT_RESOLVE_HOST=>'Cannot resolve server host.', CURLE_COULDNT_CONNECT=>'Cannot connect to server.', CURLE_PEER_FAILED_VERIFICATION=>'TLS certificate verification failed.', default=>'Connection failed.' };
        throw new RuntimeException($reason);
    }
    if ($code !== 200) throw new RuntimeException('Server returned HTTP ' . (int)$code . '.');
    return $body;
}
function bbb_meetings(array $server): DOMDocument
{
    $doc = parse_xml(fetch_remote(completeUrl($server,'getMeetings')));
    if ($doc->getElementsByTagName('returncode')->item(0)?->textContent !== 'SUCCESS') throw new RuntimeException('BBB rejected the API request. Check the shared secret and API path.');
    return $doc;
}
function meeting_join(array $server, DOMElement $meeting, string $name): string
{
    $id = getChildNodeValue($meeting->childNodes,'meetingID');
    $password = getChildNodeValue($meeting->childNodes,'moderatorPW');
    if ($id === null || $password === null) throw new RuntimeException('Meeting credentials unavailable.');
    return completeUrl($server,'join',['fullName'=>$name,'meetingID'=>$id,'password'=>$password]);
}
function sanitize_meetings(DOMDocument $doc, array $server, string $name): void
{
    foreach ($doc->getElementsByTagName('meeting') as $m) {
        $url = meeting_join($server,$m,$name);
        foreach (['moderatorPW','attendeePW','join'] as $tag) {
            foreach (iterator_to_array($m->childNodes) as $child) if (strcasecmp($child->nodeName,$tag) === 0) $m->removeChild($child);
        }
        xml_text($m,'join',$url);
    }
}
function connection_test(array $s): string
{
    if ($s['type'] === 'B') bbb_meetings($s);
    else {
        require_once __DIR__ . '/adobeconnect.php';
        $client = new AdobeConnectClient($s['domain'],$s['login'],$s['password']);
        $client->makeAuth()->getCommonInfo();
    }
    return 'Connection and authentication succeeded.';
}
