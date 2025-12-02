# PHP Utilities

## cleanup-old-files.php

A PHP script for cleaning up old files and empty folders. Designed to run in a browser.

### Features

- Deletes all empty folders in configured directories
- Deletes all files older than a configurable age (default: 12 months)
- Cleans up folders that become empty after file deletion
- Dry run mode to preview changes before executing
- Password protection
- HTML output with color-coded status messages

### Configuration

Edit the configuration section at the top of the file:

```php
// Password for access control
$password = 'PASSWORD';

// Folders to clean up
$folders = [
    'data/folder1',
    'data/folder2',
    // Add more folders here as needed
];

// Set to false to actually delete files/folders
$dryRun = true;

// Age threshold in months
$maxAgeMonths = 12;
```

### Usage

1. Set your password in the `$password` variable
2. Add folder paths to the `$folders` array
3. Access via browser with password parameter:
   ```
   https://your-domain.com/cleanup-old-files.php?pass=PASSWORD
   ```

### Dry Run Mode

By default, `$dryRun = true` which only lists what would be deleted without making any changes. Set `$dryRun = false` to actually delete files and folders.

### Output

The script displays:
- **[EMPTY]** - Empty folders found/deleted (gray)
- **[OLD]** - Files older than threshold found/deleted (orange)
- **[ERROR]** - Failed operations (red)
- **[WARNING]** - Missing folders (orange)

A summary at the end shows total counts and space freed.
