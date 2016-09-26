<?php
namespace Slime\Config;

class JsonConfAdaptor extends PHPConfAdaptor
{
    public function load()
    {
        $this->aData = $this->bDefault ?
            json_decode(file_get_contents($this->sCurrentFile), true) :
            array_merge(
                json_decode(file_get_contents($this->sCurrentFile), true),
                json_decode(file_get_contents($this->sDefaultFile), true)
            );
    }
}