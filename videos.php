<?php

class Entity {
    static protected $aCache = array();
    protected $iId;
    protected $bExcluded = false;

    public function __construct($iId) {
        $this->iId = $iId;
    }

    public function getId() {
        return $this->iId;
    }

    public function exclude() {
        $this->bExcluded = true;
    }

    public function excluded() {
        return $this->bExcluded;
    }

    public static function &create($iId, $aData) {
        $sClass = get_called_class();

        return static::$aCache[$sClass][$iId] = new $sClass($iId, $aData);
    }

    public static function &get($iId) {
        $sClass = get_called_class();

        return static::$aCache[$sClass][$iId];
    }

    public static function sortAll() {
        foreach(static::$aCache as $sClass => $aEntities) {
            if(method_exists($sClass, 'sorter')) {
                uasort(static::$aCache[$sClass], array($sClass, 'sorter'));
            }
        }
    }

    public static function allExcluded() {
        $sClass = get_called_class();

        foreach(static::$aCache[$sClass] as &$oEntity) {
            if(!$oEntity->excluded()) {
                return false;
            }
        }

        return true;
    }

    public static function each($aCallback, $bCheckExcluded = false) {
        $sClass = get_called_class();

        foreach(static::$aCache[$sClass] as &$oEntity) {
            if($bCheckExcluded && $oEntity->excluded()) {
                continue;
            }

            call_user_func_array($aCallback, array(&$oEntity));
        }
    }
}

class CacheServer extends Entity {
    protected $iCapacity;
    protected $aEndPointLatencies = array();
    protected $aEndPointDeltas = array();
    protected $aVideoGains = array();
    protected $aVideos = array();

    public function __construct($iId, $iCapacity) {
        parent::__construct($iId);
        $this->iCapacity = $iCapacity;
    }

    public static function sorter($oCacheServer1, $oCacheServer2) {
        $iGain1 = $oCacheServer1->getMaxGain();
        $iGain2 = $oCacheServer2->getMaxGain();

        if($iGain1 == $iGain2) {
            return 0;
        }

        return ($iGain1 > $iGain2) ? -1 : 1;
    }

    public function linkEndPoint($iEndPoint, $iLatency, $iDelta) {
        $this->aEndPointLatencies[$iEndPoint] = $iLatency;
        $this->aEndPointDeltas[$iEndPoint] = $iDelta;
    }

    public function linkVideoGain($iVideo, $iGain) {
        $this->aVideoGains[$iVideo] += $iGain;
    }

    public function unlinkVideoGain($iVideo, $iGain) {
        $this->aVideoGains[$iVideo] -= $iGain;

        if($this->aVideoGains[$iVideo] <= 0) {
            unset($this->aVideoGains[$iVideo]);
        }

        $this->sortVideos();
    }

    public function getMaxGain() {
        return count($this->aVideoGains)?max($this->aVideoGains):0;
    }

    public function sortVideos() {
        foreach($this->aVideoGains as $iVideo => $iGain) {
            $oVideo = Video::get($iVideo);
            if($oVideo->getSize() > $this->iCapacity) {
                unset($this->aVideoGains[$iVideo]);
            }
        }

        arsort($this->aVideoGains);
    }

    public function getBestVideo() {
        if(count($this->aVideoGains)) {
            $aVideos = array_keys($this->aVideoGains);
            return $aVideos[0];
        }

        return false;
    }

    public function cacheVideo($iVideo) {
        $oVideo = Video::get($iVideo);
        if($oVideo->getSize() > $this->iCapacity) {
            return;
        }

        $this->aVideos[] = $iVideo;
        $this->iCapacity -= $oVideo->getSize();

        foreach($this->aEndPointDeltas as $iEndPoint => $iDelta) {
            $oEndPoint = EndPoint::get($iEndPoint);
            $oEndPoint->cacheVideo($iVideo, $this->iId);
        }
    }

    public function getCachesVideos() {
        return $this->aVideos;
    }
}

class Video extends Entity {
    protected $iId;
    protected $iSize;
    protected $iRequests = 0;
    protected $aRequests = array();

    public function __construct($iId, $iSize) {
        parent::__construct($iId);
        $this->iSize = $iSize;
    }

    public function getSize() {
        return $this->iSize;
    }

    public function addRequest($iEndPoint, $iRequests) {
        $this->iRequests += $iRequests;
        $this->aRequests[$iEndPoint] += $iRequests;
    }
}

class EndPoint extends Entity {
    protected $iId;
    protected $iLatency;
    protected $iCacheServers;
    protected $aCacheServerLatencies = array();
    protected $aCacheServerDeltas = array();
    protected $aRequests = array();
    protected $aCachedVideos = array();

    public function __construct($iId, $aData) {
        parent::__construct($iId);
        $this->iLatency = $aData[0];
        $this->iCacheServers = $aData[1];
    }

    public function getCacheServers() {
        return $this->iCacheServers;
    }

    public function addCacheServerLatency($aData) {
        $iDelta = $this->iLatency - $aData[1];

        $this->aCacheServerLatencies[$aData[0]] = $aData[1];
        $this->aCacheServerDeltas[$aData[0]] = $iDelta;

        $oCacheServer = CacheServer::get($aData[0]);
        $oCacheServer->linkEndPoint($this->iId, $aData[1], $iDelta);
    }

    public function addRequest($iVideo, $iRequests) {
        $this->aRequests[$iVideo] += $iRequests;

        foreach($this->aCacheServerDeltas as $iCacheServer => $iDelta) {
            $oCacheServer = CacheServer::get($iCacheServer);
            $oCacheServer->linkVideoGain($iVideo, $iRequests * $iDelta);
        }
    }

    public function cacheVideo($iVideo, $iCacheServer) {
        $this->aCachedVideos[$iVideo] = $iCacheServer;

        foreach($this->aCacheServerDeltas as $iCacheServer => $iDelta) {
            $oCacheServer = CacheServer::get($iCacheServer);
            $oCacheServer->unlinkVideoGain($iVideo, $this->aRequests[$iVideo] * $iDelta);
        }
    }
}

class Request extends Entity {
    protected $iId;
    protected $iVideo;
    protected $iEndPoint;
    protected $iRequests;

    public function __construct($iId, $aData) {
        parent::__construct($iId);
        $this->iVideo = $aData[0];
        $this->iEndPoint = $aData[1];
        $this->iRequests = $aData[2];

        $oVideo = Video::get($this->iVideo);
        $oVideo->addRequest($this->iEndPoint, $this->iRequests);

        $oEndPoint = EndPoint::get($this->iEndPoint);
        $oEndPoint->addRequest($this->iVideo, $this->iRequests);
    }
}

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
     * Number of used cache servers
     * @var integer
     * @protected
     */
    protected $iOutputCount = 0;

    /**
     * List of server usage strings
     * @var array
     * @protected
     */
    protected $aOutput = array();

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
            CacheServer::create($j, $this->iCapacity);
        }

        $aTemp = explode(' ', trim($aFile[$i++]));
        foreach($aTemp as $j => $iVideoSize) {
            Video::create($j, $iVideoSize);
        }

        for($z = 0; $z < $this->iEndPoints; $z++) {
            $oEndPoint = EndPoint::create($z, explode(' ', trim($aFile[$i++])));

            for($j = 0; $j < $oEndPoint->getCacheServers(); $j++) {
                $oEndPoint->addCacheServerLatency(explode(' ', trim($aFile[$i++])));
            }
        }

        for($j = 0; $j < $this->iRequestDescriptions; $j++) {
            $this->aRequests[] = Request::create($j, explode(' ', trim($aFile[$i++])));
        }
    }

    /**
     * Formats the output
     */
    public function getOutput($oCacheServer) {
        $aVideos = $oCacheServer->getCachesVideos();
        if(!empty($aVideos)) {
            $this->iOutputCount++;
            $this->aOutput[] = $oCacheServer->getId().' '.implode(' ', $aVideos);
        }
    }

    public function cacheBestVideo($oCacheServer) {
        $oCacheServer->sortVideos();
        $iVideo = $oCacheServer->getBestVideo();
        if($iVideo === false) {
            $oCacheServer->exclude();
            return;
        }

        $oCacheServer->cacheVideo($iVideo);
    }

    /**
     * Delivers the videos
     */
    public function deliver() {
        while(!CacheServer::allExcluded()) {
            Entity::sortAll();
            CacheServer::each(array(&$this, 'cacheBestVideo'), true);
        }

        CacheServer::each(array(&$this, 'getOutput'));

        return $this->iOutputCount."\n".implode("\n", $this->aOutput)."\n";;
    }
}

$aFiles = array('/me_at_the_zoo.', '/videos_worth_spreading.', '/trending_today.', '/kittens.');
foreach($aFiles as $sFile) {
    $oVideos = new Videos(__DIR__.$sFile.'in');
    file_put_contents(__DIR__.$sFile.'out', $oVideos->deliver());
}
