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
    'onboarding_step_api_key' => 'Enter PowerUp Key',
    'onboarding_step_welcome' => 'Welcome',
    'onboarding_health_description' => 'Let\'s make sure your system meets all requirements.',
    'onboarding_api_key_description' => 'Enter your PowerUp key to get started.',
    'onboarding_api_key_help' => 'Get your key at tipowerup.com/profile → PowerUp Key',
    'onboarding_welcome_message' => 'Welcome, :name! You\'re all set.',
    'onboarding_get_started' => 'Get Started',

    // Health checks
    'health_php_version' => 'PHP :version',
    'health_zip_archive' => 'ZipArchive Extension',
    'health_curl' => 'cURL Extension',
    'health_storage_writable' => 'Storage Directory Writable',
    'health_vendor_writable' => 'Vendor Directory Writable',
    'health_public_vendor_writable' => 'Public Vendor Directory Writable',
    'health_api_connectivity' => 'TI PowerUp API Connectivity',
    'health_memory_limit' => 'Memory Limit (:limit MB)',
    'health_passed' => 'Passed',
    'health_failed' => 'Failed',
    'health_fix_instructions' => 'Fix Instructions',

    // Tabs
    'tab_installed' => 'My PowerUps',
    'tab_marketplace' => 'Marketplace',

    // Installed / My PowerUps
    'installed_title' => 'My PowerUps',
    'installed_empty' => 'No PowerUps installed yet.',
    'installed_empty_message' => 'Browse the marketplace to find extensions and themes for your TastyIgniter store.',
    'installed_browse_marketplace' => 'Browse Marketplace',
    'installed_version' => 'v:version',
    'installed_update_available' => 'Update to v:version',
    'installed_up_to_date' => 'Up to date',
    'installed_expires' => 'Expires: :date',
    'installed_expired' => 'License Expired',
    'installed_active' => 'Active',
    'installed_disabled' => 'Disabled',
    'installed_current' => 'Current',
    'installed_inactive' => 'Inactive',
    'installed_view_grid' => 'Grid View',
    'installed_view_list' => 'List View',
    'installed_loading' => 'Loading PowerUps...',
    'installed_installed' => 'Installed: :date',

    // My PowerUps sections
    'my_powerups_installed_title' => 'Installed PowerUps',
    'my_powerups_available_title' => 'Available PowerUps',
    'my_powerups_available_empty' => 'All your purchased PowerUps are installed.',
    'my_powerups_not_owned_tooltip' => 'This PowerUp is not linked to your account. Purchase or add it on the marketplace.',
    'updates_title' => 'Updates Available',

    // Marketplace
    'marketplace_title' => 'Marketplace',
    'marketplace_search' => 'Search extensions and themes...',
    'marketplace_filter_all' => 'All',
    'marketplace_filter_extensions' => 'Extensions',
    'marketplace_filter_themes' => 'Themes',
    'marketplace_filter_bundles' => 'Bundles',
    'marketplace_install' => 'Install',
    'marketplace_buy' => 'Buy',
    'marketplace_purchased' => 'Purchased',
    'marketplace_free' => 'Free',
    'marketplace_checking_updates' => 'Checking for updates...',
    'marketplace_refresh' => 'Refresh',
    'marketplace_loading' => 'Loading PowerUps...',
    'marketplace_empty_title' => 'No PowerUps found',
    'marketplace_empty_no_match' => 'No PowerUps match ":query". Try different keywords.',
    'marketplace_empty_generic' => 'No PowerUps available in this category yet.',
    'marketplace_table_header_name' => 'PowerUp',

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
    'detail_loading' => 'Loading PowerUp details...',
    'detail_unknown_name' => 'Unknown PowerUp',
    'detail_no_description' => 'No description available.',
    'detail_no_changelog' => 'No changelog available.',
    'detail_no_screenshots' => 'No screenshots available.',
    'detail_no_requirements' => 'No specific requirements listed.',
    'detail_table_header_package' => 'Package',
    'detail_table_header_version' => 'Version',

    // Installation Progress
    'progress_title' => 'Installing :package',
    'progress_updating_title' => 'Updating :package',
    'progress_stage_preparing' => 'Preparing...',
    'progress_stage_downloading' => 'Downloading PowerUp...',
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
    'action_details' => 'Details',
    'action_check_updates' => 'Check Updates',
    'action_verify_key' => 'Verify Key',
    'action_batch_install' => 'Install Selected',
    'action_settings' => 'Settings',
    'action_edit' => 'Edit',
    'action_customize' => 'Customize',

    // Settings
    'settings_title' => 'Settings',
    'settings_api_key' => 'PowerUp Key',
    'settings_api_key_placeholder' => 'Enter your PowerUp key',
    'settings_install_method' => 'Installation Method',
    'settings_method_auto' => 'Auto-Detect (Recommended)',
    'settings_method_direct' => 'Direct Extraction',
    'settings_method_composer' => 'Composer',
    'settings_environment' => 'Environment Info',
    'settings_save' => 'Save Settings',
    'settings_saved' => 'Settings saved successfully.',

    // Composer Phar
    'composer_source_system' => 'Available (system)',
    'composer_source_downloaded' => 'Available (downloaded)',
    'composer_source_none' => 'Not available',
    'composer_downloading' => 'Downloading...',
    'action_download_composer' => 'Download',

    // Errors
    'error_api_key_required' => 'Please enter your PowerUp key to continue.',
    'error_api_key_invalid' => 'The PowerUp key is invalid. Please check and try again.',
    'error_license_invalid' => 'License validation failed for this PowerUp.',
    'error_license_expired' => 'Your license for this PowerUp has expired.',
    'error_download_failed' => 'Failed to download the PowerUp. Please try again.',
    'error_checksum_mismatch' => 'PowerUp integrity check failed. The download may be corrupted.',
    'error_extraction_failed' => 'Failed to extract the PowerUp files.',
    'error_migration_failed' => 'Database migration failed. Changes have been rolled back.',
    'error_compatibility' => 'This PowerUp is not compatible with your current setup.',
    'error_backup_failed' => 'Failed to create a backup before installation.',
    'error_restore_failed' => 'Failed to restore from backup.',
    'error_connection_failed' => 'Could not connect to the TI PowerUp server. Please check your internet connection.',
    'error_generic' => 'Something went wrong. Please try again.',
    'error_powerup_key_invalid_alert' => 'Your PowerUp key may be invalid or expired. Please check your key in Settings.',
    'error_invalid_install_method' => 'Invalid installation method selected.',
    'error_powerup_key_not_verified' => 'Please verify your PowerUp key first.',

    // Actions (additional)
    'action_activate' => 'Activate',
    'action_delete' => 'Delete',

    // Confirmations
    'confirm_uninstall' => 'Are you sure you want to uninstall :package? This action cannot be undone.',
    'confirm_delete' => 'Are you sure you want to delete :package? All files and data will be permanently removed.',
    'confirm_update' => 'Update :package from v:from to v:to?',

    // Success messages
    'success_installed' => ':package has been installed successfully.',
    'success_updated' => ':package has been updated to v:version.',
    'success_uninstalled' => ':package has been uninstalled.',
    'success_deleted' => ':package has been deleted.',
    'success_extension_enabled' => ':package has been enabled.',
    'success_extension_disabled' => ':package has been disabled.',
    'success_theme_activated' => ':package has been activated as the default theme.',
    'success_restored' => ':package has been restored from backup.',
    'success_api_key_verified' => 'PowerUp key verified successfully.',
    'success_composer_phar_downloaded' => 'Composer downloaded successfully.',
    'error_composer_phar_download_failed' => 'Failed to download Composer. Please try again later.',

    // Community links
    'link_support' => 'Contact Support',
    'link_discord' => 'Discord Community',
    'link_reddit' => 'Reddit Community',

    // Background update notifications
    'notify_update_found_title' => 'PowerUp Updates Available',
    'notify_updates_found' => '%d PowerUp updates are available.',
    'notify_update_found' => '1 PowerUp update is available.',

    // Permissions
    'permissions_manage' => 'Install, update, and manage TI PowerUp extensions and themes',
];
