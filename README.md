# WooCommerce AI Review Manager

A powerful WordPress plugin that automatically collects customer reviews after purchase, analyzes sentiment using Google's Gemini AI, and generates AI-powered responses for negative feedback.

## Features

✨ **Automatic Review Collection**
- Hooks into WooCommerce order completion events
- Sends automatic review invitations to customers after a configurable delay
- Tracks invitation delivery and opens

🧠 **AI-Powered Sentiment Analysis**
- Uses Google Gemini API to analyze review sentiment
- Classifies reviews as positive, neutral, or negative
- Provides sentiment scores (0-1) for granular analysis
- Analyzes reviews automatically when posted

💬 **Intelligent Response Generation**
- Generates professional, empathetic responses for negative reviews
- Uses AI to understand customer issues and suggest solutions
- Provides shop owners with AI-drafted responses for approval
- Customizable response generation based on product context

📊 **Sentiment Dashboard**
- Real-time sentiment breakdown (positive/neutral/negative)
- Dashboard widgets showing sentiment trends
- Pending responses queue for quick action
- Statistics filtered by time period

🔒 **Security & Standards**
- Full WordPress security best practices (nonces, sanitization, escaping)
- WooCommerce API integration following WC standards
- Database schema with proper indexes for performance
- Admin-only access with capability checks

## Installation

1. **Download or clone** this plugin to your `/wp-content/plugins/` directory
2. **Activate** the plugin in WordPress Admin → Plugins
3. **Configure** via WooCommerce → AI Review Manager
4. **Add your Google Gemini API key** (see below)
5. **Enable** the feature and set your preferences

## Setup: Google Gemini API

### 1. Create a Google Cloud Project
- Go to [Google Cloud Console](https://console.cloud.google.com)
- Click "Select a Project" → "New Project"
- Give it a name like "WooCommerce AI Review Manager"
- Click "Create"

### 2. Enable Gemini API
- In the search bar, search for "Generative AI"
- Click on "Generative AI API"
- Click "Enable"

### 3. Create an API Key
- Go to Credentials (left sidebar)
- Click "Create Credentials" → "API Key"
- Copy the API key
- Click "Restrict Key" and set it to "Generative AI API"

### 4. Add to Plugin Settings
- Go to WooCommerce → AI Review Manager
- Paste your API key in the "Google Gemini API Key" field
- Click "Save Settings"

## Configuration

### Dashboard Settings

**Enable AI Review Manager**
- Toggle to enable/disable all plugin features

**Send Automatic Review Invitations**
- When enabled, customers receive review invitations after purchase
- Recommendations: Keep enabled for maximum review collection

**Days After Purchase to Send Invitation**
- Default: 7 days
- Recommended: 7-14 days (allows time for product evaluation)
- Configurable: 1-365 days

**Auto-generate Responses for Negative Reviews**
- When enabled, AI automatically drafts responses to negative reviews
- Responses are created but not posted automatically
- Shop owners review and approve before posting

**Negative Sentiment Threshold (0-1)**
- Default: 0.4
- 0 = most negative, 1 = most positive
- Reviews below this threshold are marked as "negative"
- Adjust based on your business needs

## Usage

### For Shop Owners

**Monitoring Reviews**
1. Check the dashboard widgets for sentiment trends
2. View pending AI responses in the "Pending AI Responses" widget
3. Click "Review & Respond" to see the AI-drafted response
4. Edit the response or accept the AI version
5. Post the response to the review

**Invitation Tracking**
- The plugin automatically sends invitations to customers
- Track invitation delivery and opens in plugin database
- Refine invitation timing based on open rates

**Sentiment Analytics**
- View sentiment breakdown over 7, 30, and 90-day periods
- Identify product-specific issues from negative reviews
- Track sentiment trends over time

### Automated Workflows

**Review Invitation Process**
1. Order marked as "Completed" in WooCommerce
2. Wait for configured days (default 7)
3. Plugin sends review invitation email
4. Customer reviews product in WooCommerce review system
5. Review automatically analyzed for sentiment

**Negative Review Response Process**
1. Negative review detected (below sentiment threshold)
2. AI analyzes review and product context
3. AI generates professional response suggestion
4. Shop owner notified via dashboard
5. Owner can approve and post response, or customize

## Database Schema

### `wp_wc_ai_review_invitations`
Tracks review invitations sent to customers
- `id`: Primary key
- `order_id`: WooCommerce order ID
- `product_id`: Product ID
- `customer_email`: Customer email
- `customer_name`: Customer name
- `product_name`: Product name
- `invitation_sent_date`: When invitation was sent
- `invitation_opened`: Whether customer opened the email
- `opened_date`: When email was opened (if tracked)

### `wp_wc_ai_review_responses`
Tracks AI-analyzed reviews and generated responses
- `id`: Primary key
- `review_id`: WordPress comment ID (review)
- `product_id`: Product ID
- `customer_email`: Reviewer's email
- `review_content`: Full review text
- `sentiment`: Classified sentiment (positive/neutral/negative)
- `sentiment_score`: Score from 0-1
- `ai_response_generated`: AI-drafted response
- `ai_response_used`: Whether the AI response was posted
- `custom_response`: Shop owner's custom response (if modified)
- `response_status`: Status (pending/approved/posted)
- `created_date`: Record creation date
- `updated_date`: Last update date

## Performance Considerations

### API Rate Limiting
- Google Gemini API has request limits based on your plan
- Plugin handles rate limit errors gracefully
- Consider API usage for high-volume stores

### Database Optimization
- Tables include indexes on frequently queried columns
- Invitation and response records archived after 90 days (configurable)
- Query performance optimized for typical WooCommerce stores

### Scheduled Events
- Review invitations sent via WordPress cron
- Ensure WP-Cron is enabled: `define('DISABLE_WP_CRON', false);` in `wp-config.php`
- If WP-Cron is disabled, set up a real cron job: `0 2 * * * wget -q -O - http://yoursite.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1`

## Troubleshooting

### API Key Not Working
- ✅ Verify API key is correct (copy/paste from Google Console)
- ✅ Check API is enabled in Google Cloud Console
- ✅ Ensure API key is restricted to "Generative AI API"
- ✅ Check for typos in settings

### Invitations Not Sending
- ✅ Check "Send Automatic Review Invitations" is enabled in settings
- ✅ Verify WordPress cron is working: add `define('DISABLE_WP_CRON', false);` to `wp-config.php`
- ✅ Check PHP error logs for email delivery issues
- ✅ Ensure WooCommerce order status is set to "Completed"

### Sentiment Analysis Failing
- ✅ Verify API key and test connection
- ✅ Check review text contains meaningful content (very short reviews may fail)
- ✅ Review API rate limits (Google may throttle requests)
- ✅ Check server can reach Google APIs (no firewall blocking)

### Dashboard Not Showing Stats
- ✅ Wait for reviews to be analyzed (happens on review posting)
- ✅ Check database tables were created: `wp_wc_ai_review_invitations` and `wp_wc_ai_review_responses`
- ✅ Verify admin user has "manage_woocommerce" capability

## Security

This plugin implements WordPress security best practices:

- ✅ **Nonce verification** on all forms
- ✅ **Capability checks** (manage_woocommerce) on admin pages
- ✅ **Input sanitization** using WordPress functions (sanitize_text_field, sanitize_email, etc.)
- ✅ **Output escaping** using esc_html, esc_attr, esc_url
- ✅ **Prepared statements** for all database queries
- ✅ **HTTPS-only** API communication
- ✅ **API key storage** in options table with no exposure in frontend

## Development & Customization

### Hooks & Filters

**Sentiment Analysis**
```php
// Filter sentiment data before saving
apply_filters( 'wc_ai_review_manager_sentiment_data', $sentiment_data, $review_id );
```

**Response Generation**
```php
// Filter AI-generated response before saving
apply_filters( 'wc_ai_review_manager_generated_response', $response_text, $review_id );
```

**Invitation Email**
```php
// Filter invitation email before sending
apply_filters( 'wc_ai_review_manager_invitation_subject', $subject );
apply_filters( 'wc_ai_review_manager_invitation_message', $message, $customer_name, $product );
```

### Creating Follow-Up Tasks

Based on the plugin architecture and feedback cycle, consider these follow-up tasks:

**Phase 2: Enhanced Features**
- Email template customization UI
- A/B testing for invitation timing
- Bulk review analysis tool
- Export sentiment data to CSV/Google Sheets
- Webhook integration for external systems
- Multi-language support for AI responses

**Phase 3: Analytics & Intelligence**
- Advanced sentiment trending and forecasting
- Competitor sentiment analysis
- Product improvement recommendations from reviews
- Customer satisfaction scoring
- Integration with business intelligence tools

**Phase 4: Integration & Expansion**
- Integration with email marketing platforms (Mailchimp, etc.)
- SMS invitation option
- Social media review integration (Facebook, Google Reviews)
- Integration with customer service platforms (Zendesk, etc.)

## License

This plugin is released under the GPL v2 or later license. See LICENSE file for details.

## Support & Contributing

For issues, feature requests, or contributions, please visit the plugin repository.

## Changelog

### Version 1.0.0 (Initial Release)
- Automatic review collection and invitation sending
- Sentiment analysis using Google Gemini API
- AI-powered response generation
- Sentiment analytics dashboard
- WordPress/WooCommerce standards compliance
- Security hardening with nonce verification and input validation
