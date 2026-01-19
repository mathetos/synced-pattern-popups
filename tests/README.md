# Testing Setup Guide

This directory contains PHPUnit and Jest tests for the Synced Pattern Popups plugin.

## Prerequisites

### PHP Tests

PHPUnit tests require the WordPress test suite. You have several options:

#### Option 1: Use wp-env (Recommended)

`wp-env` provides a containerized WordPress environment with all dependencies:

```bash
# Install wp-env globally
npm install -g @wordpress/env

# Start the environment
wp-env start

# Run tests
cd wp-content/plugins/synced-pattern-popups
vendor/bin/phpunit
```

#### Option 2: Install WordPress Test Suite

1. Clone WordPress develop repository:
```bash
git clone https://github.com/WordPress/wordpress-develop.git /path/to/wordpress-develop
```

2. Set environment variable:
```bash
# Windows PowerShell
$env:WP_TESTS_DIR = "C:\path\to\wordpress-develop\tests\phpunit"

# Linux/Mac
export WP_TESTS_DIR=/path/to/wordpress-develop/tests/phpunit
```

3. Run tests:
```bash
vendor/bin/phpunit
```

#### Option 3: Use Existing WordPress Installation

If you have WordPress installed locally, the bootstrap will automatically detect it if tests are in:
- `wp-content/tests/phpunit`
- WordPress root `/tests/phpunit`

### JavaScript Tests

JavaScript tests use Jest and require Node.js:

```bash
# Install dependencies
npm install

# Run tests
npm run test:js

# Run tests in watch mode
npm run test:js:watch

# Run tests with coverage
npm run test:js:coverage
```

## Running Tests

### All PHP Tests
```bash
vendor/bin/phpunit
```

### Specific Test File
```bash
vendor/bin/phpunit tests/phpunit/class-sppopups-settings-contract-test.php
```

### Contract Tests Only
```bash
vendor/bin/phpunit tests/phpunit/class-sppopups-settings-contract-test.php
```

### All JavaScript Tests
```bash
npm run test:js
```

## Test Structure

- `phpunit/` - PHP unit and integration tests
  - `class-sppopups-settings-contract-test.php` - Contract tests locking default values
  - `class-sppopups-settings-test.php` - Unit tests for sanitization and inheritance
  - `class-sppopups-defaults-integration-test.php` - Integration tests for defaults flow
- `js/` - JavaScript unit tests
  - `modal-defaults.test.js` - Tests for modal defaults application

## Troubleshooting

### "WordPress test suite not found" Error

The bootstrap will show helpful error messages with installation options. Make sure:
1. WordPress test suite is installed (see options above)
2. `WP_TESTS_DIR` environment variable is set (if using custom location)
3. Test suite is in one of the auto-detected locations

### Tests Fail with Database Errors

Ensure your test database is configured correctly in `phpunit.xml.dist`:
- Update database credentials in the `<php>` section
- Make sure the test database exists and is accessible
