<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite7bbc9e18f4d13c4675486b0c8f56260
{
    public static $prefixLengthsPsr4 = array (
        'y' => 
        array (
            'ysf\\' => 4,
        ),
        'p' => 
        array (
            'phpseclib\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ysf\\' => 
        array (
            0 => __DIR__ . '/..' . '/ysf',
        ),
        'phpseclib\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite7bbc9e18f4d13c4675486b0c8f56260::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite7bbc9e18f4d13c4675486b0c8f56260::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
