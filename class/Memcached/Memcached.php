<?php
namespace Slime\Memcached;

use Slime\Container\ContainerObject;
use SlimeInterface\Event\EventInterface;

/**
 * Class Memcached
 *
 * @package Slime\Memcached
 */
class Memcached extends ContainerObject
{
    protected static $aDefaultOptionConf = [
        \Memcached::OPT_CONNECT_TIMEOUT => 50,
        \Memcached::OPT_SEND_TIMEOUT    => 100,
        \Memcached::OPT_RECV_TIMEOUT    => 500,
        \Memcached::OPT_POLL_TIMEOUT    => 100
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

        $MC = $this->getInst();
        /** @var null|EventInterface $nEV */
        $nEV = $this->_getIfExist('Event');

        if ($nEV !== null) {
            $Local = new \ArrayObject();
            $nEV->fire(MemcachedEvent::EV_BEFORE_EXEC, [$sMethod, $aArgv, $Local]);
            if (!isset($Local['__RESULT__'])) {
                $mRS                 = call_user_func_array([$MC, $sMethod], $aArgv);
                $Local['__RESULT__'] = $mRS;
            }
            $nEV->fire(MemcachedEvent::EV_AFTER_EXEC, [$sMethod, $aArgv, $Local]);
            $mRS = $Local['__RESULT__'];
        } else {
            $mRS = call_user_func_array([$MC, $sMethod], $aArgv);
        }
        if ($mRS === false) {
            $iErr = $MC->getResultCode();
            $sErr = $MC->getResultMessage();

            if ($nEV) {
                $nEV->fire(
                    MemcachedEvent::EV_EXEC_ERROR,
                    [
                        ['code' => $iErr, 'msg' => $sErr],
                        ['obj' => $this, 'method' => $sMethod, 'argv' => $aArgv, 'local' => $Local],
                        $this->_getContainer()
                    ]
                );
            }

            if ($iErr == 47) {
                $this->releaseConn();
                return call_user_func_array([$this, $sMethod], $aArgv);
            }
        }

        return [$iErr, $sErr, $mRS];
    }

    /**
     * @return \Memcached
     */
    protected function getInst()
    {
        if ($this->nInst === null) {
            $MC = new \Memcached();
            $MC->setOptions($this->aOptionConf);
            $MC->addServers($this->aServerConf);
            $this->nInst = $MC;
        }

        return $this->nInst;
    }

    /**
     * @return void
     */
    public function releaseConn()
    {
        $this->nInst = null;
    }
}
