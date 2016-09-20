<?php
namespace SlimeInterface\Config;

interface ConfigureInterface extends \ArrayAccess
{
    public function load();
}