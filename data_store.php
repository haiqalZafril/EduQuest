<?php
// Simple JSON-based data storage helpers for EduQuest LMS.

const EDUQUEST_DATA_DIR = __DIR__ . '/data';

/**
 * Ensure the data directory exists.
 */
function eq_ensure_data_dir(): void {
    if (!is_dir(EDUQUEST_DATA_DIR)) {
        mkdir(EDUQUEST_DATA_DIR, 0777, true);
    }
}

/**
 * Load an array from a JSON file in the data directory.
 *
 * @param string $name Filename without extension.
 * @return array
 */
function eq_load_data(string $name): array {
    eq_ensure_data_dir();
    $file = EDUQUEST_DATA_DIR . '/' . $name . '.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Save an array as JSON into the data directory.
 *
 * @param string $name Filename without extension.
 * @param array $data
 * @return void
 */
function eq_save_data(string $name, array $data): void {
    eq_ensure_data_dir();
    $file = EDUQUEST_DATA_DIR . '/' . $name . '.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Generate a simple incremental ID based on existing items.
 *
 * @param array $items
 * @param string $key
 * @return int
 */
function eq_next_id(array $items, string $key = 'id'): int {
    $max = 0;
    foreach ($items as $item) {
        if (isset($item[$key]) && (int)$item[$key] > $max) {
            $max = (int)$item[$key];
        }
    }
    return $max + 1;
}

/**
 * Basic helper to escape HTML.
 */
function eq_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


