<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use FilesystemIterator;
use PharData;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class DistributionTest extends TestCase
{
    public function testComposerArchiveKeepsPackageFilesAndExcludesGeneratedFiles(): void
    {
        $directory = sys_get_temp_dir() . '/pqr-dist-' . bin2hex(random_bytes(8));
        $source = $directory . '/source';
        mkdir($source, 0700, true);
        $included = ['.gitattributes', 'README.md', 'LICENSE', 'src/Example.php', 'docs/README.md',
            'examples/example.php', 'tests/ExampleTest.php', '.ai/rules/general.md'];
        $excluded = ['.github/workflows/tests.yml', 'vendor/example.php', 'composer.lock', '.phpunit.cache/result',
            'coverage/index.html', 'build/package.zip', 'dist/package.zip',
            '.idea/project.xml', '.vscode/settings.json', '.DS_Store', 'src/.DS_Store',
            'debug.log', 'src/debug.log'];

        try {
            copy(dirname(__DIR__) . '/composer.json', $source . '/composer.json');
            foreach (array_merge($included, $excluded) as $file) {
                if (!is_dir(dirname($source . '/' . $file))) {
                    mkdir(dirname($source . '/' . $file), 0700, true);
                }
                file_put_contents($source . '/' . $file, $file === 'composer.lock' ? "{}\n" : "Contenido de prueba\n");
            }
            $process = proc_open(['composer', '--no-plugins', '--no-scripts', 'archive',
                '--format=tar', '--dir=' . $directory, '--file=package', '--no-interaction'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $source,
                array_merge(getenv(), ['COMPOSER_DISABLE_NETWORK' => '1',
                    'COMPOSER_HOME' => $directory . '/home', 'COMPOSER_CACHE_DIR' => $directory . '/cache']));
            self::assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $output . $errors);

            $archive = new PharData($directory . '/package.tar');
            foreach (array_merge(['composer.json'], $included) as $file) {
                self::assertTrue(isset($archive[$file]), 'Falta en el paquete: ' . $file);
            }
            foreach ($excluded as $file) {
                self::assertFalse(isset($archive[$file]), 'Archivo generado incluido: ' . $file);
            }
            unset($archive);
        } finally {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,
                FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }
}
