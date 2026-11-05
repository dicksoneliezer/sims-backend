<?php

namespace App\Services;

class SchoolContext
{
    private ?int $schoolId = null;

    private bool $resolved = false;

    public function id(): ?int
    {
        return $this->schoolId;
    }

    public function resolved(): bool
    {
        return $this->resolved;
    }

    public function set(?int $schoolId, bool $resolved = true): void
    {
        $this->schoolId = $schoolId;
        $this->resolved = $resolved;
    }

    public function run(?int $schoolId, callable $callback): mixed
    {
        $previousId = $this->schoolId;
        $previousResolved = $this->resolved;
        $this->set($schoolId);

        try {
            return $callback();
        } finally {
            $this->set($previousId, $previousResolved);
        }
    }
}
