<?php
namespace Slime\Redis;

use Slime\Container\ContainerObject;
use SlimeInterface\Event\EventInterface;

/**
 * Class PHPRedis
 *
 * @package Slime\Redis
 */
class PHPRedis extends ContainerObject
{
    protected static $aDefaultOptionConf = [
        'read_timeout' => 3
    ];

    /** @var array */
    protected $aServerConf;

    /** @var array */
    protected $aOptionConf;

    /** @var null|\Redis */
    private $nInst;

    public function __construct(array $aServer, array $aOption = [])
    {
        $this->aServerConf = $aServer;
        $this->aOptionConf = array_merge(self::$aDefaultOptionConf, $aOption);
    }

    public function __call($sMethod, $aArgv)
    {
        $iErr = 0;
        $sErr = '';
        $mRS  = null;

        $Local = new \ArrayObject();
        /** @var null|EventInterface $nEV */
        $nEV   = $this->_getIfExist('Event');
        $cbRun = function () use ($sMethod, $aArgv, $nEV, $Local) {
            $Redis = $this->getInst();
            if ($nEV !== null) {
                $nEV->fire(RedisEvent::EV_BEFORE_EXEC, [$sMethod, $aArgv, $Local]);
                if (!isset($Local['__RESULT__'])) {
                    $mRS                 = call_user_func_array([$Redis, $sMethod], $aArgv);
                    $Local['__RESULT__'] = $mRS;
                }
                $nEV->fire(RedisEvent::EV_AFTER_EXEC, [$sMethod, $aArgv, $Local]);
                $mRS = $Local['__RESULT__'];
            } else {
                $mRS = call_user_func_array([$Redis, $sMethod], $aArgv);
            }
            return $mRS;
        };

        $iRetryTimes = isset($this->aServerConf['retry_times']) ? (int)$this->aServerConf['retry_times'] : 1;
        for ($i = 0; $i <= $iRetryTimes; $i++) {
            try {
                $mRS = $cbRun();
            } catch (\RedisException $E) {
                $iInst = isset($Redis) ? (int)$Redis : 0;
                $this->releaseConn();
                $iErr = ($iCode = $E->getCode()) === 0 ? -99999999 : $iCode;
                $sErr = $E->getMessage();
                if ($nEV) {
                    $nEV->fire(
                        RedisEvent::EV_EXEC_EXCEPTION,
                        [
                            [
                                'obj'         => $this,
                                'method'      => $sMethod,
                                'argv'        => $aArgv,
                                'local'       => $Local,
                                'code'        => $iErr,
                                'msg'         => $sErr,
                                'retry_times' => $i,
                                'E'           => $E,
                                'inst'        => $iInst
                            ],
                            $this->_getContainer()
                        ]
                    );
                }
                continue;
            }
            break;
        }

        return [$iErr, $sErr, $mRS];
    }

    /**
     * @return \Redis
     * @throws \RedisException
     */
    protected function getInst()
    {
        if ($this->nInst === null) {
            $Redis = new \Redis();
            $bRS   = $Redis->connect(
                (string)$this->aServerConf['host'],
                (int)$this->aServerConf['port'],
                (float)$this->aServerConf['timeout']
            );
            if ($bRS === false) {
                throw new \RedisException('redis connect failed');
            }
            $bRS = $Redis->setOption(\Redis::OPT_READ_TIMEOUT, $this->aOptionConf['read_timeout']);
            if ($bRS === false) {
                throw new \RedisException('redis set option failed');
            }
            if (isset($this->aOptionConf['auth'])) {
                $bRS = $Redis->auth($this->aOptionConf['auth']);
                if ($bRS === false) {
                    throw new \RedisException('redis auth failed');
                }
            }
            if (isset($this->aOptionConf['db'])) {
                $bRS = $Redis->select($this->aOptionConf['db']);
                if ($bRS === false) {
                    throw new \RedisException('redis select db failed');
                }
            }
            $this->nInst = $Redis;
        }

        return $this->nInst;
    }

    public function releaseConn()
    {
        if ($this->nInst) {
            $this->nInst->close();
        }
        $this->nInst = null;
    }

    public function __destruct()
    {
        $this->releaseConn();
    }
}