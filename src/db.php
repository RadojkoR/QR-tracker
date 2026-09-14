<?php
/**
 * PDO veza + migracija. Tabele se prave same pri prvom zahtevu,
 * identično na SQLite i MySQL — nema ručnog uvoza .sql fajla.
 */

function qr_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (QR_DB_DRIVER === 'mysql') {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', QR_MYSQL_HOST, QR_MYSQL_NAME);
        $pdo = new PDO($dsn, QR_MYSQL_USER, QR_MYSQL_PASS, $opts);
    } else {
        $dir = dirname(QR_SQLITE_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . QR_SQLITE_PATH, null, null, $opts);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 3000');
    }

    qr_db_migrate($pdo);
    return $pdo;
}

function qr_db_migrate(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (QR_DB_DRIVER === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS qr_scans (
                id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                code         VARCHAR(40)  NOT NULL,
                scanned_at   DATETIME     NOT NULL,
                scan_date    DATE         NOT NULL,
                visitor_hash CHAR(64)     NOT NULL,
                is_repeat    TINYINT(1)   NOT NULL DEFAULT 0,
                excluded     TINYINT(1)   NOT NULL DEFAULT 0,
                device       VARCHAR(16)  NOT NULL DEFAULT 'desktop',
                os           VARCHAR(32)  NOT NULL DEFAULT 'Other',
                browser      VARCHAR(32)  NOT NULL DEFAULT 'Other',
                lang         VARCHAR(16)  NULL,
                country      CHAR(2)      NULL,
                referrer     VARCHAR(190) NULL,
                PRIMARY KEY (id),
                KEY idx_date_code (scan_date, code),
                KEY idx_hash_date (visitor_hash, scan_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS qr_daily (
                scan_date DATE        NOT NULL,
                code      VARCHAR(40) NOT NULL,
                scans     INT UNSIGNED NOT NULL DEFAULT 0,
                visitors  INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (scan_date, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS qr_scans (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                code         TEXT    NOT NULL,
                scanned_at   TEXT    NOT NULL,
                scan_date    TEXT    NOT NULL,
                visitor_hash TEXT    NOT NULL,
                is_repeat    INTEGER NOT NULL DEFAULT 0,
                excluded     INTEGER NOT NULL DEFAULT 0,
                device       TEXT    NOT NULL DEFAULT 'desktop',
                os           TEXT    NOT NULL DEFAULT 'Other',
                browser      TEXT    NOT NULL DEFAULT 'Other',
                lang         TEXT,
                country      TEXT,
                referrer     TEXT
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_date_code ON qr_scans (scan_date, code)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hash_date ON qr_scans (visitor_hash, scan_date)");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS qr_daily (
                scan_date TEXT    NOT NULL,
                code      TEXT    NOT NULL,
                scans     INTEGER NOT NULL DEFAULT 0,
                visitors  INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (scan_date, code)
            )
        ");
    }

    // CREATE TABLE IF NOT EXISTS ne dira postojeću tabelu — kolone dodate
    // posle prvog deploya moraju ovuda.
    qr_ensure_column($pdo, 'qr_scans', 'excluded',
        QR_DB_DRIVER === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0');

    $done = true;
}

/** Doda kolonu ako je nema. Radi isto na SQLite i MySQL. */
function qr_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    try {
        if (QR_DB_DRIVER === 'mysql') {
            $st = $pdo->prepare('
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1
            ');
            $st->execute([$table, $column]);
            $exists = (bool) $st->fetchColumn();
        } else {
            $exists = false;
            foreach ($pdo->query("PRAGMA table_info($table)") as $c) {
                if (($c['name'] ?? '') === $column) {
                    $exists = true;
                    break;
                }
            }
        }

        if (!$exists) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    } catch (Throwable $e) {
        error_log('[qr-tracker migrate] ' . $e->getMessage());
    }
}
