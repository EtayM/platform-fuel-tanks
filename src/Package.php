<?php

namespace Enjin\Platform\FuelTanks;

use Enjin\Platform\Package as CorePackage;

class Package extends CorePackage
{
    /**
     * Get any routes that have been set up for this package.
     */
    public static function getPackageRoutes(): array
    {
        return [];
    }
}
