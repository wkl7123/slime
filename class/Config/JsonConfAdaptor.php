<?php
namespace Slime\Config;

use Psr\Log\LoggerInterface;
use Slime\Container\ContainerObject;
use SlimeInterface\Config\ConfigureInterface;

class JsonConfAdaptor extends ContainerObject implements ConfigureInterface
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
        $mRS      = false;
        $nLog     = $this->getLogger();
        $sPackage = $this->getPackageName();

        # load
        $bsContent = file_get_contents($sFilePath);
        if ($bsContent === false) {
            $nLog && $nLog->notice(
                [
                    "package" => $sPackage,
                    "msg"     => "load data from file[$sFilePath] failed"
                ]
            );
            goto END;
        }

        # parse
        $baData = json_decode($bsContent, true);
        if ($baData === false) {
            $nLog && $nLog->notice(
                [
                    "package"  => $sPackage,
                    "msg"      => "json_decode data failed",
                    "err_code" => json_last_error(),
                    "err_msg"  => json_last_error_msg()
                ]
            );
            goto END;
        }

        # result
        $mRS = $baData;
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
        return 'slime.config.json';
    }
}