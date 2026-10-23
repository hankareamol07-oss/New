<?php
/**
 * App login tokens. Uses schools.api_token when that column exists; otherwise a
 * separate SQLite store in data/private, so the main database is never altered.
 */
function tg_tokens_has_column(PDO $db): bool
{
    static $has = null;
    if ($has === null) {
        try {
            $has = (bool) $db->query("SHOW COLUMNS FROM schools LIKE 'api_token'")->fetch();
        } catch (Throwable $e) {
            $has = false;
        }
    }
    return $has;
}

function tg_tokens_store(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dir = __DIR__ . '/../data/private';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        $pdo = new PDO('sqlite:' . $dir . '/app_tokens.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_tokens (token_hash TEXT PRIMARY KEY, school_id INTEGER NOT NULL, device TEXT, created_at TEXT, last_used_at TEXT)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_app_tokens_school ON app_tokens(school_id)');
    }
    return $pdo;
}

function tg_token_issue(PDO $db, int $schoolId, string $device = ''): string
{
    $token = bin2hex(random_bytes(32));
    if (tg_tokens_has_column($db)) {
        $cur = $db->prepare('SELECT api_token FROM schools WHERE id = ?');
        $cur->execute([$schoolId]);
        $existing = (string) $cur->fetchColumn();
        if ($existing !== '') {
            return $existing;
        }
        $db->prepare('UPDATE schools SET api_token = ? WHERE id = ?')->execute([$token, $schoolId]);
        return $token;
    }
    $now = date('Y-m-d H:i:s');
    tg_tokens_store()->prepare('INSERT INTO app_tokens (token_hash, school_id, device, created_at, last_used_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $schoolId, mb_substr($device, 0, 120), $now, $now]);
    return $token;
}

function tg_token_school(PDO $db, string $token): int
{
    if ($token === '') {
        return 0;
    }
    if (tg_tokens_has_column($db)) {
        $st = $db->prepare('SELECT id FROM schools WHERE api_token = ? AND is_active = 1 LIMIT 1');
        $st->execute([$token]);
        return (int) $st->fetchColumn();
    }
    $store = tg_tokens_store();
    $st = $store->prepare('SELECT school_id FROM app_tokens WHERE token_hash = ?');
    $st->execute([hash('sha256', $token)]);
    $id = (int) $st->fetchColumn();
    if ($id <= 0) {
        return 0;
    }
    $act = $db->prepare('SELECT is_active FROM schools WHERE id = ?');
    $act->execute([$id]);
    if ((int) $act->fetchColumn() !== 1) {
        return 0;
    }
    $store->prepare('UPDATE app_tokens SET last_used_at = ? WHERE token_hash = ?')->execute([date('Y-m-d H:i:s'), hash('sha256', $token)]);
    return $id;
}

function tg_token_revoke(PDO $db, int $schoolId, ?string $token = null): void
{
    if (tg_tokens_has_column($db)) {
        $db->prepare('UPDATE schools SET api_token = NULL WHERE id = ?')->execute([$schoolId]);
        return;
    }
    if ($token !== null) {
        tg_tokens_store()->prepare('DELETE FROM app_tokens WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    } else {
        tg_tokens_store()->prepare('DELETE FROM app_tokens WHERE school_id = ?')->execute([$schoolId]);
    }
}
