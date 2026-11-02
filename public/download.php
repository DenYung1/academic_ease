<?php
/**
 * Protected File Viewer for Study Materials
 * 
 * A file viewer that serves files with copy protection and download prevention
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get file path from URL
$file_path = isset($_GET['file']) ? $_GET['file'] : '';

// Security check - make sure the file path is valid and within the uploads directory
if (empty($file_path) || strpos($file_path, 'uploads/') !== 0) {
    die('Error: Invalid file path');
    exit;
}

// Convert relative path to absolute path
$absolute_path = '../' . $file_path;

// Check if file exists
if (!file_exists($absolute_path)) {
    die('Error: File not found');
    exit;
}

// Get file information
$file_info = pathinfo($absolute_path);
$file_name = $file_info['basename'];
$file_extension = strtolower($file_info['extension']);
$title = pathinfo($file_name, PATHINFO_FILENAME);

// For PDF files, use protected viewer
if ($file_extension === 'pdf' && !isset($_GET['raw'])) {
    // Get file size
    $filesize = filesize($absolute_path);
    
    // Output HTML with PDF.js viewer and protection
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - AcademicEase</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        
        <!-- PDF.js library and viewer -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js"></script>
        
        <style>
            body, html {
                height: 100%;
                margin: 0;
                padding: 0;
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            }
            .header {
                background-color: #007bff;
                color: white;
                padding: 10px 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                z-index: 100;
                position: relative;
            }
            .header h1 {
                margin: 0;
                font-size: 1.2rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 50%;
            }
            .header a {
                color: white;
                text-decoration: none;
                display: flex;
                align-items: center;
                font-size: 0.9rem;
            }
            .header a i {
                margin-right: 5px;
            }
            .header a:hover {
                text-decoration: underline;
            }
            .pdf-container {
                height: calc(100% - 50px);
                width: 100%;
                overflow: auto;
                position: relative;
                background-color: #525659;
                -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
            }
            .watermark {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                pointer-events: none;
                z-index: 10;
            }
            .watermark-text {
                color: rgba(0,0,0,0.1);
                font-size: 60px; /* Smaller on mobile */
                transform: rotate(-45deg);
                white-space: nowrap;
                user-select: none;
            }
            #pdf-viewer {
                margin: 10px auto;
                width: 95%; /* Responsive width */
                max-width: 800px;
                background: white;
                box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            }
            .page-controls {
                position: fixed;
                bottom: 10px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(0,0,0,0.7);
                padding: 8px 15px;
                border-radius: 30px;
                display: flex;
                align-items: center;
                z-index: 20;
                touch-action: manipulation; /* Better touch handling */
            }
            .page-controls button {
                background: none;
                border: none;
                color: white;
                font-size: 1.2rem;
                margin: 0 10px;
                cursor: pointer;
                padding: 5px;
            }
            .page-controls button:disabled {
                color: #666;
                cursor: not-allowed;
            }
            .page-display {
                color: white;
                margin: 0 15px;
                font-size: 0.9rem;
            }
            @media (max-width: 768px) {
                .watermark-text {
                    font-size: 40px;
                }
                .page-controls {
                    padding: 5px 10px;
                }
                .page-controls button {
                    margin: 0 5px;
                    font-size: 1rem;
                }
            }
            /* Prevent selection and right-click */
            #pdf-viewer {
                -webkit-touch-callout: none;
                -webkit-user-select: none;
                -khtml-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
            }
        </style>
    </head>
    <body oncontextmenu="return false;">
        <div class="header">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <a href="study.php"><i class="fas fa-arrow-left"></i> Back to Study Materials</a>
        </div>
        
        <div class="pdf-container">
            <div id="pdf-viewer"></div>
            
            <!-- Watermark -->
            <div class="watermark">
                <div class="watermark-text">AcademicEase</div>
            </div>
            
            <!-- Page Controls -->
            <div class="page-controls">
                <button id="prev-page" disabled><i class="fas fa-chevron-left"></i></button>
                <span class="page-display"><span id="current-page">1</span> / <span id="total-pages">?</span></span>
                <button id="next-page"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        
        <script>
            // Prevent right-click
            document.addEventListener('contextmenu', event => event.preventDefault());
            
            // Initialize PDF.js
            const pdfjsLib = window['pdfjs-dist/build/pdf'];
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
            
            // PDF viewer elements
            const pdfViewer = document.getElementById('pdf-viewer');
            const prevButton = document.getElementById('prev-page');
            const nextButton = document.getElementById('next-page');
            const currentPageDisplay = document.getElementById('current-page');
            const totalPagesDisplay = document.getElementById('total-pages');
            
            // Current state
            let currentPDF = null;
            let currentPage = 1;
            let totalPages = 0;
            
            // Load the PDF
            async function loadPDF() {
                try {
                    // Load the PDF document
                    const loadingTask = pdfjsLib.getDocument('<?php echo "download.php?file=".urlencode($file_path)."&raw=1"; ?>');
                    currentPDF = await loadingTask.promise;
                    totalPages = currentPDF.numPages;
                    totalPagesDisplay.textContent = totalPages;
                    
                    // Render the first page
                    renderPage(currentPage);
                } catch (error) {
                    console.error('Error loading PDF:', error);
                    pdfViewer.innerHTML = `<div class="alert alert-danger m-3">Error loading PDF: ${error.message}</div>`;
                }
            }
            
            // Render a specific page
            async function renderPage(pageNumber) {
                try {
                    // Get the page
                    const page = await currentPDF.getPage(pageNumber);
                    
                    // Set scale for responsive viewing
                    const viewport = page.getViewport({ scale: 1 });
                    const containerWidth = pdfViewer.clientWidth - 20; // Account for margins
                    const scale = containerWidth / viewport.width;
                    const scaledViewport = page.getViewport({ scale });
                    
                    // Prepare canvas for rendering
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = scaledViewport.height;
                    canvas.width = scaledViewport.width;
                    
                    // Clear previous content
                    pdfViewer.innerHTML = '';
                    pdfViewer.appendChild(canvas);
                    
                    // Render the PDF page
                    const renderContext = {
                        canvasContext: context,
                        viewport: scaledViewport
                    };
                    
                    await page.render(renderContext).promise;
                    
                    // Update page navigation
                    currentPageDisplay.textContent = pageNumber;
                    prevButton.disabled = pageNumber <= 1;
                    nextButton.disabled = pageNumber >= totalPages;
                    
                } catch (error) {
                    console.error('Error rendering page:', error);
                }
            }
            
            // Page navigation
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderPage(currentPage);
                }
            });
            
            nextButton.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    renderPage(currentPage);
                }
            });
            
            // Handle keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft' && currentPage > 1) {
                    currentPage--;
                    renderPage(currentPage);
                } else if (e.key === 'ArrowRight' && currentPage < totalPages) {
                    currentPage++;
                    renderPage(currentPage);
                }
            });
            
            // Load the PDF when the page loads
            window.addEventListener('load', loadPDF);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// For raw file access (used by the PDF viewer) or non-PDF files
if (isset($_GET['raw']) || $file_extension !== 'pdf') {
    // Set appropriate content type based on file extension
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];

    $content_type = isset($content_types[$file_extension]) ? $content_types[$file_extension] : 'application/octet-stream';

    // Set headers for file download
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($absolute_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Output file content
    readfile($absolute_path);
    exit;
}
?>
