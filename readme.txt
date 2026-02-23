=== Advanced LLM Tracker ===
Contributors: yourname
Tags: ai, llm, bot-detection, security, machine-learning, gptbot, claudebot
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Next-generation AI/LLM bot detection for WordPress using behavioral machine learning. Detects GPTBot, ClaudeBot, and sophisticated AI crawlers.

== Description ==

Advanced LLM Tracker is a next-generation WordPress plugin that detects AI and LLM (Large Language Model) bots using behavioral machine learning techniques. Unlike traditional rule-based detection that relies on user-agent strings, our plugin analyzes visitor behavior patterns to identify both known and unknown AI systems.

= Key Features =

**Machine Learning-Based Detection**
- Multi-layered behavioral analysis
- Heuristic scoring with 40+ features
- Support for cloud ML inference (Pro)
- Ensemble classification methods

**Comprehensive Tracking**
- Mouse movement analysis
- Scroll behavior patterns
- DOM interaction tracking
- Session-level feature extraction

**Privacy-First Design**
- GDPR/CCPA compliant
- IP address anonymization
- Differential privacy noise injection
- Granular consent management

**Automated Responses**
- Dynamic rate limiting
- JavaScript challenges
- Graduated blocking system
- Tarpitting for aggressive scrapers

**Real-time Alerts**
- Email notifications
- Slack integration
- Multi-severity alert levels
- Custom alert rules

**Analytics & Reporting**
- Live traffic monitoring
- Bot category classification
- Confidence scoring
- Historical trend analysis

= Detected Bot Categories =

- **Training Data Harvesters**: GPTBot, ClaudeBot, Meta-ExternalAgent, Common Crawl
- **Search Indexers**: OAI-SearchBot, PerplexityBot, Bingbot-AI
- **Research Aggregators**: ChatGPT-User, academic crawlers
- **Malicious Scrapers**: Content thieves, competitive espionage

= Integrations =

- Google Analytics 4
- Slack notifications
- WordPress Privacy API
- WordPress Consent API
- CDN/WAF ready (Cloudflare, AWS, Fastly)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/advanced-llm-tracker`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'LLM Tracker' > 'Settings' to configure the plugin
4. Enable tracking and set your preferences

== Frequently Asked Questions ==

= Does this plugin slow down my website? =

No. The JavaScript SDK is optimized for performance (<15KB gzipped), loads asynchronously, and uses event batching to minimize network requests. Core Web Vitals are preserved.

= Is this plugin GDPR compliant? =

Yes. The plugin includes built-in consent management, IP anonymization, differential privacy, and data retention controls. All data processing can be configured to comply with GDPR requirements.

= Can I use this with caching plugins? =

Yes. The plugin is compatible with all major caching plugins including WP Rocket, W3 Total Cache, and LiteSpeed Cache.

= Does it work with CDNs? =

Yes. The plugin is CDN-compatible and can integrate with Cloudflare, AWS CloudFront, and other major CDN providers.

= What PHP version is required? =

PHP 8.1 or higher is required for optimal performance and security.

== Screenshots ==

1. Dashboard with real-time statistics
2. Live traffic monitoring
3. Session details and classification
4. Alert management
5. Settings page
6. Privacy configuration

== Changelog ==

= 1.0.0 =
* Initial release
* Machine learning-based bot detection
* Behavioral tracking with JavaScript SDK
* Heuristic classification engine
* Privacy-compliant consent management
* Real-time alerting system
* GA4 and Slack integrations
* REST API for data access

== Upgrade Notice ==

= 1.0.0 =
Initial release of Advanced LLM Tracker.

== Privacy Policy ==

Advanced LLM Tracker collects anonymized behavioral data to detect AI bot traffic. This includes:

- Page views and navigation patterns
- Scroll depth and velocity
- Mouse movement patterns (when consent is given)
- Session duration and engagement metrics

All IP addresses are hashed for privacy. No personally identifiable information is stored. Data retention is configurable (default: 90 days).

== Additional Info ==

For more information, documentation, and support, please visit:
https://yourwebsite.com/advanced-llm-tracker
