<?php
// Simple script to check environment variables
echo "Checking environment variables...\n";
echo "OPENROUTER_API_KEY: " . (getenv('OPENROUTER_API_KEY') ? "Set (value hidden)" : "Not set") . "\n";
?>
