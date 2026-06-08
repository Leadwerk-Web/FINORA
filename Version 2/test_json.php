<?php
$data = ['test' => ['slides' => [['title' => 'Foo \\ Bar', 'url' => 'https://example.com/foo']]]];
$json1 = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo "Original JSON: " . $json1 . "\n";
$stripped = stripslashes($json1);
echo "Stripped (What WP does without wp_slash): " . $stripped . "\n";

$decoded = json_decode($stripped, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Decoded error: " . json_last_error_msg() . "\n";
} else {
    echo "Decoded after stripslashes: " . print_r($decoded, true) . "\n";
}
