<?php
namespace Slime\Log;

use Slime\Container\ContainerObject;
use SlimeInterface\Container\ContainerInterface;

/**
 * Class LoggerFactory
 *
 * @package Slime\Log
 * @author  smallslime@gmail.com
 */
class LoggerFactory extends ContainerObject
{
    public function create($aConf)
    {
        $C      = $this->_getContainer();
        $Logger = new Logger($aConf['level']);
        $Logger->__init__($C);
        foreach ($aConf['adaptor'] as $sKey => $aRow) {
            $Ref = new \ReflectionClass($aRow[0]);
            $Obj = $Ref->newInstanceArgs($aRow[1]);
            if (!$Obj instanceof IWriter) {
                throw new \RuntimeException(
                    sprintf('[LOG] ; adaptor type error ; class : %s' . $aRow[0])
                );
            }
            if ($Obj instanceof ContainerObject) {
                $Obj->__init__($C);
            }
            $Logger->setWriter($sKey, $Obj);
        }

        return $Logger;
    }
}
