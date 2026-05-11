<?php

$root = dirname(__DIR__);
$backend = $root . DIRECTORY_SEPARATOR . 'backend';
$failed = false;
$checked = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($backend, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $file): bool {
            if ($file->isDir() && $file->getFilename() === 'vendor') {
                return false;
            }

            return true;
        }
    )
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }

    $checked++;
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        $failed = true;
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
    }
}

echo "Checked {$checked} PHP files." . PHP_EOL;
exit($failed ? 1 : 0);

