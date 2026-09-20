<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const PRIORITY_USER_ID = 1;
const PRIORITY_MAX_BODY = 1048576;

function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $code, string $message, int $status, array $extra = []): never {
    json_out(array_merge(['ok'=>false,'error'=>['code'=>$code,'message'=>$message]], $extra), $status);
}

function require_auth(): void {
    if (empty($_SESSION['priority_auth'])) api_error('UNAUTHORIZED','Authentication required.',401);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $configFile = getenv('PRIORITY_DB_CONFIG') ?: '/home/sites/1a/1/1f8017897e/.priority_db.php';
    if (!is_file($configFile)) api_error('SERVER_CONFIG_ERROR','Database configuration is unavailable.',500);
    $c = require $configFile;
    if (!is_array($c) || empty($c['dsn']) || !array_key_exists('user',$c) || !array_key_exists('password',$c)) {
        api_error('SERVER_CONFIG_ERROR','Database configuration is invalid.',500);
    }
    try {
        $pdo = new PDO($c['dsn'], (string)$c['user'], (string)$c['password'], [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false
        ]);
    } catch (Throwable $e) {
        api_error('DATABASE_UNAVAILABLE','Database is unavailable.',500);
    }
    return $pdo;
}

function csrf_token(): string {
    if (empty($_SESSION['priority_csrf'])) $_SESSION['priority_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['priority_csrf'];
}

function require_csrf(): void {
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) api_error('CSRF_INVALID','Invalid CSRF token.',403);
}

function read_json_body(): array {
    $len=(int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > PRIORITY_MAX_BODY) api_error('PAYLOAD_TOO_LARGE','Request body is too large.',413);
    $raw=file_get_contents('php://input');
    if ($raw===false || $raw==='') api_error('INVALID_JSON','JSON body is required.',400);
    try { $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { api_error('INVALID_JSON','Malformed JSON.',400); }
    if (!is_array($data)) api_error('INVALID_JSON','JSON object is required.',400);
    return $data;
}

function iso(?string $value): ?string {
    if (!$value) return null;
    return (new DateTimeImmutable($value, new DateTimeZone(date_default_timezone_get())))->format(DateTimeInterface::ATOM);
}

function valid_uuid(string $id): bool {
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$id);
}
