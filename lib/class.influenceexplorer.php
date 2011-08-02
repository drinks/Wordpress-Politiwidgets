<?php
if(!function_exists('curl_init')) throw new Exception('InfluenceExplorer currently requires cURL to make HTTPS requests.');
if (!class_exists('InfluenceExplorer')) {

    class InfluenceExplorer {

        var $apikey,
            $api_default_params,
            $baseurl,
            $connection;

        function InfluenceExplorer($apikey=null){
            $this->__construct($apikey);
        }

        function __construct($apikey=null){
            if ($apikey) $this->apikey = $apikey;
            $this->baseurl = 'https://inbox.influenceexplorer.com';
            $this->api_default_params = array('apikey'=>$this->apikey);
        }

        function contextualize($text){
            if($this->apikey):
                $params = array('text'=>$text);
                $data = $this->post("{$this->baseurl}/contextualize?" .
                                    http_build_query($this->api_default_params),
                                                     $params);
                return json_decode($data);
            else:
                throw new Exception('InfluenceExplorer is unable to connect; Make sure you supply an API key.');
            endif;
        }

        function post($url, $data){
            $this->connection = curl_init();
            curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->connection, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($this->connection, CURLOPT_MAXREDIRS, 3);
            curl_setopt($this->connection, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($this->connection, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($this->connection, CURLOPT_URL, $url);
            curl_setopt($this->connection, CURLOPT_POST, true);
            curl_setopt($this->connection, CURLOPT_POSTFIELDS, $data);
            $response = curl_exec($this->connection);
            curl_close($this->connection);
            return $response;
        }

    }

}
