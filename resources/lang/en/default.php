<?php

declare(strict_types=1);

return [
    // General
    'text_title' => 'PowerUp Installer',
    'text_description' => 'Install, update, and manage TI PowerUp extensions and themes.',

    // Navigation
    'text_nav_title' => 'PowerUp Installer',

    // Onboarding
    'onboarding_title' => 'Welcome to PowerUp Installer',
    'onboarding_step_health' => 'System Health Check',
    'onboarding_step_api_key' => 'Enter API Key',
    'onboarding_step_welcome' => 'Welcome',
    'onboarding_health_description' => 'Let\'s make sure your system meets all requirements.',
    'onboarding_api_key_description' => 'Enter your TI PowerUp API key to get started.',
    'onboarding_api_key_help' => 'Get your key at tipowerup.com/profile → API Keys',
    'onboarding_welcome_message' => 'Welcome, :name! You\'re all set.',
    'onboarding_get_started' => 'Get Started',

    // Health checks
    'health_php_version' => 'PHP :version',
    'health_zip_archive' => 'ZipArchive Extension',
    'health_curl' => 'cURL Extension',
    'health_storage_writable' => 'Storage Directory Writable',
    'health_vendor_writable' => 'Vendor Directory Writable',
    'health_public_vendor_writable' => 'Public Vendor Directory Writable',
    'health_memory_limit' => 'Memory Limit (:limit MB)',
    'health_passed' => 'Passed',
    'health_failed' => 'Failed',
    'health_fix_instructions' => 'Fix Instructions',

    // Tabs
    'tab_installed' => 'Installed',
    'tab_marketplace' => 'Marketplace',

    // Installed Packages
    'installed_title' => 'Installed Packages',
    'installed_empty' => 'No packages installed yet.',
    'installed_empty_message' => 'Browse the marketplace to find extensions and themes for your TastyIgniter store.',
    'installed_browse_marketplace' => 'Browse Marketplace',
    'installed_version' => 'v:version',
    'installed_update_available' => 'Update to v:version',
    'installed_up_to_date' => 'Up to date',
    'installed_expires' => 'Expires: :date',
    'installed_expired' => 'License Expired',
    'installed_active' => 'Active',
    'installed_disabled' => 'Disabled',
    'installed_view_grid' => 'Grid View',
    'installed_view_list' => 'List View',
    'installed_loading' => 'Loading packages...',
    'installed_installed' => 'Installed: :date',

    // Marketplace
    'marketplace_title' => 'Marketplace',
    'marketplace_search' => 'Search extensions and themes...',
    'marketplace_filter_all' => 'All',
    'marketplace_filter_extensions' => 'Extensions',
    'marketplace_filter_themes' => 'Themes',
    'marketplace_install' => 'Install',
    'marketplace_buy' => 'Buy on TI PowerUp',
    'marketplace_purchased' => 'Purchased',
    'marketplace_free' => 'Free',
    'marketplace_detecting_purchase' => 'Detecting purchase...',

    // Package Detail
    'detail_description' => 'Description',
    'detail_screenshots' => 'Screenshots',
    'detail_reviews' => 'Reviews',
    'detail_changelog' => 'Changelog',
    'detail_compatibility' => 'Compatibility',
    'detail_dependencies' => 'Dependencies',
    'detail_version' => 'Version',
    'detail_author' => 'Author',
    'detail_last_updated' => 'Last Updated',

    // Installation Progress
    'progress_title' => 'Installing :package',
    'progress_updating_title' => 'Updating :package',
    'progress_stage_preparing' => 'Preparing...',
    'progress_stage_downloading' => 'Downloading package...',
    'progress_stage_verifying' => 'Verifying integrity...',
    'progress_stage_extracting' => 'Extracting files...',
    'progress_stage_migrating' => 'Running migrations...',
    'progress_stage_finalizing' => 'Finalizing...',
    'progress_stage_completed' => 'Completed!',
    'progress_stage_failed' => 'Installation Failed',
    'progress_cancel' => 'Cancel',
    'progress_close' => 'Close',
    'progress_retry' => 'Retry',

    // Actions
    'action_install' => 'Install',
    'action_update' => 'Update',
    'action_uninstall' => 'Uninstall',
    'action_disable' => 'Disable',
    'action_enable' => 'Enable',
    'action_restore' => 'Restore Backup',
    'action_check_updates' => 'Check Updates',
    'action_verify_key' => 'Verify Key',
    'action_batch_install' => 'Install Selected',

    // Settings
    'settings_title' => 'Settings',
    'settings_api_key' => 'API Key',
    'settings_api_key_placeholder' => 'Enter your TI PowerUp API key',
    'settings_install_method' => 'Installation Method',
    'settings_method_auto' => 'Auto-Detect (Recommended)',
    'settings_method_direct' => 'Direct Extraction',
    'settings_method_composer' => 'Composer',
    'settings_environment' => 'Environment Info',
    'settings_save' => 'Save Settings',
    'settings_saved' => 'Settings saved successfully.',

    // Errors
    'error_api_key_required' => 'Please enter your API key to continue.',
    'error_api_key_invalid' => 'The API key is invalid. Please check and try again.',
    'error_license_invalid' => 'License validation failed for this package.',
    'error_license_expired' => 'Your license for this package has expired.',
    'error_download_failed' => 'Failed to download the package. Please try again.',
    'error_checksum_mismatch' => 'Package integrity check failed. The download may be corrupted.',
    'error_extraction_failed' => 'Failed to extract the package files.',
    'error_migration_failed' => 'Database migration failed. Changes have been rolled back.',
    'error_compatibility' => 'This package is not compatible with your current setup.',
    'error_backup_failed' => 'Failed to create a backup before installation.',
    'error_restore_failed' => 'Failed to restore from backup.',
    'error_connection_failed' => 'Could not connect to the TI PowerUp server. Please check your internet connection.',

    // Confirmations
    'confirm_uninstall' => 'Are you sure you want to uninstall :package? This action cannot be undone.',
    'confirm_update' => 'Update :package from v:from to v:to?',

    // Success messages
    'success_installed' => ':package has been installed successfully.',
    'success_updated' => ':package has been updated to v:version.',
    'success_uninstalled' => ':package has been uninstalled.',
    'success_restored' => ':package has been restored from backup.',
    'success_api_key_verified' => 'API key verified successfully.',

    // Community links
    'link_support' => 'Contact Support',
    'link_discord' => 'Discord Community',
    'link_reddit' => 'Reddit Community',

    // Permissions
    'permissions_manage' => 'Install, update, and manage TI PowerUp extensions and themes',
];
