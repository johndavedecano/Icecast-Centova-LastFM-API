<?php
/**
 * @author lolkittens
 * @copyright 2013
 */

class Centova {
    
    protected $host;
    
    protected $params = array();
    
    protected $query;
    
    protected $data;
    
    public function __construct($host = '',$params = array())
    {
        try{
            
            if(empty($host) || empty($params)){
                throw new Exception("Invalid arguments.");
            }else{
                $this->host = $host;
                $this->params = $params;
                $this->build_query();
                $this->request();
            }
            
        }catch(Exception $e){
            echo $e->getMessage();
        }
    }
    
    public function get()
    {
        return json_decode($this->data);
    }
    
    public function json()
    {
        return $this->data;
    }
    
    private function build_query()
    {
        $this->query = $this->host.'/api.php?'.http_build_query($this->params);
    }
    
    private function request()
    {
        try{
            
            $this->data = $this->curl($this->query); 
                
        }catch(Exception $e){
            echo $e->getMessage();
        }
    }
    
    private function curl($url)
    {
        if(function_exists('curl_init')){
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,60); # timeout after 10 seconds, you can increase it
            curl_setopt($ch, CURLOPT_USERAGENT , "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)"); 
            curl_setopt($ch, CURLOPT_URL, $url); #set the url and get string together
            curl_setopt($ch, CURLOPT_SSLVERSION, 3); // OpenSSL issue
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);  // Wildcard certificate
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $return = curl_exec($ch);
            curl_close($ch);
            
            return $return;
        }else{
            return file_get_contents($url);
        }
        
    }
}

?>