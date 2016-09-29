<?php
namespace Slime\RDBMS\ORM;

use Slime\Container\ContainerObject;
use Slime\RDBMS\ORM\Engine\RDBEvent;
use SlimeInterface\Event\EventInterface;
use SlimeInterface\RDBMS\ORM\CollectionInterface;
use SlimeInterface\RDBMS\ORM\Engine\EnginePoolInterface;
use SlimeInterface\RDBMS\ORM\ModelFactoryInterface;
use SlimeInterface\RDBMS\ORM\ModelInterface;
use SlimeInterface\RDBMS\SQL\SQLInterface;
use SlimeInterface\RDBMS\SQL\DeleteInterface;
use SlimeInterface\RDBMS\SQL\InsertInterface;
use SlimeInterface\RDBMS\SQL\SelectInterface;
use SlimeInterface\RDBMS\SQL\UpdateInterface;

use Slime\RDBMS\SQL\Condition;
use Slime\RDBMS\SQL\SQLFactory;
use Slime\RDBMS\ORM\Engine\PDO;

/**
 * Class Model
 *
 * @package Slime\RDBMS\SQL
 */
class Model extends ContainerObject implements ModelInterface
{
    /** @var SQLFactory */
    protected $SQLFactory;

    /** @var int */
    protected $iSQLType = SQLFactory::TYPE_MYSQL;

    /** @var string */
    protected $sTable;

    /** @var string */
    protected $sOrgTable;

    /** @var string */
    protected $sPK = 'id';

    /** @var string */
    protected $sFK;

    /** @var string */
    protected $sModelItem;

    /** @var string */
    protected $sEngineKey = 'default';

    /** @var EnginePoolInterface */
    protected $EnginePool;

    /** @var ModelFactoryInterface */
    protected $ModelFactory;

    /** @var mixed */
    protected $mTableCB = null;

    public function __construct(
        $sModelName,
        array $aModelConfig,
        EnginePoolInterface $EnginePool,
        ModelFactoryInterface $ModelFactory
    ) {
        $this->EnginePool = $EnginePool;
        $this->SQLFactory = SQLFactory::create($this->iSQLType, $this);
        if (!$this->sTable) {
            $sFullClass      = get_called_class();
            $biPos           = strrpos($sFullClass, '\\');
            $this->sTable    = $biPos === false ? $sFullClass : substr($sFullClass, $biPos + 1);
        }
        $this->sOrgTable = $this->sTable;
        if (!$this->sFK) {
            $this->sFK = $this->sTable . '_id';
        }
        if (!$this->sModelItem) {
            $this->sModelItem =
                $aModelConfig['namespace'] . "\\" .
                $aModelConfig['item_pre'] . $sModelName . $aModelConfig['item_post'];
        }

        $this->ModelFactory = $ModelFactory;
        $this->setTableNameCB();
    }

    protected function setTableNameCB()
    {
    }

    /**
     * @return $this
     */
    public function cbForTableName()
    {
        if ($this->mTableCB) {
            $aParam = func_get_args();
            array_unshift($aParam, $this->sOrgTable);
            $this->sTable = call_user_func_array($this->mTableCB, $aParam);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getPK()
    {
        return $this->sPK;
    }

    /**
     * @return string
     */
    public function getFK()
    {
        return $this->sFK;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->sTable;
    }

    /**
     * @param $mPK
     *
     * @return array [0:int<errCode>, 1:string<errMsg>, 3:Item|null<data>]
     */
    public function findByPK($mPK)
    {
        /** @var CollectionInterface $Col */
        list($iErr, $sErr, $Col) = $this->SQLFactory->select()
            ->table($this->getTable())
            ->where(Condition::asAnd()->eq($this->sPK, $mPK))
            ->limit(1)
            ->run();

        return [
            $iErr,
            $sErr,
            ($Col === null ? null : ($Col->isEmpty() ? null : $Col->current()))
        ];
        //) === 0 ? null : $Col->current();
    }

    /**
     * @return Collection
     */
    public function findAll()
    {
        return $this->SQLFactory->select()->table($this->getTable())->run();
    }

    /**
     * @return Item
     */
    public function createEmptyItem()
    {
        $Item = new Item([], $this, null);
        $Item->__init__($this->_getContainer());
        return $Item;
    }

    /**
     * @return SelectInterface
     */
    public function select()
    {
        return $this->SQLFactory->select()->table($this->getTable());
    }

    /**
     * @return UpdateInterface
     */
    public function update()
    {
        return $this->SQLFactory->update()->table($this->getTable());
    }

    /**
     * @return InsertInterface
     */
    public function insert()
    {
        return $this->SQLFactory->insert()->table($this->getTable());
    }

    /**
     * @return DeleteInterface
     */
    public function delete()
    {
        return $this->SQLFactory->delete()->table($this->getTable());
    }

    /**
     * @param SQLInterface $SQL
     *
     * @return array [0:int<errCode>, 1:string<errMsg>, 3:mixed<data>]
     */
    public function run(SQLInterface $SQL)
    {
        return $this->_runWithPDO($this->EnginePool->getInst($this->sEngineKey, $this, $SQL), $SQL);
    }

    /**
     * @param null|string $nsEngineKey
     *
     * @return mixed
     */
    public function getEngine($nsEngineKey = null)
    {
        return $this->EnginePool->getInst(
            $nsEngineKey === null ? $this->sEngineKey : $nsEngineKey,
            $this, ''
        );
    }

    /**
     * @param PDO|\PDO     $PDO
     * @param SQLInterface $SQL
     *
     * @return array [0:int<errCode>, 1:string<errMsg>, 3:mixed<data>]
     */
    public function _runWithPDO(PDO $PDO, SQLInterface $SQL)
    {
        static $aMapP = [
            0         => \PDO::PARAM_STR,
            'string'  => \PDO::PARAM_STR,
            'boolean' => \PDO::PARAM_BOOL,
            'integer' => \PDO::PARAM_INT,
            'null'    => \PDO::PARAM_NULL
        ];
        $naBind = $SQL->getBind();

        $iErr = 0;
        $sErr = '';
        $mRS  = null;

        try {
            switch ($iType = $SQL->getSQLType()) {
                case SQLInterface::SQL_TYPE_SELECT:
                    if ($naBind === null) {
                        $mSTMT = $PDO->query((string)$SQL);
                        $aRS   = $mSTMT === false ? [] : $mSTMT->fetchAll(\PDO::FETCH_ASSOC);
                    } else {
                        $mSTMT = $PDO->prepare((string)$SQL);
                        if ($mSTMT) {
                            foreach ($naBind as $sK => $mOne) {
                                $sType = gettype($mOne);
                                $mSTMT->bindValue($sK, $mOne, isset($aMapP[$sType]) ? $aMapP[$sType] : $aMapP[0]);
                            }
                        }
                        $aRS = $mSTMT->execute() ? $mSTMT->fetchAll(\PDO::FETCH_ASSOC) : [];
                    }
                    $Obj = new Collection(
                        $aRS,
                        $this->sModelItem,
                        $this
                    );
                    $Obj->__init__($this->_getContainer());
                    $Obj->buildData();
                    $mRS = $Obj;
                    break;
                case SQLInterface::SQL_TYPE_INSERT:
                case SQLInterface::SQL_TYPE_UPDATE:
                case SQLInterface::SQL_TYPE_DELETE:
                    if ($naBind === null) {
                        $iEffectRows = $PDO->exec((string)$SQL);
                    } else {
                        $mSTMT = $PDO->prepare((string)$SQL);
                        if ($mSTMT) {
                            foreach ($naBind as $sK => $mOne) {
                                $sType = gettype($mOne);
                                $mSTMT->bindValue($sK, $mOne, isset($aMapP[$sType]) ? $aMapP[$sType] : $aMapP[0]);
                            }
                            $iEffectRows = $mSTMT->execute();
                        } else {
                            $iEffectRows = 0;
                        }
                    }
                    $mRS = $iType === SQLInterface::SQL_TYPE_INSERT ?
                        ($iEffectRows === 0 ? false : $PDO->lastInsertId()) :
                        $iEffectRows;
                    break;
                default:
                    throw new \InvalidArgumentException();
            }
        } catch (\PDOException $E) {
            $iErr = $PDO->errorCode();
            $sErr = $PDO->errorInfo();
            /*
            $iErr = ($iCode = $E->getCode()) === 0 ? -99999999 : $iCode;
            $sErr = $E->getMessage();
            */
            /** @var EventInterface $nEvent */
            $nEvent = $this->_getIfExist('Event');
            if ($nEvent !== null) {
                $nEvent->fire(
                    RDBEvent::EV_QUERY_EXCEPTION,
                    [
                        $E,
                        ['obj' => $this, 'method' => __FUNCTION__, 'argv' => func_get_args(), 'local' => null],
                        $this->_getContainer()
                    ]
                );
            }
            if ($iErr == 2006 || $iErr == 2013) {
                $PDO->releaseConn();
                return call_user_func_array([$this, '_runWithPDO'], [$PDO, $SQL]);
            }
        }

        return [$iErr, $sErr, $mRS];
    }

    /**
     * @param string $sModelName
     *
     * @return ModelInterface
     */
    public function getOtherModel($sModelName)
    {
        return $this->ModelFactory->getModel($sModelName);
    }

    /**
     * @param mixed $mCB
     *
     * @return int
     */
    public function transaction($mCB)
    {
        /** @var \PDO|PDO $PDO */
        $PDO = $this->getEngine();
        $PDO->query('begin');
        try {
            $iErr = call_user_func($mCB, $this);
            if ($iErr !== 0) {
                $PDO->query('rollback');
            }
            $PDO->query('commit');
        } catch (\PDOException $E) {
            $iErr = -1;
            //var_dump($E->getMessage());
            $PDO->query('rollback');
        }
        return $iErr;
    }
}