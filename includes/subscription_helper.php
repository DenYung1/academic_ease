<?php
/**
 * Subscription management helper functions
 * Handles parent-child subscription relationships
 */

/**
 * Update a user's subscription status and plan
 * If the user is a student, also update linked parent accounts
 * 
 * @param string $user_id The user ID to update
 * @param string $subscription_status The new subscription status
 * @param string $subscription_plan The new subscription plan
 * @param string $subscription_end_date The new subscription end date
 * @return bool True if successful, false otherwise
 */
function update_user_subscription($user_id, $subscription_status, $subscription_plan, $subscription_end_date) {
    // Log the update attempt
    error_log("Updating subscription for user $user_id: Status=$subscription_status, Plan=$subscription_plan, End date=$subscription_end_date");
    
    // Get the user's type to determine if we need to update linked accounts
    $user_data = supabaseRequest("users?user_id=eq.$user_id", 'GET', null, true);
    
    if (empty($user_data) || !isset($user_data[0]['user_type'])) {
        error_log("Failed to get user type for user $user_id");
        return false;
    }
    
    $user_type = $user_data[0]['user_type'];
    
    // For debugging - log the user type
    error_log("Processing subscription update for $user_type account $user_id");
    
    // Update the user's subscription
    $userData = [
        'subscription_status' => $subscription_status,
        'subscription_plan' => $subscription_plan,
        'subscription_end_date' => $subscription_end_date
        // Removed admin_renewed field as it doesn't exist in the database
    ];
    
    // Add detailed logging
    error_log("Updating subscription with data: " . json_encode($userData));
    
    // Use service role key to ensure we have proper permissions
    $result = supabaseRequest("users?user_id=eq.$user_id", 'PATCH', $userData, true);
    
    // Log the result for debugging
    error_log("Subscription update result: " . json_encode($result));
    
    // Check for specific error conditions
    if (isset($result['error']) && $result['error'] === true) {
        error_log("Subscription update error: " . ($result['message'] ?? 'Unknown error'));
        return false;
    } else if (empty($result)) {
        error_log("Failed to update subscription for user $user_id - empty result");
        return false;
    }
    
    // If the user is a student, update all linked parent accounts
    if ($user_type === 'student') {
        // Get all linked parents for this student
        $linked_parents = supabaseRequest("parent_child?child_id=eq.$user_id", 'GET', null, true);
        
        if (!empty($linked_parents)) {
            foreach ($linked_parents as $link) {
                $parent_id = $link['parent_id'];
                
                // Update parent with same subscription details
                $parent_update = supabaseRequest("users?user_id=eq.$parent_id", 'PATCH', $userData, true);
                
                if (!empty($parent_update)) {
                    error_log("Updated subscription for parent $parent_id to match student $user_id");
                } else {
                    error_log("Failed to update subscription for parent $parent_id");
                }
            }
        }
    }
    
    return true;
}

/**
 * Update subscription status for all parents linked to a student
 * 
 * @param string $student_id The student's user ID
 * @param string $subscription_status The new subscription status
 * @param string $subscription_plan The new subscription plan
 * @param string $subscription_end_date The new subscription end date
 */
function update_linked_parent_subscriptions($student_id, $subscription_status, $subscription_plan, $subscription_end_date) {
    // Get all linked parents for this student
    $linked_parents = supabaseRequest("parent_child?child_id=eq.$student_id", 'GET', null, true);
    
    if (empty($linked_parents)) {
        error_log("No linked parents found for student $student_id");
        return;
    }
    
    error_log("Found " . count($linked_parents) . " linked parents for student $student_id");
    
    // Update each linked parent's subscription
    foreach ($linked_parents as $link) {
        $parent_id = $link['parent_id'];
        
        // Get the parent's current data
        $parent_data = supabaseRequest("users?user_id=eq.$parent_id", 'GET', null, true);
        
        if (empty($parent_data) || !isset($parent_data[0])) {
            error_log("Failed to get data for parent $parent_id");
            continue;
        }
        
        $parent = $parent_data[0];
        
        // Check if the parent has other children with active subscriptions
        $has_other_active_children = has_other_active_children($parent_id, $student_id);
        
        // Only update the parent's subscription if they don't have other active children
        if (!$has_other_active_children) {
            $parent_update = [
                'subscription_status' => $subscription_status,
                'subscription_plan' => $subscription_plan,
                'subscription_end_date' => $subscription_end_date
            ];
            
            $update_result = supabaseRequest("users?user_id=eq.$parent_id", 'PATCH', $parent_update, true);
            
            if (!empty($update_result)) {
                error_log("Updated subscription for parent $parent_id based on student $student_id");
            } else {
                error_log("Failed to update subscription for parent $parent_id");
            }
        } else {
            error_log("Parent $parent_id has other active children, not updating subscription");
        }
    }
}

/**
 * Check if a parent has other children with active subscriptions
 * 
 * @param string $parent_id The parent's user ID
 * @param string $excluded_student_id A student ID to exclude from the check
 * @return bool True if the parent has other active children, false otherwise
 */
function has_other_active_children($parent_id, $excluded_student_id) {
    // Get all linked children for this parent
    $linked_children = supabaseRequest("parent_child?parent_id=eq.$parent_id", 'GET', null, true);
    
    if (empty($linked_children)) {
        return false;
    }
    
    // Check each child's subscription status
    foreach ($linked_children as $link) {
        $child_id = $link['child_id'];
        
        // Skip the excluded student
        if ($child_id === $excluded_student_id) {
            continue;
        }
        
        // Get the child's data
        $child_data = supabaseRequest("users?user_id=eq.$child_id", 'GET', null, true);
        
        if (empty($child_data) || !isset($child_data[0])) {
            continue;
        }
        
        $child = $child_data[0];
        
        // Check if the child has an active subscription
        if (isset($child['subscription_status']) && 
            ($child['subscription_status'] === 'active' || $child['subscription_status'] === 'trial')) {
            
            // Also check if the subscription is not expired
            if (isset($child['subscription_end_date'])) {
                $end_date = strtotime($child['subscription_end_date']);
                $current_time = time();
                
                if ($end_date > $current_time) {
                    return true; // Found another active child
                }
            }
        }
    }
    
    return false; // No other active children found
}

/**
 * Sync all parent-child subscriptions to ensure consistency
 * This function makes sure all parent accounts have the same subscription as their linked students
 * @return bool True if successful, false otherwise
 */
function sync_all_parent_child_subscriptions() {
    // Get all parent-child relationships
    $parent_child_links = supabaseRequest("parent_child", 'GET', null, true);
    
    if (empty($parent_child_links)) {
        error_log("No parent-child relationships found");
        return false;
    }
    
    $updated_count = 0;
    
    foreach ($parent_child_links as $link) {
        $parent_id = $link['parent_id'];
        $child_id = $link['child_id'];
        
        // Get child data (student is the source of truth)
        $child_data = supabaseRequest("users?user_id=eq.$child_id", 'GET', null, true);
        
        if (empty($child_data) || !isset($child_data[0])) {
            error_log("Could not find child account: $child_id");
            continue;
        }
        
        $child = $child_data[0];
        
        // Get subscription details from child
        $subscription_status = $child['subscription_status'] ?? 'expired';
        $subscription_plan = $child['subscription_plan'] ?? 'trial';
        $subscription_end_date = $child['subscription_end_date'] ?? date('c');
        
        // Update parent with child's subscription details
        $update_data = [
            'subscription_status' => $subscription_status,
            'subscription_plan' => $subscription_plan,
            'subscription_end_date' => $subscription_end_date
        ];
        
        $result = supabaseRequest("users?user_id=eq.$parent_id", 'PATCH', $update_data, true);
        
        if (!empty($result)) {
            $updated_count++;
            error_log("Synced parent $parent_id subscription to match child $child_id");
        } else {
            error_log("Failed to sync subscription for parent $parent_id");
        }
    }
    
    error_log("Updated $updated_count parent accounts with their children's subscription details");
    return true;
}

/**
 * Check if a user's subscription is active
 * For parents, also check if any linked children have active subscriptions
 * 
 * @param string $user_id The user ID to check
 * @param array $user_data Optional user data array
 * @return bool True if the subscription is active, false otherwise
 */
function is_subscription_active($user_id, $user_data = null) {
    // If user data wasn't provided, fetch it
    if ($user_data === null) {
        $user_data_result = supabaseRequest("users?user_id=eq.$user_id", 'GET', null, true);
        
        if (empty($user_data_result) || !isset($user_data_result[0])) {
            error_log("Failed to get user data for subscription check: $user_id");
            return false;
        }
        
        $user_data = $user_data_result[0];
    }
    
    // Check if the user's own subscription is active
    $is_active = false;
    
    if (isset($user_data['subscription_status']) && 
        ($user_data['subscription_status'] === 'active' || $user_data['subscription_status'] === 'trial')) {
        
        // Also check if the subscription is not expired
        if (isset($user_data['subscription_end_date'])) {
            $end_date = strtotime($user_data['subscription_end_date']);
            $current_time = time();
            
            if ($end_date > $current_time) {
                $is_active = true;
            }
        }
    }
    
    // If the user is a parent, also check if any linked children have active subscriptions
    if (isset($user_data['user_type']) && $user_data['user_type'] === 'parent') {
        // Get all linked children
        $linked_children = supabaseRequest("parent_child?parent_id=eq.$user_id", 'GET', null, true);
        
        if (!empty($linked_children)) {
            foreach ($linked_children as $link) {
                $child_id = $link['child_id'];
                
                // Get the child's data
                $child_data = supabaseRequest("users?user_id=eq.$child_id", 'GET', null, true);
                
                if (empty($child_data) || !isset($child_data[0])) {
                    continue;
                }
                
                $child = $child_data[0];
                
                // Check if the child has an active subscription
                if (isset($child['subscription_status']) && 
                    ($child['subscription_status'] === 'active' || $child['subscription_status'] === 'trial')) {
                    
                    // Also check if the subscription is not expired
                    if (isset($child['subscription_end_date'])) {
                        $end_date = strtotime($child['subscription_end_date']);
                        $current_time = time();
                        
                        if ($end_date > $current_time) {
                            $is_active = true;
                            break; // Found an active child, no need to check others
                        }
                    }
                }
            }
        }
    }
    
    return $is_active;
}
?>
