<?php
// save_wish.php
// Reads form data (POST) and saves into data/wishes.json

header('Content-Type: text/html; charset=utf-8');

function clean($s) {
  $s = trim((string)$s);
  $s = str_replace(["\r\n", "\r"], "\n", $s);
  return $s;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo "Method not allowed.";
  exit;
}

$wish = clean($_POST['wish'] ?? '');
$category = clean($_POST['category'] ?? '');
$timeframe = clean($_POST['timeframe'] ?? '');
$name = clean($_POST['name'] ?? 'Anonymous');
$email = clean($_POST['email'] ?? '');
$intensity = (int)($_POST['intensity'] ?? 0);

// Basic validation
if (mb_strlen($wish) < 5 || mb_strlen($wish) > 500) {
  http_response_code(400);
  echo "Wish must be between 5 and 500 characters.";
  exit;
}
if ($category === '' || $timeframe === '') {
  http_response_code(400);
  echo "Category and timeframe are required.";
  exit;
}
if ($intensity < 1 || $intensity > 10) {
  http_response_code(400);
  echo "Intensity must be between 1 and 10.";
  exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo "Invalid email.";
  exit;
}

$entry = [
  'id' => bin2hex(random_bytes(8)),
  'wish' => $wish,
  'category' => $category,
  'timeframe' => $timeframe,
  'intensity' => $intensity,
  'name' => ($name === '' ? 'Anonymous' : $name),
  'email' => $email,
  'created_at' => date('c'),
];

// Ensure data folder exists
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
  mkdir($dataDir, 0755, true);
}

$file = $dataDir . '/wishes.json';

// Load existing JSON safely
$all = [];
if (file_exists($file)) {
  $raw = file_get_contents($file);
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) $all = $decoded;
}

// Append new entry
$all[] = $entry;

// Save with file lock to prevent corruption
$json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
  http_response_code(500);
  echo "Failed to encode JSON.";
  exit;
}

$fp = fopen($file, 'c+');
if (!$fp) {
  http_response_code(500);
  echo "Cannot open data file.";
  exit;
}
if (!flock($fp, LOCK_EX)) {
  http_response_code(500);
  echo "Cannot lock data file.";
  exit;
}
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, $json);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// Redirect back (or show success)
header("Location: index.html?success=1");
exit;
