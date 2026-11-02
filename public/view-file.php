<?php
/**
 * Protected File Viewer using PDF.js
 * 
 * A file viewer that serves PDFs with copy protection and download prevention
 */

// Start session for authentication
session_start();

// Basic error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get the filename from the URL
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($filename)) {
    die('Error: No file specified');
}

// Security check - only allow PDF files
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if ($extension !== 'pdf') {
    die('Error: Only PDF files are allowed');
}

// Set the path to the file
$filepath = '../uploads/past_questions/' . $filename;

// Check if the file exists
if (!file_exists($filepath)) {
    die('Error: File not found');
}

// If this is a direct file request, serve the PDF in a protected viewer
if (!isset($_GET['raw'])) {
    // Get file info
    $filesize = filesize($filepath);
    $title = pathinfo($filename, PATHINFO_FILENAME);
    
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
                background: transparent;
                border: none;
                color: white;
                font-size: 1.2rem;
                cursor: pointer;
                padding: 5px 12px;
                touch-action: manipulation; /* Better touch handling */
            }
            .page-controls button:disabled {
                color: #666;
                cursor: not-allowed;
            }
            .page-controls span {
                color: white;
                margin: 0 8px;
                font-size: 0.9rem;
            }
            /* Disable selection */
            body {
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
                touch-action: manipulation; /* Better touch handling */
            }
            .page-canvas {
                display: block;
                margin: 0 auto;
                border: 1px solid #ddd;
                width: 100%; /* Make canvas responsive */
                height: auto;
                max-width: 100%;
            }
            
            /* Mobile-specific styles */
            @media (max-width: 768px) {
                .header h1 {
                    font-size: 1rem;
                    max-width: 40%;
                }
                .header a span {
                    display: none; /* Hide text on very small screens */
                }
                .header a i {
                    margin-right: 0;
                    font-size: 1.2rem;
                }
                .watermark-text {
                    font-size: 40px;
                }
                .page-controls {
                    padding: 5px 10px;
                    bottom: 5px;
                }
                .page-controls button {
                    padding: 5px 8px;
                }
            }
            
            /* Orientation change handling */
            @media screen and (orientation: landscape) and (max-height: 500px) {
                .header {
                    padding: 5px 10px;
                }
                .pdf-container {
                    height: calc(100% - 40px);
                }
                #pdf-viewer {
                    margin: 5px auto;
                }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <a href="study.php">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Study Materials</span>
            </a>
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <div></div> <!-- Empty div for flex spacing -->
        </div>
        
        <div class="pdf-container" id="pdf-container">
            <!-- Watermark overlay -->
            <div class="watermark">
                <div class="watermark-text">AcademicEase</div>
            </div>
            
            <!-- PDF.js viewer -->
            <div id="pdf-viewer"></div>
            
            <!-- Page and segment controls -->
            <div class="page-controls">
                <button id="prev"><i class="fas fa-chevron-left"></i></button>
                <span id="page-num"></span> / <span id="page-count"></span>
                <span id="segment-indicator" style="display: none;"> â€¢ Section <span id="current-segment">1</span>/<span id="total-segments">1</span></span>
                <button id="next"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        
        <script>
            // Disable right-click and keyboard shortcuts
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });
            
            document.addEventListener('keydown', function(e) {
                // Disable Ctrl+S, Ctrl+P, Ctrl+Shift+I
                if ((e.ctrlKey && e.key === 's') || 
                    (e.ctrlKey && e.key === 'p') || 
                    (e.ctrlKey && e.shiftKey && e.key === 'i')) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Override print function
            window.print = function() {
                alert('Printing is disabled for this document.');
                return false;
            };
            
            // Initialize PDF.js
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
            
            // PDF.js variables
            let pdfDoc = null;
            let pageNum = 1;
            let pageRendering = false;
            let pageNumPending = null;
            let pdfScale = 1.5; // Renamed to avoid duplicate declaration
            const pdfViewer = document.getElementById('pdf-viewer');
            const pageNumSpan = document.getElementById('page-num');
            const pageCountSpan = document.getElementById('page-count');
            const prevButton = document.getElementById('prev');
            const nextButton = document.getElementById('next');
            
            // Detect if we're on a mobile device
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            // Adjust scale based on device
            pdfScale = isMobile ? (window.innerWidth < 500 ? 1.0 : 1.2) : 1.5;
            
            // Load the PDF with proper URL
            const pdfUrl = 'view-file.php?file=<?php echo urlencode($filename); ?>&raw=1';
            console.log('Loading PDF from:', pdfUrl); // Debug log
            
            pdfjsLib.getDocument(pdfUrl).promise
                .then(function(pdf) {
                    console.log('PDF loaded successfully with', pdf.numPages, 'pages'); // Debug log
                    pdfDoc = pdf;
                    pageCountSpan.textContent = pdf.numPages;
                    
                    // Initial render of the first page
                    renderPage(pageNum);
                })
                .catch(function(error) {
                    console.error('Error loading PDF:', error);
                    document.getElementById('pdf-viewer').innerHTML = 
                        '<div class="alert alert-danger">Error loading PDF: ' + error.message + '</div>';
                });
            
            // Variables for segmented viewing
            let currentSegment = 0;
            let totalSegments = 1;
            let segmentMode = false;
            
            // Render a specific page
            function renderPage(num, segment = 0) {
                pageRendering = true;
                
                // Update page number display
                pageNumSpan.textContent = num;
                
                // Update button states for page navigation
                prevButton.disabled = num <= 1 && segment === 0;
                nextButton.disabled = num >= pdfDoc.numPages && segment >= totalSegments - 1;
                
                // Get the page
                pdfDoc.getPage(num).then(function(page) {
                    // Create a canvas for this page
                    const canvas = document.createElement('canvas');
                    canvas.className = 'page-canvas';
                    const ctx = canvas.getContext('2d');
                    
                    // Calculate viewport size based on container width
                    const containerWidth = document.getElementById('pdf-container').clientWidth * 0.95;
                    const containerMaxWidth = 800; // Maximum width
                    const effectiveWidth = Math.min(containerWidth, containerMaxWidth);
                    
                    // Create a scaled viewport that fits the container width
                    const originalViewport = page.getViewport({scale: 1.0});
                    const scaleFactor = effectiveWidth / originalViewport.width;
                    const viewport = page.getViewport({scale: scaleFactor});
                    
                    // Debug viewport dimensions
                    console.log('PDF viewport dimensions:', viewport.width, 'x', viewport.height);
                    
                    // Determine if we should use segmented viewing on mobile
                    segmentMode = isMobile && viewport.height > window.innerHeight * 1.2;
                    
                    // Calculate segments for mobile if needed
                    if (segmentMode) {
                        // Divide the page into segments for mobile viewing
                        const segmentHeight = Math.min(window.innerHeight * 0.7, 600);
                        totalSegments = Math.ceil(viewport.height / segmentHeight);
                        
                        // Ensure segment is valid
                        currentSegment = Math.min(Math.max(0, segment), totalSegments - 1);
                        
                        // Update segment indicator if we have multiple segments
                        if (totalSegments > 1) {
                            document.getElementById('segment-indicator').style.display = 'inline';
                            document.getElementById('current-segment').textContent = currentSegment + 1;
                            document.getElementById('total-segments').textContent = totalSegments;
                        } else {
                            document.getElementById('segment-indicator').style.display = 'none';
                        }
                        
                        // Set canvas dimensions for this segment
                        canvas.width = viewport.width;
                        canvas.height = segmentHeight;
                        
                        // Calculate the segment position
                        const segmentY = currentSegment * segmentHeight;
                        
                        // Clear previous page
                        pdfViewer.innerHTML = '';
                        pdfViewer.appendChild(canvas);
                        
                        // Create a modified viewport for this segment
                        const segmentViewport = new pdfjsLib.PageViewport({
                            viewBox: [
                                viewport.viewBox[0],
                                viewport.viewBox[1] + segmentY / scaleFactor,
                                viewport.viewBox[2],
                                viewport.viewBox[3]
                            ],
                            scale: scaleFactor,
                            rotation: viewport.rotation,
                            offsetX: viewport.offsetX,
                            offsetY: viewport.offsetY - segmentY,
                            dontFlip: false
                        });
                        
                        // Render the segment
                        const renderContext = {
                            canvasContext: ctx,
                            viewport: segmentViewport,
                            transform: [scaleFactor, 0, 0, scaleFactor, 0, -segmentY * scaleFactor]
                        };
                        
                        const renderTask = page.render(renderContext);
                        
                        // Wait for rendering to finish
                        renderTask.promise.then(function() {
                            pageRendering = false;
                            
                            // Add watermark to the canvas
                            ctx.save();
                            ctx.globalAlpha = 0.1;
                            
                            // Add watermark with student info and timestamp
                            const fontSize = Math.min(40, canvas.width / 12);
                            ctx.font = fontSize + 'px Arial';
                            
                            // Position watermark in the middle of the visible segment
                            ctx.translate(canvas.width/2, canvas.height/2);
                            ctx.rotate(-45 * Math.PI / 180);
                            ctx.fillText('AcademicEase', 0, 0);
                            
                            // Add timestamp to watermark
                            const timestamp = new Date().toLocaleString();
                            ctx.font = (fontSize/2) + 'px Arial';
                            ctx.fillText(timestamp, 0, fontSize);
                            
                            ctx.restore();
                            
                            // If another page is pending, render it
                            if (pageNumPending !== null) {
                                renderPage(pageNumPending, currentSegment);
                                pageNumPending = null;
                            }
                        });
                    } else {
                        // Standard rendering for desktop or small pages
                        totalSegments = 1;
                        currentSegment = 0;
                        document.getElementById('segment-indicator').style.display = 'none';
                        
                        // Set canvas dimensions
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        // Clear previous page
                        pdfViewer.innerHTML = '';
                        pdfViewer.appendChild(canvas);
                        
                        // Render the page
                        const renderContext = {
                            canvasContext: ctx,
                            viewport: viewport
                        };
                        
                        const renderTask = page.render(renderContext);
                        
                        // Wait for rendering to finish
                        renderTask.promise.then(function() {
                            pageRendering = false;
                            
                            // Add watermark to the canvas
                            ctx.save();
                            ctx.globalAlpha = 0.1;
                            
                            // Adjust watermark size based on canvas size
                            const fontSize = Math.min(60, canvas.width / 10);
                            ctx.font = fontSize + 'px Arial';
                            
                            ctx.translate(canvas.width/2, canvas.height/2);
                            ctx.rotate(-45 * Math.PI / 180);
                            
                            // Add watermark with timestamp
                            const timestamp = new Date().toLocaleString();
                            ctx.fillText('AcademicEase', 0, 0);
                            ctx.font = (fontSize/2) + 'px Arial';
                            ctx.fillText(timestamp, 0, fontSize);
                            
                            ctx.restore();
                            
                            // If another page is pending, render it
                            if (pageNumPending !== null) {
                                renderPage(pageNumPending);
                                pageNumPending = null;
                            }
                        });
                    }
                });
            }
            
            // Queue a page render if another is in progress
            function queueRenderPage(num, segment = null) {
                if (pageRendering) {
                    pageNumPending = num;
                    currentSegment = segment !== null ? segment : currentSegment;
                } else {
                    renderPage(num, segment !== null ? segment : currentSegment);
                }
            }
            
            // Handle navigation (previous/next)
            function navigate(direction) {
                if (segmentMode) {
                    // In segment mode, first navigate through segments
                    if (direction === 'prev') {
                        if (currentSegment > 0) {
                            // Go to previous segment of current page
                            currentSegment--;
                            queueRenderPage(pageNum, currentSegment);
                        } else if (pageNum > 1) {
                            // Go to last segment of previous page
                            pageNum--;
                            // We don't know how many segments the previous page has yet,
                            // so we'll set currentSegment after the page loads
                            queueRenderPage(pageNum, 999); // Large number will be adjusted in renderPage
                        }
                    } else { // direction === 'next'
                        if (currentSegment < totalSegments - 1) {
                            // Go to next segment of current page
                            currentSegment++;
                            queueRenderPage(pageNum, currentSegment);
                        } else if (pageNum < pdfDoc.numPages) {
                            // Go to first segment of next page
                            pageNum++;
                            queueRenderPage(pageNum, 0);
                        }
                    }
                } else {
                    // Standard page navigation for non-segmented mode
                    if (direction === 'prev' && pageNum > 1) {
                        pageNum--;
                        queueRenderPage(pageNum);
                    } else if (direction === 'next' && pageNum < pdfDoc.numPages) {
                        pageNum++;
                        queueRenderPage(pageNum);
                    }
                }
            }
            
            // Previous button
            prevButton.addEventListener('click', function() {
                navigate('prev');
            });
            
            // Next button
            nextButton.addEventListener('click', function() {
                navigate('next');
            });
            
            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                // Allow left/right/up/down arrows for navigation
                if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    navigate('prev');
                } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    navigate('next');
                }
            });
            
            // Swipe navigation for mobile
            let touchStartX = 0;
            let touchStartY = 0;
            
            document.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            }, false);
            
            document.addEventListener('touchend', function(e) {
                const touchEndX = e.changedTouches[0].screenX;
                const touchEndY = e.changedTouches[0].screenY;
                const dx = touchEndX - touchStartX;
                const dy = touchEndY - touchStartY;
                
                // Detect horizontal swipe (if stronger than vertical)
                if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50) {
                    if (dx > 0) {
                        navigate('prev'); // Swipe right = previous
                    } else {
                        navigate('next'); // Swipe left = next
                    }
                }
            }, false);
        </script>
    </body>
    </html>
    <?php
    exit;
}

// If raw=1 is set, serve the actual PDF file
if (isset($_GET['raw']) && $_GET['raw'] == 1) {
    // Set appropriate headers for PDF
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($filepath));
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=0');
    
    // Output the file and exit
    readfile($filepath);
    exit;
}

// Set headers to prevent caching and downloading
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Output the file
readfile($filepath);
exit;
?>
