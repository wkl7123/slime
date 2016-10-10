<?php
namespace Slime\Config;

use Slime\Log\Logger;

Trait ConfigureFileTrait
{
    protected $aData = [];

    /**
     * @param string $sDir
     */
    public function loadDir($sDir)
    {
        $nLog = $this->getLogger();
        $sPackage = $this->getPackageName();

        if (!is_dir($sDir)) {
            $nLog->notice(
                [
                    "package" => $sPackage,
                    "msg" => "dir[$sDir] is not dir"
                ]
            );
            goto END;
        }
        if (($rDir = opendir($sDir)) === false) {
            $nLog->notice(
                [
                    "package" => $sPackage,
                    "msg" => "open dir[$sDir] failed"
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
            $this->aData = array_merge($this->aData, $this->getDataFromFile($sFilePath));
        }

        END:
    }

    /**
     * @param string $sFile
     */
    public function loadFile($sFile)
    {
        if (!file_exists($sFile)) {
            goto END;
        }
        $this->aData = array_merge($this->aData, json_decode(file_get_contents($sFile), true));

        END:
    }

    /**
     * @return Logger|null
     */
    abstract protected function getLogger();

    /**
     * @return string
     */
    abstract protected function getPackageName();

    /**
     * @param string $sFilePath
     *
     * @return string|bool
     */
    abstract protected function getDataFromFile($sFilePath);
}