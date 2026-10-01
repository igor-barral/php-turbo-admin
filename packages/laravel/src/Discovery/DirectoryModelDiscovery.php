<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Discovery;

use Illuminate\Database\Eloquent\Model;
use PhpTurbo\Admin\Core\Discovery\ModelDiscovery;
use PhpTurbo\Admin\Laravel\HasAdmin;

/**
 * Procura models com o trait HasAdmin nos diretórios configurados (mapa namespace => diretório,
 * no mesmo formato do PSR-4) e acrescenta as classes listadas explicitamente.
 *
 * Nunca varre vendor/: models de pacotes entram estendendo a classe original no app.
 */
final readonly class DirectoryModelDiscovery implements ModelDiscovery
{
    /**
     * @param array<string, string> $paths   namespace => diretório
     * @param list<string>          $classes classes adicionais (validadas na descoberta)
     */
    public function __construct(private array $paths, private array $classes = [])
    {
    }

    public function discover(): array
    {
        $found = [];

        foreach ($this->paths as $namespace => $directory) {
            foreach ($this->scan(rtrim($namespace, '\\') . '\\', rtrim($directory, '/\\')) as $class) {
                $found[$class] = true;
            }
        }

        foreach ($this->classes as $class) {
            if ($this->isAdminModel($class)) {
                $found[$class] = true;
            }
        }

        $classes = array_keys($found);
        sort($classes);

        return $classes;
    }

    /** @return iterable<class-string> */
    private function scan(string $namespace, string $directory): iterable
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
            $class = $namespace . str_replace(['/', '\\'], '\\', $relative);

            if ($this->isAdminModel($class)) {
                yield $class;
            }
        }
    }

    /** @phpstan-assert-if-true class-string $class */
    private function isAdminModel(string $class): bool
    {
        if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
            return false;
        }

        if ((new \ReflectionClass($class))->isAbstract()) {
            return false;
        }

        return in_array(HasAdmin::class, class_uses_recursive($class), true);
    }
}
