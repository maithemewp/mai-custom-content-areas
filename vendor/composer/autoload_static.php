<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit188ad706d04e1d9b7c3d848c66524498
{
    public static $files = array (
        '9b77ddcfb14408a32f5aaf74e0a11694' => __DIR__ . '/..' . '/yahnis-elsts/plugin-update-checker/load-v5p1.php',
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInit188ad706d04e1d9b7c3d848c66524498::$classMap;

        }, null, ClassLoader::class);
    }
}
