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
     * Size of every video file
     * @var array
     * @protected
     */
    protected $aVideoSizes = array();

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
     * List of EndPoints
     * @var array
     * @protected
     */
    protected $aEndPoints = array();

    /**
     * Constructor
     * @param   string  file    input file
     */
    public function __construct($sFile) {
        $i = 0;

        //let's trust the input file
        $aFile = file($sFile);
        list($this->iVideos, $this->iEndPoints, $this->iRequestDescriptions, $this->iCacheServers, $this->iCapacity) = explode(' ', trim($aFile[$i++]));
        $this->aVideoSizes = explode(' ', trim($aFile[$i++]));

        for($z = 0; $z < $this->iCacheServers; $z++) {
            $aServer = array('latencies' => array());

            list($aServer['latency'], $aServer['servers']) = explode(' ', trim($aFile[$i++]));

            for($j = 0; $j < $aServer['servers']; $j++) {
                $aTemp = array();
                list($aTemp['endpoint'], $aTemp['latency']) = explode(' ', trim($aFile[$i++]));
                $aServer['latencies'][$aTemp['endpoint']] = $aTemp['latency'];

                $this->aEndPoints[$aTemp['endpoint']]['server'][count($this->aServers)] = $aTemp['latency'];
            }

            $this->aServers[] = $aServer;
        }

        for($j = 0; $j < $this->iRequestDescriptions; $j++) {
            $aTemp = array();
            list($aTemp['video'], $aTemp['endpoint'], $aTemp['requests']) = explode(' ', trim($aFile[$i++]));
            $this->aRequests[] = $aTemp;

            $this->aEndPoints[$aTemp['endpoint']]['videos'][$aTemp['video']] += $aTemp['requests'];
        }
    }

    /**
     * Delivers the videos
     */
    public function deliver() {
        var_dump($this->aRequests);
    }
}

$aFiles = array('/me_at_the_zoo.', '/videos_worth_spreading.', '/trending_today.', '/kittens.');
foreach($aFiles as $sFile) {
    $oVideos = new Videos(__DIR__.$sFile.'in');
    file_put_contents(__DIR__.$sFile.'out', $oVideos->deliver());
    break;
}
