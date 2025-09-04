<?php
/**
 * Plugin Name: Simple Fact Checker
 * Description: Minimal fact checker for testing
 * Version: 1.0.5
 * Author: Mohamed Sawah
 */

if (!defined('ABSPATH')) exit;

class SimpleFactChecker {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('wp_ajax_test_api', array($this, 'test_api'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function activate() {
        add_option('simple_fact_checker_api_key', '');
    }
    
    public function add_menu() {
        add_options_page(
            'Simple Fact Checker',
            'Simple Fact Checker',
            'manage_options',
            'simple-fact-checker',
            array($this, 'settings_page')
        );
    }
    
    public function settings_page() {
        if (isset($_POST['save_settings'])) {
            update_option('simple_fact_checker_api_key', sanitize_text_field($_POST['api_key']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $api_key = get_option('simple_fact_checker_api_key', '');
        ?>
        <div class="wrap">
            <h1>Simple Fact Checker Settings</h1>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th>OpenRouter API Key</th>
                        <td>
                            <input type="password" name="api_key" value="<?php echo esc_attr($api_key); ?>" style="width: 400px;">
                            <br><small>Get your API key from <a href="https://openrouter.ai" target="_blank">openrouter.ai</a></small>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary', 'save_settings'); ?>
            </form>
            
            <hr>
            
            <h2>Test API Connection</h2>
            <button type="button" id="test-api" class="button button-secondary">Test Connection</button>
            <div id="test-result" style="margin-top: 15px;"></div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#test-api').click(function() {
                    var button = $(this);
                    var result = $('#test-result');
                    
                    button.prop('disabled', true).text('Testing...');
                    result.html('<p>Testing API connection...</p>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'test_api',
                            api_key: $('input[name="api_key"]').val(),
                            nonce: '<?php echo wp_create_nonce('test_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                result.html('<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px;">✅ ' + response.data + '</div>');
                            } else {
                                result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">❌ ' + response.data + '</div>');
                            }
                        },
                        error: function(xhr, status, error) {
                            result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">❌ Request failed: ' + error + '</div>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('Test Connection');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function test_api() {
        check_ajax_referer('test_nonce', 'nonce');
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error('Please enter an API key');
            return;
        }
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url()
            ),
            'body' => json_encode(array(
                'model' => 'openai/gpt-3.5-turbo',
                'messages' => array(array(
                    'role' => 'user',
                    'content' => 'Say "Connection successful"'
                )),
                'max_tokens' => 20
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
            return;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            $error = json_decode($body, true);
            $message = isset($error['error']['message']) ? $error['error']['message'] : 'Unknown error';
            wp_send_json_error('API Error (' . $code . '): ' . $message);
            return;
        }
        
        $data = json_decode($body, true);
        if (isset($data['choices'][0]['message']['content'])) {
            wp_send_json_success('API connection successful! Response: ' . $data['choices'][0]['message']['content']);
        } else {
            wp_send_json_error('Invalid response format');
        }
    }
}

new SimpleFactChecker();
?>