<?php
namespace Slime\Config;

class JsonAdaptor extends PHPConfAdaptor
{
    /**
     * @return bool
     */
    public function load()
    {
        $aData = $this->bDefault ?
            require $this->sCurrentFile :
            array_merge(require $this->sCurrentFile, require $this->sDefaultFile);

        $this->aData = json_decode($aData, true);
    }
}