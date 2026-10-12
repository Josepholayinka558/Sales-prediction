<?php
require_once dirname(__FILE__, 3) . '/app/bootstrap.php';
require_once dirname(__FILE__, 3) . '/app/helpers/DatasetImporter.php';
require_once dirname(__FILE__, 3) . '/app/helpers/SimpleXLSX.php';

header('Content-Type: application/json');

$sendJson = function ($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_NUMERIC_CHECK);
    exit;
};

try {
    if (!auth()->check()) {
        $sendJson(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    if (!Csrf::verifyRequest()) {
        $sendJson(['success' => false, 'error' => 'Invalid CSRF token'], 400);
    }

    $importer = new DatasetImporter();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $userId = Session::userId();

    switch ($action) {
        case 'upload_import':
            $truncate = !empty($_POST['truncate']);
            if (isset($_FILES['dataset_file']) && is_uploaded_file($_FILES['dataset_file']['tmp_name'])) {
                $file = $_FILES['dataset_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext === 'csv') {
                    $result = $importer->importCSV($file['tmp_name'], $file['name'], $userId, $truncate);
                } elseif ($ext === 'xlsx') {
                    $rows = SimpleXLSX::parseFile($file['tmp_name']);
                    if ($rows === false) {
                        echo json_encode(['success' => true, 'needs_parse' => true, 'ext' => $ext, 'filename' => $file['name'], 'truncate' => $truncate, 'server_parse_failed' => true], JSON_NUMERIC_CHECK);
                        exit;
                    }
                    $result = $importer->importJSONRows($rows, $file['name'], $userId, $truncate);
                } elseif ($ext === 'xls') {
                    echo json_encode(['success' => true, 'needs_parse' => true, 'ext' => $ext, 'filename' => $file['name'], 'truncate' => $truncate], JSON_NUMERIC_CHECK);
                    exit;
                } else {
                    throw new Exception("Unsupported file type: $ext. Use CSV or XLSX.");
                }
                echo json_encode($result, JSON_NUMERIC_CHECK);
                exit;
            }
            throw new Exception("No file uploaded.");
            break;

        case 'import_json':
            $truncate = !empty($_POST['truncate']);
            $filename = $_POST['filename'] ?? 'dataset.xlsx';
            $rowsJson = $_POST['rows'] ?? '';
            $rows = json_decode($rowsJson, true);
            if (!is_array($rows)) throw new Exception("Invalid rows data.");
            $result = $importer->importJSONRows($rows, $filename, $userId, $truncate);
            echo json_encode($result, JSON_NUMERIC_CHECK);
            exit;
            break;

        case 'auto_import':
            $truncate = !empty($_GET['truncate']) || !empty($_POST['truncate']);
            $filePath = DATASET_PATH . DIRECTORY_SEPARATOR . 'dataset.xlsx';
            if (!file_exists($filePath)) $filePath = dirname(APP_ROOT) . DIRECTORY_SEPARATOR . 'dataset.xlsx';
            if (!file_exists($filePath)) {
                $csvPath = DATASET_PATH . DIRECTORY_SEPARATOR . 'dataset.csv';
                if (file_exists($csvPath)) {
                    $result = $importer->importCSV($csvPath, 'dataset.csv', $userId, $truncate);
                    echo json_encode($result, JSON_NUMERIC_CHECK);
                    exit;
                }
                throw new Exception("dataset.xlsx not found in /dataset folder or project root.");
            }
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($ext === 'xlsx') {
                $rows = SimpleXLSX::parseFile($filePath);
                if ($rows !== false) {
                    $result = $importer->importJSONRows($rows, basename($filePath), $userId, $truncate);
                    echo json_encode($result, JSON_NUMERIC_CHECK);
                    exit;
                }
            }
            echo json_encode(['success' => true, 'needs_parse' => true, 'file_path' => $filePath, 'filename' => basename($filePath), 'truncate' => $truncate], JSON_NUMERIC_CHECK);
            exit;
            break;

        case 'server_import_file':
            $truncate = !empty($_POST['truncate']);
            $fileName = basename($_POST['file'] ?? 'dataset.xlsx');
            $candidates = [
                DATASET_PATH . DIRECTORY_SEPARATOR . $fileName,
                dirname(APP_ROOT) . DIRECTORY_SEPARATOR . $fileName,
            ];
            $filePath = null;
            foreach ($candidates as $c) if (file_exists($c)) { $filePath = $c; break; }
            if (!$filePath) throw new Exception("File not found: $fileName");
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($ext === 'csv') {
                $result = $importer->importCSV($filePath, basename($filePath), $userId, $truncate);
            } elseif ($ext === 'xlsx') {
                $rows = SimpleXLSX::parseFile($filePath);
                if ($rows === false) throw new Exception("Server-side XLSX parse failed. Try upload via drag-drop instead.");
                $result = $importer->importJSONRows($rows, basename($filePath), $userId, $truncate);
            } else {
                throw new Exception("Unsupported file type: $ext");
            }
            echo json_encode($result, JSON_NUMERIC_CHECK);
            exit;
            break;

        case 'delete_import':
            $id = (int)($_POST['id'] ?? 0);
            db()->query("DELETE FROM dataset_imports WHERE id = ?", [$id]);
            echo json_encode(['success' => true, 'message' => 'Import record deleted']);
            exit;
            break;

        default:
            throw new Exception("Unknown action: $action");
    }
} catch (Throwable $e) {
    $logDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'error-' . date('Y-m-d') . '.log', date('Y-m-d H:i:s') . ' | [import.php] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL, FILE_APPEND);
    $sendJson([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => APP_ENV === 'development' ? $e->getTraceAsString() : null,
    ], 400);
}
