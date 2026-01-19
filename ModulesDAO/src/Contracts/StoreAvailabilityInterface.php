<?php

namespace Ksfraser\ModulesDAO\Contracts;

interface StoreAvailabilityInterface
{
    /**
     * Whether this store can operate in the current runtime.
     */
    public function isAvailable(): bool;
}
