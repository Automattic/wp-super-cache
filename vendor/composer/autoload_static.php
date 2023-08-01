<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6fe342bc02f0b440f7b3c8d8ade42286_super_cacheⓥ1_10_1_alpha
{
    public static $classMap = array (
        'Automattic\\Jetpack\\Device_Detection' => __DIR__ . '/..' . '/automattic/jetpack-device-detection/src/class-device-detection.php',
        'Automattic\\Jetpack\\Device_Detection\\User_Agent_Info' => __DIR__ . '/..' . '/automattic/jetpack-device-detection/src/class-user-agent-info.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInit6fe342bc02f0b440f7b3c8d8ade42286_super_cacheⓥ1_10_1_alpha::$classMap;

        }, null, ClassLoader::class);
    }
}
