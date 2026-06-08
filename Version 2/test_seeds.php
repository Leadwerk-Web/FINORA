<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__) . '/wp-load.php';

$manager = new Leadwerk_Translation_Seed_Manager();
$seed = $manager->get_page_seed('finora-home', 'en');
echo "Has seed for finora-home? " . ($manager->has_page_seed('finora-home', 'en') ? 'Yes' : 'No') . "\n";
$paths = $seed['paths'] ?? [];
$keys = array_keys($paths);
echo "Total paths: " . count($keys) . "\n";
echo "First 5 keys:\n" . print_r(array_slice($keys, 0, 5), true) . "\n";
