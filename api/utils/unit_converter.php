<?php
/**
 * Unit Converter Utility
 * Handles normalization of quantities to a base unit (g or ml) for comparison
 */

function normalizeQuantity($quantity, $unit) {
    $unit = strtolower(trim($unit));
    $baseQuantity = $quantity;
    $baseUnit = $unit;

    // Weight conversions (Base: g)
    switch ($unit) {
        case 'kg':
        case 'kilogram':
        case 'kilograms':
            $baseQuantity = $quantity * 1000;
            $baseUnit = 'g';
            break;
        case 'g':
        case 'gram':
        case 'grams':
            $baseQuantity = $quantity;
            $baseUnit = 'g';
            break;
        case 'mg':
        case 'milligram':
        case 'milligrams':
            $baseQuantity = $quantity / 1000;
            $baseUnit = 'g';
            break;
        
        // Volume conversions (Base: ml)
        case 'l':
        case 'liter':
        case 'liters':
        case 'litre':
        case 'litres':
            $baseQuantity = $quantity * 1000;
            $baseUnit = 'ml';
            break;
        case 'ml':
        case 'milliliter':
        case 'milliliters':
        case 'millilitre':
        case 'millilitres':
            $baseQuantity = $quantity;
            $baseUnit = 'ml';
            break;
            
        // Kitchen units (Approximate)
        case 'tbsp':
        case 'tablespoon':
        case 'tablespoons':
            $baseQuantity = $quantity * 15;
            $baseUnit = 'ml'; // or g depending on substance, using ml as default for fluid
            break;
        case 'tsp':
        case 'teaspoon':
        case 'teaspoons':
            $baseQuantity = $quantity * 5;
            $baseUnit = 'ml';
            break;
        case 'cup':
        case 'cups':
            $baseQuantity = $quantity * 240;
            $baseUnit = 'ml';
            break;
    }

    return [
        'quantity' => $baseQuantity,
        'unit' => $baseUnit
    ];
}

/**
 * Compare two quantities with different units
 * Returns true if available is greater than or equal to required
 */
function hasEnoughInventory($availableQty, $availableUnit, $requiredQty, $requiredUnit) {
    $normalizedAvailable = normalizeQuantity($availableQty, $availableUnit);
    $normalizedRequired = normalizeQuantity($requiredQty, $requiredUnit);
    
    // If base units are different (e.g., g vs ml), we assume they are comparable if both are weight/volume 
    // for simple logic, but ideally we'd check compatibility.
    return $normalizedAvailable['quantity'] >= $normalizedRequired['quantity'];
}

/**
 * Calculate remaining quantity after deduction with unit support
 */
function calculateRemaining($availableQty, $availableUnit, $deductQty, $deductUnit) {
    $normalizedAvailable = normalizeQuantity($availableQty, $availableUnit);
    $normalizedDeduct = normalizeQuantity($deductQty, $deductUnit);
    
    $remainingBase = $normalizedAvailable['quantity'] - $normalizedDeduct['quantity'];
    
    // Convert back to original available unit if possible
    if ($availableUnit === 'kg' || $availableUnit === 'kilogram') {
        return [
            'quantity' => $remainingBase / 1000,
            'unit' => $availableUnit
        ];
    } elseif ($availableUnit === 'l' || $availableUnit === 'liter') {
        return [
            'quantity' => $remainingBase / 1000,
            'unit' => $availableUnit
        ];
    }
    
    return [
        'quantity' => $remainingBase,
        'unit' => $normalizedAvailable['unit']
    ];
}
?>
