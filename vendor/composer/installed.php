<?php return array(
    'root' => array(
        'pretty_version' => 'dev-trunk',
        'version' => 'dev-trunk',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'reference' => NULL,
        'name' => 'automattic/wp-super-cache',
        'dev' => false,
    ),
    'versions' => array(
        'automattic/jetpack-device-detection' => array(
            'pretty_version' => '1.4.x-dev',
            'version' => '1.4.9999999.9999999-dev',
            'type' => 'jetpack-library',
            'install_path' => __DIR__ . '/../automattic/jetpack-device-detection',
            'aliases' => array(),
            'reference' => '7ee3b20c68666eaa451bb62c4330d0926cf1dd29',
            'dev_requirement' => false,
        ),
        'automattic/wp-super-cache' => array(
            'pretty_version' => 'dev-trunk',
            'version' => 'dev-trunk',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'reference' => NULL,
            'dev_requirement' => false,
        ),
    ),
);
