<?php return array(
    'root' => array(
        'name' => 'automattic/wp-super-cache',
        'pretty_version' => 'dev-trunk',
        'version' => 'dev-trunk',
        'reference' => null,
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'automattic/jetpack-device-detection' => array(
            'pretty_version' => '3.0.9',
            'version' => '3.0.9.0',
            'reference' => 'd85a430fcd1da596c27aae45bc985ce62f9bb56b',
            'type' => 'jetpack-library',
            'install_path' => __DIR__ . '/../automattic/jetpack-device-detection',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'automattic/wp-super-cache' => array(
            'pretty_version' => 'dev-trunk',
            'version' => 'dev-trunk',
            'reference' => null,
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
