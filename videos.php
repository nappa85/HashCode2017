<?php

class Videos {
    /**
     * Number of Videos
     * @var integer
     * @protected
     */
    protected $iVideos;

    /**
     * Number of EndPoints
     * @var integer
     * @protected
     */
    protected $iEndPoints;

    /**
     * Numner of request descriptions
     * @var integer
     * @protected
     */
    protected $iRequestDescriptions;

    /**
     * Number of Cache servers
     * @var integer
     * @protected
     */
    protected $iCacheServers;

    /**
     * Capacity of each server
     * @var integer
     * @protected
     */
    protected $iCapacity;

    /**
     * List of Cache servers
     * @var array
     * @protected
     */
    protected $aServers = array();

    /**
     * List of Requests
     * @var array
     * @protected
     */
    protected $aRequests = array();

    /**
     * Matrix of EndPoints
     * @var array
     * @protected
     */
    protected $aEndPoints = array();

    /**
     * Matrix of Videos
     * @var array
     * @protected
     */
    protected $aVideos = array();

    /**
     * Array of Gains
     * @var array
     * @protected
     */
    protected $aGains = array();

    /**
     * Constructor
     * @param   string  file    input file
     */
    public function __construct($sFile) {
        $i = 0;

        //let's trust the input file
        $aFile = file($sFile);
        list($this->iVideos, $this->iEndPoints, $this->iRequestDescriptions, $this->iCacheServers, $this->iCapacity) = explode(' ', trim($aFile[$i++]));

        for($j = 0; $j < $this->iCacheServers; $j++) {
            $this->aServers[] = array(
                'size' => $this->iCapacity,
                'endpoints' => array(),
                'videos' => array()
            );
        }

        $aTemp = explode(' ', trim($aFile[$i++]));
        foreach($aTemp as $iVideoSize) {
            $this->aVideos[] = array(
                'size' => $iVideoSize,
                'endpoints' => array(),
                'requests' => 0
            );
        }

        for($z = 0; $z < $this->iEndPoints; $z++) {
            $aEndPoint = array(
                'videos' => array(),
                'latencies' => array(),
                'servers' => array(),
                'requests' => array()
            );

            list($aEndPoint['latency'], $aEndPoint['servers']) = explode(' ', trim($aFile[$i++]));

            for($j = 0; $j < $aEndPoint['servers']; $j++) {
                $aTemp = array();
                list($aTemp['server'], $aTemp['latency']) = explode(' ', trim($aFile[$i++]));
                //store difference between datacenter latency and cache server latency
                $aEndPoint['latencies'][$aTemp['server']] = $aEndPoint['latency'] - $aTemp['latency'];

                $this->aServers[$aTemp['server']]['endpoints'][count($this->aEndPoints)] = $aTemp['latency'];
            }

            $this->aEndPoints[] = $aEndPoint;
        }

        for($j = 0; $j < $this->iRequestDescriptions; $j++) {
            $aTemp = array();
            list($aTemp['video'], $aTemp['endpoint'], $aTemp['requests']) = explode(' ', trim($aFile[$i++]));
            $this->aRequests[] = $aTemp;

            $this->aEndPoints[$aTemp['endpoint']]['requests'][$aTemp['video']] += $aTemp['requests'];

            $this->aVideos[$aTemp['video']]['endpoints'][$aTemp['endpoint']] = $aTemp['requests'];
            $this->aVideos[$aTemp['video']]['requests'] += $aTemp['requests'];
        }
/*
        foreach($this->aEndPoints as &$aEndPoint) {
            arsort($aEndPoint['latencies']);
            arsort($aEndPoint['videos']);
        }

        foreach($this->aVideos as &$aVideo) {
            arsort($aVideo['endpoints']);
        }
        uasort($this->aVideos, array($this, '_sortVideos'));
*/
        foreach($this->aEndPoints as $iEndPoint => $aEndPoint) {
            foreach($aEndPoint['requests'] as $iVideo => $iRequests) {
                foreach($aEndPoint['latencies'] as $iServer => $iDelta) {
                    $this->aGains[$iEndPoint.'-'.$iVideo.'-'.$iServer] = $iDelta * $iRequests;
                }
            }
        }
        arsort($this->aGains);
    }

    /**
     * Internal function to sort the videos based on total requests
     */
    protected function _sortVideos($aVideo1, $aVideo2) {
        if ($aVideo1['requests'] == $aVideo2['requests']) {
            return 0;
        }

        return ($aVideo1['requests'] > $aVideo2['requests']) ? -1 : 1;
    }

    /**
     * Caches the video
     */
//     protected function cacheUnrequestedVideo($iVideo) {
//         foreach($this->aServers as $iServer => $aServer) {
//             if($aServer['size'] >= $this->aVideos[$iVideo]['size']) {
//                 $this->aServers[$iServer]['videos'][] = $iVideo;
//                 $this->aServers[$iServer]['size'] -= $this->aVideos[$iVideo]['size'];
//                 break;
//             }
//         }
//     }

    /**
     * Caches the video
     */
    protected function cacheVideo($iVideo, $iEndPoint, $iServer) {
        if(in_array($iVideo, $this->aServers[$iServer]['videos'])) {
            return;
        }

        if($this->aServers[$iServer]['size'] < $this->aVideos[$iVideo]['size']) {
            return;
        }

        $this->aServers[$iServer]['size'] -= $this->aVideos[$iVideo]['size'];
        $this->aServers[$iServer]['videos'][] = $iVideo;

        //delete unneeded values
        for($i = 0; $i < $this->iCacheServers; $i++) {
            unset($this->aGains[$iEndPoint.'-'.$iVideo.'-'.$i]);
        }
    }

    /**
     * Formats the output
     */
    protected function getOutput() {
        $iCount = 0;
        $aRows = array();
        foreach($this->aServers as $iServer => $aServer) {
            if(count($aServer['videos']) > 0) {
                $iCount++;
                $aRows[] = $iServer.' '.implode(' ', $aServer['videos']);
            }
        }

        return $iCount."\n".implode("\n", $aRows)."\n";
    }

    /**
     * Delivers the videos
     */
    public function deliver() {
        $sLastKey = '';
        while(!empty($this->aGains)) {
            //check if it has been already parsed
            $aKeys = array_keys($this->aGains);
            if($aKeys[0] == $sLastKey) {
                unset($this->aGains[$aKeys[0]]);
                $iIndex = 1;
            }
            else {
                $iIndex = 0;
            }

            $sLastKey = $aKeys[$iIndex];echo "$sLastKey\n";
            list($iEndPoint, $iVideo, $iServer) = explode('-', $sLastKey);

            $this->cacheVideo($iVideo, $iEndPoint, $iServer);

//             if($aVideo['requests'] > 0) {
//                 foreach($aVideo['endpoints'] as $iEndPoint => $iRequests) {
//                     $this->cacheVideo($iVideo, $iEndPoint);
//                 }
//             }
//             else {
//                 $this->cacheUnrequestedVideo($iVideo);
//             }
        }

        return $this->getOutput();
    }
}

$aFiles = array('/me_at_the_zoo.', '/videos_worth_spreading.', '/trending_today.', '/kittens.');
foreach($aFiles as $sFile) {
    $oVideos = new Videos(__DIR__.$sFile.'in');
    file_put_contents(__DIR__.$sFile.'out', $oVideos->deliver());
}
