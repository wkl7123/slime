<?php
namespace SlimeInterface\Config;

interface ConfigureInterface extends \ArrayAccess
{
    /**
     * @param string $sDir
     *
     * @return void
     */
    public function loadDir($sDir);

    /**
     * @param string $sFile
     *
     * @return void
     */
    public function loadFile($sFile);
}