<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

final class GreetingModel
{
    public function getMessage(): string
    {
        return 'Composer PSR-4 autoloading is working!';
    }
}
