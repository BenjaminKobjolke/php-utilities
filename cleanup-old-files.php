<?php
/**
 * Cleanup script for old files and empty folders
 *
 * - Deletes all empty folders
 * - Deletes all files older than 12 months
 * - Cleans up newly empty folders after file deletion
 */

// ============================================================================
// CONFIGURATION
// ============================================================================


// use it like url....cleanup-old-files.php?pass=PASSWORD
$password = 'PASSWORD';

$folders = [
	'data/folder1',
	'data/folder2',
    // Add more folders here as needed
];

// Set to false to actually delete files/folders
$dryRun = true;

// Age threshold in months
$maxAgeMonths = 12;

// ============================================================================
// SCRIPT
// ============================================================================

header('Content-Type: text/html; charset=utf-8');

// Password check
if (!isset($_GET['pass']) || $_GET['pass'] !== $password) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head>';
    echo '<body><h1>403 - Access Denied</h1></body></html>';
    exit;
}

echo '<!DOCTYPE html><html><head><title>Cleanup Script</title>';
echo '<style>body { font-family: monospace; font-size: 14px; padding: 20px; }</style>';
echo '</head><body>';

$cutoffDate = strtotime("-{$maxAgeMonths} months");
$stats = [
    'emptyFoldersDeleted' => 0,
    'oldFilesDeleted' => 0,
    'bytesFreed' => 0,
];

out("==========================================================");
out("<b>Cleanup Script</b>");
out("==========================================================");
out("Mode: " . ($dryRun ? "<span style='color:blue'>DRY RUN (no changes will be made)</span>" : "<span style='color:red'>LIVE (files will be deleted!)</span>"));
out("Cutoff date: " . date('Y-m-d H:i:s', $cutoffDate));
out("==========================================================");
out("");

foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        out("<span style='color:orange'>[WARNING] Folder does not exist: " . htmlspecialchars($folder) . "</span>");
        out("");
        continue;
    }

    out("<b>Processing: " . htmlspecialchars($folder) . "</b>");
    out(str_repeat('-', 60));

    // Step 1: Delete empty folders first
    out("");
    out("<b>[Step 1]</b> Removing empty folders...");
    $emptyCount1 = deleteEmptyFolders($folder, $dryRun);
    $stats['emptyFoldersDeleted'] += $emptyCount1;

    // Step 2: Delete old files
    out("");
    out("<b>[Step 2]</b> Removing files older than {$maxAgeMonths} months...");
    $fileStats = deleteOldFiles($folder, $cutoffDate, $dryRun);
    $stats['oldFilesDeleted'] += $fileStats['count'];
    $stats['bytesFreed'] += $fileStats['bytes'];

    // Step 3: Clean up any newly empty folders
    out("");
    out("<b>[Step 3]</b> Removing newly empty folders...");
    $emptyCount2 = deleteEmptyFolders($folder, $dryRun);
    $stats['emptyFoldersDeleted'] += $emptyCount2;

    out("");
}

// Summary
out("==========================================================");
out("<b>SUMMARY" . ($dryRun ? " (DRY RUN)" : "") . "</b>");
out("==========================================================");
out("Empty folders " . ($dryRun ? "to delete" : "deleted") . ": <b>{$stats['emptyFoldersDeleted']}</b>");
out("Old files " . ($dryRun ? "to delete" : "deleted") . ": <b>{$stats['oldFilesDeleted']}</b>");
out("Space " . ($dryRun ? "to free" : "freed") . ": <b>" . formatBytes($stats['bytesFreed']) . "</b>");
out("==========================================================");

if ($dryRun) {
    out("");
    out("<span style='color:blue'>This was a DRY RUN. Set \$dryRun = false to actually delete files.</span>");
}

echo '</body></html>';

// ============================================================================
// FUNCTIONS
// ============================================================================

/**
 * Output a line with HTML line break
 */
function out(string $text): void {
    echo $text . "<br>\n";
    flush();
}

/**
 * Recursively delete empty folders
 */
function deleteEmptyFolders(string $path, bool $dryRun): int {
    $deletedCount = 0;

    if (!is_dir($path)) {
        return 0;
    }

    $items = scandir($path);
    $items = array_diff($items, ['.', '..']);

    // First, recurse into subdirectories
    foreach ($items as $item) {
        $itemPath = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            $deletedCount += deleteEmptyFolders($itemPath, $dryRun);
        }
    }

    // Re-scan after recursive deletion
    $items = scandir($path);
    $items = array_diff($items, ['.', '..']);

    // Check if folder is now empty
    if (count($items) === 0) {
        out("&nbsp;&nbsp;<span style='color:gray'>[EMPTY]</span> " . htmlspecialchars($path));
        if (!$dryRun) {
            if (rmdir($path)) {
                $deletedCount++;
            } else {
                out("&nbsp;&nbsp;<span style='color:red'>[ERROR]</span> Failed to delete: " . htmlspecialchars($path));
            }
        } else {
            $deletedCount++;
        }
    }

    return $deletedCount;
}

/**
 * Recursively delete files older than cutoff date
 */
function deleteOldFiles(string $path, int $cutoffDate, bool $dryRun): array {
    $result = ['count' => 0, 'bytes' => 0];

    if (!is_dir($path)) {
        return $result;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $mtime = $file->getMTime();

            if ($mtime < $cutoffDate) {
                $filePath = $file->getPathname();
                $fileSize = $file->getSize();
                $fileDate = date('Y-m-d', $mtime);

                out("&nbsp;&nbsp;<span style='color:orange'>[OLD]</span> " . htmlspecialchars($filePath) . " (modified: {$fileDate}, size: " . formatBytes($fileSize) . ")");

                if (!$dryRun) {
                    if (unlink($filePath)) {
                        $result['count']++;
                        $result['bytes'] += $fileSize;
                    } else {
                        out("&nbsp;&nbsp;<span style='color:red'>[ERROR]</span> Failed to delete: " . htmlspecialchars($filePath));
                    }
                } else {
                    $result['count']++;
                    $result['bytes'] += $fileSize;
                }
            }
        }
    }

    return $result;
}

/**
 * Format bytes to human readable format
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}
