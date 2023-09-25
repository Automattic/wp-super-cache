<?php return array(
    'root' => array(
        'name' => 'automattic/wp-super-cache',
        'pretty_version' => 'dev-trunk',
        'version' => 'dev-trunk',
        'reference' => NULL,
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'automattic/jetpack-device-detection' => array(
            'pretty_version' => '1.4.27',
            'version' => '1.4.27.0',
            'reference' => 'cc2f185b159961b3bd73e798a43a469d5f09c5b5',
            'type' => 'jetpack-library',
            'install_path' => __DIR__ . '/../automattic/jetpack-device-detection',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'automattic/wp-super-cache' => array(
            'pretty_version' => 'dev-trunk',
            'version' => 'dev-trunk',
            'reference' => NULL,
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
