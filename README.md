# Fact Checker WordPress Plugin

A modern, AI-powered fact-checking plugin that verifies article accuracy using OpenRouter and web searches.

## Directory Structure

Create the following directory structure in your `wp-content/plugins/` folder:

```
fact-checker/
├── fact-checker.php                 # Main plugin file (from first artifact)
├── assets/
│   ├── css/
│   │   └── fact-checker.css        # Styles (from second artifact)
│   └── js/
│       └── fact-checker.js         # JavaScript (from third artifact)
└── README.md                       # This file
```

## Installation Steps

1. **Create Plugin Directory:**

   ```bash
   mkdir wp-content/plugins/fact-checker
   mkdir wp-content/plugins/fact-checker/assets
   mkdir wp-content/plugins/fact-checker/assets/css
   mkdir wp-content/plugins/fact-checker/assets/js
   ```

2. **Add Plugin Files:**

   - Copy the PHP code from the first artifact to `fact-checker/fact-checker.php`
   - Copy the CSS code from the second artifact to `fact-checker/assets/css/fact-checker.css`
   - Copy the JavaScript code from the third artifact to `fact-checker/assets/js/fact-checker.js`

3. **Activate Plugin:**

   - Go to WordPress Admin → Plugins
   - Find "Fact Checker" and click "Activate"

4. **Configure Settings:**
   - Go to WordPress Admin → Settings → Fact Checker
   - Add your OpenRouter API key
   - Configure your preferences

## Configuration

### Getting an OpenRouter API Key

1. Visit [OpenRouter.ai](https://openrouter.ai)
2. Sign up for an account
3. Go to your dashboard and create an API key
4. Copy the key to the plugin settings

### Settings Options

- **Enable Fact Checker**: Toggle global activation
- **OpenRouter API Key**: Your API key from OpenRouter
- **Model**: Choose AI model (GPT-4, Claude, Gemini, etc.)
- **Web Searches**: Number of searches (3, 5, 7, or 10)
- **Colors**: Customize the appearance to match your theme

## Features

- **AI-Powered Analysis**: Uses advanced AI models to analyze content
- **Web Search Verification**: Performs real-time web searches to verify facts
- **Caching System**: Caches results for 24 hours to reduce API costs
- **Modern UI**: Clean, responsive design that works with any theme
- **Customizable Colors**: Match your brand colors
- **Download Reports**: Export fact-check results as text files
- **Mobile Responsive**: Works perfectly on all devices

## Usage

Once activated and configured:

1. The fact checker automatically appears at the end of single blog posts
2. Visitors click "Check Facts" to start the analysis
3. The AI analyzes the content and performs web searches
4. Results show a score, issues found, and verification sources
5. Results are cached to avoid repeated API calls

## Styling

The plugin uses completely independent CSS with `!important` declarations to ensure it looks consistent regardless of your theme. It includes:

- Custom font loading (Inter)
- CSS custom properties for colors
- Responsive design
- Modern animations and transitions

## API Usage

The plugin makes efficient use of API calls:

- **Caching**: Results cached for 24 hours
- **Configurable searches**: Choose 3-10 web searches per analysis
- **Error handling**: Graceful fallbacks for API failures
- **Timeout protection**: 60-second timeout prevents hanging

## Customization

### Colors

Customize colors through the admin settings or by overriding CSS custom properties:

```css
:root {
  --fc-primary: #your-primary-color;
  --fc-success: #your-success-color;
  --fc-warning: #your-warning-color;
  --fc-background: #your-background-color;
}
```

### Positioning

To change where the fact checker appears, modify the `add_fact_checker_to_content` filter or use custom placement:

```php
// Remove automatic placement
remove_filter('the_content', array($fact_checker_instance, 'add_fact_checker_to_content'));

// Add custom placement
echo do_shortcode('[fact_checker]');
```

## Database

The plugin creates a cache table: `wp_fact_checker_cache`

- Stores analysis results with content hashes
- Automatically cleans up old entries
- Prevents duplicate analysis of unchanged content

## Security

- CSRF protection with WordPress nonces
- SQL injection prevention with prepared statements
- XSS protection with proper escaping
- API key encryption in database

## Performance

- **Caching**: 24-hour result caching
- **Lazy Loading**: JavaScript loads only on single posts
- **Optimized CSS**: Minimal, efficient styles
- **Database Indexing**: Proper indexes for fast lookups

## Troubleshooting

### Common Issues

1. **"API request failed"**

   - Check your OpenRouter API key
   - Verify your account has credits
   - Check internet connectivity

2. **"Analysis timed out"**

   - Reduce number of web searches
   - Try a different AI model
   - Check server timeout settings

3. **Styling issues**
   - The plugin CSS uses `!important` to override theme styles
   - Check for JavaScript errors in browser console
   - Verify all files are properly uploaded

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and updates:

- Website: [https://sawahsolutions.com](https://sawahsolutions.com)
- Email: Contact through website

## License

GPL v2 or later

## Changelog

### Version 1.0.0

- Initial release
- AI-powered fact checking
- Web search verification
- Caching system
- Modern responsive UI
- Admin settings panel
- Color customization
