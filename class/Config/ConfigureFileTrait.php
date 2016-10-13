<?php
namespace Slime\Config;

use Slime\Log\Logger;

Trait ConfigureFileTrait
{
    use ConfigureTrait;

    /**
     * @param string $sDir
     */
    public function loadDir($sDir)
    {
        $nLog     = $this->getLogger();
        $sPackage = $this->getPackageName();

        if (!is_dir($sDir)) {
            $nLog && $nLog->notice(
                [
                    "package" => $sPackage,
                    "msg"     => "dir[$sDir] is not dir"
                ]
            );
            goto END;
        }
        if (($rDir = opendir($sDir)) === false) {
            $nLog && $nLog->notice(
                [
                    "package" => $sPackage,
                    "msg"     => "open dir[$sDir] failed"
                ]
            );
            goto END;
        }

        while (($sFileName = readdir($rDir)) !== false) {
            if ($sFileName[0] === '.') {
                continue;
            }
            $sFilePath = $sDir . DIRECTORY_SEPARATOR . $sFileName;
            if (!is_file($sFilePath)) {
                continue;
            }
            $baData = $this->getDataFromFile($sFilePath);
            if ($baData === false) {
                $nLog && $nLog->notice(
                    [
                        "package" => $sPackage,
                        "msg"     => "data format error in file[$sFileName] when load dir[$sDir]"
                    ]
                );
                continue;
            }

            $this->aData = array_merge($this->aData, $baData);
        }

        END:
    }

    /**
     * @param string $sFile
     */
    public function loadFile($sFile)
    {
        $nLog     = $this->getLogger();
        $sPackage = $this->getPackageName();

        if (!file_exists($sFile)) {
            goto END;
        }
        $baData = json_decode(file_get_contents($sFile), true);
        if ($baData === false) {
            $nLog && $nLog->notice(
                [
                    "package" => $sPackage,
                    "msg"     => "data format error in file[$sFile]"
                ]
            );
            goto END;
        }
        $this->aData = array_merge($this->aData, $baData);

        END:
    }

    /**
     * @return Logger|null
     */
    abstract protected function getLogger();

    /**
     * @param string $sFilePath
     *
     * @return string|bool
     */
    abstract protected function getDataFromFile($sFilePath);

    /**
     * name for log
     *
     * @return string
     */
    abstract protected function getPackageName();
}