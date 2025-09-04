<?php
/**
 * Plugin Name: Fact Checker
 * Description: AI-powered fact-checking plugin that verifies article accuracy using OpenRouter and web searches
 * Version: 1.0.2
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
define('FACT_CHECKER_VERSION', '1.0.2');
define('FACT_CHECKER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FACT_CHECKER_PLUGIN_PATH', plugin_dir_path(__FILE__));

class FactChecker {
    
    private $options;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_fact_check_article', array($this, 'ajax_fact_check'));
        add_action('wp_ajax_nopriv_fact_check_article', array($this, 'ajax_fact_check'));
        
        // Add fact checker to content
        add_filter('the_content', array($this, 'add_fact_checker_to_content'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        $this->options = get_option('fact_checker_options', array(
            'enabled' => true,
            'api_key' => '',
            'model' => 'openai/gpt-4',
            'web_searches' => 5,
            'primary_color' => '#3b82f6',
            'success_color' => '#059669',
            'warning_color' => '#f59e0b',
            'background_color' => '#f8fafc'
        ));
    }
    
    public function activate() {
        // Create cache table
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fact_checker_cache';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            content_hash varchar(64) NOT NULL,
            result longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_content (post_id, content_hash)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
                'colors' => array(
                    'primary' => $this->options['primary_color'],
                    'success' => $this->options['success_color'],
                    'warning' => $this->options['warning_color'],
                    'background' => $this->options['background_color']
                )
            ));
        }
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
            $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => home_url(),
                    'X-Title' => get_bloginfo('name')
                ),
                'body' => json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => 'Test connection. Please respond with "Connection successful".'
                        )
                    ),
                    'max_tokens' => 50
                )),
                'timeout' => 30
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
            
            wp_send_json_success('Connection successful');
            
        } catch (Exception $e) {
            wp_send_json_error('Test failed: ' . $e->getMessage());
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
        
        ob_start();
        ?>
        <div class="fact-check-container" data-post-id="<?php echo get_the_ID(); ?>">
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
                    <button class="check-button" onclick="factCheckerStart()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                        </svg>
                        <span>Check Facts</span>
                    </button>
                </div>
                <p class="fact-check-description">Verify the accuracy of this article using AI-powered analysis and real-time sources.</p>
                
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
    
    public function ajax_fact_check() {
        check_ajax_referer('fact_checker_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_die('Post not found');
        }
        
        // Check cache first
        $cached_result = $this->get_cached_result($post_id, $post->post_content);
        if ($cached_result) {
            wp_send_json_success($cached_result);
            return;
        }
        
        // Get article content
        $content = strip_tags($post->post_content);
        $content = wp_trim_words($content, 500); // Limit for API
        
        try {
            $result = $this->analyze_content($content);
            
            // Cache the result
            $this->cache_result($post_id, $post->post_content, $result);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error('Analysis failed: ' . $e->getMessage());
        }
    }
    
    private function analyze_content($content) {
        $api_key = $this->options['api_key'];
        $model = $this->options['model'];
        $web_searches = intval($this->options['web_searches']);
        
        // Prepare the prompt
        $prompt = "You are a fact-checking AI. Analyze the following article content and:
1. Identify any factual claims that can be verified
2. Rate the overall accuracy on a scale of 0-100
3. List any outdated, incorrect, or misleading information
4. Provide suggestions for improvement
5. Perform {$web_searches} web searches to verify key facts

Article content:
{$content}

Please respond in JSON format with this structure:
{
    \"score\": 85,
    \"status\": \"Mostly Accurate\",
    \"description\": \"Brief description of findings\",
    \"issues\": [
        {
            \"type\": \"Outdated Information\",
            \"description\": \"Description of the issue\",
            \"suggestion\": \"Suggested correction\"
        }
    ],
    \"sources\": [
        {
            \"title\": \"Source title\",
            \"url\": \"https://example.com\"
        }
    ]
}";
        
        // Make API call to OpenRouter
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                )
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response');
        }
        
        $result = json_decode($data['choices'][0]['message']['content'], true);
        
        if (!$result) {
            throw new Exception('Failed to parse AI response');
        }
        
        return $result;
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
    
    public function add_admin_menu() {
        add_options_page(
            'Fact Checker Settings',
            'Fact Checker',
            'manage_options',
            'fact-checker',
            array($this, 'options_page')
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
        echo '<p>Configure your Fact Checker plugin settings below.</p>';
    }
    
    public function enabled_render() {
        ?>
        <input type='checkbox' name='fact_checker_options[enabled]' <?php checked($this->options['enabled'], 1); ?> value='1'>
        <p class="description">Enable fact checker globally on all single posts</p>
        <?php
    }
    
    public function api_key_render() {
        ?>
        <input type='password' name='fact_checker_options[api_key]' value='<?php echo esc_attr($this->options['api_key']); ?>' style="width: 400px;">
        <p class="description">Your OpenRouter API key. Get one at <a href="https://openrouter.ai" target="_blank">openrouter.ai</a></p>
        <?php
    }
    
    public function model_render() {
        $models = array(
            'openai/gpt-4' => 'GPT-4',
            'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
            'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet',
            'google/gemini-pro' => 'Gemini Pro'
        );
        ?>
        <select name='fact_checker_options[model]'>
            <?php foreach ($models as $value => $label): ?>
                <option value='<?php echo $value; ?>' <?php selected($this->options['model'], $value); ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">Choose which AI model to use for fact-checking</p>
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
        <p class="description">Number of web searches the AI should perform for verification</p>
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
        </div>
        
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
            .form-table td select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
        </style>
        <?php
    }
}

// Initialize the plugin
new FactChecker();