<?php

declare(strict_types=1);

/**
 * Sync NatAphp frontend dist assets into public/nata.
 *
 * Runs on Composer post-install/post-update so assets are present after
 * installing/upgrading nataphp/core.
 */

$packageRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
// Composer runs scripts from the root project; prefer that for output paths.
// If invoked manually from another cwd, fall back to the package root.
$projectRoot = getcwd() ?: $packageRoot;

$source = $packageRoot . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'dist';
$target = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'nata';

if (!is_dir($source)) {
    fwrite(STDERR, "[sync-nata-assets] Source dist folder not found: {$source}\n");
    fwrite(STDERR, "[sync-nata-assets] Skipping.\n");
    exit(0);
}

if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
    fwrite(STDERR, "[sync-nata-assets] Failed to create target folder: {$target}\n");
    exit(1);
}

/**
 * Delete all contents inside target (but keep the directory).
 */
$targetIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($targetIterator as $item) {
    $path = $item->getPathname();
    if ($item->isDir()) {
        @rmdir($path);
        continue;
    }
    @unlink($path);
}

/**
 * Copy source dist tree into target, preserving relative paths.
 */
$sourceIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$sourceLen = strlen($source) + 1;
$copied = 0;

foreach ($sourceIterator as $item) {
    $srcPath = $item->getPathname();
    $rel = substr($srcPath, $sourceLen);
    $dstPath = $target . DIRECTORY_SEPARATOR . $rel;

    if ($item->isDir()) {
        if (!is_dir($dstPath) && !mkdir($dstPath, 0775, true) && !is_dir($dstPath)) {
            fwrite(STDERR, "[sync-nata-assets] Failed to create directory: {$dstPath}\n");
            exit(1);
        }
        continue;
    }

    $dstDir = dirname($dstPath);
    if (!is_dir($dstDir) && !mkdir($dstDir, 0775, true) && !is_dir($dstDir)) {
        fwrite(STDERR, "[sync-nata-assets] Failed to create directory: {$dstDir}\n");
        exit(1);
    }

    if (!copy($srcPath, $dstPath)) {
        fwrite(STDERR, "[sync-nata-assets] Failed to copy: {$srcPath} -> {$dstPath}\n");
        exit(1);
    }
    $copied++;
}

fwrite(STDOUT, "[sync-nata-assets] Synced {$copied} files to {$target}\n");

