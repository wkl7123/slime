<?php
namespace Slime\Config;

use Psr\Log\LoggerInterface;
use Slime\Container\ContainerObject;
use SlimeInterface\Config\ConfigureInterface;

class PHPConfAdaptor extends ContainerObject implements ConfigureInterface
{
    use ConfigureTrait;
    use ConfigureFileTrait;

    /**
     * @return LoggerInterface|null
     */
    protected function getLogger()
    {
        return $this->_getIfExist('Log');
    }

    /**
     * @param string $sFilePath
     *
     * @return array|bool
     */
    protected function getDataFromFile($sFilePath)
    {
        $nLog     = $this->getLogger();
        $sPackage = $this->getPackageName();

        # load
        $mRS = require $sFilePath;
        if (!is_array($mRS)) {
            $nLog && $nLog->notice(
                [
                    "package" => $sPackage,
                    "msg"     => "data in file[$sFilePath] is not array"
                ]
            );
            $mRS = [];
            goto END;
        }
        $nLog && $nLog->debug(
            [
                "package" => $sPackage,
                "msg"     => "load data from file[$sFilePath] succ"
            ]
        );

        END:
        return $mRS;
    }

    /**
     * @return string
     */
    protected function getPackageName()
    {
        return 'slime.config.php';
    }
}