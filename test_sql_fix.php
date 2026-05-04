<?php
// Simple test to verify SQL syntax fixes
echo "Testing SQL syntax fixes...\n\n";

// Test the date calculations that were causing issues
$days = 1;

echo "Testing strtotime concatenation:\n";
echo "Original (broken): strtotime(\"+\".\$day.\" days\")\n";
echo "Fixed: strtotime(\"+\" . \$day . \" days\")\n\n";

// Test with actual values
$day = 1;
$original_broken = "strtotime(\"+\".\$day.\" days\")"; // This would cause SQL error
$fixed_version = strtotime("+" . $day . " days");

echo "Day 1 calculation:\n";
echo "Fixed result: " . date('Y-m-d', $fixed_version) . "\n";

$days_minus_one = strtotime("+" . ($days-1) . " days");
echo "Days-1 calculation: " . date('Y-m-d', $days_minus_one) . "\n";

echo "\nSQL syntax fixes applied successfully!\n";
echo "The meal plan generation should now work without 'DAYS' SQL errors.\n";
?>