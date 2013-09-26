<?php
require_once('Centova.php');

class CentoLast {
    
    protected $lastFM = array(
        'api'    => '',
        'secret' => ''
    );
    
    protected $centova = array(
        'host'      =>  '',
        'username'  => '',
        'password'  => ''
    );
    
    protected $track = array(
        'info' => array(
                'title' => 'Untitled',
                'genre' => 'None',
                'artist_song' => 'None',
                'artist' => 'None',
                'song' => 'None',
                'status' => 'OFF AIR'),
        'track' => array(
                'name'     =>  '',
                'artist'    => '',
                'duration'  => '',
                'title'     => '',
                'covers'     => array(),
                'url'       => ''
        ),
        'fetch_time' => ''
    );
    
    protected $cache_folder;
    
    public function __construct()
    {
        $this->cache_folder = dirname(dirname(__FILE__)).'/cache/recent_tracks';
    }
    
    public function setLastFM($api,$secret)
    {
        $this->lastFM = array('api' => $api,'secret' => $secret);
        return $this;
    }
    
    public function setCentova($host,$username,$password)
    {
        $this->centova = array('host' => $host,'username' => $username,'password' => $password);
        return $this;
    } 
    
    public function getServerStatus()
    {
        // REQUEST FROM CENTOVA
        $method = 'server.getstatus';
        $params = array(
            'xm'    => $method,
            'f'     => 'json',
            'a[username]' => $this->centova['username'],
            'a[password]' => $this->centova['password'],  
        );
        $centova = new Centova($this->centova['host'],$params);
        $centova_response = $centova->get();
        
        if($centova_response->type != 'success'){
            throw new Exception("Unable able to retrieve data or connect to centova server.");
        }else{
            
            return $centova_response;
        }
    }  
    public function getRecentTracks($mountpoints = array())
    {
        // REQUEST FROM CENTOVA
        $method = 'server.getsongs';
        $mountpoints = implode(',',$mountpoints);
        
        $params = array(
            'xm'    => $method,
            'f'     => 'json',
            'a[username]' => $this->centova['username'],
            'a[password]' => $this->centova['password'],  
        );
        if(!empty($mountpoints)){
            $params['a[mountpoints]'] = $mountpoints;
        }
        $centova = new Centova($this->centova['host'],$params);
        $centova_response = $centova->get();
        
        if($centova_response->type != 'success'){
            throw new Exception("Unable able to retrieve data or connect to centova server.");
        }else{
            
            $tracks = array(array(
                'track'     =>  '',
                'artist'    => '',
                'time'      => '',
                'duration'  => '',
                'covers'     => array(),
                'album'     => '',
                'url'       => ''
            ));
            
            $songs = $centova_response->response->data->songs;
            $counter = 0;
            foreach($songs as $song){
                
                $track_info = array('None','None');
                
                $track_info = array_merge(explode(" - ",trim($song->title)),$track_info);
                
                $track = $this->getTrackInfo($track_info[0],$track_info[1]);
                
                if($track['@attributes']['status'] == 'ok'){
                    
                    $tracks[$counter]['track'] = (isset($track['track']['name']))?$track['track']['name']:'';
                    $tracks[$counter]['artist'] = (isset($track['track']['album']['artist']))?$track['track']['album']['artist']:'';
                    $tracks[$counter]['time'] = $song->time;
                    $tracks[$counter]['duration'] = (isset($track['track']['duration']))?$track['track']['duration']:'';
                    $tracks[$counter]['covers'] = (isset($track['track']['album']['image']))?$track['track']['album']['image']:'';
                    $tracks[$counter]['album'] = (isset($track['track']['album']['title']))?$track['track']['album']['title']:'';
                    $tracks[$counter]['url'] = (isset($track['track']['artist']['url']))?$track['track']['artist']['url']:'';
                    
                    $counter++;
                }
                
            }
            
            return $tracks;
        }
        
    }
    public function getTracksHistory()
    {
        if(!file_exists($this->cache_folder)){
            mkdir($this->cache_folder);
        }
        $files = array();
        foreach(glob($this->cache_folder.'/*.json') as $filename){
            $files[] = basename($filename);
        }
        $newest = max($files);
        
        $handler = fopen($this->cache_folder.'/'.$newest,'r+');
        $contents = @fread($handler,filesize($this->cache_folder.'/'.$newest));
        fclose($handler);
        return $contents;
    }
    public function deleteHistory()
    {
        $files = array();
        foreach(glob($this->cache_folder.'/*.json') as $filename){
            $files[] = basename($filename);
        }
        $newest = max($files);
        
        foreach($files as $file){
            if($file != $newest){
                // DELETE THE OLD FILES
                 unlink($this->cache_folder.'/'.$file); 
            }
        }
    }
    public function cache()
    {
        $this->deleteHistory();
        
        echo "History Deleted";
        
        if(!file_exists($this->cache_folder)){
            mkdir($this->cache_folder);
        }
        $tracks = json_encode($this->getRecentTracks());
        
        $timestamp = date("YmdHis");
        $handler = fopen($this->cache_folder.'/'.$timestamp.'.json','w+');
        fwrite($handler,$tracks);
        fclose($handler);
        
        echo "History has been successfully cache.";
        
    }
    public function getCurrentTrack()
    {
        $interface = array(
            'info'
        );
        $server = $this->obj_to_array($this->getServerStatus());
        $status = $server['type'];
        $response = $server['response']['data']['status'];
        
        if($status == 'success'){
            
            $this->track['fetch_time'] = date("YmdHis");
            $this->track['info']['status'] = ($response['sourceconnected'] > 0)?'ON AIR':'OFF AIR';
            $this->track['info']['title'] = $response['title'];
            $this->track['info']['genre'] = $response['genre'];
            $this->track['info']['artist_song'] = $response['currentsong'];;
            if(isset($response['currentsong'])){
                
                $x = explode("-", $response['currentsong']);
                $this->track['info']['artist'] = (isset($x[0]))?$x[0]:'';
                $this->track['info']['song'] = (isset($x[1]))?$x[1]:'';
                
                $track = $this->getTrackInfo($this->track['info']['artist'],$this->track['info']['song']);
                
                $this->track['track']['name'] = (isset($track['track']['name']))?$track['track']['name']:'';
                $this->track['track']['artist'] = (isset($track['track']['album']['artist']))?$track['track']['album']['artist']:'';
                $this->track['track']['duration'] = (isset($track['track']['duration']))?$track['track']['duration']:'';
                $this->track['track']['covers'] = (isset($track['track']['album']['image']))?$track['track']['album']['image']:'';
                $this->track['track']['title'] = (isset($track['track']['album']['title']))?$track['track']['album']['title']:'';
                $this->track['track']['url'] = (isset($track['track']['artist']['url']))?$track['track']['artist']['url']:'';
                
            }else{
                $this->track['info']['artist'] = "None";
                $this->track['info']['song'] = "None";
            }   
        }
        return $this->track;
    }
    //get information of the current song use last.fm's API
    public function getTrackInfo($artist,$track)
    {
        set_time_limit(0);
        
        $url = str_replace('#', '','http://ws.audioscrobbler.com/2.0/?method=track.getinfo&artist=' . urlencode($artist).'&track=' . urlencode($track) . '&api_key=' . $this->lastFM['api']); 

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
        $xml = simplexml_load_string($return, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $this->obj_to_array($xml);
    }
    
    public function obj_to_array($obj)
    {
        $array = (is_object($obj)) ? (array )$obj : $obj;
        foreach ($array as $k => $v)
        {
            if (is_object($v) or is_array($v))
                $array[$k] = $this->obj_to_array($v);
        }
        return $array;
    }
}

