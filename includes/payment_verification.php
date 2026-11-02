<?php
function verify_payment($user_id, $plan) {
    try {
        // Check if there's a valid confirmation for this user and plan
        $filter = "user_id=eq." . urlencode($user_id) . "&plan=eq." . urlencode($plan) . "&status=eq.confirmed";
        $confirmations = supabaseRequest("payment_confirmations?$filter", 'GET', null, true);
        
        if (empty($confirmations)) {
            // No confirmed payment found
            return false;
        }
        
        // Get the confirmation details
        $confirmation = $confirmations[0];
        
        // Verify that the confirmation ID matches the session
        if (isset($_SESSION['payment_confirmation_id']) && 
            $_SESSION['payment_confirmation_id'] === $confirmation['confirmation_id']) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log('Payment verification error: ' . $e->getMessage());
        return false;
    }
}
?>
