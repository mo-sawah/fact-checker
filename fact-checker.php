<?php
/**
 * Plugin Name: Fact Checker
 * Description: AI-powered fact-checking plugin that verifies article accuracy using OpenRouter with web search
 * Version: 3.0.2
 * Author: Mohamed Sawah
 * Author URI: https://sawahsolutions.com
 * License: GPL v2 or later
 * Text Domain: fact-checker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FACT_CHECKER_VERSION', '3.0.2');
define('FACT_CHECKER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FACT_CHECKER_PLUGIN_PATH', plugin_dir_path(__FILE__));

class FactChecker {
    
    private $options;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_fact_check_article', array($this, 'ajax_fact_check'));
        add_action('wp_ajax_nopriv_fact_check_article', array($this, 'ajax_fact_check'));
        add_action('wp_ajax_test_fact_checker_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_fact_checker_email_submit', array($this, 'ajax_email_submit'));
        add_action('wp_ajax_nopriv_fact_checker_email_submit', array($this, 'ajax_email_submit'));
        add_action('wp_ajax_fact_checker_signup', array($this, 'ajax_signup'));
        add_action('wp_ajax_nopriv_fact_checker_signup', array($this, 'ajax_signup'));
        
        // Add fact checker to content
        add_filter('the_content', array($this, 'add_fact_checker_to_content'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        $default_options = array(
            'enabled' => true,
            'api_key' => '',
            'model' => 'openai/gpt-4',
            'web_searches' => 5,
            'search_context' => 'medium',
            'theme_mode' => 'light',
            'primary_color' => '#3b82f6',
            'success_color' => '#059669',
            'warning_color' => '#f59e0b',
            'background_color' => '#f8fafc',
            'free_limit' => 5,
            'terms_url' => '',
            'privacy_url' => '',
            'require_email' => true
        );
        
        $saved_options = get_option('fact_checker_options', array());
        $this->options = array_merge($default_options, $saved_options);
        
        // Update the options in database if new defaults were added
        if (count($this->options) > count($saved_options)) {
            update_option('fact_checker_options', $this->options);
        }
    }
    
    public function activate() {
        // Create cache table
        global $wpdb;
        
        $cache_table = $wpdb->prefix . 'fact_checker_cache';
        $users_table = $wpdb->prefix . 'fact_checker_users';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql1 = "CREATE TABLE $cache_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            content_hash varchar(64) NOT NULL,
            result longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_content (post_id, content_hash)
        ) $charset_collate;";
        
        $sql2 = "CREATE TABLE $users_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            usage_count int(11) DEFAULT 0,
            is_registered boolean DEFAULT FALSE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY ip_address (ip_address)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }
    
    public function enqueue_scripts() {
        if (is_single() && $this->options['enabled']) {
            wp_enqueue_style(
                'fact-checker-style',
                FACT_CHECKER_PLUGIN_URL . 'assets/css/fact-checker.css',
                array(),
                FACT_CHECKER_VERSION
            );
            
            wp_enqueue_script(
                'fact-checker-script',
                FACT_CHECKER_PLUGIN_URL . 'assets/js/fact-checker.js',
                array('jquery'),
                FACT_CHECKER_VERSION,
                true
            );
            
            wp_localize_script('fact-checker-script', 'factChecker', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fact_checker_nonce'),
                'theme_mode' => isset($this->options['theme_mode']) ? $this->options['theme_mode'] : 'light',
                'require_email' => $this->options['require_email'],
                'free_limit' => $this->options['free_limit'],
                'terms_url' => $this->options['terms_url'],
                'privacy_url' => $this->options['privacy_url'],
                'colors' => array(
                    'primary' => isset($this->options['primary_color']) ? $this->options['primary_color'] : '#3b82f6',
                    'success' => isset($this->options['success_color']) ? $this->options['success_color'] : '#059669',
                    'warning' => isset($this->options['warning_color']) ? $this->options['warning_color'] : '#f59e0b',
                    'background' => isset($this->options['background_color']) ? $this->options['background_color'] : '#f8fafc'
                )
            ));
        }
    }
    
    public function admin_enqueue_scripts($hook) {
        if ($hook === 'settings_page_fact-checker') {
            wp_enqueue_script('jquery');
        }
    }
    
    public function add_fact_checker_to_content($content) {
        if (is_single() && $this->options['enabled'] && !empty($this->options['api_key'])) {
            $fact_checker_html = $this->get_fact_checker_html();
            $content .= $fact_checker_html;
        }
        return $content;
    }
    
    private function get_fact_checker_html() {
        $colors = array(
            'primary' => $this->options['primary_color'],
            'success' => $this->options['success_color'],
            'warning' => $this->options['warning_color'],
            'background' => $this->options['background_color']
        );
        
        $user_status = $this->get_user_status();
        
        ob_start();
        ?>
        <div class="fact-check-container" data-post-id="<?php echo get_the_ID(); ?>" data-user-status="<?php echo esc_attr(json_encode($user_status)); ?>">
            <div class="fact-check-box">
                <div class="fact-check-header">
                    <div class="fact-check-title">
                        <div class="fact-check-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M9 12l2 2 4-4"></path>
                                <circle cx="12" cy="12" r="9"></circle>
                            </svg>
                        </div>
                        <h3>Fact Checker</h3>
                    </div>
                    <button class="check-button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                        </svg>
                        <span>Check Facts</span>
                    </button>
                </div>
                <p class="fact-check-description">Verify the accuracy of this article using AI-powered analysis and real-time sources.</p>
                
                <!-- Email Capture Form -->
                <div class="email-capture-form" id="email-capture-form" style="display: none;">
                    <div class="form-header">
                        <h4>Get Your Fact Check Report</h4>
                        <p>Enter your email to receive detailed fact-checking analysis</p>
                    </div>
                    <form class="email-form">
                        <div class="input-group">
                            <input type="email" id="visitor-email" placeholder="Enter your email address" required>
                            <button type="submit" class="submit-btn">
                                <span>Get Report</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 2L11 13"></path>
                                    <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                                </svg>
                            </button>
                        </div>
                        <label class="checkbox-label">
                            <input type="checkbox" id="accept-terms" required>
                            <span class="checkmark"></span>
                            <span class="terms-text">I agree to the <a href="<?php echo esc_url($this->options['terms_url']); ?>" target="_blank">Terms of Use</a> and <a href="<?php echo esc_url($this->options['privacy_url']); ?>" target="_blank">Privacy Policy</a></span>
                        </label>
                    </form>
                    <div class="form-footer">
                        <small><?php echo ($this->options['free_limit'] - $user_status['usage_count']); ?> free reports remaining</small>
                    </div>
                </div>
                
                <!-- Signup Form -->
                <div class="signup-form" id="signup-form" style="display: none;">
                    <div class="form-header">
                        <h4>Continue with Full Access</h4>
                        <p>You've used your <?php echo $this->options['free_limit']; ?> free reports. Sign up for unlimited access!</p>
                    </div>
                    <form class="signup-form-inner">
                        <div class="input-row">
                            <input type="text" id="signup-name" placeholder="Full Name" required>
                            <input type="email" id="signup-email" placeholder="Email Address" required>
                        </div>
                        <input type="password" id="signup-password" placeholder="Create Password" required>
                        <button type="submit" class="submit-btn signup-btn">
                            <span>Create Account & Continue</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 2L11 13"></path>
                                <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                            </svg>
                        </button>
                        <label class="checkbox-label">
                            <input type="checkbox" id="signup-terms" required>
                            <span class="checkmark"></span>
                            <span class="terms-text">I agree to the <a href="<?php echo esc_url($this->options['terms_url']); ?>" target="_blank">Terms of Use</a> and <a href="<?php echo esc_url($this->options['privacy_url']); ?>" target="_blank">Privacy Policy</a></span>
                        </label>
                    </form>
                    <div class="login-link">
                        <p>Already have an account? <a href="<?php echo wp_login_url(get_permalink()); ?>">Sign in here</a></p>
                    </div>
                </div>
                
                <div class="results-container" id="fact-check-results" style="display: none;">
                    <!-- Results will be loaded here via AJAX -->
                </div>
            </div>
        </div>
        
        <style>
            :root {
                --fc-primary: <?php echo $colors['primary']; ?>;
                --fc-success: <?php echo $colors['success']; ?>;
                --fc-warning: <?php echo $colors['warning']; ?>;
                --fc-background: <?php echo $colors['background']; ?>;
            }
        </style>
        <?php
        return ob_get_clean();
    }
    
    private function get_user_status() {
        if (is_user_logged_in()) {
            return array(
                'logged_in' => true,
                'usage_count' => 0,
                'can_use' => true,
                'email' => wp_get_current_user()->user_email
            );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fact_checker_users';
        $ip_address = $this->get_client_ip();
        
        // Check by cookie first (email)
        $email = isset($_COOKIE['fact_checker_email']) ? sanitize_email($_COOKIE['fact_checker_email']) : '';
        
        if ($email) {
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE email = %s",
                $email
            ));
            
            if ($user) {
                return array(
                    'logged_in' => false,
                    'email' => $user->email,
                    'usage_count' => intval($user->usage_count),
                    'can_use' => $user->is_registered || intval($user->usage_count) < $this->options['free_limit'],
                    'is_registered' => (bool) $user->is_registered
                );
            }
        }
        
        // Check by IP as fallback
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE ip_address = %s ORDER BY created_at DESC LIMIT 1",
            $ip_address
        ));
        
        if ($user) {
            return array(
                'logged_in' => false,
                'email' => $user->email,
                'usage_count' => intval($user->usage_count),
                'can_use' => $user->is_registered || intval($user->usage_count) < $this->options['free_limit'],
                'is_registered' => (bool) $user->is_registered
            );
        }
        
        return array(
            'logged_in' => false,
            'email' => '',
            'usage_count' => 0,
            'can_use' => true,
            'is_registered' => false
        );
    }
    
    private function get_client_ip() {
        $ipkeys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ipkeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'];
    }
    
    public function ajax_email_submit() {
        check_ajax_referer('fact_checker_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $ip_address = $this->get_client_ip();
        
        if (!is_email($email)) {
            wp_send_json_error('Please enter a valid email address');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fact_checker_users';
        
        // Check if email already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s",
            $email
        ));
        
        if ($existing) {
            // Update IP and last used
            $wpdb->update(
                $table_name,
                array(
                    'ip_address' => $ip_address,
                    'last_used' => current_time('mysql')
                ),
                array('email' => $email),
                array('%s', '%s'),
                array('%s')
            );
        } else {
            // Insert new user
            $wpdb->insert(
                $table_name,
                array(
                    'email' => $email,
                    'ip_address' => $ip_address,
                    'usage_count' => 0
                ),
                array('%s', '%s', '%d')
            );
        }
        
        // Set cookie
        setcookie('fact_checker_email', $email, time() + (86400 * 365), '/');
        
        wp_send_json_success(array('email' => $email));
    }
    
    public function ajax_signup() {
        check_ajax_referer('fact_checker_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $ip_address = $this->get_client_ip();
        
        if (!is_email($email) || empty($name) || empty($password)) {
            wp_send_json_error('Please fill in all required fields');
            return;
        }
        
        // Create WordPress user
        $user_id = wp_create_user($email, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
            return;
        }
        
        // Update user meta
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name
        ));
        
        // Update fact checker user record
        global $wpdb;
        $table_name = $wpdb->prefix . 'fact_checker_users';
        
        $wpdb->replace(
            $table_name,
            array(
                'email' => $email,
                'ip_address' => $ip_address,
                'usage_count' => 0,
                'is_registered' => 1
            ),
            array('%s', '%s', '%d', '%d')
        );
        
        // Auto login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        wp_send_json_success(array('user_id' => $user_id, 'email' => $email));
    }
    
    public function ajax_fact_check() {
        check_ajax_referer('fact_checker_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error('Post not found');
            return;
        }
        
        // Check if API key is configured
        if (empty($this->options['api_key'])) {
            wp_send_json_error('API key not configured. Please check plugin settings.');
            return;
        }
        
        // Check user permissions
        $user_status = $this->get_user_status();
        if (!$user_status['can_use']) {
            wp_send_json_error('Usage limit exceeded. Please sign up for unlimited access.');
            return;
        }
        
        // Check cache first
        $cached_result = $this->get_cached_result($post_id, $post->post_content);
        if ($cached_result) {
            wp_send_json_success($cached_result);
            return;
        }
        
        // Get article content
        $content = strip_tags($post->post_content);
        $content = wp_trim_words($content, 800);
        
        if (empty(trim($content))) {
            wp_send_json_error('No content to analyze');
            return;
        }
        
        try {
            $result = $this->analyze_content($content);
            
            // Cache the result
            $this->cache_result($post_id, $post->post_content, $result);
            
            // Update usage count
            $this->update_usage_count($user_status);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            error_log('Fact Checker Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function update_usage_count($user_status) {
        if ($user_status['logged_in'] || $user_status['is_registered']) {
            return; // No limits for registered users
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fact_checker_users';
        
        if (!empty($user_status['email'])) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET usage_count = usage_count + 1, last_used = %s WHERE email = %s",
                current_time('mysql'),
                $user_status['email']
            ));
        }
    }
    
    private function analyze_content($content) {
        $api_key = $this->options['api_key'];
        $model = $this->options['model'];
        $web_searches = intval($this->options['web_searches']);
        $search_context = $this->options['search_context'];
        
        // Use OpenRouter's online model for web search
        $online_model = $model . ':online';
        
        // Prepare the comprehensive fact-checking prompt
        $prompt = "You are a professional fact-checker. Analyze the following article content using web search to verify factual claims.

IMPORTANT INSTRUCTIONS:
1. Use web search to verify key factual claims in the article
2. Rate overall accuracy on a scale of 0-100
3. Identify any outdated, incorrect, or misleading information
4. Provide specific improvement suggestions
5. Return results in EXACT JSON format (no markdown, no extra text)

Article Content:
{$content}

Search and analyze this content thoroughly. Respond ONLY with valid JSON in this exact format:
{
    \"score\": 85,
    \"status\": \"Mostly Accurate\",
    \"description\": \"Brief description of your findings based on web search results\",
    \"issues\": [
        {
            \"type\": \"Outdated Information\",
            \"description\": \"Specific description of what's wrong\",
            \"suggestion\": \"Specific suggestion for correction\"
        }
    ],
    \"sources\": [
        {
            \"title\": \"Actual source title from web search\",
            \"url\": \"https://actual-source-url.com\"
        }
    ]
}

Focus on factual accuracy and provide real sources from your web search results.";
        
        // Prepare API request body with web search options
        $api_body = array(
            'model' => $online_model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2500,
            'temperature' => 0.3,
            'web_search_options' => array(
                'max_results' => $web_searches,
                'search_context_size' => $search_context
            )
        );
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($api_body),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed';
            throw new Exception('API Error (' . $http_code . '): ' . $error_message);
        }
        
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure');
        }
        
        $ai_content = trim($data['choices'][0]['message']['content']);
        
        // Clean up the AI response
        $ai_content = preg_replace('/^```json\s*/', '', $ai_content);
        $ai_content = preg_replace('/\s*```$/', '', $ai_content);
        $ai_content = trim($ai_content);
        
        $result = json_decode($ai_content, true);
        
        if (!$result || !is_array($result)) {
            return array(
                'score' => 50,
                'status' => 'Analysis Incomplete',
                'description' => 'Web search completed but response parsing failed.',
                'issues' => array(),
                'sources' => $this->extract_sources_from_response($body)
            );
        }
        
        // Ensure required fields exist
        $result = array_merge(array(
            'score' => 0,
            'status' => 'Unknown',
            'description' => 'No description provided',
            'issues' => array(),
            'sources' => array()
        ), $result);
        
        // Validate score
        $result['score'] = intval($result['score']);
        if ($result['score'] < 0) $result['score'] = 0;
        if ($result['score'] > 100) $result['score'] = 100;
        
        // If no sources in result, try to extract from response annotations
        if (empty($result['sources'])) {
            $result['sources'] = $this->extract_sources_from_response($body);
        }
        
        return $result;
    }
    
    private function extract_sources_from_response($response_body) {
        $sources = array();
        $data = json_decode($response_body, true);
        
        if (isset($data['choices'][0]['message']['annotations'])) {
            foreach ($data['choices'][0]['message']['annotations'] as $annotation) {
                if (isset($annotation['type']) && $annotation['type'] === 'web_search') {
                    if (isset($annotation['web_search']['results'])) {
                        foreach ($annotation['web_search']['results'] as $result) {
                            if (isset($result['title']) && isset($result['url'])) {
                                $sources[] = array(
                                    'title' => $result['title'],
                                    'url' => $result['url']
                                );
                            }
                        }
                    }
                }
            }
        }
        
        return array_slice($sources, 0, 8);
    }
    
    private function get_cached_result($post_id, $content) {
        global $wpdb;
        
        $content_hash = hash('sha256', $content);
        $table_name = $wpdb->prefix . 'fact_checker_cache';
        
        $cached = $wpdb->get_var($wpdb->prepare(
            "SELECT result FROM $table_name WHERE post_id = %d AND content_hash = %s AND created_at > %s",
            $post_id,
            $content_hash,
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        
        return $cached ? json_decode($cached, true) : false;
    }
    
    private function cache_result($post_id, $content, $result) {
        global $wpdb;
        
        $content_hash = hash('sha256', $content);
        $table_name = $wpdb->prefix . 'fact_checker_cache';
        
        $wpdb->replace(
            $table_name,
            array(
                'post_id' => $post_id,
                'content_hash' => $content_hash,
                'result' => json_encode($result)
            ),
            array('%d', '%s', '%s')
        );
    }
    
    public function ajax_test_api() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        $model = sanitize_text_field($_POST['model']);
        
        if (empty($api_key)) {
            wp_send_json_error('API key is required');
            return;
        }
        
        try {
            $online_model = $model . ':online';
            
            $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => home_url(),
                    'X-Title' => get_bloginfo('name')
                ),
                'body' => json_encode(array(
                    'model' => $online_model,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => 'Search the web for "OpenRouter web search feature" and confirm it works. Respond with: Connection and web search successful.'
                        )
                    ),
                    'max_tokens' => 100,
                    'web_search_options' => array(
                        'max_results' => 3
                    )
                )),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Connection failed: ' . $response->get_error_message());
                return;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($http_code !== 200) {
                $error_data = json_decode($body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
                wp_send_json_error('API Error (' . $http_code . '): ' . $error_message);
                return;
            }
            
            $data = json_decode($body, true);
            
            if (!$data || !isset($data['choices'][0]['message']['content'])) {
                wp_send_json_error('Invalid API response format');
                return;
            }
            
            wp_send_json_success('API and web search connection successful! Model: ' . $online_model);
            
        } catch (Exception $e) {
            wp_send_json_error('Test failed: ' . $e->getMessage());
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Fact Checker Settings',
            'Fact Checker',
            'manage_options',
            'fact-checker',
            array($this, 'options_page')
        );
        
        add_management_page(
            'Fact Checker Users',
            'Fact Checker Users',
            'manage_options',
            'fact-checker-users',
            array($this, 'users_page')
        );
    }
    
    public function settings_init() {
        register_setting('fact_checker', 'fact_checker_options');
        
        add_settings_section(
            'fact_checker_section',
            'Fact Checker Settings',
            array($this, 'settings_section_callback'),
            'fact_checker'
        );
        
        $fields = array(
            'enabled' => 'Enable Fact Checker',
            'api_key' => 'OpenRouter API Key',
            'model' => 'OpenRouter Model',
            'web_searches' => 'Number of Web Searches',
            'search_context' => 'Search Context Size',
            'require_email' => 'Require Email for Visitors',
            'free_limit' => 'Free Reports Limit',
            'terms_url' => 'Terms of Use URL',
            'privacy_url' => 'Privacy Policy URL',
            'theme_mode' => 'Theme Mode',
            'primary_color' => 'Primary Color',
            'success_color' => 'Success Color',
            'warning_color' => 'Warning Color',
            'background_color' => 'Background Color'
        );
        
        foreach ($fields as $field => $title) {
            add_settings_field(
                $field,
                $title,
                array($this, $field . '_render'),
                'fact_checker',
                'fact_checker_section'
            );
        }
    }
    
    public function settings_section_callback() {
        echo '<p>Configure your Fact Checker plugin settings below. This plugin uses OpenRouter\'s web search feature to verify factual claims.</p>';
    }
    
    public function enabled_render() {
        ?>
        <input type='checkbox' name='fact_checker_options[enabled]' <?php checked($this->options['enabled'], 1); ?> value='1'>
        <p class="description">Enable fact checker globally on all single posts</p>
        <?php
    }
    
    public function require_email_render() {
        ?>
        <input type='checkbox' name='fact_checker_options[require_email]' <?php checked($this->options['require_email'], 1); ?> value='1'>
        <p class="description">Require visitors to enter email before accessing fact checker</p>
        <?php
    }
    
    public function free_limit_render() {
        ?>
        <input type='number' name='fact_checker_options[free_limit]' value='<?php echo esc_attr($this->options['free_limit']); ?>' min="1" max="50" style="width: 80px;">
        <p class="description">Number of free fact checks for visitors before requiring signup</p>
        <?php
    }
    
    public function terms_url_render() {
        ?>
        <input type='url' name='fact_checker_options[terms_url]' value='<?php echo esc_attr($this->options['terms_url']); ?>' style="width: 400px;" placeholder="https://yoursite.com/terms">
        <p class="description">URL to your Terms of Use page</p>
        <?php
    }
    
    public function privacy_url_render() {
        ?>
        <input type='url' name='fact_checker_options[privacy_url]' value='<?php echo esc_attr($this->options['privacy_url']); ?>' style="width: 400px;" placeholder="https://yoursite.com/privacy">
        <p class="description">URL to your Privacy Policy page</p>
        <?php
    }
    
    public function api_key_render() {
        ?>
        <input type='password' name='fact_checker_options[api_key]' value='<?php echo esc_attr($this->options['api_key']); ?>' style="width: 400px;">
        <button type="button" id="test-api-connection" class="button">Test Connection</button>
        <p class="description">Your OpenRouter API key with web search access. Get one at <a href="https://openrouter.ai" target="_blank">openrouter.ai</a></p>
        <div id="api-test-result"></div>
        <?php
    }
    
    public function model_render() {
        $models = array(
            'openai/gpt-4o' => 'GPT-4o (Recommended)',
            'openai/gpt-4' => 'GPT-4',
            'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet',
            'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
            'google/gemini-pro' => 'Gemini Pro'
        );
        ?>
        <select name='fact_checker_options[model]'>
            <?php foreach ($models as $value => $label): ?>
                <option value='<?php echo $value; ?>' <?php selected($this->options['model'], $value); ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">AI model for fact-checking (will use :online version for web search)</p>
        <?php
    }
    
    public function web_searches_render() {
        $searches = array(3, 5, 7, 10);
        ?>
        <select name='fact_checker_options[web_searches]'>
            <?php foreach ($searches as $num): ?>
                <option value='<?php echo $num; ?>' <?php selected($this->options['web_searches'], $num); ?>><?php echo $num; ?> searches</option>
            <?php endforeach; ?>
        </select>
        <p class="description">Maximum web search results to retrieve (affects cost: $4 per 1000 results)</p>
        <?php
    }
    
    public function search_context_render() {
        $contexts = array(
            'low' => 'Low - Basic queries',
            'medium' => 'Medium - General queries (Recommended)',
            'high' => 'High - Detailed research'
        );
        ?>
        <select name='fact_checker_options[search_context]'>
            <?php foreach ($contexts as $value => $label): ?>
                <option value='<?php echo $value; ?>' <?php selected($this->options['search_context'], $value); ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">Search context size - higher means more thorough but more expensive</p>
        <?php
    }
    
    public function theme_mode_render() {
        $modes = array(
            'light' => 'Light Mode',
            'dark' => 'Dark Mode'
        );
        ?>
        <select name='fact_checker_options[theme_mode]'>
            <?php foreach ($modes as $value => $label): ?>
                <option value='<?php echo $value; ?>' <?php selected($this->options['theme_mode'], $value); ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">Choose between light and dark theme for the fact checker</p>
        <?php
    }
    
    public function primary_color_render() {
        ?>
        <input type='color' name='fact_checker_options[primary_color]' value='<?php echo esc_attr($this->options['primary_color']); ?>'>
        <p class="description">Primary color for buttons and icons</p>
        <?php
    }
    
    public function success_color_render() {
        ?>
        <input type='color' name='fact_checker_options[success_color]' value='<?php echo esc_attr($this->options['success_color']); ?>'>
        <p class="description">Color for success indicators and high scores</p>
        <?php
    }
    
    public function warning_color_render() {
        ?>
        <input type='color' name='fact_checker_options[warning_color]' value='<?php echo esc_attr($this->options['warning_color']); ?>'>
        <p class="description">Color for warnings and issues</p>
        <?php
    }
    
    public function background_color_render() {
        ?>
        <input type='color' name='fact_checker_options[background_color]' value='<?php echo esc_attr($this->options['background_color']); ?>'>
        <p class="description">Background color for the fact checker box</p>
        <?php
    }
    
    public function users_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fact_checker_users';
        
        $users = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 500");
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_registered = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_registered = 1");
        
        ?>
        <div class="wrap">
            <h1>Fact Checker Users</h1>
            
            <div class="stats-boxes" style="display: flex; gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #3b82f6;"><?php echo $total_users; ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Total Users</p>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #059669;"><?php echo $total_registered; ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Registered Users</p>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #f59e0b;"><?php echo ($total_users - $total_registered); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;">Free Users</p>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Usage Count</th>
                        <th>IP Address</th>
                        <th>Created</th>
                        <th>Last Used</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo esc_html($user->email); ?></td>
                        <td>
                            <?php if ($user->is_registered): ?>
                                <span style="color: #059669; font-weight: 600;">Registered</span>
                            <?php else: ?>
                                <span style="color: #f59e0b;">Free (<?php echo ($this->options['free_limit'] - $user->usage_count); ?> remaining)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $user->usage_count; ?></td>
                        <td><?php echo esc_html($user->ip_address); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($user->created_at)); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($user->last_used)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function options_page() {
        ?>
        <div class="wrap">
            <h1>Fact Checker Settings</h1>
            <form action='options.php' method='post'>
                <?php
                settings_fields('fact_checker');
                do_settings_sections('fact_checker');
                submit_button();
                ?>
            </form>
            
            <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                <h2>About Email Collection & Usage Limits</h2>
                <p>This plugin collects visitor emails to provide fact-checking reports and enforce usage limits. Here's how it works:</p>
                <ul>
                    <li><strong>Free Users:</strong> Can use <?php echo $this->options['free_limit']; ?> fact checks, then must sign up</li>
                    <li><strong>Registered Users:</strong> Unlimited fact checks</li>
                    <li><strong>Logged-in Users:</strong> Always unlimited access</li>
                    <li><strong>Email Storage:</strong> Stored securely in your WordPress database</li>
                    <li><strong>Privacy:</strong> Make sure your Terms and Privacy links are configured</li>
                </ul>
                <p><a href="<?php echo admin_url('tools.php?page=fact-checker-users'); ?>" class="button">View User List →</a></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-api-connection').on('click', function() {
                var button = $(this);
                var apiKey = $('input[name="fact_checker_options[api_key]"]').val();
                var model = $('select[name="fact_checker_options[model]"]').val();
                var resultDiv = $('#api-test-result');
                
                if (!apiKey) {
                    resultDiv.html('<div style="color: red; margin-top: 10px;">Please enter an API key first.</div>');
                    return;
                }
                
                button.prop('disabled', true).text('Testing...');
                resultDiv.html('<div style="color: #666; margin-top: 10px;">Testing API and web search connection...</div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_fact_checker_api',
                        api_key: apiKey,
                        model: model,
                        nonce: '<?php echo wp_create_nonce('test_api_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div style="color: green; margin-top: 10px;">✓ ' + response.data + '</div>');
                        } else {
                            resultDiv.html('<div style="color: red; margin-top: 10px;">✗ ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div style="color: red; margin-top: 10px;">✗ Test failed - please try again.</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        });
        </script>
        
        <style>
            .wrap {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .form-table th {
                font-weight: 600;
            }
            .form-table td input[type="color"] {
                width: 50px;
                height: 35px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .form-table td input[type="password"],
            .form-table td input[type="url"],
            .form-table td input[type="number"],
            .form-table td select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            #test-api-connection {
                background: #0073aa;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                margin-left: 10px;
            }
            #test-api-connection:hover:not(:disabled) {
                background: #005a87;
            }
            #test-api-connection:disabled {
                background: #ccc;
                cursor: not-allowed;
            }
        </style>
        <?php
    }
}

// Initialize the plugin
new FactChecker();