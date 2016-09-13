<?php
namespace Slime\Config;

use InvalidArgumentException;
use Slime\Container\ContainerObject;
use SlimeInterface\Config\ConfigureInterface;

/**
 * Class ConfigurationFactory
 *
 * @package Slime\Config
 * @author  smallslime@gmail.com
 */
final class ConfigurationFactory extends ContainerObject
{
    /**
     * @param string $sAdaptor
     * @param array  $aArgv
     *
     * @return ConfigureInterface
     */
    public function create($sAdaptor, array $aArgv = [])
    {
        $Ref = new \ReflectionClass($sAdaptor);
        $Obj = $Ref->newInstanceArgs($aArgv);
        if (!$Obj instanceof ConfigureInterface) {
            throw new InvalidArgumentException();
        }
        if ($Obj instanceof ContainerObject) {
            $Obj->__init__($this->_getContainer());
        }
        return $Obj;
    }
}