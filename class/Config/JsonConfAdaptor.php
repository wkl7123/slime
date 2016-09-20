<?php
namespace Slime\Config;

class JsonConfAdaptor extends PHPConfAdaptor
{
    public function load()
    {
        $aData = $this->bDefault ?
            json_decode(require $this->sCurrentFile, true) :
            array_merge(
                json_decode(require $this->sCurrentFile, true),
                json_decode(require $this->sDefaultFile, true)
            );

        $this->aData = json_decode($aData, true);
    }
}