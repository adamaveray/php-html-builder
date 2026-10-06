<?php

declare(strict_types=1);

$finder = new \PhpCsFixer\Finder()->in([__DIR__])->exclude(['node_modules', 'vendor']);

// @mago-expect analysis:non-existent-method
return \Averay\Codeformat\PhpCsFixerConfig::default($finder);
