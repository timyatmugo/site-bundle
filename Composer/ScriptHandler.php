<?php

declare(strict_types=1);

namespace Netgen\Bundle\MoreBundle\Composer;

use Composer\Script\Event;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as DistributionBundleScriptHandler;

class ScriptHandler extends DistributionBundleScriptHandler
{
    /**
     * Symlinks various project files and folders to their proper locations.
     *
     * @param $event \Composer\Script\Event
     */
    public static function installProjectSymlinks(Event $event)
    {
        $options = self::getOptions($event);
        $consoleDir = static::getConsoleDir($event, 'install project symlinks');

        static::executeCommand($event, $consoleDir, 'ngmore:symlink:project', $options['process-timeout']);
    }
}
