#!/usr/bin/env php
<?php
/**
 * Builds the distributable plugin archive.
 *
 * Usage:
 *   php bin/build.php                          # uses the version from the plugin header
 *   php bin/build.php --version=1.7.42         # stamps a release version
 *   php bin/build.php --out=/tmp/out --no-zip  # staging directory only
 *
 * Output: <out>/print-options.zip with the plugin folder at its root, so
 * WordPress installs it as wp-content/plugins/print-options/.
 */

$root    = dirname(__DIR__);
$options = getopt('', array('version:', 'out:', 'no-zip'));

$outDir = isset($options['out']) ? (string) $options['out'] : $root . '/dist';
if ('' === $outDir || '/' !== substr($outDir, 0, 1)) {
    $outDir = $root . '/' . ltrim($outDir, '/');
}

$mainFile = $root . '/print-options.php';
if (!is_readable($mainFile)) {
    fwrite(STDERR, "Cannot read print-options.php\n");
    exit(1);
}

$source  = file_get_contents($mainFile);
$version = isset($options['version']) ? trim((string) $options['version']) : '';
if ('' === $version) {
    if (preg_match('/^ \* Version:\s*([0-9][0-9A-Za-z.\-]*)/m', $source, $matches)) {
        $version = $matches[1];
    } else {
        $version = '1.0.0';
    }
}

$exclude = array(
    '.git',
    '.github',
    '.gitignore',
    '.DS_Store',
    'bin',
    'docs',
    'tests',
    'dist',
    'node_modules',
    'README.md',
    'AGENTS.md',
);

$stage = rtrim(sys_get_temp_dir(), '/') . '/print-options-build-' . getmypid() . '-' . time() . '/print-options';

echo "Building print-options {$version}\n";

if (!mkdir($stage, 0777, true) && !is_dir($stage)) {
    fwrite(STDERR, "Cannot create {$stage}\n");
    exit(1);
}

copy_tree($root, $stage, $exclude);

// Stamp the version into the staged copy only: the repository keeps the base
// version so that CI releases never create merge conflicts.
$stagedMain = $stage . '/print-options.php';
$contents   = file_get_contents($stagedMain);
$contents   = preg_replace('/^ \* Version:.*$/m', ' * Version: ' . $version, $contents, 1);
$contents   = preg_replace("/define\('POV_VERSION',\s*'[^']*'\);/", "define('POV_VERSION', '" . $version . "');", $contents, 1);
file_put_contents($stagedMain, $contents);

$readme = $stage . '/readme.txt';
if (is_readable($readme)) {
    $contents = file_get_contents($readme);
    $contents = preg_replace('/^Stable tag:.*$/m', 'Stable tag: ' . $version, $contents, 1);
    file_put_contents($readme, $contents);
}

$zipPath = rtrim($outDir, '/') . '/print-options.zip';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
if (isset($options['no-zip'])) {
    echo "Staged in {$stage}\n";
    exit(0);
}

if (file_exists($zipPath)) {
    unlink($zipPath);
}

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if (true !== $zip->open($zipPath, ZipArchive::CREATE)) {
        fwrite(STDERR, "Cannot create {$zipPath}\n");
        exit(1);
    }
    add_to_zip($zip, $stage, 'print-options');
    $zip->close();
} else {
    $cwd = getcwd();
    chdir(dirname($stage));
    $command = 'zip -rq ' . escapeshellarg($zipPath) . ' print-options -x ' . escapeshellarg('*/.DS_Store');
    exec($command, $output, $code);
    chdir($cwd);
    if (0 !== $code) {
        fwrite(STDERR, "zip failed with code {$code}\n");
        exit(1);
    }
}

echo "Created {$zipPath} (" . size_format_local(filesize($zipPath)) . ")\n";

// Best effort cleanup of the staging copy.
$tempRoot = dirname($stage);
if (is_dir($tempRoot)) {
    remove_tree($tempRoot);
}

/**
 * Recursively copy $src into $dst skipping top-level entries in $exclude.
 */
function copy_tree($src, $dst, array $exclude) {
    $items = scandir($src);
    foreach ($items as $item) {
        if ('.' === $item || '..' === $item || in_array($item, $exclude, true)) {
            continue;
        }
        $from = $src . '/' . $item;
        $to   = $dst . '/' . $item;
        if (is_dir($from)) {
            if (!is_dir($to)) {
                mkdir($to, 0777, true);
            }
            copy_tree($from, $to, array());
        } else {
            copy($from, $to);
        }
    }
}

function remove_tree($dir) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ('.' === $item || '..' === $item) {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            remove_tree($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function add_to_zip(ZipArchive $zip, $dir, $prefix) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ('.' === $item || '..' === $item) {
            continue;
        }
        $path = $dir . '/' . $item;
        $name = $prefix . '/' . $item;
        if (is_dir($path)) {
            $zip->addEmptyDir($name);
            add_to_zip($zip, $path, $name);
        } else {
            $zip->addFile($path, $name);
        }
    }
}

function size_format_local($bytes) {
    $units = array('B', 'KB', 'MB');
    $index = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }
    return round($value, 1) . ' ' . $units[$index];
}
