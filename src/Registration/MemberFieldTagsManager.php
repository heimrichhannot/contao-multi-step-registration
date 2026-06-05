<?php

declare(strict_types=1);

namespace HeimrichHannot\MultiStepRegistration\Registration;

use Codefog\TagsBundle\Manager\ManagerInterface;
use Codefog\TagsBundle\Tag;

class MemberFieldTagsManager implements ManagerInterface
{
    public function __construct(private readonly EditableMemberFieldProvider $fieldProvider)
    {
    }

    public function getAllTags(?string $source = null): array
    {
        return $this->createTags($this->fieldProvider->getOptions());
    }

    public function getFilteredTags(array $values, ?string $source = null): array
    {
        $fields = $this->fieldProvider->getOptions();
        $filtered = [];

        foreach ($values as $value) {
            if (!\is_string($value) || !isset($fields[$value])) {
                continue;
            }

            $filtered[$value] = $fields[$value];
        }

        return $this->createTags($filtered);
    }

    /**
     * @param array<string, string> $fields
     *
     * @return list<Tag>
     */
    private function createTags(array $fields): array
    {
        $tags = [];

        foreach ($fields as $field => $label) {
            $tags[] = new Tag($field, $label);
        }

        return $tags;
    }
}
