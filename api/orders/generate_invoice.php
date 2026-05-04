<?php
session_start();
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define FPDF font path explicitly BEFORE requiring fpdf
define('FPDF_FONTPATH', __DIR__ . '/../libs/font/');

require_once __DIR__ . '/../../config/database.php';

// Prevent any unexpected output impacting the PDF
while (ob_get_level()) ob_end_clean();

require_once __DIR__ . '/../libs/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    die('Please login to view invoice');
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    die('Invalid Order ID');
}

$conn = getDBConnection();

// Get Order Details
$sql = "SELECT o.*, u.first_name, u.last_name, u.email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ? AND o.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die('Order not found or access denied');
}

// Get Order Items
$item_sql = "SELECT oi.*, i.ingredient_name 
             FROM order_items oi 
             JOIN ingredients i ON oi.ingredient_id = i.id 
             WHERE oi.order_id = ?";
$item_stmt = $conn->prepare($item_sql);
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items_result = $item_stmt->get_result();
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}

closeDBConnection($conn);

class PDF extends FPDF
{
    function Header()
    {
        // Company Logo/Header
        $this->SetFillColor(44, 62, 80); // Dark Blue
        $this->Rect(0, 0, 210, 40, 'F');
        
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 24);
        $this->SetXY(10, 10);
        $this->Cell(0, 10, 'SMART MEAL PLANNER', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->SetXY(10, 22);
        $this->Cell(0, 5, 'Your Personalized Nutrition Partner', 0, 1, 'L');
        
        $this->SetXY(150, 10);
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(50, 10, 'INVOICE', 0, 1, 'R');
        
        $this->Ln(20);
    }

    function Footer()
    {
        $this->SetY(-30);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
        
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'Thank you for your business!', 0, 1, 'C');
        $this->Cell(0, 5, 'Smart Meal Planner - Providing healthy choices every day.', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    function CleanText($str) {
        // Convert UTF-8 to windows-1252 (standard FPDF encoding)
        // characters that can't be converted are transliterated or ignored
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $str);
    }

    function SectionTitle($label) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 10, "  " . $this->CleanText($label), 0, 1, 'L', true);
        $this->Ln(2);
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Invoice Details
$pdf->SetY(45);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(50, 50, 50);

$y_start = $pdf->GetY();

// Left Column: Billed To
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(95, 6, 'BILLED TO:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 5, $pdf->CleanText($order['first_name'] . ' ' . $order['last_name']), 0, 1);
$pdf->Cell(95, 5, $pdf->CleanText($order['email']), 0, 1);
if (!empty($order['delivery_address'])) {
    $pdf->MultiCell(95, 5, $pdf->CleanText($order['delivery_address']));
}

// Right Column: Invoice Info
$pdf->SetXY(110, $y_start);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(90, 6, 'INVOICE DETAILS:', 0, 1, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->SetX(110);
$pdf->Cell(30, 6, 'Order ID:', 0, 0);
$pdf->Cell(60, 6, $pdf->CleanText($order['order_number']), 0, 1, 'R');

$pdf->SetX(110);
$pdf->Cell(30, 6, 'Date:', 0, 0);
$pdf->Cell(60, 6, date('d M Y', strtotime($order['order_date'] ?? 'now')), 0, 1, 'R');

$pdf->SetX(110);
$pdf->Cell(30, 6, 'Status:', 0, 0);
$pdf->SetFont('Arial', 'B', 10);
if ($order['status'] == 'confirmed') $pdf->SetTextColor(46, 204, 113); // Green
else $pdf->SetTextColor(50, 50, 50);
$pdf->Cell(60, 6, strtoupper($order['status']), 0, 1, 'R');

$pdf->SetTextColor(50, 50, 50); // Reset color
$pdf->SetFont('Arial', '', 10);

$pdf->Ln(20);

// Items Table Header
$pdf->SetFillColor(44, 62, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(44, 62, 80);
$pdf->SetLineWidth(.3);
$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell(10, 10, '#', 1, 0, 'C', true);
$pdf->Cell(100, 10, 'Item Description', 1, 0, 'L', true);
$pdf->Cell(25, 10, 'Quantity', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Unit Price', 1, 0, 'R', true);
$pdf->Cell(25, 10, 'Total', 1, 1, 'R', true);

// Table Rows
$pdf->SetFillColor(245, 245, 245);
$pdf->SetTextColor(50, 50, 50);
$pdf->SetFont('Arial', '', 10);

$fill = false;
$i = 1;
$grand_total = 0;

foreach ($items as $item) {
    // Determine row height based on name length
    $cellWidth = 100;
    $cellHeight = 8;
    
    // Check if name needs multiline
    if($pdf->GetStringWidth($item['ingredient_name']) < $cellWidth){
        $line = 1;
    }else{
        $line = 2; // Simple approximation
    }
    $height = $line * $cellHeight;

    $pdf->Cell(10, 10, $i++, 'LRB', 0, 'C', $fill);
    $pdf->Cell(100, 10, $pdf->CleanText($item['ingredient_name']), 'LRB', 0, 'L', $fill);
    $pdf->Cell(25, 10, $pdf->CleanText($item['quantity'] . ' ' . $item['unit']), 'LRB', 0, 'C', $fill);
    $pdf->Cell(30, 10, 'Rs. ' . number_format($item['price'], 2), 'LRB', 0, 'R', $fill);
    $pdf->Cell(25, 10, 'Rs. ' . number_format($item['subtotal'], 2), 'LRB', 1, 'R', $fill);
    
    $grand_total += $item['subtotal'];
    $fill = !$fill; // Alternating colors
}

// Totals
$pdf->Ln(5);
$pdf->SetX(120);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(45, 8, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(25, 8, 'Rs. ' . number_format($grand_total, 2), 0, 1, 'R');

$pdf->SetX(120);
$pdf->Cell(45, 8, 'Tax (0%):', 0, 0, 'R');
$pdf->Cell(25, 8, 'Rs. 0.00', 0, 1, 'R');

$pdf->SetX(120);
$pdf->SetFillColor(44, 62, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(45, 10, 'GRAND TOTAL', 1, 0, 'R', true);
$pdf->Cell(25, 10, 'Rs. ' . number_format($grand_total, 2), 1, 1, 'R', true);

$pdf->Output('D', 'Invoice_' . $order['order_number'] . '.pdf');
?>
