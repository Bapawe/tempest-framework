<?php

use Rector\Config\RectorConfig;
use Tempest\Upgrade\Tempest321\AddEarlyMaintenanceCheckRector;

return static function (RectorConfig $config): void {
    $config->rule(AddEarlyMaintenanceCheckRector::class);
};
