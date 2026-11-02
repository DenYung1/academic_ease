<?php
// Helper functions for parent-child linking

/**
 * Link a child to a parent - Comprehensive approach with transaction-like behavior
 * 
 * @param string $parent_id The parent's user ID
 * @param string $child_id The child's user ID
 * @return array Array with success status and message
 */
function link_child_to_parent($parent_id, $child_id) {
    error_log("LINK: Attempting to link child $child_id to parent $parent_id");
    
    // First, check if the child exists and is a student
    $child_data = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'GET', null, true);
    if (empty($child_data) || !isset($child_data[0]['user_type']) || $child_data[0]['user_type'] !== 'student') {
        error_log("LINK: Child with ID $child_id not found or not a student");
        return ['success' => false, 'message' => 'Child not found or not a student'];
    }
    $child = $child_data[0];
    $child_name = isset($child['full_name']) ? $child['full_name'] : 'Unknown';
    
    // Check if the parent exists and is a parent
    $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
    if (empty($parent_data) || !isset($parent_data[0]['user_type']) || $parent_data[0]['user_type'] !== 'parent') {
        error_log("LINK: Parent with ID $parent_id not found or not a parent");
        return ['success' => false, 'message' => 'Parent not found or not a parent'];
    }
    $parent = $parent_data[0];
    
    // Check if already linked
    $existing_links = get_linked_children($parent_id);
    foreach ($existing_links as $existing_child) {
        if (isset($existing_child['user_id']) && $existing_child['user_id'] === $child_id) {
            error_log("LINK: Child $child_id is already linked to parent $parent_id");
            return ['success' => true, 'message' => 'Child is already linked to this parent'];
        }
    }
    
    // Store original values for rollback if needed
    $original_child_school = isset($child['school_name']) ? $child['school_name'] : null;
    $original_parent_school = isset($parent['school_name']) ? $parent['school_name'] : null;
    $original_parent_metadata = isset($parent['metadata']) ? $parent['metadata'] : null;
    
    // Track success of each method
    $method_results = [
        'method1' => false,
        'method2' => false,
        'method3' => false
    ];
    
    // Method 1: Update student's school_name
    $child_update = [
        'school_name' => 'PARENT:' . $parent_id
    ];
    $child_result = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'PATCH', $child_update, true);
    $method_results['method1'] = !empty($child_result);
    error_log("LINK: Method 1 (Update student's school_name): " . ($method_results['method1'] ? "SUCCESS" : "FAILURE"));
    
    // Method 2: Update parent's school_name
    $parent_update = [
        'school_name' => 'CHILD:' . $child_id . ':' . $child_name
    ];
    $parent_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $parent_update, true);
    $method_results['method2'] = !empty($parent_result);
    error_log("LINK: Method 2 (Update parent's school_name): " . ($method_results['method2'] ? "SUCCESS" : "FAILURE"));
    
    // Method 3: Update parent's metadata
    $parent_metadata = isset($parent['metadata']) && !empty($parent['metadata']) ? 
        json_decode($parent['metadata'], true) : [];
    
    if (!is_array($parent_metadata)) {
        $parent_metadata = [];
    }
    
    if (!isset($parent_metadata['linked_children'])) {
        $parent_metadata['linked_children'] = [];
    }
    
    if (!in_array($child_id, $parent_metadata['linked_children'])) {
        $parent_metadata['linked_children'][] = $child_id;
    }
    
    $metadata_update = [
        'metadata' => json_encode($parent_metadata)
    ];
    $metadata_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $metadata_update, true);
    $method_results['method3'] = !empty($metadata_result);
    error_log("LINK: Method 3 (Update parent's metadata): " . ($method_results['method3'] ? "SUCCESS" : "FAILURE"));
    
    // Check if we need to rollback due to partial failure
    $success_count = array_sum($method_results);
    
    if ($success_count > 0 && $success_count < 3) {
        error_log("LINK: Partial success detected. Attempting to repair inconsistencies.");
        // Call the audit function to repair any inconsistencies
        audit_and_repair_link($parent_id, $child_id);
    }
    
    $overall_success = $success_count > 0;
    error_log("LINK: Overall result = " . ($overall_success ? "SUCCESS" : "FAILURE"));
    
    if ($overall_success) {
        return ['success' => true, 'message' => 'Child linked successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to link child to parent'];
    }
}

/**
 * Get all children linked to a parent - Comprehensive approach
 * 
 * @param string $parent_id The parent's user ID
 * @return array Array of child user records
 */
function get_linked_children($parent_id) {
    $linked_children = [];
    $linked_child_ids = [];
    
    error_log("GET CHILDREN: Searching for children linked to parent $parent_id");
    
    // Method 1: Look for students with school_name = PARENT:parent_id
    $filter = "user_type=eq.student&school_name=like.PARENT:" . urlencode($parent_id) . "%";
    $children_by_school_name = supabaseRequest("users?$filter", 'GET', null, true);
    error_log("GET CHILDREN: School name query: " . $filter);
    
    if (!empty($children_by_school_name)) {
        error_log("GET CHILDREN: Found " . count($children_by_school_name) . " children by school_name");
        foreach ($children_by_school_name as $child) {
            if (!empty($child['user_id']) && !in_array($child['user_id'], $linked_child_ids)) {
                $linked_child_ids[] = $child['user_id'];
                $linked_children[] = $child;
            }
        }
    }
    
    // Method 2: Check if parent has school_name with CHILD:child_id format
    $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
    if (!empty($parent_data) && isset($parent_data[0]['school_name']) && !empty($parent_data[0]['school_name'])) {
        $school_name = $parent_data[0]['school_name'];
        error_log("GET CHILDREN: Parent school_name: $school_name");
        
        if (strpos($school_name, 'CHILD:') === 0) {
            $parts = explode(':', $school_name);
            if (count($parts) >= 2) {
                $child_id = $parts[1];
                if (!empty($child_id) && !in_array($child_id, $linked_child_ids)) {
                    // Get the child's data
                    $child_data = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'GET', null, true);
                    if (!empty($child_data)) {
                        $linked_child_ids[] = $child_id;
                        $linked_children[] = $child_data[0];
                        error_log("GET CHILDREN: Found child in parent's school_name: $child_id");
                    }
                }
            }
        }
    }
    
    // Method 3: Check parent's metadata for linked_children
    if (!empty($parent_data) && isset($parent_data[0]['metadata']) && !empty($parent_data[0]['metadata'])) {
        $metadata = json_decode($parent_data[0]['metadata'], true);
        if (is_array($metadata) && isset($metadata['linked_children']) && is_array($metadata['linked_children'])) {
            foreach ($metadata['linked_children'] as $child_id) {
                if (!empty($child_id) && !in_array($child_id, $linked_child_ids)) {
                    // Get the child's data
                    $child_data = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'GET', null, true);
                    if (!empty($child_data)) {
                        $linked_child_ids[] = $child_id;
                        $linked_children[] = $child_data[0];
                        error_log("GET CHILDREN: Found child in parent's metadata: $child_id");
                    }
                }
            }
        }
    }
    
    error_log("GET CHILDREN: Total linked children found: " . count($linked_children));
    return $linked_children;
}

/**
 * Audit and repair parent-child links to ensure data consistency
 * 
 * @param string $parent_id The parent's user ID
 * @param string $child_id The child's user ID
 * @return array Results of the audit and repair operations
 */
function audit_and_repair_link($parent_id, $child_id) {
    error_log("AUDIT: Starting audit and repair for parent $parent_id and child $child_id");
    
    $results = [
        'method1_status' => false,
        'method2_status' => false,
        'method3_status' => false,
        'repairs_made' => 0
    ];
    
    // Get current data
    $child_data = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'GET', null, true);
    $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
    
    if (empty($child_data) || empty($parent_data)) {
        error_log("AUDIT: Could not retrieve data for parent or child");
        return $results;
    }
    
    $child = $child_data[0];
    $parent = $parent_data[0];
    $child_name = isset($child['full_name']) ? $child['full_name'] : 'Unknown';
    
    // Check Method 1: Student's school_name
    $method1_ok = isset($child['school_name']) && $child['school_name'] === 'PARENT:' . $parent_id;
    $results['method1_status'] = $method1_ok;
    
    // Check Method 2: Parent's school_name
    $method2_ok = isset($parent['school_name']) && strpos($parent['school_name'], 'CHILD:' . $child_id) === 0;
    $results['method2_status'] = $method2_ok;
    
    // Check Method 3: Parent's metadata
    $parent_metadata = isset($parent['metadata']) && !empty($parent['metadata']) ? 
        json_decode($parent['metadata'], true) : [];
    
    if (!is_array($parent_metadata)) {
        $parent_metadata = [];
    }
    
    $has_child_in_metadata = isset($parent_metadata['linked_children']) && 
                             is_array($parent_metadata['linked_children']) && 
                             in_array($child_id, $parent_metadata['linked_children']);
    $results['method3_status'] = $has_child_in_metadata;
    
    // Repair inconsistencies
    if (!$method1_ok) {
        error_log("AUDIT: Repairing Method 1 (student's school_name)");
        $child_update = [
            'school_name' => 'PARENT:' . $parent_id
        ];
        $child_result = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'PATCH', $child_update, true);
        if (!empty($child_result)) {
            $results['repairs_made']++;
            $results['method1_status'] = true;
        }
    }
    
    if (!$method2_ok) {
        error_log("AUDIT: Repairing Method 2 (parent's school_name)");
        $parent_update = [
            'school_name' => 'CHILD:' . $child_id . ':' . $child_name
        ];
        $parent_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $parent_update, true);
        if (!empty($parent_result)) {
            $results['repairs_made']++;
            $results['method2_status'] = true;
        }
    }
    
    if (!$has_child_in_metadata) {
        error_log("AUDIT: Repairing Method 3 (parent's metadata)");
        if (!isset($parent_metadata['linked_children'])) {
            $parent_metadata['linked_children'] = [];
        }
        
        if (!in_array($child_id, $parent_metadata['linked_children'])) {
            $parent_metadata['linked_children'][] = $child_id;
        }
        
        $metadata_update = [
            'metadata' => json_encode($parent_metadata)
        ];
        $metadata_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $metadata_update, true);
        if (!empty($metadata_result)) {
            $results['repairs_made']++;
            $results['method3_status'] = true;
        }
    }
    
    error_log("AUDIT: Completed with " . $results['repairs_made'] . " repairs");
    return $results;
}

/**
 * Unlink a child from a parent - Comprehensive approach with transaction-like behavior
 * 
 * @param string $parent_id The parent's user ID
 * @param string $child_id The child's user ID
 * @return array Array with success status and message
 */
function unlink_child_from_parent($parent_id, $child_id) {
    error_log("UNLINK: Attempting to unlink child $child_id from parent $parent_id");
    
    // Get the child's record
    $child_data = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'GET', null, true);
    if (empty($child_data)) {
        error_log("UNLINK: Child with ID $child_id not found");
        return ['success' => false, 'message' => 'Child not found'];
    }
    error_log("UNLINK: Child data: " . json_encode($child_data));
    
    // Get the parent's record
    $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
    if (empty($parent_data)) {
        error_log("UNLINK: Parent with ID $parent_id not found");
        return ['success' => false, 'message' => 'Parent not found'];
    }
    $parent = $parent_data[0];
    error_log("UNLINK: Parent data: " . json_encode($parent));
    
    // Track success of each method
    $method_results = [
        'method1' => false,
        'method2' => false,
        'method3' => false
    ];
    
    // Method 1: Clear student's school_name
    $child_update = [
        'school_name' => null
    ];
    $child_result = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'PATCH', $child_update, true);
    $method_results['method1'] = !empty($child_result);
    error_log("UNLINK: Method 1 (Clear student's school_name): " . ($method_results['method1'] ? "SUCCESS" : "FAILURE"));
    error_log("UNLINK: Method 1 result: " . json_encode($child_result));
    
    // Method 2: Clear parent's school_name if it contains this child
    if (isset($parent['school_name']) && !empty($parent['school_name'])) {
        $school_name = $parent['school_name'];
        if (strpos($school_name, 'CHILD:' . $child_id) === 0) {
            $parent_update = [
                'school_name' => null
            ];
            $parent_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $parent_update, true);
            $method_results['method2'] = !empty($parent_result);
            error_log("UNLINK: Method 2 (Clear parent's school_name): " . ($method_results['method2'] ? "SUCCESS" : "FAILURE"));
        } else {
            $method_results['method2'] = true; // No need to update if not linked in this way
        }
    } else {
        $method_results['method2'] = true; // No need to update if school_name is empty
    }
    
    // Method 3: Remove child from parent's metadata
    if (isset($parent['metadata']) && !empty($parent['metadata'])) {
        $metadata = json_decode($parent['metadata'], true);
        if (is_array($metadata) && isset($metadata['linked_children']) && is_array($metadata['linked_children'])) {
            $key = array_search($child_id, $metadata['linked_children']);
            if ($key !== false) {
                // Remove the child ID from the array
                unset($metadata['linked_children'][$key]);
                // Reindex the array
                $metadata['linked_children'] = array_values($metadata['linked_children']);
                
                $metadata_update = [
                    'metadata' => json_encode($metadata)
                ];
                $metadata_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $metadata_update, true);
                $method_results['method3'] = !empty($metadata_result);
                error_log("UNLINK: Method 3 (Remove from parent's metadata): " . ($method_results['method3'] ? "SUCCESS" : "FAILURE"));
            } else {
                $method_results['method3'] = true; // Child not in metadata, so no need to update
            }
        } else {
            $method_results['method3'] = true; // No linked_children array, so no need to update
        }
    } else {
        error_log("UNLINK: Method 3 skipped - parent has no metadata");
    }
    
    // Check if we need to repair due to partial failure
    $success_count = array_sum($method_results);
    
    if ($success_count > 0 && $success_count < 3) {
        error_log("UNLINK: Partial success detected. Attempting to repair inconsistencies.");
        // Call the audit function to verify the unlinking was complete
        $audit_results = audit_parent_child_relationship($parent_id, $child_id);
        if ($audit_results['is_linked']) {
            error_log("UNLINK: Audit found remaining links. Forcing complete unlink.");
            force_unlink_child($parent_id, $child_id);
        }
    }
    
    $overall_success = $success_count > 0;
    error_log("UNLINK: Overall result = " . ($overall_success ? "SUCCESS" : "FAILURE"));
    
    if ($overall_success) {
        return ['success' => true, 'message' => 'Child unlinked successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to unlink child from parent'];
    }
}

/**
 * Audit the parent-child relationship to check if they are properly linked or unlinked
 * 
 * @param string $parent_id The parent's user ID
 * @param string $child_id The child's user ID
 * @return array Results of the audit
 */
function audit_parent_child_relationship($parent_id, $child_id) {
    error_log("AUDIT RELATIONSHIP: Checking relationship between parent $parent_id and child $child_id");
    
    $results = [
        'is_linked' => false,
        'method1_linked' => false,
        'method2_linked' => false,
        'method3_linked' => false,
        'inconsistent' => false
    ];
    
    // Get current data
    $child_data = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'GET', null, true);
    $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
    
    if (empty($child_data) || empty($parent_data)) {
        error_log("AUDIT RELATIONSHIP: Could not retrieve data for parent or child");
        return $results;
    }
    
    $child = $child_data[0];
    $parent = $parent_data[0];
    
    // Check Method 1: Student's school_name
    $method1_linked = isset($child['school_name']) && $child['school_name'] === 'PARENT:' . $parent_id;
    $results['method1_linked'] = $method1_linked;
    
    // Check Method 2: Parent's school_name
    $method2_linked = isset($parent['school_name']) && strpos($parent['school_name'], 'CHILD:' . $child_id) === 0;
    $results['method2_linked'] = $method2_linked;
    
    // Check Method 3: Parent's metadata
    $parent_metadata = isset($parent['metadata']) && !empty($parent['metadata']) ? 
        json_decode($parent['metadata'], true) : [];
    
    if (!is_array($parent_metadata)) {
        $parent_metadata = [];
    }
    
    $method3_linked = isset($parent_metadata['linked_children']) && 
                      is_array($parent_metadata['linked_children']) && 
                      in_array($child_id, $parent_metadata['linked_children']);
    $results['method3_linked'] = $method3_linked;
    
    // Determine overall linked status
    $results['is_linked'] = $method1_linked || $method2_linked || $method3_linked;
    
    // Check for inconsistencies
    $results['inconsistent'] = ($method1_linked !== $method2_linked) || 
                              ($method1_linked !== $method3_linked) || 
                              ($method2_linked !== $method3_linked);
    
    error_log("AUDIT RELATIONSHIP: Results - is_linked: " . ($results['is_linked'] ? 'true' : 'false') . 
              ", inconsistent: " . ($results['inconsistent'] ? 'true' : 'false'));
    
    return $results;
}

/**
 * Force unlink a child from a parent by directly setting all fields to null
 * This is a last resort method when normal unlinking fails
 * 
 * @param string $parent_id The parent's user ID
 * @param string $child_id The child's user ID
 * @return bool True if successful, false otherwise
 */
function force_unlink_child($parent_id, $child_id) {
    error_log("FORCE UNLINK: Forcing unlink of child $child_id from parent $parent_id");
    
    $success = true;
    
    // Force clear student's school_name
    $child_update = [
        'school_name' => null
    ];
    $child_result = supabaseRequest("users?user_id=eq." . urlencode($child_id), 'PATCH', $child_update, true);
    if (empty($child_result)) {
        error_log("FORCE UNLINK: Failed to clear student's school_name");
        $success = false;
    }
    
    // Get parent data
    $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
    if (!empty($parent_data)) {
        $parent = $parent_data[0];
        
        // Force clear parent's school_name if it contains this child
        if (isset($parent['school_name']) && strpos($parent['school_name'], 'CHILD:' . $child_id) === 0) {
            $parent_update = [
                'school_name' => null
            ];
            $parent_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $parent_update, true);
            if (empty($parent_result)) {
                error_log("FORCE UNLINK: Failed to clear parent's school_name");
                $success = false;
            }
        }
        
        // Force remove child from parent's metadata
        if (isset($parent['metadata']) && !empty($parent['metadata'])) {
            $metadata = json_decode($parent['metadata'], true);
            if (is_array($metadata) && isset($metadata['linked_children'])) {
                $key = array_search($child_id, $metadata['linked_children']);
                if ($key !== false) {
                    unset($metadata['linked_children'][$key]);
                    $metadata['linked_children'] = array_values($metadata['linked_children']);
                    
                    $metadata_update = [
                        'metadata' => json_encode($metadata)
                    ];
                    $metadata_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $metadata_update, true);
                    if (empty($metadata_result)) {
                        error_log("FORCE UNLINK: Failed to update parent's metadata");
                        $success = false;
                    }
                }
            }
        }
    } else {
        error_log("FORCE UNLINK: Could not retrieve parent data");
        $success = false;
    }
    
    return $success;
}

/**
 * Perform a comprehensive audit of all parent-child relationships in the system
 * This function should be run periodically to ensure data consistency
 * 
 * @param bool $fix_issues Whether to automatically fix inconsistencies found
 * @return array Audit results including statistics and issues found
 */
function audit_all_parent_child_relationships($fix_issues = true) {
    error_log("AUDIT ALL: Starting comprehensive audit of all parent-child relationships");
    
    $results = [
        'total_parents' => 0,
        'total_students' => 0,
        'total_relationships' => 0,
        'inconsistent_relationships' => 0,
        'orphaned_students' => 0,
        'repairs_made' => 0,
        'issues' => []
    ];
    
    // Get all parents
    $parents = supabaseRequest("users?user_type=eq.parent", 'GET', null, true);
    $results['total_parents'] = count($parents);
    
    // Get all students
    $students = supabaseRequest("users?user_type=eq.student", 'GET', null, true);
    $results['total_students'] = count($students);
    
    // Check each parent for linked children
    foreach ($parents as $parent) {
        $parent_id = $parent['user_id'];
        $linked_children = get_linked_children($parent_id);
        
        foreach ($linked_children as $child) {
            $results['total_relationships']++;
            $child_id = $child['user_id'];
            
            // Audit this specific relationship
            $audit_result = audit_parent_child_relationship($parent_id, $child_id);
            
            if ($audit_result['inconsistent']) {
                $results['inconsistent_relationships']++;
                $issue = [
                    'parent_id' => $parent_id,
                    'child_id' => $child_id,
                    'issue_type' => 'inconsistent_linking',
                    'details' => $audit_result
                ];
                $results['issues'][] = $issue;
                
                // Fix the issue if requested
                if ($fix_issues) {
                    error_log("AUDIT ALL: Fixing inconsistent relationship between parent $parent_id and child $child_id");
                    $repair_result = audit_and_repair_link($parent_id, $child_id);
                    if ($repair_result['repairs_made'] > 0) {
                        $results['repairs_made'] += $repair_result['repairs_made'];
                    }
                }
            }
        }
    }
    
    // Check for orphaned students (students with PARENT: prefix but no actual parent link)
    foreach ($students as $student) {
        $student_id = $student['user_id'];
        $school_name = isset($student['school_name']) ? $student['school_name'] : '';
        
        if (strpos($school_name, 'PARENT:') === 0) {
            $parent_id = substr($school_name, 7);
            $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
            
            if (empty($parent_data) || $parent_data[0]['user_type'] !== 'parent') {
                $results['orphaned_students']++;
                $issue = [
                    'student_id' => $student_id,
                    'issue_type' => 'orphaned_student',
                    'details' => "Student references non-existent parent: $parent_id"
                ];
                $results['issues'][] = $issue;
                
                // Fix the issue if requested
                if ($fix_issues) {
                    error_log("AUDIT ALL: Fixing orphaned student $student_id");
                    $update = ['school_name' => null];
                    $update_result = supabaseRequest("users?user_id=eq." . urlencode($student_id), 'PATCH', $update, true);
                    if (!empty($update_result)) {
                        $results['repairs_made']++;
                    }
                }
            }
        }
    }
    
    error_log("AUDIT ALL: Completed with " . count($results['issues']) . " issues found and " . 
              $results['repairs_made'] . " repairs made");
    return $results;
}

/**
 * Get the parent of a student - Comprehensive approach
 * 
 * @param string $student_id The student's user ID
 * @return array|null Parent user record or null if no parent is linked
 */
function get_student_parent($student_id) {
    error_log("GET PARENT: Searching for parent linked to student $student_id");
    
    // Method 1: Check if student has school_name with PARENT:parent_id format
    $student_data = supabaseRequest("users?user_id=eq." . urlencode($student_id), 'GET', null, true);
    if (!empty($student_data) && isset($student_data[0]['school_name']) && !empty($student_data[0]['school_name'])) {
        $school_name = $student_data[0]['school_name'];
        error_log("GET PARENT: Student school_name: $school_name");
        
        if (strpos($school_name, 'PARENT:') === 0) {
            $parent_id = substr($school_name, 7); // Remove 'PARENT:' prefix
            if (!empty($parent_id)) {
                // Get the parent's data
                $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
                if (!empty($parent_data)) {
                    error_log("GET PARENT: Found parent in student's school_name: $parent_id");
                    return $parent_data[0];
                }
            }
        }
    }
    
    // Method 2: Look for parents with school_name = CHILD:student_id
    $filter = "user_type=eq.parent&school_name=like.CHILD:" . urlencode($student_id) . "%";
    $parents_by_school_name = supabaseRequest("users?$filter", 'GET', null, true);
    error_log("GET PARENT: School name query: " . $filter);
    
    if (!empty($parents_by_school_name)) {
        error_log("GET PARENT: Found parent by school_name");
        return $parents_by_school_name[0];
    }
    
    // Method 3: Check all parents' metadata for linked_children containing this student_id
    $all_parents = supabaseRequest("users?user_type=eq.parent", 'GET', null, true);
    if (!empty($all_parents)) {
        foreach ($all_parents as $parent) {
            if (isset($parent['metadata']) && !empty($parent['metadata'])) {
                $metadata = json_decode($parent['metadata'], true);
                if (is_array($metadata) && isset($metadata['linked_children']) && 
                    is_array($metadata['linked_children']) && 
                    in_array($student_id, $metadata['linked_children'])) {
                    error_log("GET PARENT: Found parent in metadata: " . $parent['user_id']);
                    return $parent;
                }
            }
        }
    }
    
    error_log("GET PARENT: No parent found for student $student_id");
    return null;
}

/**
 * Get student progress data for a specific student
 * 
 * @param string $student_id The student's user ID
 * @return array Student progress data including quiz results, notes, and study plans
 */
function get_student_progress($student_id) {
    $progress = [
        'quiz_results' => [],
        'notes_count' => 0,
        'study_plans_count' => 0,
        'subject_performance' => [
            'Mathematics' => 0,
            'Science' => 0,
            'English' => 0,
            'Social Studies' => 0
        ],
        'recent_activity' => [],
        'improvement_areas' => [],
        'usage_stats' => [
            'hours_spent' => 0,
            'questions_answered' => 0,
            'study_sessions' => 0,
            'ai_assistant_usage' => 0,
            'last_active' => ''
        ]
    ];
    
    // Get quiz results for the student
    $quiz_results = supabaseRequest("quiz_results?user_id=eq." . urlencode($student_id) . "&order=created_at.desc", 'GET', null, true);
    
    // Debug the response
    error_log('Quiz results for student ' . $student_id . ': ' . json_encode($quiz_results));
    
    // Make sure quiz_results is an array
    if (!empty($quiz_results) && is_array($quiz_results)) {
        $progress['quiz_results'] = $quiz_results;
        
        // Calculate subject performance based on quiz results
        $subject_totals = [];
        $subject_counts = [];
        
        foreach ($quiz_results as $result) {
            // Make sure result is an array before accessing its elements
            if (!is_array($result)) {
                error_log('Invalid quiz result format: ' . json_encode($result));
                continue;
            }
            
            $subject = isset($result['subject']) && is_string($result['subject']) ? $result['subject'] : 'Other';
            $score = isset($result['score']) && is_numeric($result['score']) ? $result['score'] : 0;
            $total = isset($result['total_questions']) && is_numeric($result['total_questions']) ? $result['total_questions'] : 1;
            $percentage = ($total > 0) ? ($score / $total) * 100 : 0;
            
            if (!isset($subject_totals[$subject])) {
                $subject_totals[$subject] = 0;
                $subject_counts[$subject] = 0;
            }
            
            $subject_totals[$subject] += $percentage;
            $subject_counts[$subject]++;
            
            // Add to recent activity only if created_at exists and is a string
            if (isset($result['created_at']) && is_string($result['created_at'])) {
                $progress['recent_activity'][] = [
                    'type' => 'quiz',
                    'subject' => $subject,
                    'score' => $score,
                    'total' => $total,
                    'percentage' => $percentage,
                    'date' => $result['created_at']
                ];
            } else {
                // Use current date if created_at is missing
                $progress['recent_activity'][] = [
                    'type' => 'quiz',
                    'subject' => $subject,
                    'score' => $score,
                    'total' => $total,
                    'percentage' => $percentage,
                    'date' => date('c')
                ];
            }
        }
    } else {
        // If quiz_results is not an array or is empty, log it
        error_log('No valid quiz results found for student ' . $student_id);
    }
    
    // Calculate average performance for each subject if we have data
    if (!empty($subject_totals) && is_array($subject_totals)) {
        foreach ($subject_totals as $subject => $total) {
            $count = $subject_counts[$subject];
            $average = ($count > 0) ? $total / $count : 0;
            
            // Map subject to one of our main categories if possible
            if (stripos($subject, 'math') !== false) {
                $progress['subject_performance']['Mathematics'] = $average;
            } else if (stripos($subject, 'science') !== false || stripos($subject, 'physics') !== false || 
                      stripos($subject, 'chemistry') !== false || stripos($subject, 'biology') !== false) {
                $progress['subject_performance']['Science'] = $average;
            } else if (stripos($subject, 'english') !== false || stripos($subject, 'language') !== false || 
                      stripos($subject, 'literature') !== false) {
                $progress['subject_performance']['English'] = $average;
            } else if (stripos($subject, 'social') !== false || stripos($subject, 'history') !== false || 
                      stripos($subject, 'geography') !== false) {
                $progress['subject_performance']['Social Studies'] = $average;
            }
            
            // Identify improvement areas (subjects with scores below 70%)
            if ($average < 70) {
                // Create more specific recommendations based on the subject and score
                $recommendation = '';
                
                if ($average < 50) {
                    // Poor performance - needs significant improvement
                    switch(strtolower($subject)) {
                        case 'mathematics':
                        case 'math':
                            $recommendation = 'Struggling with Math fundamentals. Consider daily practice with basic concepts and formulas';
                            break;
                        case 'science':
                        case 'physics':
                        case 'chemistry':
                        case 'biology':
                            $recommendation = 'Needs help understanding core ' . $subject . ' concepts. Try visual learning resources and practical experiments';
                            break;
                        case 'english':
                        case 'language':
                        case 'literature':
                            $recommendation = 'Needs to improve reading comprehension and writing skills. Encourage daily reading and vocabulary practice';
                            break;
                        case 'social studies':
                        case 'history':
                        case 'geography':
                            $recommendation = 'Having difficulty with ' . $subject . ' facts and concepts. Try using timelines, maps, and visual aids';
                            break;
                        default:
                            $recommendation = 'Significant improvement needed in ' . $subject . '. Consider additional support or tutoring';
                    }
                } else {
                    // Moderate performance - needs some improvement
                    switch(strtolower($subject)) {
                        case 'mathematics':
                        case 'math':
                            $recommendation = 'Could benefit from more practice with problem-solving in Math. Focus on applying formulas to real-world examples';
                            break;
                        case 'science':
                        case 'physics':
                        case 'chemistry':
                        case 'biology':
                            $recommendation = 'Showing moderate understanding of ' . $subject . '. Encourage more practice with complex concepts';
                            break;
                        case 'english':
                        case 'language':
                        case 'literature':
                            $recommendation = 'Making progress in English but needs more practice with advanced grammar and essay writing';
                            break;
                        case 'social studies':
                        case 'history':
                        case 'geography':
                            $recommendation = 'Showing basic understanding of ' . $subject . '. Encourage deeper analysis of events and their connections';
                            break;
                        default:
                            $recommendation = 'Making progress in ' . $subject . ' but needs more consistent practice';
                    }
                }
                
                $progress['improvement_areas'][] = [
                    'subject' => $subject,
                    'average' => $average,
                    'recommendation' => $recommendation,
                    'severity' => ($average < 50) ? 'high' : 'medium'
                ];
            }
        }
    }
    
    // Get notes count
    $notes = supabaseRequest("notes?user_id=eq." . urlencode($student_id) . "&select=count", 'GET', null, true);
    if (!empty($notes) && isset($notes[0]['count'])) {
        $progress['notes_count'] = $notes[0]['count'];
    }
    
    // Get study plans count
    $study_plans = supabaseRequest("study_plans?user_id=eq." . urlencode($student_id) . "&select=count", 'GET', null, true);
    if (!empty($study_plans) && isset($study_plans[0]['count'])) {
        $progress['study_plans_count'] = $study_plans[0]['count'];
        // Each study plan counts as a study session
        $progress['usage_stats']['study_sessions'] = $study_plans[0]['count'];
    }
    
    // Get user activity logs to calculate hours spent and other metrics
    $activity_logs = supabaseRequest("user_activity?user_id=eq." . urlencode($student_id) . "&order=timestamp.desc", 'GET', null, true);
    
    if (!empty($activity_logs) && is_array($activity_logs)) {
        // Calculate hours spent (based on session durations)
        $total_minutes = 0;
        $questions_answered = 0;
        $ai_assistant_usage = 0;
        $last_active = '';
        
        foreach ($activity_logs as $index => $log) {
            // Track last active time from the most recent log
            if ($index === 0 && isset($log['timestamp'])) {
                $last_active = $log['timestamp'];
            }
            
            // Calculate session duration if available
            if (isset($log['session_duration']) && is_numeric($log['session_duration'])) {
                $total_minutes += $log['session_duration'];
            }
            
            // Count questions answered
            if (isset($log['activity_type']) && $log['activity_type'] === 'question_answered') {
                $questions_answered++;
            }
            
            // Count AI assistant usage
            if (isset($log['activity_type']) && $log['activity_type'] === 'ai_assistant') {
                $ai_assistant_usage++;
            }
        }
        
        // Convert minutes to hours (rounded to 1 decimal place)
        $hours_spent = round($total_minutes / 60, 1);
        
        // Update the usage stats
        $progress['usage_stats']['hours_spent'] = $hours_spent;
        $progress['usage_stats']['questions_answered'] = $questions_answered;
        $progress['usage_stats']['ai_assistant_usage'] = $ai_assistant_usage;
        $progress['usage_stats']['last_active'] = $last_active;
    }
    
    // If we don't have enough data, add some default improvement suggestions
    if (empty($progress['improvement_areas'])) {
        $progress['improvement_areas'] = [
            ['subject' => 'General', 'recommendation' => 'Encourage regular use of the platform for better tracking'],
            ['subject' => 'Study Habits', 'recommendation' => 'Create a consistent study schedule'],
            ['subject' => 'Note Taking', 'recommendation' => 'Use the notes feature to organize learning materials']
        ];
    }
    
    return $progress;
}
