# MySQL Backup & Restore Library (Built for CodeIgniter 4)

A simple and developer-friendly way to back up and restore your MySQL database using PHP — now made to work smoothly with CodeIgniter 4.

## Table of Contents

- [Introduction](#introduction)
- [About the Project](#about-the-project)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Disclaimer](#disclaimer)
- [Contributing](#contributing)
- [Authors](#authors)
- [License](#license)
- [Copyright](#copyright)

## Introduction

Whether you're a beginner getting started or a developer who wants reliable backup features, this library is made to help you manage your database backups and restores in a simple and efficient way.

## About the Project

This lightweight PHP library makes it easy to create clean and organized backups of your MySQL database — either full backups or selected tables. You can also restore your database using a `.sql` file or a `.zip` backup with just one line of code. It’s ideal for regular backups or moving your database from one place to another.

## Features

- Back up the full database or just selected tables
- Restore from `.sql` files quickly and safely
- Export backups with readable SQL structure
- Automatically names backup files using date/time
- Optionally compress backups into `.zip` format
- Designed for use with CodeIgniter 4

## Requirements

To use this library, make sure you have:

- PHP 8.0 or higher
- CodeIgniter 4
- `ZipArchive` extensions enabled
- A working MySQL database
- Composer installed

## Installation

Install it with Composer:

```bash
composer require zaiblab/mysql-backup
```

If needed, make sure Composer knows where to find the classes:

```json
"autoload": {
    "psr-4": {
        "DatabaseBackupManager\\": "vendor/zaiblab/mysql-backup/src/"
    }
}
```

Then refresh the autoload files:

```bash
composer dump-autoload
```

## Usage

### 1. Set it up

```php
use DatabaseBackupManager\MySQLBackup;

$db = db_connect(); // CI4's database connection
$backup = new MySQLBackup($db, WRITEPATH . 'backups');
```

### 2. Back up your database

```php
// Back up everything
$info = $backup->backup();

// Back up only specific tables
$info = $backup->backup(['users', 'orders']);

// Only save table structure (no data)
$info = $backup->backup(null, false);

// Create a zipped version of the backup
$info = $backup->backup(null, true, true);

if ($info) {
    echo "Backup file created: " . $info['file_name'] . "\n";
    echo "File size: " . $info['file_size'] . " bytes\n";
}
```

### 3. Restore from a backup

```php
// Path to your backup file
$path = WRITEPATH . 'backups/backup_mydb-2025-06-21_081400.sql';

// Restore the backup
$restored = $backup->restore($path);

// Optionally: remove old tables before restoring
$restored = $backup->restore($path, true);

if ($restored) {
    echo "Database restored successfully!";
}
```

## Disclaimer

This tool is shared in good faith, but please test everything before using it in production. Every setup is different, and it's always a good idea to double-check before relying on any tool for critical tasks.

By using this library, you agree that you're responsible for any outcomes related to your data or systems.

_Last updated: June 21, 2025_

## Contributing

Suggestions, improvements, and bug reports are always welcome! Open an issue or create a pull request on [GitHub](https://github.com/zaiblab/mysql-backup).

## Authors

- **Ramazan Çetinkaya** — [@ramazancetinkaya](https://github.com/ramazancetinkaya)
- **Shahzaib** — [@zaiblab](https://github.com/zaiblab) (Maintainer for CodeIgniter 4 version)

## License

This project uses the [MIT License](LICENSE). You’re free to use, modify, and share it however you'd like.

## Copyright

© 2025 Ramazan Çetinkaya & Shahzaib. All rights reserved.
