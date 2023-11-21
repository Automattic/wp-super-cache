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
            'pretty_version' => '2.0.0',
            'version' => '2.0.0.0',
            'reference' => '9b1a55b87dffd6e898d6a1bf03fb7740cbe9d6a8',
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
