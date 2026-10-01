<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Validation;

use PhpTurbo\Admin\Core\Exception\AdminException;

final class InvalidValue extends \InvalidArgumentException implements AdminException
{
}
