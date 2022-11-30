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
            'pretty_version' => '1.4.22-alpha',
            'version' => '1.4.22.0-alpha',
            'type' => 'jetpack-library',
            'install_path' => __DIR__ . '/../automattic/jetpack-device-detection',
            'aliases' => array(),
            'reference' => 'fd130f9ada9905b17a10ec907583201df338f21a',
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
