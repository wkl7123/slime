<?php
namespace SlimeInterface\Container;

use SlimeInterface\Container\Exception\ContainerExceptionInterface;
use SlimeInterface\Container\Exception\NotFoundExceptionInterface;

/**
 * Describes the interface of a container that exposes methods to read its entries.
 */
interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for this identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($id);

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return boolean
     */
    public function has($id);

    // add by smallslime
    /**
     * @param string $id
     *
     * @return bool
     */
    public function hasData($id);

    /**
     * @param string $id
     *
     * @return bool
     */
    public function hasCallable($id);

    /**
     * @param string $id
     *
     * @return callable|null
     */
    public function getCallable($id);

    /**
     * @param string $id
     *
     * @return mixed|null
     */
    public function make($id);

    /**
     * @param string $sKey1
     * ....
     *
     * @return void
     */
    public function clearData($sKey1);
}