<?php
namespace Slime\Config;

use Slime\Container\ContainerObject;
use SlimeInterface\Config\ConfigureInterface;

class PHPConfAdaptor extends ContainerObject implements ConfigureInterface
{
    use ConfigureTrait;

    /**
     * @param string $sDir
     */
    public function loadDir($sDir)
    {
        if (!is_dir($sDir)) {
            goto END;
        }
        if (($rDir = opendir($sDir)) === false) {
            goto END;
        }
        while (($sFileName = readdir($rDir)) !== false) {
            if (ltrim($sFileName, '.') === '') {
                continue;
            }
            $this->aData = array_merge($this->aData, (require $sDir . DIRECTORY_SEPARATOR . $sFileName));
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
        $this->aData = array_merge($this->aData, (require $sFile));

        END:
    }
}