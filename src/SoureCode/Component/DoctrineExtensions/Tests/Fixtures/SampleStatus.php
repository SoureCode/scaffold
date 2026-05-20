<?php

declare(strict_types=1);

namespace SoureCode\Component\DoctrineExtensions\Tests\Fixtures;

enum SampleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
