<?php
# ICECAST WRAPPER
#THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
#IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
#FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
#AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
#LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
#OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
#THE SOFTWARE.
class Icecast
{
    const SERVER = 'http://78.129.181.183:8000'; //your icecast server address, without the ending "/"
    const MOUNT = '/live'; //your radio's mount point, with the leading "/"
    const LAST_FM_API = 'xxxxxxxxxxxxxxxxxxxxxxxxxxx'; //your last.fm API key, get from http://www.last.fm/api/account
    const DEFAULT_ALBUM_ART = './images/default.jpg'; //the default album art image, will be used if failed to get from last.fm's API
    const GET_TRACK_INFO = true; //get information of the current song from last.fm
    const GET_ALBUM_INFO = true; //get extra information of the album from last.fm, if enabled, may increase script execute time
    const GET_ARTIST_INFO = false; //get extra information of the artist from last.fm, if enabled, may increase script execute time
    const GET_TRACK_BUY_LINK = false; //get buy links on Amazon, iTune and 7digital
    const GET_LYRICS = false; //get lyrics of the current song using chartlyrics.com's API
    const CACHE_ALBUM_ART = false; //cache album art images to local server
    const RECORD_HISTORY = false; //record play history of your radio

    protected $cache_folder;

    protected $stream = array(
        'info' => array(
            'title' => '',
            'description' => '',
            'type' => '',
            'start' => '',
            'bitrate' => '',
            'listeners' => '',
            'msx_listeners' => '',
            'genre' => '',
            'stream_url' => '',
            'artist_song' => '',
            'artist' => '',
            'song' => '',
            'status' => 'OFF AIR',
            'artwork' => ''),
        'album' => array(),
        'track' => array(),
        'fetch_time' => '');

    public function __construct()
    {
        $this->cache_folder = dirname(dirname(__file__)) . '/cache';

        if (!file_exists($this->cache_folder))
        {
            mkdir($this->cache_folder);
        }

        $this->stream = array_merge($this->stream, $this->getStreamInfo());

        $last_song = @file_get_contents($this->cache_folder . '/last.txt');
        $current_song = (isset($this->stream['info']['song'])) ? $this->stream['info']['song'] :
            'None';

        if ($last_song != $current_song)
        {
            @file_put_contents($this->cache_folder . '/last.txt', @base64_encode($current_song));
        }

        $this->cacheVar($this->stream);

        if (self::RECORD_HISTORY == true)
        {
            $this->cacheHistory($this->stream);
        }
    }

    public function __get($key)
    {
        if (isset($this->$key))
        {
            return $this->$key;
        }
        throw new Exception(sprintf('%s::%s cannot be accessed.', __class__, $key));
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

    public function getStreamInfo()
    {

        set_time_limit(0);

        $url = self::SERVER . '/status.xsl?mount=' . self::MOUNT;

        if (function_exists('curl_init'))
        {

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); # timeout after 10 seconds, you can increase it
            curl_setopt($ch, CURLOPT_USERAGENT,
                "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
            curl_setopt($ch, CURLOPT_URL, $url); #set the url and get string together
            $str = curl_exec($ch);
            curl_close($ch);

        } else
        {
            $str = @file_get_contents($url);
        }

        if (preg_match_all('/<td\s[^>]*class=\"streamdata\">(.*)<\/td>/isU', $str, $match))
        {
            $stream['info']['status'] = 'ON AIR';
            $stream['info']['title'] = $match[1][0];
            $stream['info']['description'] = $match[1][1];
            $stream['info']['type'] = $match[1][2];
            $stream['info']['start'] = $match[1][3];
            $stream['info']['bitrate'] = $match[1][4];
            $stream['info']['listeners'] = $match[1][5];
            $stream['info']['msx_listeners'] = $match[1][6];
            $stream['info']['genre'] = $match[1][7];
            $stream['info']['stream_url'] = $match[1][8];
            $stream['info']['artist_song'] = (isset($match[1][9])) ? $match[1][9] : '';
            if (isset($match[1][9]))
            {
                $x = explode("-", $match[1][9]);
                $stream['info']['artist'] = (isset($x[0])) ? $x[0] : '';
                $stream['info']['song'] = (isset($x[1])) ? $x[1] : '';
            } else
            {
                $stream['info']['artist'] = "None";
                $stream['info']['song'] = "None";
            }
        } else
        {
            $stream['info']['status'] = 'OFF AIR';
        }
        return $stream;
    }

    //get information of the current song use last.fm's API
    public function getTrackInfo($stream)
    {

        $url = str_replace('#', '',
            'http://ws.audioscrobbler.com/2.0/?method=track.getinfo&artist=' . urlencode($this->
            stream['info']['artist']) . '&track=' . urlencode($this->stream['info']['song']) .
            '&api_key=' . self::LAST_FM_API);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); # timeout after 10 seconds, you can increase it
        curl_setopt($ch, CURLOPT_USERAGENT,
            "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
        curl_setopt($ch, CURLOPT_URL, $url); #set the url and get string together
        curl_setopt($ch, CURLOPT_SSLVERSION, 3); // OpenSSL issue
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Wildcard certificate
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $return = curl_exec($ch);
        curl_close($ch);
        $xml = simplexml_load_string($return, 'SimpleXMLElement', LIBXML_NOCDATA);
        $track = $this->obj_to_array($xml);
        $x = array();
        $x['track']['track'] = (isset($track['track']['name'])) ? $track['track']['name'] :
            '';
        $x['track']['artist'] = (isset($track['track']['album']['artist'])) ? $track['track']['album']['artist'] :
            '';
        $x['track']['duration'] = (isset($track['track']['duration'])) ? $track['track']['duration'] :
            '';
        $x['track']['covers'] = (isset($track['track']['album']['image'])) ? $track['track']['album']['image'] :
            array();
        $x['track']['title'] = (isset($track['track']['album']['title'])) ? $track['track']['album']['title'] :
            '';
        $x['track']['url'] = (isset($track['track']['artist']['url'])) ? $track['track']['artist']['url'] :
            '';
        $stream = array_merge($stream, $x);
        return $stream;
    }

    //get extra information of the album
    public function getAlbumInfo($stream)
    {

        set_time_limit(0);

        $url = str_replace('#', '',
            'http://ws.audioscrobbler.com/2.0/?method=album.getinfo&artist=' . urlencode($stream['info']['artist']) .
            '&album=' . ($stream['album']['title']) . '&api_key=' . self::LAST_FM_API);
        $xml = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA);
        $xml = $this->obj_to_array($xml);
        if ($xml['album']['releasedate'] && strlen($xml['album']['releasedate']) > 10)
        {
            $stream['album']['releasedate'] = reset(explode(",", $xml['album']['releasedate']));
        }
        if ($xml['album']['tracks']['track'])
        {
            foreach ($xml['album']['tracks']['track'] as $track)
            {
                $stream['album']['track_list'][] = array('title' => $track['name'], 'url' => $track['url']);
            }
        }
        if ($xml['album']['wiki']['summary'])
        {
            $stream['album']['summary'] = $xml['album']['wiki']['summary'];
            $stream['album']['info'] = $xml['album']['wiki']['content'];
        }
        return $stream;
    }

    //get extra information of the artist
    public function getArtistInfo($stream)
    {
        set_time_limit(0);

        $url = 'http://ws.audioscrobbler.com/2.0/?method=artist.gettopalbums&artist=' .
            urlencode($stream['info']['artist']) . '&api_key=' . self::LAST_FM_API .
            '&autocorrect=1';
        $xml = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA);
        $xml = $this->obj_to_array($xml);
        //print_r($xml);
        if ($xml['topalbums']['album'])
        {
            foreach ($xml['topalbums']['album'] as $album)
            {
                $stream['artist']['top_albums'][] = array(
                    'title' => $album['name'],
                    'url' => $album['url'],
                    'image' => $album['image']);
            }
        }

        $url = 'http://ws.audioscrobbler.com/2.0/?method=artist.getInfo&artist=' .
            urlencode($stream['info']['artist']) . '&api_key=' . self::LAST_FM_API .
            '&autocorrect=1';
        $xml = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA);
        $xml = $this->obj_to_array($xml);
        //print_r($xml);
        if ($xml['artist']['bio']['summary'])
        {
            $stream['artist']['summary'] = $xml['artist']['bio']['summary'];
            $stream['artist']['info'] = $xml['artist']['bio']['content'];
        }
        return $stream;
    }

    //get buylink
    public function getTrackBuyLink($stream)
    {

        set_time_limit(0);

        $url = 'http://ws.audioscrobbler.com/2.0/?method=track.getbuylinks&artist=' .
            urlencode($stream['info']['artist']) . '&track=' . urlencode($stream['info']['song']) .
            '&api_key=' . LAST_FM_API . '&country=' . urlencode('united states') .
            '&autocorrect=1';
        $xml = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA);
        $xml = $this->obj_to_array($xml);
        //print_r($xml);
        if ($xml['affiliations']['physicals']['affiliation'])
        {
            foreach ($xml['affiliations']['physicals']['affiliation'] as $buy)
            {
                $supplier = str_replace('iTuens', 'iTunes', $buy['supplierName']);
                if ($buy['isSearch'] == 0)
                {
                    $new = array(
                        'link' => $buy['buyLink'],
                        'price' => $buy['price']['amount'],
                        'currency' => $buy['price']['currency'],
                        'icon' => $buy['supplierIcon']);
                } else
                {
                    $new = array('link' => $buy['buyLink'], 'icon' => $buy['supplierIcon']);
                }
                $stream['track']['buylink']['physical'][$supplier] = $new;
            }
        }
        if ($xml['affiliations']['downloads']['affiliation'])
        {
            foreach ($xml['affiliations']['downloads']['affiliation'] as $buy)
            {
                $supplier = str_replace('Amazon MP3', 'Amazon', $buy['supplierName']);
                if ($buy['isSearch'] == 0)
                {
                    $new = array(
                        'link' => $buy['buyLink'],
                        'price' => $buy['price']['amount'],
                        'currency' => $buy['price']['currency'],
                        'icon' => $buy['supplierIcon']);
                } else
                {
                    $new = array('link' => $buy['buyLink'], 'icon' => $buy['supplierIcon']);
                }
                $stream['track']['buylink']['download'][$supplier] = $new;
            }
        }
        return $stream;
    }


    //cache album art images to local server, change the image size if you want
    public function cacheAlbumArt($image_url)
    {
        $contents = file_get_contents($image_url);
        $filename = end(explode('/', $image_url));
        $imagefile = $this->cache_folder . '/images/' . $filename;

        if (!file_exists($this->cache_folder . '/images'))
        {
            mkdir($this->cache_folder . '/images');
        }
        $handler = fopen($imagefile, "w+");
        fwrite($handler, $contents);
        fclose($handler);
        return $filename;
    }

    //get lyrics from chartlyrics.com's API
    public function getLyric($artist, $song)
    {
        $url = str_replace('\'', '',
            'http://api.chartlyrics.com/apiv1.asmx/SearchLyricDirect?artist=' . urlencode($artist) .
            '&song=' . urlencode($song));
        $xml = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA);
        $xml = $this->obj_to_array($xml);
        //print_r($xml);
        if ($xml['LyricId'] && ($xml['Lyric'] != array()))
        {
            return $xml['Lyric'];
        } else
        {
            return 'Sorry, there\'s no lyric found for this song';
        }
    }

    public function getInfo()
    {
        if (!isset($this->stream['info']['song']))
        {
            $this->stream['info']['song'] = 'Not Found';
            return $this->stream;

        } else
        {

            if (self::GET_TRACK_INFO == true)
            {
                $this->stream = @$this->getTrackInfo($this->stream);
            }
            if (self::GET_ALBUM_INFO && isset($this->stream['album']['title']))
            {
                $this->stream = @$this->getAlbumInfo($this->stream);
            }
            if (self::GET_ARTIST_INFO == true)
            {
                $this->stream = @$this->getArtistInfo($this->stream);
            }
            if (self::GET_TRACK_BUY_LINK == true)
            {
                $this->stream = @$this->getTrackBuyLink($this->stream);
            }
            if (self::CACHE_ALBUM_ART == true)
            {
                $this->stream['album']['local_image'] = @$this->cacheAlbumArt($this->stream['album']['image_l']);
                $this->stream['info']['artwork'] = $this->stream['album']['local_image'];
            }
            if (self::GET_LYRICS == true)
            {
                $this->stream['track']['lyric'] = @$this->getLyric($this->stream['info']['artist'],
                    $this->stream['info']['song']);
            }
        }

        $this->stream['fetch_time'] = time();

        return $this->stream;
    }

    public function array_encode($array)
    {
        foreach ($array as $key => $value)
        {
            if (is_array($value))
            {
                $array[$key] = $this->array_encode($value);
            } else
            {
                $array[$key] = base64_encode($value);
            }
        }
        return $array;
    }

    public function array_decode($array)
    {
        foreach ($array as $key => $value)
        {
            if (is_array($value))
            {
                $array[$key] = $this->array_decode($value);
            } else
            {
                $array[$key] = base64_decode($value);
            }
        }
        return $array;
    }

    public function cacheVar($stream)
    {
        $stream = $this->array_encode($stream);
        $fhandler = fopen($this->cache_folder . '/info.json', 'w+');
        fwrite($fhandler, json_encode($stream));
        fclose($fhandler);
    }

    public function cacheHistory($stream)
    {
        if (!isset($stream['info']['song']) || $stream['info']['song'] == "Not Found")
        {
            return;
        }
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        if (!is_dir($this->cache_folder . '/history'))
        {
            mkdir($this->cache_folder . '/history', 0777);
        }
        if (!is_dir($this->cache_folder . '/history/' . $year))
        {
            mkdir($this->cache_folder . '/history/' . $year);
        }
        if (!is_dir($this->cache_folder . '/history/' . $year . '/' . $month))
        {
            mkdir($this->cache_folder . '/history/' . $year . '/' . $month);
        }
        $file = $this->cache_folder . '/history/' . $year . '/' . $month . '/' . $day .
            '.json';
        $history['time'] = gmdate('c');
        $history['artist'] = $stream['info']['artist'];
        $history['song'] = (isset($stream['info']['song'])) ? $stream['info']['song'] :
            '';
        $history['image'] = (isset($stream['album']['image_s'])) ? $stream['album']['image_s'] :
            '';
        $history['itunes'] = (isset($stream['track']['buylink']['download']['iTunes']['link'])) ?
            $stream['track']['buylink']['download']['iTunes']['link'] : '';
        $history['Amazon'] = (isset($stream['track']['buylink']['download']['Amazon']['link'])) ?
            $stream['track']['buylink']['download']['Amazon']['link'] : '';
        $history = $this->array_encode($history);
        $handler = fopen($this->cache_folder . '/history.json', 'w+');
        fwrite($handler, json_encode($history));
        fclose($handler);
        $this->createHistory();
    }

    public function createHistory()
    {
        $handler = fopen($this->cache_folder . '/history.json', 'rw+');
        $history = json_decode(@fread($handler), true);
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $history[$year][$month][$day] = $year . $month . $day;
        $file = 'history/' . $year . '/' . $month . '/' . $day . '.json';
        fwrite($handler, json_encode($history));
        fclose($handler);
    }


    public function init($stream)
    {
        $stream['album']['image_s'] = $stream['album']['image_m'] = $stream['album']['image_l'] =
            $stream['album']['image_xl'] = self::DEFAULT_ALBUM_ART;
        $stream['track']['summary'] = $stream['track']['info'] =
            "No information found for this track, try searching for <a target='_blank' href='http://www.google.com/search?q=" .
            urlencode($stream['info']['artist'] . " - " . $stream['info']['song']) . "'>" .
            $stream['info']['artist'] . " - " . $stream['info']['song'] . "</a> on Google";
        $stream['album']['title'] = 'Not found';
        $stream['album']['lastfm_url'] = 'http://www.google.com/search?q=' . urlencode($stream['info']['artist'] .
            " - " . $stream['info']['song']);
        $stream['track']['download_cn'] = 'http://www.google.cn/music/search?q=' .
            urlencode($stream['info']['artist'] . " - " . $stream['info']['song']);
        $stream['album']['summary'] = $stream['album']['info'] =
            'No information found for this album, try searching for <a target="_blank" href="http://www.google.com/search?q=' .
            urlencode($stream['info']['artist'] . " - " . $stream['info']['song']) . '">' .
            $stream['info']['artist'] . " - " . $stream['info']['song'] . '</a> on Google';
        $stream['album']['releasedate'] = 'Unknown';
        $stream['artist']['summary'] = $stream['artist']['info'] =
            'No information found for this artist, try searching for <a target="_blank" href="http://www.google.com/search?q=' .
            urlencode($stream['info']['artist']) . '">' . $stream['info']['artist'] .
            '</a> on Google';
        return $stream;
    }

    public function getHistory($date)
    {
        $date = strtotime($date) ? strtotime($date) : time();
        //get path to the history file of today
        $year = date('Y', $date);
        $month = date('m', $date);
        $day = date('d', $date);
        $file = 'history/' . $year . '/' . $month . '/' . $day . '.json';
        if (!is_file($file))
        {
            return array('no history record found for this date');
        }
        $history = array_decode(json_decode(@file_get_contents($file), true));
        return $history;
    }

}

?>