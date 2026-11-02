<?php
/**
 * Database connection handler for Academic Ease
 * Handles connections to both local MySQL database and Supabase
 */

require_once 'config.php';

/**
 * Get a PDO connection to the local MySQL database
 * 
 * @return PDO Database connection
 */
function getLocalDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the error and display a user-friendly message
            error_log("Database Connection Error: " . $e->getMessage());
            die("Could not connect to the database. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Make a request to the Supabase API
 * 
 * @param string $endpoint The API endpoint
 * @param string $method The HTTP method (GET, POST, PUT, DELETE)
 * @param array $data The data to send (for POST and PUT requests)
 * @return array The response data
 */
function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY
    ];
    
    // Add Prefer header for insert operations
    if ($method === 'POST') {
        $headers[] = 'Prefer: return=representation';
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Disable SSL verification for local development
    // NOTE: This should be removed in production
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } else if ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("Supabase CURL Error: " . $curlError);
        return ['error' => true, 'status' => 0, 'message' => $curlError];
    }
    
    if ($statusCode >= 400) {
        error_log("Supabase API Error: " . $response . " (Status: $statusCode, URL: $url)");
        return ['error' => true, 'status' => $statusCode, 'message' => $response];
    }
    
    return json_decode($response, true) ?: [];
}

/**
 * Get data from a Supabase table
 * 
 * @param string $table The table name
 * @param array $params Query parameters (select, filter, order, limit, etc.)
 * @return array The fetched data
 */
function supabaseSelect($table, $params = []) {
    $query = [];
    
    if (isset($params['select'])) {
        $query[] = 'select=' . urlencode($params['select']);
    }
    
    if (isset($params['filter'])) {
        foreach ($params['filter'] as $column => $value) {
            $query[] = $column . '=' . urlencode($value);
        }
    }
    
    if (isset($params['order'])) {
        $query[] = 'order=' . urlencode($params['order']);
    }
    
    if (isset($params['limit'])) {
        $query[] = 'limit=' . intval($params['limit']);
    }
    
    if (isset($params['offset'])) {
        $query[] = 'offset=' . intval($params['offset']);
    }
    
    $queryString = !empty($query) ? '?' . implode('&', $query) : '';
    
    return supabaseRequest($table . $queryString);
}

/**
 * Insert data into a Supabase table
 * 
 * @param string $table The table name
 * @param array $data The data to insert
 * @return array The response data
 */
function supabaseInsert($table, $data) {
    return supabaseRequest($table, 'POST', $data);
}

/**
 * Update data in a Supabase table
 * 
 * @param string $table The table name
 * @param array $data The data to update
 * @param string $filter The filter to apply (e.g., "id=eq.123")
 * @return array The response data
 */
function supabaseUpdate($table, $data, $filter) {
    return supabaseRequest($table . '?' . $filter, 'PATCH', $data);
}

/**
 * Delete data from a Supabase table
 * 
 * @param string $table The table name
 * @param string $filter The filter to apply (e.g., "id=eq.123")
 * @return array The response data
 */
function supabaseDelete($table, $filter) {
    return supabaseRequest($table . '?' . $filter, 'DELETE');
}

/**
 * Execute a stored procedure or function in Supabase
 * 
 * @param string $functionName The function name
 * @param array $params The parameters to pass
 * @return array The response data
 */
function supabaseRpc($functionName, $params = []) {
    return supabaseRequest('rpc/' . $functionName, 'POST', $params);
}

/**
 * Upload a file to Supabase Storage
 * 
 * @param string $bucket The storage bucket
 * @param string $path The file path in the bucket
 * @param string $filePath The local file path
 * @return array The response data
 */
function supabaseUploadFile($bucket, $path, $filePath) {
    $url = SUPABASE_URL . '/storage/v1/object/' . $bucket . '/' . $path;
    
    $headers = [
        'Content-Type: application/octet-stream',
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY
    ];
    
    $fileData = file_get_contents($filePath);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
    
    // Disable SSL verification for local development
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($statusCode >= 400) {
        error_log("Supabase Storage Error: " . $response);
        return ['error' => true, 'status' => $statusCode, 'message' => $response];
    }
    
    return json_decode($response, true) ?: [];
}

/**
 * Get a public URL for a file in Supabase Storage
 * 
 * @param string $bucket The storage bucket
 * @param string $path The file path in the bucket
 * @return string The public URL
 */
function supabaseGetPublicUrl($bucket, $path) {
    return SUPABASE_URL . '/storage/v1/object/public/' . $bucket . '/' . $path;
}
