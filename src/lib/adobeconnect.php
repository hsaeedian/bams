<?php
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/util.php';
/*
 * AdobeConnect 9 api client
 * @see https://github.com/sc0rp10/AdobeConnect-php-api-client
 * @see http://help.adobe.com/en_US/connect/9.0/webservices/index.html
 * @version 0.1a
 *
 * Copyright 2012, sc0rp10
 * https://weblab.pro
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 *
 */

/**
 * Class AdobeConnectClient
 */
class AdobeConnectClient
{
    const PERMISSION_VIEW = 'view';
    const PERMISSION_HOST = 'host';

    protected $curl;
    protected $is_authorized = false;
    protected $breezecookie = null;
    protected $login;
    protected $password;
    protected $root_folder;
    protected $template_folder;
    protected $host;

    /**
     * @param $host
     * @param $login
     * @param $password
     * @param $root_folder
     * @param $template_folder
     */
    public function __construct($host, $login, $password, $root_folder = null, $template_folder = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_REFERER, $host);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $this->curl = $ch;

        $this->login = $login;
        $this->password = $password;
        $this->root_folder = $root_folder;
        $this->template_folder = $template_folder;
        $this->host = address($host) . '/';
        curl_setopt_array($ch, [CURLOPT_COOKIEFILE=>'', CURLOPT_MAXFILESIZE=>8388608, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS]);
    }

    /**
     *
     * @param null $login
     * @param null $password
     *
     * @return AdobeConnectClient
     */
    public function makeAuth($login = null, $password = null)
    {
        $this->login = $login ?: $this->login;
        $this->password = $password ?: $this->password;

        $result = null;

        if (!$this->breezecookie) {
            try {
                $this->makeRequest('login', [
                    'login' => $this->login,
                    'password' => $this->password
                ]);
            } catch (Exception $e) {
                $e = new Exception('Cannot auth with credentials', 0, $e);
                throw $e;
            }
        };

        $this->is_authorized = true;

        return $this;
    }

    /**
     * get common info about current user
     *
     * @return array
     */
    public function getCommonInfo()
    {
        return $this->makeRequest('common-info');
    }

    /**
     * create user
     *
     * @param string $email
     * @param string $password
     * @param string $name
     * @param string $type
     *
     * @return array
     */
    public function createUser($email, $password, $name, $type = 'user')
    {
        $result = $this->makeRequest('principal-update', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'type' => $type,
            'has-children' => 0,
        ]);

        return $result['principal']['@attributes']['principal-id'];
    }

    /**
     * @return null
     */
    public function getBreezeCookie()
    {
        return $this->breezecookie;
    }


    /**
     * @param string $email
     * @param bool $only_id
     *
     * @return mixed
     *
     * @throws Exception
     *
     */
    public function getUserByEmail($email, $only_id = false)
    {
        $result = $this->makeRequest('principal-list', [
            'filter-email' => $email
        ]);

        if (empty($result['principal-list'])) {
            throw new Exception(sprintf('Cannot find user [%s]', $email));
        }

        if ($only_id) {
            return $result['principal-list']['principal']['@attributes']['principal-id'];
        }

        return $result;
    }

    /**
     * update user fields
     *
     * @param string $email
     * @param array $data
     *
     * @return mixed
     */
    public function updateUser($email, array $data = [])
    {
        $principal_id = $this->getUserByEmail($email, true);
        $data['principal-id'] = $principal_id;

        return $this->makeRequest('principal-update', $data);
    }

    /**
     * get all users list
     *
     * @return array
     */
    public function getUsersList()
    {
        $users = $this->makeRequest('principal-list');
        $result = [];

        foreach ($users['principal-list']['principal'] as $key => $value) {
            $result[$key] = $value['@attributes'] + $value;
        };

        return $result;
    }

    /**
     * get all meetings
     *
     * @return array
     */
    public function getAllMeetings()
    {
        return $this->makeRequest('report-my-meetings');
    }

    /**
     * get active meetings
     *
     * @return array
     */
    public function getActiveMeetings()
    {
        return $this->makeRequest('report-active-meetings');
    }

    /**
     * get meeting attendees
     *
     * @return array
     */
    public function getMeetingAttendees($sco_id)
    {
        return $this->makeRequest('report-meeting-attendance', ['sco-id' => $sco_id]);
    }

    /**
     * get shortcuts
     *
     * @return array
     */
    public function getShortcuts()
    {
        return $this->makeRequest('sco-shortcuts')['shortcuts']['sco'];
    }

    /**
     * get shortcuts
     *
     * @param $sco_id
     *
     * @return array
     */
    public function getScoInfo($sco_id)
    {
        return $this->makeRequest('sco-info', ['sco-id' => $sco_id]);
    }

    /**
     * create meeting-folder
     *
     * @param string $name
     * @param string $url
     *
     * @return array
     */
    public function createFolder($name, $url)
    {
        $result = $this->makeRequest('sco-update', [
            'type' => 'folder',
            'name' => $name,
            'folder-id' => $this->root_folder,
            'depth' => 1,
            'url-path' => $url
        ]);

        return $result['sco']['@attributes']['sco-id'];
    }

    /**
     *
     * @return mixed
     */
    public function getTemplates()
    {
        return $this->makeRequest('sco-contents', [
            'sco-id' => $this->template_folder
        ])['scos']['sco'];
    }

    /**
     * create meeting
     *
     * @param int $folder_id
     * @param string $name
     * @param DateTime $date_begin
     * @param DateTime $date_end
     * @param string $url
     * @param null $template_sco_id
     *
     * @return int
     */
    public function createMeeting(
        $folder_id,
        $name,
        DateTime $date_begin,
        DateTime $date_end,
        $url,
        $template_sco_id = null
    ) {
        $data = [
            'type' => 'meeting',
            'name' => $name,
            'folder-id' => $folder_id,
            'date-begin' => $date_begin->format(\DateTime::ISO8601),
            'date-end' => $date_end->format(DateTime::ISO8601),
            'url-path' => $url
        ];

        if ($template_sco_id) {
            $data['source-sco-id'] = $template_sco_id;
        }
        $result = $this->makeRequest('sco-update', $data);

        return $result['sco']['@attributes']['sco-id'];
    }

    /**
     * get info about meeting
     * @param $meeting_id
     *
     * @return array
     */
    public function getMeetingInfo($meeting_id)
    {
        $result = $this->makeRequest('sco-info', [
            'sco-id' => $meeting_id
        ]);

        return $result['sco'];
    }


    /**
     * get meeting archives
     *
     * @param int $meeting_id
     * @param DateTime $from
     * @param DateTime $to
     *
     * @return array
     */
    public function getMeetingArchive(
        $meeting_id,
        DateTime $from = null,
        DateTime $to = null
    ) {
        $request = [
            'sco-id' => $meeting_id,
            'filter-icon' => 'archive'
        ];

        if ($from) {
            $request['filter-gte-date-end'] = $from->format(DateTime::ISO8601);
        }

        if ($to) {
            $request['filter-lt-date-end'] = $to->format(DateTime::ISO8601);
        }

        return $this->makeRequest('sco-contents', $request);
    }

    /**
     * @return mixed
     */
    public function getTemplateFolder()
    {
        return $this->template_folder;
    }

    /**
     * @return mixed
     */
    public function getRootFolder()
    {
        return $this->root_folder;
    }

    /**
     * @return mixed
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param $principal_id
     * @param $group_id
     * @return mixed
     */
    public function addUserToGroup($principal_id, $group_id)
    {
        return $this->makeRequest('group-membership-update', [
            'group-id' => $group_id,
            'principal-id' => $principal_id,
            'is-member' => true,
        ]);
    }
    /**
     * @param $principal_id
     * @param $group_id
     * @return mixed
     */
    public function removeUserFromGroup($principal_id, $group_id)
    {
        return $this->makeRequest('group-membership-update', [
            'group-id' => $group_id,
            'principal-id' => $principal_id,
            'is-member' => false,
        ]);
    }

    /**
     * invite user to meeting
     *
     * @param int $meeting_id
     * @param string $email
     *
     * @param string $permission
     * @return mixed
     */
    public function inviteUserToMeeting($meeting_id, $email, $permission = self::PERMISSION_VIEW)
    {
        $user_id = $this->getUserByEmail($email, true);

        $result = $this->makeRequest('permissions-update', [
            'principal-id' => $user_id,
            'acl-id' => $meeting_id,
            'permission-id' => $permission,
        ]);

        return $result;
    }

    /**
     * invite user to meeting
     *
     * @param int $meeting_id
     * @param $group_id
     * @param string $permission
     *
     * @return mixed
     */
    public function inviteGroupToMeeting(
        $meeting_id,
        $group_id,
        $permission = self::PERMISSION_VIEW
    ) {
        $result = $this->makeRequest('permissions-update', [
            'principal-id' => $group_id,
            'acl-id' => $meeting_id,
            'permission-id' => $permission
        ]);

        return $result;
    }

    /**
     *
     */
    public function __destruct()
    {
        curl_close($this->curl);
    }

    /**
     * @param       $action
     * @param array $params
     * @return mixed
     * @throws Exception
     * @throws Exception
     */
    public function makeRequest($action, array $params = [])
    {
        // cURL keeps the authenticated cookie in memory, including enhanced-security sessions.
        $url = $this->host . 'api/xml';
        curl_setopt_array($this->curl, [CURLOPT_URL=>$url, CURLOPT_HEADER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query(['action'=>$action]+$params, '', '&', PHP_QUERY_RFC3986)]);
        $response = curl_exec($this->curl);
        if ($response === false) throw new RuntimeException('Adobe Connect request failed. Check reachability and TLS configuration.');
        $code = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        if ($code !== 200) throw new RuntimeException('Adobe Connect returned HTTP ' . (int)$code . '.');
        $size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response,0,$size);
        if (preg_match('/BREEZESESSION=([^;\s]+);/', $headers, $m)) $this->breezecookie = $m[1];
        $doc = parse_xml(substr($response,$size));
        $xml = simplexml_import_dom($doc);
        $data = json_decode(json_encode($xml),true);
        if (($data['status']['@attributes']['code'] ?? '') !== 'ok') throw new RuntimeException('Adobe Connect rejected the API request. Check credentials and permissions.');
        return $data;
    }
}