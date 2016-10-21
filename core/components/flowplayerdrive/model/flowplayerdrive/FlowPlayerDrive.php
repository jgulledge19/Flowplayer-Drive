<?php

/**
 * Class FlowPlayerDrive
 */
class FlowPlayerDrive
{
    /** @var null|MODX  */
    protected $modx = null;

    /** @var null|string  */
    protected $drive_url = null;

    /** @var null|string */
    protected $authcode =null;

    /** @var bool */
    protected $debug = false;

    /** @var string  */
    protected $package_name = 'flowplayerdrive';

    /**
     * @var bool
     */
    protected $use_cache = true;

    /**
     * FlowPlayerDrive constructor.
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->drive_url = 'https://drive.api.flowplayer.org/';

    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug=true)
    {
        $this->debug = $debug;
    }

    /**
     * @param bool $bool
     */
    public function setUseCache($bool=true)
    {
        $this->use_cache = $bool;
    }

    /**
     * @param $username
     * @param $password
     * @param int $expires
     *
     * @return bool
     */
    public function login($username, $password, $expires=3600*24)
    {
        // load from cache:
        $cache_key = $this->package_name.'-'.$username.'-'.$expires;

        // Gets the data from cache again. Returns null if cache is not available or expired.
        $authcode = $this->modx->cacheManager->get($cache_key);

        if ( empty($authcode) || !$this->use_cache ) {

            $data = $this->sendRequest(
                'login',
                'POST',
                array(
                    'username' => $username,
                    'password' => $password,
                    'ttl' => $expires
                )
            );

            $authcode = $data['user']['authcode'];

            $this->modx->cacheManager->set($cache_key, $authcode, $expires - 60);
        }

        $this->authcode = $authcode;

        return true;
    }

    public function logout()
    {

    }

    protected function loginFromModx()
    {
        return $this->login(
            $this->modx->getOption($this->package_name.'.username'),
            $this->modx->getOption($this->package_name.'.password'),
            $this->modx->getOption($this->package_name.'.session.expires', null, 3600)
        );
    }

    /**
     * See: https://flowplayer.org/docs/drive-api.html#list
     * @param $search
     * @param int $cache_limit ~ in seconds
     *
     * @return array|bool
     */
    public function getVideos($search, $cache_limit=3600*24)
    {
        // To get videos with tags 'promo' or 'first' or having 'promo'
        // or 'first' as part of the title. Return first 30 videos.
        // GET /videos?search=promo%2Cfirst&page=1
        // response status should be 200 and the response payload is
        if ( !$this->loginFromModx() ) {
            return false;
        }

        $cache_key = $this->package_name.'-videos-'.$this->authcode.'-'.$search;

        // Gets the data from cache again. Returns null if cache is not available or expired.
        $videos = $this->modx->cacheManager->get($cache_key);

        if ( empty($videos) || !$this->use_cache ) {
            $results = $this->sendRequest(
                'videos',
                'GET',
                array(
                    'search' => $search// Does this need to be URL encoded?
                )
            );
            $videos = array();
            if ( isset($results['videos']) ) {
                $videos = $results['videos'];
            }
            $this->modx->cacheManager->set($cache_key, $videos, $cache_limit);
        }
        return $videos;
    }


    /**
     * @param string $resource_uri
     * @param string $method
     * @param array $data ~ array(name => value, ...)
     *
     * @return mixed $response ~ Array for JSON
     * 1. Builds correct URL
     * 2. Sends curl request
     * 3. Shows response
     */
    protected function sendRequest($resource_uri, $method="GET", $data=array())
    {
        $url = $this->drive_url.$resource_uri;

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HEADER, 'Content-Type: application/json');

            if ( !empty($this->authcode) ) {
                 curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'flowplayer-authcode: ' . $this->authcode
                     )
                 );
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            switch ($method) {
                case 'DELETE':
                    // http://stackoverflow.com/questions/13420952/php-curl-delete-request
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    break;
                case 'POST':
                    // http://davidwalsh.name/curl-post
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, count($data));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    break;
                case 'PUT':
                    // http://www.lornajane.net/posts/2009/putting-data-fields-with-php-curl
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    break;

                case 'GET':
                default:
                    $url = $url. (strpos($url, '?') === FALSE ? '?' : '').http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url );
                    break;
            }
            if ( $this->debug ) {
                echo '<h2>Method: '.$method.' URI: '.$resource_uri.'</h2>';
                $tmp_data = $data;

                if ( isset($tmp_data['username']) ) {
                    $l = strlen($tmp_data['username']);
                    $tmp_data['username'] = str_pad(substr($tmp_data['username'], 0, 4 ), $l + 5, 'X');
                }
                if ( isset($tmp_data['password']) ) {
                    $tmp_data['password'] = 'XXXXXXXXXX-Hidden-XXXXXXXXXX';
                }

                echo '<pre>'.print_r($tmp_data, true).'</pre>';
            }

            $response = curl_exec($ch);

            if ( $this->debug ) {
                echo 'Response: '.$response;
            }

        } catch (exception $e) {
            print_r($e);
        }
        if ( !isset($response) || !$response ) {
            trigger_error(curl_error($ch));
            return false;
        }
        return json_decode($response, true);

    }

    /**
     * @param array $videos
     * @param string $clip_order
     *
     * @return array $reordered
     */
    public function reorderVideos($videos, $clip_order='')
    {
        $order = explode(',', $clip_order);
        /** @var array $org ~ array($clip_id => $c,... ) */
        $org = array();
        foreach ($videos as $c => $video ) {
            if ( isset($video['id']) ) {
                $org[$video['id']] = $c;
            }
        }
        $reorder = array();
        $preferred = array();
        foreach ($order as $clip_id) {
            if ( isset($org[$clip_id]) && isset($videos[$org[$clip_id]])) {
                $preferred[] = $clip_id;
                $reorder[] = $videos[$org[$clip_id]];
            }
        }
        foreach ($videos as $c => $video) {
            if ( isset($video['id']) && in_array($video['id'], $preferred) ) {
                continue;
            }
            $reorder[] = $video;
        }
        return $reorder;
    }

    /**
     * @param array $encodings ~ from $video['encodings']
     * @param string $format ~ mp4, webm, hls
     *
     * @return bool|array
     */
    public function getEncodingInfo($encodings, $format)
    {
        $type = '';
        switch ($format) {
            case 'hls':
                $type = "application/x-mpegurl";
                break;
            case 'webm':
                $type = "video/webm";
                break;
            case 'mp4':
                $type = "video/mp4";
                break;
        }
        foreach ($encodings as $encoding) {
            if ( isset($encoding['format']) && $encoding['format'] == $format && $encoding['status'] != 'original' ) {
                $encoding['url'] = str_replace('http://', '//', $encoding['url']);
                $encoding['type'] = $type;
                return $encoding;
            }
        }
        return false;
    }
}