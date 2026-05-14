<?php
require __DIR__ . '/lib/bootstrap.php';

$dbPath = __DIR__ . '/db.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = db();
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

require __DIR__ . '/lib/migrations_runner.php';
run_migrations();

// Seed staff with passwords
$staffMembers = [
    ['email' => 'freddy@folio.example', 'name' => 'Freddy Folio'],
    ['email' => 'alice@folio.example', 'name' => 'Alice Admin'],
    ['email' => 'bob@folio.example', 'name' => 'Bob Builder'],
    ['email' => 'carol@folio.example', 'name' => 'Carol Creator'],
    ['email' => 'dave@folio.example', 'name' => 'Dave Designer'],
];

$stmt = $pdo->prepare('INSERT INTO staff (email, name, password_hash) VALUES (?, ?, ?)');
foreach ($staffMembers as $s) {
    $stmt->execute([$s['email'], $s['name'], password_hash('password', PASSWORD_DEFAULT)]);
}

// Seed documents
$docs = [
    ['title' => 'Welcome Packet', 'body' => "Welcome to Folio!\n\nThis is the body of your welcome packet.", 'owner' => 1],
    ['title' => 'Q3 Budget', 'body' => "Q3 Budget Overview\n\nRevenue: $X\nExpenses: $Y", 'owner' => 2],
];

$stmt = $pdo->prepare('INSERT INTO documents (title, body, created_by) VALUES (?, ?, ?)');
foreach ($docs as $d) {
    $stmt->execute([$d['title'], $d['body'], $d['owner']]);
}
$docId = (int) $pdo->lastInsertId();

// Create a share for the first doc
$token = random_token();
$stmt = $pdo->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)');
$stmt->execute([1, $token, 'recipient@example.com']);

echo "\n=== Seeded Credentials ===\n";
echo "All passwords: 'password'\n\n";
foreach ($staffMembers as $i => $s) {
    echo ($i+1) . ". {$s['name']} <{$s['email']}>\n";
}
echo "\n";
echo "Admin:        http://localhost:8000/admin.php\n";
echo "Sample share: http://localhost:8000/view.php?token={$token}\n";