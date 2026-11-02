<?php
/**
 * Database connection handler for Academic Ease
 * Handles connections to both local MySQL database and Supabase
 * This version uses the service role key for user registration to bypass RLS
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
 * @param bool $useServiceRole Whether to use the service role key (for operations that need to bypass RLS)
 * @param array $customHeaders Additional headers to include in the request
 * @return array The response data
 */
function supabaseRequest($endpoint, $method = 'GET', $data = null, $useServiceRole = false, $customHeaders = []) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    
    // Debug the API request
    error_log("Supabase API Request: $method $url");
    if ($data) {
        error_log("Request data: " . json_encode($data));
    }
    
    // Use service role key for user registration to bypass RLS if available
    $key = $useServiceRole && defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : SUPABASE_KEY;
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key
    ];
    
    // Add Prefer header for insert and delete operations
    if ($method === 'POST' || $method === 'DELETE') {
        $headers[] = 'Prefer: return=representation';
    }
    
    // Add any custom headers
    if (!empty($customHeaders) && is_array($customHeaders)) {
        $headers = array_merge($headers, $customHeaders);
    }
    
    // Debug headers
    error_log("Supabase Request Headers: " . json_encode($headers));

    $response = false;
    $statusCode = 0;
    $curlError = '';
    $requestBody = null;

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        $requestBody = $data ? json_encode($data) : null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($requestBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
            }
        } else if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            // No need to add Prefer header here as it's already passed in customHeaders
        }
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
    } else {
        // Fallback for hosts where the cURL extension is unavailable
        $headerLines = implode("\r\n", $headers);
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => $headerLines,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];

        if ($requestBody !== null) {
            $contextOptions['http']['content'] = $requestBody;
        }

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            $statusCode = (int) $matches[1];
        }

        if ($response === false) {
            $error = error_get_last();
            $curlError = $error['message'] ?? 'Failed to request Supabase endpoint using stream fallback.';
        }
    }
    
    // Debug the API response
    error_log("Supabase API Response: Status $statusCode");
    if (is_string($response) && strlen($response) < 1000) { // Only log if not too large
        error_log("Response data: $response");
    } elseif (is_string($response)) {
        error_log("Response data too large to log (" . strlen($response) . " bytes)");
    }
    
    if ($curlError) {
        error_log("Supabase CURL Error: " . $curlError);
        return ['error' => true, 'status' => 0, 'message' => $curlError];
    }
    
    if ($statusCode >= 400) {
        error_log("Supabase API Error: " . $response . " (Status: $statusCode, URL: $url)");
        return ['error' => true, 'status' => $statusCode, 'message' => $response];
    }
    
    // For GET requests to quizzes endpoint, ensure we're getting an array
    if ($method === 'GET' && strpos($endpoint, 'quizzes') === 0) {
        $decoded = json_decode($response, true);
        
        // If the response is empty or not an array, log it and return an empty array
        if (empty($decoded) || !is_array($decoded)) {
            error_log("Supabase Quiz Data Warning: Expected array but got " . gettype($decoded));
            return [];
        }
        
        // Log the number of quizzes for debugging
        error_log("Supabase Quiz Data: Retrieved " . count($decoded) . " quizzes");
        return $decoded;
    }
    
    // For other requests, decode normally
    $decoded = json_decode($response, true);
    return $decoded ?: [];
}

/**
 * Get data from a Supabase table
 * 
 * @param string $table The table name
 * @param array $params Query parameters (select, filter, order, limit, etc.)
 * @param bool $useServiceRole Whether to use the service role key
 * @return array The fetched data
 */
function supabaseSelect($table, $params = [], $useServiceRole = false) {
    $query = [];
    
    if (isset($params['select'])) {
        $query[] = 'select=' . urlencode($params['select']);
    }
    
    if (isset($params['filter'])) {
        foreach ($params['filter'] as $column => $value) {
            // Fix the query format for Supabase PostgREST API
            // The format should be column=eq.value not column.eq=value
            if (strpos($column, '.') !== false) {
                // Convert email.eq => email=eq.
                list($col, $op) = explode('.', $column);
                $query[] = $col . '=' . $op . '.' . urlencode($value);
            } else {
                $query[] = $column . '=' . urlencode($value);
            }
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
    
    return supabaseRequest($table . $queryString, 'GET', null, $useServiceRole);
}

/**
 * Insert data into a Supabase table
 * 
 * @param string $table The table name
 * @param array $data The data to insert
 * @return array The inserted data
 */
function supabaseInsert($table, $data) {
    // Use service role key for user registration and past_questions to bypass RLS
    $useServiceRole = ($table === 'users' || $table === 'past_questions');
    return supabaseRequest($table, 'POST', $data, $useServiceRole);
}

/**
 * Update data in a Supabase table
 * 
 * @param string $table The table name
 * @param array $data The data to update
 * @param string $filter The filter to apply (e.g. 'id=eq.123')
 * @param bool $useServiceRole Whether to use the service role key
 * @return array The updated data
 */
function supabaseUpdate($table, $data, $filter, $useServiceRole = false) {
    return supabaseRequest($table . '?' . $filter, 'PATCH', $data, $useServiceRole);
}

/**
 * Delete data from a Supabase table
 * 
 * @param string $table The table name
 * @param string $filter The filter to apply (e.g. 'id=eq.123')
 * @return array The deleted data
 */
function supabaseDelete($table, $filter) {
    // Use service role key for past_questions and study_schedules tables to bypass RLS if needed
    $useServiceRole = in_array($table, ['past_questions', 'study_schedules']);
    
    // For delete operations, we need to include the filter in the URL
    $endpoint = $table . '?' . $filter . '&select=*';
    
    // Log the delete operation for debugging
    error_log("Deleting from $table with filter: $filter");
    
    $result = supabaseRequest($endpoint, 'DELETE', null, $useServiceRole);
    
    // Log the result for debugging
    error_log("Delete result: " . json_encode($result));
    
    return $result;
}

/**
 * Upload a file to Supabase Storage with user-specific paths for better security
 * 
 * @param string $bucket The storage bucket
 * @param string $path The file path in the bucket
 * @param string $filePath The local file path
 * @param string $userId Optional user ID to organize files by user (recommended for security)
 * @return array The response data
 */
function supabaseUploadFile($bucket, $path, $filePath, $userId = null) {
    // If userId is provided, organize files by user ID for better security
    if ($userId) {
        // Check if the path already contains the user ID to avoid duplication
        if (strpos($path, 'user-' . $userId) === false) {
            $path = 'user-' . $userId . '/' . $path;
        }
    }
    
    $url = SUPABASE_URL . '/storage/v1/object/' . $bucket . '/' . $path;
    
    // Use service role key for storage operations to bypass RLS if needed
    $key = defined('SUPABASE_SERVICE_KEY') && !empty(SUPABASE_SERVICE_KEY) 
        ? SUPABASE_SERVICE_KEY 
        : SUPABASE_KEY;
    
    $headers = [
        'Content-Type: application/octet-stream',
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key
    ];
    
    $fileData = file_get_contents($filePath);
    $response = false;
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        
        // Disable SSL verification for local development
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $contextOptions = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $fileData,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            $statusCode = (int) $matches[1];
        }
    }
    
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
 * @param string $userId Optional user ID to organize files by user (recommended for security)
 * @return string The public URL
 */
function supabaseGetPublicUrl($bucket, $path, $userId = null) {
    // If userId is provided, organize files by user ID for better security
    if ($userId) {
        // Check if the path already contains the user ID to avoid duplication
        if (strpos($path, 'user-' . $userId) === false) {
            $path = 'user-' . $userId . '/' . $path;
        }
    }
    
    return SUPABASE_URL . '/storage/v1/object/public/' . $bucket . '/' . $path;
}
