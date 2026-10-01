<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Core\Data;

use PhpTurbo\Admin\Core\Exception\AdminException;

/** Violação de restrição de unicidade/integridade detectada pelo banco. */
final class ConflictException extends \RuntimeException implements AdminException
{
}
