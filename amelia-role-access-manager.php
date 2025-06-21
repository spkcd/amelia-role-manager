<?php
/**
 * Plugin Name: Amelia Role Access Manager
 * Description: Manage which roles and users have access to AmeliaWP capabilities.
 * Version: 1.0.0
 * Author: SPARKWEB Studio
 * Author URI: https://sparkwebstudio.com
 * Plugin URI: https://sparkwebstudio.com/projects/amelia-role-access
 * License: GPLv2 or later
 * Text Domain: amelia-role-access-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AMELIA_ROLE_MANAGER_VERSION', '1.0.0');
define('AMELIA_ROLE_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMELIA_ROLE_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class AmeliaRoleAccessManager {
    
    /**
     * Plugin version
     */
    const ARAM_VERSION = '1.1.0';
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    // Capabilities are now defined in get_amelia_capabilities() method
    
    /**
     * Get plugin instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load plugin textdomain
        load_plugin_textdomain('amelia-role-access-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Hook into init to modify role capabilities
        add_action('init', [$this, 'apply_role_capabilities']);
        
        // Add capability filter if force override is enabled
        $force_override = get_option('aram_force_override', false);
        if ($force_override) {
            add_filter('user_has_cap', [$this, 'force_amelia_capabilities'], 10, 4);
        }
        
        // Add admin menu
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }
    }
    
    /**
     * Apply role capabilities based on saved settings
     */
    public function apply_role_capabilities() {
        $saved_roles = get_option('amelia_role_access_settings', []);
        $saved_user_ids = get_option('amelia_user_ids_settings', '');
        
        // Get all roles using wp_roles()->roles
        $all_roles = wp_roles()->roles;
        
        foreach ($all_roles as $role_name => $role_info) {
            $role = get_role($role_name);
            
            if ($role) {
                // Check if this role should have Amelia capabilities
                $should_have_caps = isset($saved_roles[$role_name]) && $saved_roles[$role_name];
                
                foreach ($this->get_amelia_capabilities() as $capability) {
                    if ($should_have_caps) {
                        $role->add_cap($capability);
                    } else {
                        $role->remove_cap($capability);
                    }
                }
            }
        }
        
        // Always ensure staff role has capabilities if it exists
        $staff_role = get_role('staff');
        if ($staff_role) {
            foreach ($this->get_amelia_capabilities() as $capability) {
                $staff_role->add_cap($capability);
            }
        }
        
        // Apply capabilities to individual users
        $this->apply_user_capabilities($saved_user_ids);
    }
    
    /**
     * Apply capabilities to individual users
     */
    public function apply_user_capabilities($user_ids_string) {
        if (empty($user_ids_string)) {
            return;
        }
        
        // Parse user IDs from comma-separated string
        $user_ids = $this->parse_user_ids($user_ids_string);
        
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            
            if ($user) {
                foreach ($this->get_amelia_capabilities() as $capability) {
                    $user->add_cap($capability);
                }
            }
        }
    }
    
    /**
     * Parse and validate user IDs from comma-separated string
     */
    public function parse_user_ids($user_ids_string) {
        $user_ids = [];
        
        if (!empty($user_ids_string)) {
            $raw_ids = explode(',', $user_ids_string);
            
            foreach ($raw_ids as $raw_id) {
                // Sanitize and validate each user ID
                $user_id = intval(trim(sanitize_text_field($raw_id)));
                
                // Validate that the user ID exists and is greater than 0
                if ($user_id > 0 && get_userdata($user_id)) {
                    $user_ids[] = $user_id;
                }
            }
        }
        
        return $user_ids;
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('Amelia Role Access', 'amelia-role-access-manager'),
            __('Amelia Role Access', 'amelia-role-access-manager'),
            'manage_options',
            'amelia-role-access',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'amelia_role_access_group',
            'amelia_role_access_settings',
            [$this, 'sanitize_settings']
        );
        
        register_setting(
            'amelia_role_access_group',
            'amelia_user_ids_settings',
            [$this, 'sanitize_user_ids']
        );
        
        register_setting(
            'amelia_role_access_group',
            'aram_force_override',
            [$this, 'sanitize_force_override']
        );
        
        add_settings_section(
            'amelia_role_access_section',
            __('Role Capabilities Settings', 'amelia-role-access-manager'),
            [$this, 'render_section_description'],
            'amelia-role-access'
        );
        
        add_settings_field(
            'amelia_role_checkboxes',
            __('Assign Amelia Capabilities to Roles', 'amelia-role-access-manager'),
            [$this, 'render_role_checkboxes'],
            'amelia-role-access',
            'amelia_role_access_section'
        );
        
        add_settings_field(
            'amelia_user_ids',
            __('Assign Amelia Capabilities to Individual Users', 'amelia-role-access-manager'),
            [$this, 'render_user_ids_field'],
            'amelia-role-access',
            'amelia_role_access_section'
        );
        
        add_settings_field(
            'aram_force_override',
            __('Force Override Amelia Capabilities', 'amelia-role-access-manager'),
            [$this, 'render_force_override_field'],
            'amelia-role-access',
            'amelia_role_access_section'
        );
    }
    
    /**
     * Sanitize settings input
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        if (is_array($input)) {
            $all_roles = wp_roles()->roles;
            
            foreach ($all_roles as $role_name => $role_info) {
                $sanitized_role_name = sanitize_text_field($role_name);
                
                if (isset($input[$sanitized_role_name])) {
                    $sanitized[$sanitized_role_name] = filter_var($input[$sanitized_role_name], FILTER_VALIDATE_BOOLEAN);
                } else {
                    $sanitized[$sanitized_role_name] = false;
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize user IDs input
     */
    public function sanitize_user_ids($input) {
        // Sanitize the text field first
        $sanitized = sanitize_text_field($input);
        
        // Parse and validate user IDs with improved sanitization
        $user_ids = $this->parse_user_ids($sanitized);
        
        // Return as comma-separated string of valid IDs
        return implode(', ', $user_ids);
    }
    
    /**
     * Sanitize force override setting
     */
    public function sanitize_force_override($input) {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'amelia-role-access-manager'));
        }
        
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('aram_settings_action', 'aram_nonce')) {
            $this->handle_form_submission();
        }
        
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
                <span class="plugin-version">v<?php echo esc_html(self::ARAM_VERSION); ?></span>
            </h1>
            
            <div class="notice notice-info">
                <p><?php esc_html_e('Configure which user roles should have access to Amelia booking plugin capabilities. The "staff" role will always have these capabilities enabled.', 'amelia-role-access-manager'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php
                wp_nonce_field('aram_settings_action', 'aram_nonce');
                settings_fields('amelia_role_access_group');
                do_settings_sections('amelia-role-access');
                submit_button(__('Save Changes', 'amelia-role-access-manager'));
                ?>
            </form>
            
            <div class="amelia-capabilities-info">
                <h2><?php esc_html_e('Amelia Capabilities Included:', 'amelia-role-access-manager'); ?></h2>
                <div class="capabilities-grid">
                    <?php foreach ($this->get_amelia_capabilities() as $capability): ?>
                        <div class="capability-item">
                            <code><?php echo esc_html($capability); ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php $this->render_users_with_capabilities_section(); ?>
            
        </div>
        
        <style>
        .plugin-version {
            font-size: 14px;
            font-weight: normal;
            color: #666;
            margin-left: 10px;
            background: #e8f5e8;
            padding: 4px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .amelia-capabilities-info {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .capabilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            background: #fafafa;
        }
        
        .capability-item {
            background: #ffffff;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 12px;
            border: 1px solid #e0e0e0;
            transition: all 0.2s ease;
        }
        
        .capability-item:hover {
            background: #f0f6ff;
            border-color: #0073aa;
        }
        
        .role-checkbox-item {
            margin-bottom: 12px;
            padding: 15px;
            background: #ffffff;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .role-checkbox-item:hover {
            border-color: #0073aa;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .role-checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.1);
        }
        
        .role-checkbox-item label {
            font-weight: 600;
            cursor: pointer;
            color: #1d2327;
        }
        
        .role-description {
            font-size: 12px;
            color: #646970;
            margin-top: 8px;
            margin-left: 32px;
            font-style: italic;
        }
        
        .user-ids-field {
            background: #ffffff;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .user-ids-field textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        
        .force-override-field {
            margin-top: 20px;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .force-override-field label {
            font-weight: 600;
            display: block;
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        .force-override-field input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.1);
        }
        
        .force-override-field .notice.inline {
            margin-top: 15px;
            margin-bottom: 0;
        }
        
        .users-with-capabilities {
            background: #ffffff;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #c3c4c7;
        }
        
        .users-table th {
            background: #f6f7f7;
            font-weight: 600;
            color: #1d2327;
        }
        
        .users-table tr:hover {
            background: #f6f7f7;
        }
        
        .capability-badge {
            display: inline-block;
            background: #0073aa;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin: 1px;
        }
        </style>
        <?php
    }
    
    /**
     * Handle form submission
     */
    private function handle_form_submission() {
        // Double-check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'amelia-role-access-manager'));
        }
        
        // Verify nonce
        if (!check_admin_referer('aram_settings_action', 'aram_nonce')) {
            wp_die(__('Security check failed. Please try again.', 'amelia-role-access-manager'));
        }
        
        $settings = [];
        $all_roles = wp_roles()->roles;
        
        // Sanitize and validate role settings
        foreach ($all_roles as $role_name => $role_info) {
            $sanitized_role_name = sanitize_text_field($role_name);
            
            if (isset($_POST['amelia_role_access_settings'][$sanitized_role_name])) {
                $checkbox_value = $_POST['amelia_role_access_settings'][$sanitized_role_name];
                $settings[$sanitized_role_name] = filter_var($checkbox_value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $settings[$sanitized_role_name] = false;
            }
        }
        
        // Sanitize user IDs
        $user_ids_input = isset($_POST['amelia_user_ids_settings']) ? $_POST['amelia_user_ids_settings'] : '';
        $sanitized_user_ids = $this->sanitize_user_ids($user_ids_input);
        
        // Sanitize force override setting
        $force_override = isset($_POST['aram_force_override']) ? $_POST['aram_force_override'] : false;
        $sanitized_force_override = filter_var($force_override, FILTER_VALIDATE_BOOLEAN);
        
        // Update options
        update_option('amelia_role_access_settings', $settings);
        update_option('amelia_user_ids_settings', $sanitized_user_ids);
        update_option('aram_force_override', $sanitized_force_override);
        
        // Apply capabilities immediately
        $this->apply_role_capabilities();
        
        // Provide feedback about force override status
        $success_message = __('Settings saved and capabilities applied successfully!', 'amelia-role-access-manager');
        if ($sanitized_force_override) {
            $success_message .= ' ' . __('Force override filter is now active.', 'amelia-role-access-manager');
        }
        
        add_settings_error(
            'amelia_role_access_settings',
            'settings_updated',
            $success_message,
            'updated'
        );
    }
    
    /**
     * Render section description
     */
    public function render_section_description() {
        echo '<p>' . esc_html__('Select which user roles should have access to Amelia booking plugin capabilities.', 'amelia-role-access-manager') . '</p>';
    }
    
    /**
     * Render role checkboxes
     */
    public function render_role_checkboxes() {
        $saved_settings = get_option('amelia_role_access_settings', []);
        $all_roles = wp_roles()->roles;
        
        if (empty($all_roles)) {
            echo '<p>' . esc_html__('No roles found.', 'amelia-role-access-manager') . '</p>';
            return;
        }
        
        foreach ($all_roles as $role_name => $role_info) {
            $sanitized_role_name = sanitize_text_field($role_name);
            $checked = isset($saved_settings[$sanitized_role_name]) && $saved_settings[$sanitized_role_name];
            $is_staff = $sanitized_role_name === 'staff';
            $disabled = $is_staff ? 'disabled' : '';
            $checked_attr = ($checked || $is_staff) ? 'checked' : '';
            
            ?>
            <div class="role-checkbox-item">
                <input 
                    type="checkbox" 
                    id="role_<?php echo esc_attr($sanitized_role_name); ?>" 
                    name="amelia_role_access_settings[<?php echo esc_attr($sanitized_role_name); ?>]" 
                    value="1" 
                    <?php echo $checked_attr; ?>
                    <?php echo $disabled; ?>
                />
                <label for="role_<?php echo esc_attr($sanitized_role_name); ?>">
                    <?php echo esc_html(translate_user_role($role_info['name'])); ?>
                    <?php if ($is_staff): ?>
                        <em><?php esc_html_e('(Always enabled)', 'amelia-role-access-manager'); ?></em>
                    <?php endif; ?>
                </label>
                
                <?php if (!empty($role_info['capabilities'])): ?>
                    <div class="role-description">
                        <?php 
                        printf(
                            esc_html__('Current capabilities: %d', 'amelia-role-access-manager'),
                            intval(count($role_info['capabilities']))
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
        
        // Hidden field for staff role to ensure it's always enabled
        ?>
        <input type="hidden" name="amelia_role_access_settings[staff]" value="1" />
        <?php
    }
    
    /**
     * Render user IDs input field
     */
    public function render_user_ids_field() {
        $saved_user_ids = get_option('amelia_user_ids_settings', '');
        $sanitized_saved_user_ids = sanitize_text_field($saved_user_ids);
        ?>
        <div class="user-ids-field">
            <textarea 
                id="amelia_user_ids" 
                name="amelia_user_ids_settings" 
                placeholder="<?php esc_attr_e('4, 15, 23, 156', 'amelia-role-access-manager'); ?>"
                rows="3"
                cols="50"
            ><?php echo esc_textarea($sanitized_saved_user_ids); ?></textarea>
            <p class="description">
                <?php esc_html_e('Enter comma-separated user IDs to grant Amelia capabilities directly to specific users (e.g., 4, 15, 23, 156). Invalid user IDs will be automatically removed.', 'amelia-role-access-manager'); ?>
            </p>
            
            <?php if (!empty($sanitized_saved_user_ids)): ?>
                <div class="current-users-info">
                    <h4><?php esc_html_e('Current Users with Amelia Access:', 'amelia-role-access-manager'); ?></h4>
                    <div class="users-list">
                        <?php
                        $user_ids = $this->parse_user_ids($sanitized_saved_user_ids);
                        foreach ($user_ids as $user_id):
                            $sanitized_user_id = intval($user_id);
                            $user = get_userdata($sanitized_user_id);
                            if ($user):
                        ?>
                            <div class="user-item">
                                <strong><?php printf(esc_html__('ID: %d', 'amelia-role-access-manager'), $sanitized_user_id); ?></strong> - 
                                <?php echo esc_html($user->display_name); ?> 
                                (<?php echo esc_html($user->user_login); ?>)
                                <em>[<?php echo esc_html($user->user_email); ?>]</em>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .user-ids-field {
            margin-top: 15px;
        }
        .current-users-info {
            margin-top: 15px;
            padding: 15px;
            background: #f0f8ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
        }
        .current-users-info h4 {
            margin-top: 0;
            color: #0073aa;
        }
        .users-list {
            margin-top: 10px;
        }
        .user-item {
            padding: 5px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .user-item:last-child {
            border-bottom: none;
        }
        </style>
        <?php
    }
    
    /**
     * Render users with capabilities section
     */
    public function render_users_with_capabilities_section() {
        ?>
        <div class="users-with-capabilities">
            <h2><?php esc_html_e('Users With Amelia Capabilities', 'amelia-role-access-manager'); ?></h2>
            
            <?php
            $users_with_caps = $this->get_users_with_amelia_capabilities();
            $total_users = count(get_users(['fields' => 'ID']));
            
            if ($total_users > 100): ?>
                <div class="notice notice-info inline">
                    <p>
                        <?php 
                        printf(
                            esc_html__('Showing results from the first 100 users only. Your site has %d total users.', 'amelia-role-access-manager'),
                            $total_users
                        ); 
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($users_with_caps)): ?>
                <p><?php esc_html_e('No users currently have Amelia capabilities.', 'amelia-role-access-manager'); ?></p>
            <?php else: ?>
                <div class="table-controls">
                    <label for="sort-users-by"><?php esc_html_e('Sort by:', 'amelia-role-access-manager'); ?></label>
                    <select id="sort-users-by">
                        <option value="id"><?php esc_html_e('User ID', 'amelia-role-access-manager'); ?></option>
                        <option value="username"><?php esc_html_e('Username', 'amelia-role-access-manager'); ?></option>
                    </select>
                    <button type="button" class="button" onclick="sortUsersTable()"><?php esc_html_e('Sort', 'amelia-role-access-manager'); ?></button>
                </div>
                
                <table class="wp-list-table widefat fixed striped users-table" id="users-capabilities-table">
                    <thead>
                        <tr>
                            <th scope="col" data-sort="id"><?php esc_html_e('User ID', 'amelia-role-access-manager'); ?></th>
                            <th scope="col" data-sort="username"><?php esc_html_e('Username', 'amelia-role-access-manager'); ?></th>
                            <th scope="col"><?php esc_html_e('Email', 'amelia-role-access-manager'); ?></th>
                            <th scope="col"><?php esc_html_e('Amelia Capabilities', 'amelia-role-access-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_with_caps as $user_data): ?>
                            <tr data-user-id="<?php echo esc_attr(intval($user_data['id'])); ?>" data-username="<?php echo esc_attr(sanitize_text_field($user_data['username'])); ?>">
                                <td><strong><?php echo esc_html(intval($user_data['id'])); ?></strong></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_user_link(intval($user_data['id']))); ?>" target="_blank">
                                        <?php echo esc_html(sanitize_text_field($user_data['username'])); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html(sanitize_email($user_data['email'])); ?></td>
                                <td>
                                    <div class="capabilities-list">
                                        <?php foreach ($user_data['capabilities'] as $capability): ?>
                                            <span class="capability-badge">
                                                <?php echo esc_html(sanitize_text_field($capability)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="users-summary">
                    <?php 
                    printf(
                        esc_html__('Found %d users with Amelia capabilities.', 'amelia-role-access-manager'),
                        count($users_with_caps)
                    ); 
                    ?>
                </p>
            <?php endif; ?>
        </div>
        
        <style>
        .users-with-capabilities {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .table-controls {
            margin: 15px 0;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .table-controls label {
            font-weight: 600;
            margin-right: 10px;
        }
        
        .table-controls select {
            margin-right: 10px;
        }
        
        #users-capabilities-table th {
            cursor: pointer;
        }
        
        #users-capabilities-table th:hover {
            background-color: #f0f0f0;
        }
        
        .capabilities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .capability-badge {
            background: #0073aa;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            white-space: nowrap;
        }
        
        .users-summary {
            margin-top: 15px;
            font-style: italic;
            color: #666;
        }
        
        .notice.inline {
            margin: 15px 0;
        }
        </style>
        
        <script>
        function sortUsersTable() {
            const sortBy = document.getElementById('sort-users-by').value;
            const table = document.getElementById('users-capabilities-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                let aValue, bValue;
                
                if (sortBy === 'id') {
                    aValue = parseInt(a.dataset.userId);
                    bValue = parseInt(b.dataset.userId);
                } else if (sortBy === 'username') {
                    aValue = a.dataset.username.toLowerCase();
                    bValue = b.dataset.username.toLowerCase();
                }
                
                if (sortBy === 'id') {
                    return aValue - bValue;
                } else {
                    return aValue.localeCompare(bValue);
                }
            });
            
            // Clear tbody and append sorted rows
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        }
        
        // Add click handlers to table headers
        document.addEventListener('DOMContentLoaded', function() {
            const headers = document.querySelectorAll('#users-capabilities-table th[data-sort]');
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const sortType = this.dataset.sort;
                    document.getElementById('sort-users-by').value = sortType;
                    sortUsersTable();
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get users with Amelia capabilities
     */
    public function get_users_with_amelia_capabilities() {
        $users_with_caps = [];
        
        // Get first 100 users for performance
        $users = get_users([
            'number' => 100,
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);
        
        foreach ($users as $user) {
            $user_capabilities = [];
            
            // Check each Amelia capability
            foreach ($this->get_amelia_capabilities() as $capability) {
                if (user_can($user->ID, $capability)) {
                    $user_capabilities[] = $capability;
                }
            }
            
            // If user has at least one Amelia capability, add to results
            if (!empty($user_capabilities)) {
                $users_with_caps[] = [
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'capabilities' => $user_capabilities
                ];
            }
        }
        
        return $users_with_caps;
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_amelia-role-access') {
            return;
        }
        
        // Example of using version constant for cache-busting when enqueuing scripts/styles
        // wp_enqueue_script('amelia-role-access-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', [], self::ARAM_VERSION, true);
        // wp_enqueue_style('amelia-role-access-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::ARAM_VERSION);
    }
    
    /**
     * Get Amelia capabilities
     */
    public function get_amelia_capabilities() {
        return array(
            // Core Amelia Capabilities
            'amelia_read_appointments',
            'amelia_manage_appointments', 
            'amelia_manage_bookings',
            'amelia_manage_services',
            'amelia_manage_providers',
            'amelia_manage_customers',
            'amelia_manage_settings',
            'amelia_dashboard_access',
            'amelia_read_coupons',
            
            // Extended Amelia Capabilities
            'amelia_read_services',
            'amelia_edit_services',
            'amelia_delete_services',
            'amelia_read_employees',
            'amelia_edit_employees',
            'amelia_delete_employees',
            'amelia_read_customers',
            'amelia_edit_customers',
            'amelia_delete_customers',
            'amelia_read_locations',
            'amelia_edit_locations',
            'amelia_delete_locations',
            'amelia_read_categories',
            'amelia_edit_categories',
            'amelia_delete_categories',
            'amelia_read_events',
            'amelia_manage_events',
            'amelia_edit_events',
            'amelia_delete_events',
            'amelia_read_packages',
            'amelia_manage_packages',
            'amelia_read_resources',
            'amelia_manage_resources',
            'amelia_read_extras',
            'amelia_manage_extras',
            'amelia_read_finance',
            'amelia_manage_finance',
            'amelia_read_coupons',
            'amelia_edit_coupons',
            'amelia_delete_coupons',
            'amelia_read_notifications',
            'amelia_manage_notifications',
            'amelia_read_calendar',
            'amelia_manage_calendar',
            'amelia_export_data',
            'amelia_import_data',
            
            // Essential WordPress Core Capabilities that Amelia requires
            'manage_options',           // Core admin capability - required for Amelia admin access
            'edit_posts',              // Required for content management
            'edit_pages',              // Required for page management
            'edit_others_posts',       // Required for managing other users' content
            'edit_others_pages',       // Required for managing other users' pages
            'edit_published_posts',    // Required for editing published content
            'edit_published_pages',    // Required for editing published pages
            'publish_posts',           // Required for publishing content
            'publish_pages',           // Required for publishing pages
            'delete_posts',            // Required for content management
            'delete_pages',            // Required for page management
            'delete_others_posts',     // Required for managing other users' content
            'delete_others_pages',     // Required for managing other users' pages
            'delete_published_posts',  // Required for content cleanup
            'delete_published_pages',  // Required for page cleanup
            'read',                    // Basic read capability
            'upload_files',            // Required for media management
            'edit_files',              // Required for file editing capabilities
            'import',                  // Required for data import
            'export',                  // Required for data export
            'manage_categories',       // Required for category management
            'manage_links',            // Required for link management
            'moderate_comments',       // Required for comment management
            'list_users',              // Required for user listing
            'edit_users',              // Required for user management
            'create_users',            // Required for user creation
            'delete_users',            // Required for user deletion
            'promote_users',           // Required for user role management
            'remove_users',            // Required for user removal
            'add_users',               // Required for adding users
            'edit_theme_options',      // Required for theme customization
            'customize',               // Required for WordPress customizer
            'edit_dashboard',          // Required for dashboard access
            'unfiltered_html',         // Required for HTML content (single site)
            
            // Payment and E-commerce capabilities
            'manage_woocommerce',      // Required for WooCommerce integration
            'edit_shop_orders',        // Required for order management
            'edit_others_shop_orders', // Required for managing other users' orders
            'edit_products',           // Required for product management
            'view_woocommerce_reports', // Required for viewing reports
            
            // Additional WordPress capabilities that may be checked
            'switch_themes',           // Theme switching capability
            'edit_themes',             // Theme editing capability
            'install_themes',          // Theme installation capability
            'activate_plugins',        // Plugin activation capability
            'edit_plugins',            // Plugin editing capability
            'install_plugins',         // Plugin installation capability
            'update_plugins',          // Plugin update capability
            'delete_plugins',          // Plugin deletion capability
            'update_themes',           // Theme update capability
            'delete_themes',           // Theme deletion capability
            'update_core',             // Core update capability
        );
    }
    
    /**
     * Plugin activation hook
     */
    public static function activate() {
        // Set default options
        $default_settings = [
            'staff' => true,
            'administrator' => true
        ];
        
        if (!get_option('amelia_role_access_settings')) {
            add_option('amelia_role_access_settings', $default_settings);
        }
        
        // Set default force override setting
        if (get_option('aram_force_override') === false) {
            add_option('aram_force_override', false);
        }
        
        // Apply capabilities immediately after activation
        $instance = self::getInstance();
        $instance->apply_role_capabilities();
    }
    
    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        $instance = self::getInstance();
        
        // Remove all Amelia capabilities from all roles
        $all_roles = wp_roles()->roles;
        
        foreach ($all_roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($instance->get_amelia_capabilities() as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
        
        // Remove capabilities from individual users
        $saved_user_ids = get_option('amelia_user_ids_settings', '');
        if (!empty($saved_user_ids)) {
            $user_ids = $instance->parse_user_ids($saved_user_ids);
            
            foreach ($user_ids as $user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    foreach ($instance->get_amelia_capabilities() as $capability) {
                        $user->remove_cap($capability);
                    }
                }
            }
        }
    }
    
    /**
     * Plugin uninstall hook
     */
    public static function uninstall() {
        // Remove plugin options
        delete_option('amelia_role_access_settings');
        delete_option('amelia_user_ids_settings');
        delete_option('aram_force_override');
        
        // Remove all Amelia capabilities from all roles and users
        self::deactivate();
    }
    
    /**
     * Force Amelia capabilities via user_has_cap filter
     */
    public function force_amelia_capabilities($allcaps, $caps, $args, $user) {
        // Check if this is a capability check
        if (empty($caps)) {
            return $allcaps;
        }
        
        // Get user roles
        if (!$user || !isset($user->roles)) {
            return $allcaps;
        }
        
        // Get selected roles that should have Amelia capabilities
        $saved_roles = get_option('amelia_role_access_settings', []);
        
        // Check if user has any of the selected roles
        $user_has_selected_role = false;
        foreach ($user->roles as $role) {
            if (isset($saved_roles[$role]) && $saved_roles[$role]) {
                $user_has_selected_role = true;
                break;
            }
        }
        
        // Always grant to staff role
        if (in_array('staff', $user->roles)) {
            $user_has_selected_role = true;
        }
        
        // Get all Amelia capabilities
        $amelia_capabilities = $this->get_amelia_capabilities();
        if (!is_array($amelia_capabilities)) {
            return $allcaps;
        }
        
        // If user has a selected role, grant all Amelia capabilities
        if ($user_has_selected_role) {
            foreach ($caps as $cap) {
                // Grant all Amelia capabilities being checked
                if (in_array($cap, $amelia_capabilities)) {
                    $allcaps[$cap] = true;
                }
            }
        }
        
        // Also check individual user IDs
        $saved_user_ids = get_option('amelia_user_ids_settings', '');
        if (!empty($saved_user_ids)) {
            $user_ids = $this->parse_user_ids($saved_user_ids);
            if (is_array($user_ids) && in_array($user->ID, $user_ids)) {
                foreach ($caps as $cap) {
                    // Grant all Amelia capabilities for individual users
                    if (in_array($cap, $amelia_capabilities)) {
                        $allcaps[$cap] = true;
                    }
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Render force override checkbox field
     */
    public function render_force_override_field() {
        $force_override = get_option('aram_force_override', false);
        $checked = $force_override ? 'checked' : '';
        ?>
        <div class="force-override-field">
            <label>
                <input 
                    type="checkbox" 
                    id="aram_force_override" 
                    name="aram_force_override" 
                    value="1" 
                    <?php echo $checked; ?>
                />
                <?php esc_html_e('Enable force override via user_has_cap filter', 'amelia-role-access-manager'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('When enabled, this will use WordPress user_has_cap filter to automatically grant Amelia capabilities to selected roles and users. This can help resolve issues where capabilities are not being recognized properly. Recommended if role-based assignments are not working.', 'amelia-role-access-manager'); ?>
            </p>
            
            <?php if ($force_override): ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong><?php esc_html_e('Active:', 'amelia-role-access-manager'); ?></strong>
                        <?php esc_html_e('Force override is currently enabled. All users with selected roles will automatically receive Amelia capabilities via filter.', 'amelia-role-access-manager'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .force-override-field {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .force-override-field label {
            font-weight: 600;
            display: block;
            margin-bottom: 10px;
        }
        .force-override-field input[type="checkbox"] {
            margin-right: 8px;
        }
        .force-override-field .notice.inline {
            margin-top: 15px;
            margin-bottom: 0;
        }
        </style>
        <?php
    }
    

}

// Initialize plugin
AmeliaRoleAccessManager::getInstance();

// Register activation, deactivation, and uninstall hooks
register_activation_hook(__FILE__, ['AmeliaRoleAccessManager', 'activate']);
register_deactivation_hook(__FILE__, ['AmeliaRoleAccessManager', 'deactivate']);
register_uninstall_hook(__FILE__, ['AmeliaRoleAccessManager', 'uninstall']); 