<?php
namespace Slime\HttpCrawler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Crawler
{
    /** @var _Task[] */
    protected $aToStartTask = [];

    /** @var _Task[] */
    protected $aToWriteTask = [];

    /** @var _Task[] */
    protected $aToReadTask = [];

    /** @var _Task[] */
    protected $aBufferTask = [];

    /** @var mixed */
    protected $mCBSucc = null;

    /** @var mixed */
    protected $mCBFail = null;

    /** @var mixed */
    protected $mCBCacheFetch = null;

    /** @var mixed */
    protected $mCBCacheSave = null;

    public function __construct($iMaxConcurrency = 1000, $iDefaultTimeout = 30, $iSleepUSWhenWait = 10)
    {
        $this->iMaxConcurrency  = $iMaxConcurrency;
        $this->iDefaultTimeout  = $iDefaultTimeout;
        $this->iSleepUSWhenWait = $iSleepUSWhenWait;
    }

    public function addTask(
        RequestInterface $REQ,
        $mCBSucc = null,
        $mCBFail = null,
        $mCBCacheFetch = null,
        $mCBCacheSave = null,
        $niTimeout = null
    ) {
        $Task = new _Task($this, $REQ, $mCBSucc, $mCBFail, $mCBCacheFetch, $mCBCacheSave, $niTimeout);
        if ($this->getCurrentTaskCount() >= $this->iMaxConcurrency) {
            $this->aBufferTask[] = $Task;
        } else {
            $this->aToStartTask[] = $Task;
        }

        return $this;
    }

    public function run()
    {
        do {
            # init
            foreach ($this->aToStartTask as $iK => $Task) {
                unset($this->aToStartTask[$iK]);

                $nRESP = $Task->fetchFromCache();
                if ($nRESP instanceof ResponseInterface) {
                    $Task->setResponse($nRESP);
                    $Task->callSucc();
                    continue;
                }

                $REQ   = $Task->getRequest();
                $rCurl = curl_init();
                curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, 1);
                self::curl_setting_with_req($rCurl, $REQ);
                $rMCurl = curl_multi_init();
                curl_multi_add_handle($rMCurl, $rCurl);
                curl_multi_exec($rMCurl, $iActive);
                $Task->setAttr(['curl' => $rCurl, 'm_curl' => $rMCurl,]);

                $this->aToWriteTask[] = $Task;
            }

            # write
            foreach ($this->aToWriteTask as $iK => $Task) {
                if ($Task->isTimeout()) {
                    unset($this->aToWriteTask[$iK]);
                    $Task->callFail();
                    $rCurl  = $Task->getAttr('curl');
                    $rMCurl = $Task->getAttr('m_curl');
                    curl_close($rCurl);
                    curl_multi_close($rMCurl);
                    continue;
                }

                $iStillRunning = null;
                curl_multi_exec($Task->getAttr('m_curl'), $iStillRunning);
                if ($iStillRunning) {
                    continue;
                }
                $Task                = $this->aToWriteTask[$iK];
                $this->aToReadTask[] = $Task;
                unset($this->aToWriteTask[$iK]);
            }

            # reading && cb
            foreach ($this->aToReadTask as $iK => $Task) {
                $rCurl     = $Task->getAttr('curl');
                $rMCurl    = $Task->getAttr('m_curl');
                $sResponse = curl_multi_getcontent($rCurl);
                curl_close($rCurl);
                curl_multi_close($rMCurl);
                unset($this->aToReadTask[$iK]);

                $nRESP = ResponseFactory::create($sResponse);
                if ($nRESP === null) {
                    $Task->callFail();
                    continue;
                }

                $Task->setResponse($nRESP);
                $Task->saveToCache();
                $Task->callSucc();
            }

            NEXT:
            if (count($this->aBufferTask) > 0 && (($iCurrentTaskCount = $this->getCurrentTaskCount()) < $this->iMaxConcurrency)) {
                $iCouldTodoCount = $this->iMaxConcurrency - $iCurrentTaskCount;
                $i               = 0;
                while (count($this->aBufferTask) > 0) {
                    $this->aToStartTask[] = array_shift($this->aBufferTask);
                    if (++$i >= $iCouldTodoCount) {
                        break;
                    }
                }
            }
            usleep($this->iSleepUSWhenWait);
        } while (
            count($this->aToStartTask) !== 0 ||
            count($this->aToWriteTask) !== 0 ||
            count($this->aToReadTask) !== 0
        );
    }

    protected function getCurrentTaskCount()
    {
        return count($this->aToStartTask) + count($this->aToWriteTask) + count($this->aToReadTask);
    }

    /**
     * @param RequestInterface $REQ
     * @param int              $iErr
     * @param string           $sErr
     *
     * @return null|\Slime\Http\Response
     */
    public function doOneTask(RequestInterface $REQ, &$iErr, &$sErr)
    {
        $rCurl = curl_init();
        curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, 1);
        self::curl_setting_with_req($rCurl, $REQ);
        $bsResponse = curl_exec($rCurl);
        $iErr       = curl_errno($rCurl);
        $sErr       = curl_error($rCurl);
        return $bsResponse === false ? null : ResponseFactory::create($bsResponse);
    }

    public static function curl_setting_with_req($rCurl, RequestInterface $REQ)
    {
        curl_setopt($rCurl, CURLOPT_HEADER, true);
        curl_setopt($rCurl, CURLOPT_URL, (string)$REQ->getUri());
        $aHeader = $REQ->getHeaders();
        if (count($aHeader) > 0) {
            $aTidyHeader = [];
            foreach ($aHeader as $sK => $aRow) {
                foreach ($aRow as $aOne) {
                    $aTidyHeader[] = "$sK: $aOne";
                }
            }
            curl_setopt($rCurl, CURLOPT_HTTPHEADER, $aTidyHeader);
        }
    }

    public function getTimeout()
    {
        return $this->iDefaultTimeout;
    }

    public function getCBCacheFetch()
    {
        return $this->mCBCacheFetch;
    }

    public function getCBCacheSave()
    {
        return $this->mCBCacheSave;
    }

    public function getCBSucc()
    {
        return $this->mCBSucc;
    }

    public function getCBFail()
    {
        return $this->mCBFail;
    }

    public function setCBSucc($mCBSucc)
    {
        $this->mCBSucc = $mCBSucc;
        return $this;
    }

    public function setCBFail($mCBFail)
    {
        $this->mCBFail = $mCBFail;
        return $this;
    }

    public function setCBCache($mFetch, $mSave)
    {
        $this->mCBCacheFetch = $mFetch;
        $this->mCBCacheSave  = $mSave;
        return $this;
    }
}

class _Task
{
    /** @var  Crawler */
    private $Crawler;

    /** @var  RequestInterface */
    private $REQ;

    /** @var mixed */
    private $mCBSucc = null;

    /** @var mixed */
    private $mCBFail = null;

    /** @var mixed */
    private $mCBCacheFetch = null;

    /** @var mixed */
    private $mCBCacheSave = null;

    /** @var null|int */
    protected $niTimeout;

    /** @var null|RequestInterface */
    private $nRESP = null;

    /** @var array */
    private $aAttr = [];

    /** @var int */
    private $iStartAT;

    public function __construct(
        Crawler $Crawler,
        RequestInterface $REQ,
        $mCBSucc = null,
        $mCBFail = null,
        $mCBCacheFetch = null,
        $mCBCacheSave = null,
        $niTimeout = null
    ) {
        $this->Crawler       = $Crawler;
        $this->REQ           = $REQ;
        $this->mCBSucc       = $mCBSucc;
        $this->mCBFail       = $mCBFail;
        $this->mCBCacheFetch = $mCBCacheFetch;
        $this->mCBCacheSave  = $mCBCacheSave;
        $this->niTimeout     = $niTimeout;
        $this->iStartAT      = time();
    }

    public function isTimeout()
    {
        $niTimeout = $this->niTimeout ? $this->niTimeout : $this->Crawler->getTimeout();
        # 永不过期
        if ($niTimeout === null) {
            return false;
        }
        return time() - $this->iStartAT <= $niTimeout;
    }

    public function fetchFromCache()
    {
        $mCB = $this->mCBCacheFetch ? $this->mCBCacheFetch : $this->Crawler->getCBCacheFetch();
        if ($mCB === null) {
            return null;
        }
        return call_user_func($mCB, $this->REQ);
    }

    public function saveToCache()
    {
        $mCB = $this->mCBCacheSave ? $this->mCBCacheSave : $this->Crawler->getCBCacheSave();
        if ($mCB === null) {
            return null;
        }
        return call_user_func($mCB, $this->REQ, $this->nRESP);
    }

    public function callSucc()
    {
        $mCB = $this->mCBSucc ? $this->mCBSucc : $this->Crawler->getCBSucc();
        if ($mCB === null) {
            return null;
        }
        return call_user_func($mCB, $this->REQ, $this->nRESP, $this->Crawler);
    }

    public function callFail()
    {
        $mCB = $this->mCBFail ? $this->mCBFail : $this->Crawler->getCBFail();
        if ($mCB === null) {
            return null;
        }
        return call_user_func($mCB, $this->REQ, $this->Crawler);
    }

    public function getRequest()
    {
        return $this->REQ;
    }

    public function setResponse(ResponseInterface $RESP)
    {
        $this->nRESP = $RESP;
    }

    public function setAttr($m_sKey_aMap, $mV = null)
    {
        if (!is_array($m_sKey_aMap)) {
            $m_sKey_aMap = [(string)$m_sKey_aMap => $mV];
        }
        $this->aAttr = array_merge($m_sKey_aMap, $this->aAttr);
    }

    public function getAttr($sK)
    {
        return isset($this->aAttr[$sK]) ? $this->aAttr[$sK] : null;
    }
}