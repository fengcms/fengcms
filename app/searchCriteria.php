<?php

defined('TPL_INCLUDE') or die('Restricted access');

/** Syntactically validated search input; route authorization stays in the controller. */
final class CmsSearchCriteria
{
    private function __construct(
        public readonly string $tags,
        public readonly string $project,
        public readonly int $page,
    ) {}

    public static function fromInput(array $input, mixed $rawPage): self
    {
        $tags = $input['tags'] ?? '';
        $project = $input['project'] ?? '';
        if (!is_string($tags) || !is_string($project) || mb_strlen($tags, 'UTF-8') > 80 ||
            ($project !== '' && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $project))) {
            throw new InvalidArgumentException('搜索参数不正确。');
        }
        if (!is_scalar($rawPage) || !preg_match('/^[1-9][0-9]{0,2}$/D', (string)$rawPage) || (int)$rawPage > 100) {
            throw new InvalidArgumentException('搜索页码不正确。');
        }
        return new self($tags, $project, (int)$rawPage);
    }
}
