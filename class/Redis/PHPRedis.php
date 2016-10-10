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

    /** @var null|\Memcached */
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

        for ($i = 0; $i <= 1; $i++) {
            try {
                $mRS = $cbRun();
            } catch (\RedisException $E) {
                $iErr = ($iCode = $E->getCode()) === 0 ? -99999999 : $iCode;
                $sErr = $E->getMessage();
                if ($nEV) {
                    $nEV->fire(
                        RedisEvent::EV_EXEC_EXCEPTION,
                        [
                            [
                                'obj'    => $this,
                                'method' => $sMethod,
                                'argv'   => $aArgv,
                                'local'  => $Local,
                                'code'   => $iErr,
                                'msg'    => $sErr,
                                'E'      => $E,
                            ],
                            $this->_getContainer()
                        ]
                    );
                }

                if ($E->getMessage() === 'Redis server went away') {
                    $this->releaseConn();
                    $nEV->fire(
                        RedisEvent::EV_EXEC_RETRY,
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
     */
    protected function getInst()
    {
        if ($this->nInst === null) {
            $Redis = new \Redis();
            $Redis->connect((string)$this->aServerConf['host'], (int)$this->aServerConf['port'],
                $this->aServerConf['timeout']);
            $Redis->setOption(\Redis::OPT_READ_TIMEOUT, $this->aOptionConf['read_timeout']);
            if (isset($this->aOptionConf['auth'])) {
                $Redis->auth($this->aOptionConf['auth']);
            }
            if (isset($this->aOptionConf['db'])) {
                $Redis->select($this->aOptionConf['db']);
            }
            $this->nInst = $Redis;
        }

        return $this->nInst;
    }

    public function releaseConn()
    {
        $this->nInst = null;
    }
}