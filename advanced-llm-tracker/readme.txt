=== Advanced LLM Tracker ===
Contributors: yourname
Tags: ai, bot detection, llm, chatgpt, claude, security, tracking, gdpr, privacy
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Next-gen AI/LLM bot detection with behavioral ML. Detects GPTBot, ClaudeBot, and sophisticated AI crawlers using machine learning.

== Description ==

Advanced LLM Tracker is a privacy-first WordPress plugin that detects and tracks AI/LLM bot traffic on your website. Using behavioral analysis and machine learning, it can distinguish between human visitors and AI systems like GPTBot, ClaudeBot, and other sophisticated crawlers.

= Features =

**Bot Detection**
- Detects known AI bots (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, etc.)
- Behavioral analysis using mouse tracking, scroll patterns, and interaction data
- Machine learning classification with confidence scores
- Real-time session classification

**Privacy First**
- GDPR compliant with built-in consent management
- IP address anonymization
- Differential privacy for coordinate data
- Configurable data retention policies
- WordPress Privacy API integration

**Response Actions**
- Allow, monitor, challenge, or block detected bots
- Rate limiting per session
- JavaScript challenges for suspicious traffic
- Automatic blocking for high-confidence bot detection

**Analytics Dashboard**
- Real-time traffic monitoring
- Bot vs human traffic statistics
- Session details and event logs
- Alert notifications (email and Slack)

**Integrations**
- Google Analytics 4 event tracking
- Slack webhook notifications
- WordPress REST API
- Third-party consent plugin support (CookieYes, Complianz, etc.)

= Bot Categories =

The plugin identifies and categorizes bots into:

* **Training Harvesters** - Bots collecting data for AI training (GPTBot, ClaudeBot, etc.)
* **Search Indexers** - AI-powered search crawlers (PerplexityBot, etc.)
* **Research Aggregators** - Academic and research bots
* **Malicious Scrapers** - Aggressive, unwanted crawlers
* **Unknown Bots** - Unclassified bot traffic

= Requirements =

* WordPress 6.6 or higher
* PHP 8.1 or higher
* MySQL 5.7 or higher (or MariaDB 10.3+)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/advanced-llm-tracker/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'LLM Tracker' > 'Settings' to configure the plugin
4. The plugin will automatically start tracking and detecting AI bot traffic

== Frequently Asked Questions ==

= Does this plugin slow down my website? =

No. The plugin is designed for performance with:
- Asynchronous JavaScript loading
- Configurable sampling rates
- Efficient database queries with proper indexing
- Optional caching layer

= Is this plugin GDPR compliant? =

Yes. The plugin includes:
- Built-in consent banner
- IP address anonymization
- Configurable data retention
- WordPress Privacy API integration for data export/erasure

= What bots can it detect? =

The plugin can detect:
- OpenAI GPTBot
- Anthropic ClaudeBot
- Google-Extended (Bard/AI training)
- CommonCrawl CCBot
- PerplexityBot
- ChatGPT-User agent
- Meta-ExternalAgent
- Plus many more via pattern matching

= Can I customize the response to detected bots? =

Yes. You can configure different responses based on confidence levels:
- Allow - Let the bot access your site
- Monitor - Track but allow access
- Challenge - Present a JavaScript challenge
- Block - Deny access

= Does it work with caching plugins? =

Yes. The plugin is compatible with popular caching plugins like WP Rocket, W3 Total Cache, and LiteSpeed Cache.

== Screenshots ==

1. Dashboard with traffic statistics
2. Live traffic monitoring
3. Session details and classification
4. Settings page
5. Alerts and notifications

== Changelog ==

= 1.0.0 =
* Initial release
* Bot detection with behavioral analysis
* Privacy-first design with GDPR compliance
* Admin dashboard with real-time stats
* Alert notifications (email and Slack)
* GA4 integration

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade necessary.

== Privacy Policy ==

Advanced LLM Tracker collects the following data:

* **Session Information** - Anonymous session ID, hashed IP address, user agent
* **Behavioral Data** - Mouse movements, scroll patterns, click events (with noise added for privacy)
* **Page Views** - URLs visited during the session

All data is stored in your WordPress database and is never shared with third parties (unless you enable optional integrations like GA4 or Slack).

Users can:
- Opt-out via the consent banner
- Request data export via WordPress Privacy tools
- Request data deletion via WordPress Privacy tools

== Credits ==

* Chart.js for data visualization
* WordPress REST API for backend communication
* Inspired by the need for better AI bot detection in the age of LLMs
