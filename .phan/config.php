<?php
/**
 * Phan configuration for WP Super Cache.
 */

return [
	'minimum_target_php_version' => '7.2',
	'target_php_version'         => '8.5',

	'backward_compatibility_checks' => false,
	'enable_class_alias_support'    => false,
	'redundant_condition_detection' => true,

	'directory_list' => [
		'.',
	],

	'file_list' => [
		__DIR__ . '/stubs/amp-stubs.php',
	],

	'exclude_analysis_directory_list' => [
		'vendor/',
		'node_modules/',
		'tests/e2e/node_modules/',
		'jetpack_vendor/',
	],

	'autoload_internal_extension_signatures' => [],

	'stubs' => [
		'vendor/php-stubs/wordpress-stubs/wordpress-stubs.php',
		'vendor/php-stubs/wp-cli-stubs/wp-cli-stubs.php',
	],

	'plugins' => [
		'AddNeverReturnTypePlugin',
		'DuplicateArrayKeyPlugin',
		'DuplicateExpressionPlugin',
		'LoopVariableReusePlugin',
		'PHPUnitNotDeadCodePlugin',
		'PregRegexCheckerPlugin',
		'RedundantAssignmentPlugin',
		'SimplifyExpressionPlugin',
		'UnreachableCodePlugin',
		'UseReturnValuePlugin',
		'UnusedSuppressionPlugin',
	],
];
