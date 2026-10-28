<?php

function cms_php_version_supported(int $versionId): bool
{
    return $versionId >= 80300 && $versionId < 80600;
}

function cms_php_version_requirement_label(): string
{
    return 'PHP 8.3、8.4 或 8.5';
}
