<?php
require dirname(__DIR__).'/system/common/runtime_requirements.php';

$cases = [
    80299 => false,
    80300 => true,
    80400 => true,
    80500 => true,
    80600 => false,
];
foreach ($cases as $version => $expected) {
    if (cms_php_version_supported($version) !== $expected) {
        throw new RuntimeException('PHP support boundary mismatch at PHP_VERSION_ID '.$version);
    }
}
if (cms_php_version_requirement_label() !== 'PHP 8.3、8.4 或 8.5') {
    throw new RuntimeException('PHP support label does not match the accepted version range.');
}
echo 'PHP runtime support regression passed: PHP 8.3, 8.4 and 8.5 accepted; adjacent versions rejected. '.PHP_VERSION."\n";
