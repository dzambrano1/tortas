<?php
function getDatabasePath(): string {
    return __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'images.db';
}

function ensureDataDirectory(): void {
    $dir = dirname(getDatabasePath());
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function getDb(): PDO {
    static $db = null;

    if ($db !== null) {
        return $db;
    }

    ensureDataDirectory();
    $db = new PDO('sqlite:' . getDatabasePath());
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec(
        'CREATE TABLE IF NOT EXISTS images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL UNIQUE,
            slug TEXT UNIQUE,
            title TEXT,
            description TEXT,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    ensureSlugColumn($db);
    return $db;
}

function ensureSlugColumn(PDO $db): void {
    $columns = [];
    $stmt = $db->query("PRAGMA table_info('images')");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[] = $column['name'];
    }

    if (!in_array('slug', $columns, true)) {
        $db->exec('ALTER TABLE images ADD COLUMN slug TEXT');
    }

    $rows = $db->query('SELECT id, filename, slug, title FROM images')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (empty($row['slug'])) {
            $slug = generateSlug($row['title'] ?: pathinfo($row['filename'], PATHINFO_FILENAME), $db, (int)$row['id']);
            $update = $db->prepare('UPDATE images SET slug = :slug WHERE id = :id');
            $update->execute([':slug' => $slug, ':id' => $row['id']]);
        }
    }
}

function generateSlug(string $text, PDO $db, int $excludeId = 0): string {
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($text)));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'imagen';
    }

    $baseSlug = $slug;
    $suffix = 1;
    while (slugExists($slug, $db, $excludeId)) {
        $slug = $baseSlug . '-' . $suffix++;
    }

    return $slug;
}

function slugExists(string $slug, PDO $db, int $excludeId = 0): bool {
    $query = 'SELECT id FROM images WHERE slug = :slug';
    if ($excludeId > 0) {
        $query .= ' AND id != :excludeId';
    }
    $stmt = $db->prepare($query);
    $params = [':slug' => $slug];
    if ($excludeId > 0) {
        $params[':excludeId'] = $excludeId;
    }
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function syncFolderImages(): void {
    $db = getDb();
    $files = [];
    $patterns = ['*.{png,PNG}', '*.{jpg,JPG}', '*.{jpeg,JPEG}', '*.{gif,GIF}', '*.{webp,WEBP}'];

    $dir = __DIR__ . '/image';
    foreach ($patterns as $pattern) {
        $paths = glob($dir . '/' . $pattern, GLOB_BRACE);
        if (is_array($paths)) {
            foreach ($paths as $filePath) {
                if (is_file($filePath)) {
                    $files[] = basename($filePath);
                }
            }
        }
    }

    $existing = [];
    foreach ($db->query('SELECT id, filename, active, slug, title FROM images') as $row) {
        $existing[$row['filename']] = $row;
    }

    $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    $insert = $db->prepare('INSERT INTO images (filename, slug, title, description, active, created_at, updated_at) VALUES (:filename, :slug, :title, :description, 1, :created_at, :updated_at)');
    $updateActive = $db->prepare('UPDATE images SET active = 1, updated_at = :updated_at WHERE filename = :filename');
    $updateSlug = $db->prepare('UPDATE images SET slug = :slug, updated_at = :updated_at WHERE id = :id');
    $deactivate = $db->prepare('UPDATE images SET active = 0, updated_at = :updated_at WHERE filename = :filename');

    foreach ($files as $filename) {
        if (!array_key_exists($filename, $existing)) {
            $slug = generateSlug(pathinfo($filename, PATHINFO_FILENAME), $db);
            $insert->execute([
                ':filename' => $filename,
                ':slug' => $slug,
                ':title' => pathinfo($filename, PATHINFO_FILENAME),
                ':description' => '',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } else {
            $row = $existing[$filename];
            if (empty($row['slug'])) {
                $slug = generateSlug($row['title'] ?: pathinfo($filename, PATHINFO_FILENAME), $db, (int)$row['id']);
                $updateSlug->execute([':slug' => $slug, ':updated_at' => $now, ':id' => $row['id']]);
            }
            if ((int)$row['active'] === 0) {
                $updateActive->execute([':updated_at' => $now, ':filename' => $filename]);
            }
        }
    }

    foreach ($existing as $filename => $row) {
        if (!in_array($filename, $files, true) && (int)$row['active'] === 1) {
            $deactivate->execute([':updated_at' => $now, ':filename' => $filename]);
        }
    }
}

function getImageRecords(bool $onlyActive = true): array {
    $db = getDb();
    if ($onlyActive) {
        $stmt = $db->prepare('SELECT * FROM images WHERE active = 1 ORDER BY title, filename');
        $stmt->execute();
    } else {
        $stmt = $db->prepare('SELECT * FROM images ORDER BY active DESC, title, filename');
        $stmt->execute();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getImageById(int $id): ?array {
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM images WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function getImageBySlug(string $slug): ?array {
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM images WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function saveImageRecord(array $record): bool {
    $db = getDb();
    $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    $filename = trim($record['filename'] ?? '');
    $slug = trim($record['slug'] ?? '');
    $title = trim($record['title'] ?? '');
    $description = trim($record['description'] ?? '');
    $active = !empty($record['active']) ? 1 : 0;

    if ($filename === '') {
        return false;
    }

    if ($slug === '') {
        $slug = generateSlug($title ?: pathinfo($filename, PATHINFO_FILENAME), $db, (int)($record['id'] ?? 0));
    }

    if (!empty($record['id'])) {
        $conflict = $db->prepare('SELECT id FROM images WHERE slug = :slug AND id != :id');
        $conflict->execute([':slug' => $slug, ':id' => (int)$record['id']]);
        if ($conflict->fetch(PDO::FETCH_ASSOC)) {
            return false;
        }

        $stmt = $db->prepare('UPDATE images SET filename = :filename, slug = :slug, title = :title, description = :description, active = :active, updated_at = :updated_at WHERE id = :id');
        return $stmt->execute([
            ':filename' => $filename,
            ':slug' => $slug,
            ':title' => $title,
            ':description' => $description,
            ':active' => $active,
            ':updated_at' => $now,
            ':id' => (int)$record['id'],
        ]);
    }

    $conflict = $db->prepare('SELECT id FROM images WHERE slug = :slug');
    $conflict->execute([':slug' => $slug]);
    if ($conflict->fetch(PDO::FETCH_ASSOC)) {
        return false;
    }

    $stmt = $db->prepare('INSERT INTO images (filename, slug, title, description, active, created_at, updated_at) VALUES (:filename, :slug, :title, :description, :active, :created_at, :updated_at)');
    return $stmt->execute([
        ':filename' => $filename,
        ':slug' => $slug,
        ':title' => $title,
        ':description' => $description,
        ':active' => $active,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function deleteImageRecord(int $id): bool {
    $db = getDb();
    $stmt = $db->prepare('DELETE FROM images WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}
