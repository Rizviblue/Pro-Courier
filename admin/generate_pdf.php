<?php
session_start();
// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'reports';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="courier_' . $type . '_' . $date . '.pdf"');

// Generate PDF content (simplified version)
$pdf_content = generatePDFContent($type, $date);

// Output the PDF content
echo $pdf_content;

function generatePDFContent($type, $date) {
    // This is a simplified PDF generation
    // In a real application, you would use a library like TCPDF or FPDF
    
    $content = "%PDF-1.4\n";
    $content .= "1 0 obj\n";
    $content .= "<<\n";
    $content .= "/Type /Catalog\n";
    $content .= "/Pages 2 0 R\n";
    $content .= ">>\n";
    $content .= "endobj\n";
    
    $content .= "2 0 obj\n";
    $content .= "<<\n";
    $content .= "/Type /Pages\n";
    $content .= "/Kids [3 0 R]\n";
    $content .= "/Count 1\n";
    $content .= ">>\n";
    $content .= "endobj\n";
    
    $content .= "3 0 obj\n";
    $content .= "<<\n";
    $content .= "/Type /Page\n";
    $content .= "/Parent 2 0 R\n";
    $content .= "/MediaBox [0 0 612 792]\n";
    $content .= "/Contents 4 0 R\n";
    $content .= ">>\n";
    $content .= "endobj\n";
    
    $content .= "4 0 obj\n";
    $content .= "<<\n";
    $content .= "/Length 100\n";
    $content .= ">>\n";
    $content .= "stream\n";
    $content .= "BT\n";
    $content .= "/F1 12 Tf\n";
    $content .= "50 750 Td\n";
    $content .= "(Courier Management System Report) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Generated on: " . $date . ") Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Report Type: " . $type . ") Tj\n";
    $content .= "ET\n";
    $content .= "endstream\n";
    $content .= "endobj\n";
    
    $content .= "xref\n";
    $content .= "0 5\n";
    $content .= "0000000000 65535 f \n";
    $content .= "0000000009 00000 n \n";
    $content .= "0000000058 00000 n \n";
    $content .= "0000000115 00000 n \n";
    $content .= "0000000204 00000 n \n";
    $content .= "trailer\n";
    $content .= "<<\n";
    $content .= "/Size 5\n";
    $content .= "/Root 1 0 R\n";
    $content .= ">>\n";
    $content .= "startxref\n";
    $content .= "350\n";
    $content .= "%%EOF\n";
    
    return $content;
}
?> 