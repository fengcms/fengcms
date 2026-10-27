<?php

defined('TPL_INCLUDE') or die('Restricted access');

/** Validated content route parameters; authorization remains in the controller. */
final class CmsContentRequest
{
    private function __construct(
        public readonly string $project,
        public readonly int $id,
    ) {}

    public static function fromInput(array $input): self
    {
        $project = $input['project'] ?? null;
        $rawId = $input['id'] ?? null;
        if (!is_string($project) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,29}$/D', $project) ||
            !is_string($rawId) || !preg_match('/^[0-9]{1,10}$/D', $rawId) ||
            (int)$rawId < 1 || (int)$rawId > 2147483647) {
            throw new InvalidArgumentException('内容参数不正确。');
        }
        return new self($project, (int)$rawId);
    }
}
