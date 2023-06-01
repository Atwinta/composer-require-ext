<?php

namespace Lira\ComposerExt;

use Composer\Plugin\Capability\CommandProvider;

class PluginCommandProvider implements CommandProvider
{

    public function getCommands()
    {
        return [
            new RequireExtCommand(),
        ];
    }
}
