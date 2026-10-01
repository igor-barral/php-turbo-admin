<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Fixtures\Models;

enum PostStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
}
