<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentationTest extends TestCase
{
    public static function programs(): iterable
    {
        yield 'tipo' => ['README.md', 'por-tipo', "2026-11-03T23:59:59.999999-05:00\nCierre pendiente: no\n"];
        yield 'reglas' => ['README.md', 'reglas-dc', "2026-10-19T23:59:59.999999-05:00\n"];
        yield 'suspensión' => ['README.md', 'suspension-cerrada', "2026-11-10T23:59:59.999999-05:00\n"];
        yield 'error capturado' => ['README.md', 'error-fecha', "FECHA_RADICACION_IMPOSIBLE\nLa fecha u hora indicada no existe.\n"];
        yield 'migración' => ['docs/migracion.md', 'migracion', "2026-11-10T23:59:59.999999-05:00\n"];
    }

    #[DataProvider('programs')]
    public function testDocumentedProgramRunsIndependently(string $document, string $id, string $expected): void
    {
        $root = dirname(__DIR__);
        $markdown = file_get_contents($root . '/' . $document);
        self::assertSame(1, preg_match('/<!-- ejemplo-ejecutable: ' . preg_quote($id, '/') . ' -->\s*```php\n(.*?)\n```/s', $markdown, $match));
        $directory = sys_get_temp_dir() . '/pqr-doc-' . bin2hex(random_bytes(8));
        mkdir($directory . '/vendor', 0700, true);
        try {
            // Solo prepara el autoload del entorno de prueba; el programa se ejecuta sin modificarlo.
            file_put_contents($directory . '/vendor/autoload.php', '<?php return require ' . var_export($root . '/vendor/autoload.php', true) . ';');
            file_put_contents($directory . '/consulta.php', $match[1]);
            $process = proc_open([PHP_BINARY, '-d', 'date.timezone=Pacific/Apia', $directory . '/consulta.php'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $directory);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
            self::assertSame('', $errors);
            self::assertSame($expected, $output);
        } finally {
            foreach (['consulta.php', 'vendor/autoload.php'] as $file) {
                if (is_file($directory . '/' . $file)) {
                    unlink($directory . '/' . $file);
                }
            }
            rmdir($directory . '/vendor');
            rmdir($directory);
        }
    }
}
