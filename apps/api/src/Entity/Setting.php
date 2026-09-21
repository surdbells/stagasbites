<?php

declare(strict_types=1);

namespace StagasBites\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'settings')]
class Setting
{
    #[ORM\Id]
    #[ORM\Column(name: 'key', type: 'string', length: 80)]
    private string $key;

    #[ORM\Column(type: 'json')]
    private mixed $value;

    public function __construct(string $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
