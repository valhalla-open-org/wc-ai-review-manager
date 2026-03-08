# WooCommerce AI Review Manager - Technical Architecture

## Overview

The WooCommerce AI Review Manager is a production-ready WordPress plugin that integrates with WooCommerce and Google's Gemini API to provide automated review collection, sentiment analysis, and intelligent response generation.

## Core Architecture

### Plugin Structure

```
wc-ai-review-manager/
├── wc-ai-review-manager.php          # Main plugin file
├── includes/
│   ├── class-database.php            # Database operations & schema
│   ├── class-settings.php            # Admin settings & configuration
│   ├── class-sentiment-analyzer.php  # Gemini API integration
│   ├── class-review-collector.php    # Invitation sending & tracking
│   ├── class-review-response-generator.php  # AI response generation
│   └── class-dashboard.php           # Dashboard widgets & analytics
├── assets/
│   └── css/
│       ├── admin.css                 # Admin interface styles
│       └── dashboard.css             # Dashboard widget styles
└── README.md                         # User documentation
```

### Class Responsibilities

#### `Database`
- Creates and maintains plugin tables
- Provides CRUD operations for invitations and responses
- Handles schema versioning
- Provides query methods for analytics

**Key Methods:**
- `create_tables()` - Creates wp_wc_ai_review_invitations and wp_wc_ai_review_responses
- `log_invitation()` - Tracks sent review invitations
- `create_response_record()` - Stores sentiment analysis results
- `get_sentiment_stats()` - Returns aggregated sentiment data
- `update_response_status()` - Updates response approval status

#### `Settings`
- Manages admin settings page under WooCommerce menu
- Sanitizes and validates user input
- Stores settings in wp_options table
- Provides getters for settings throughout the plugin

**Settings Stored:**
- `gemini_api_key` - Google Gemini API key (password field)
- `enabled` - Master feature toggle
- `auto_invite_enabled` - Enable automatic invitations
- `days_after_purchase` - Delay before sending invitation (1-365 days)
- `auto_respond_negative` - Enable AI response generation
- `negative_threshold` - Sentiment score threshold (0-1)

#### `Sentiment_Analyzer`
- Integrates with Google Gemini API
- Analyzes review sentiment
- Classifies sentiment as positive/neutral/negative
- Handles API errors gracefully

**API Integration:**
- Endpoint: `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent`
- Model: gemini-1.5-flash (fast, cost-effective)
- Method: HTTP POST with JSON payloads
- Response format: JSON with sentiment classification and score

**Key Methods:**
- `analyze()` - Analyzes single review
- `batch_analyze()` - Analyzes multiple reviews
- `classify_by_score()` - Classifies sentiment based on threshold
- `call_gemini_api()` - Internal API communication

#### `Review_Collector`
- Handles WooCommerce order completion events
- Schedules and sends review invitations
- Tracks invitation metrics

**Key Methods:**
- `on_order_completed()` - Hooks into woocommerce_order_status_completed
- `send_invitation()` - Sends review request email to customer
- `send_pending_invitations()` - Batch sends scheduled invitations
- `get_invitation_stats()` - Returns invitation metrics

#### `Review_Response_Generator`
- Generates AI responses for negative reviews
- Hooks into new review posting
- Stores responses for shop owner approval

**Key Methods:**
- `generate_response()` - Creates AI response using Gemini API
- `maybe_generate_response()` - Hooks into comment_post action
- `regenerate_response()` - Allows manual regeneration

#### `Dashboard`
- Renders dashboard widgets
- Displays sentiment analytics
- Shows pending responses queue
- Provides sentiment analytics page

**Key Methods:**
- `add_dashboard_widgets()` - Registers widgets
- `render_sentiment_widget()` - Displays sentiment breakdown
- `render_pending_widget()` - Shows pending responses
- `render_sentiment_page()` - Full analytics page

## Data Flow

### Review Invitation Flow

```
Order Completed
    ↓
woocommerce_order_status_completed hook
    ↓
Review_Collector::on_order_completed()
    ↓
wp_schedule_single_event() - Schedule after N days
    ↓
Review_Collector::send_invitation()
    ↓
Database::log_invitation() - Record in wp_wc_ai_review_invitations
    ↓
wp_mail() - Send email invitation
    ↓
Customer receives review invitation
```

### Sentiment Analysis Flow

```
Customer posts review (comment)
    ↓
comment_post action
    ↓
Review_Response_Generator::maybe_generate_response()
    ↓
Sentiment_Analyzer::analyze()
    ↓
Call Gemini API
    ↓
Parse API response
    ↓
Database::create_response_record() - Store sentiment data
    ↓
If negative review + auto_respond_enabled:
    ↓
Review_Response_Generator::generate_response()
    ↓
Call Gemini API for response
    ↓
Store AI-generated response
```

### Dashboard Display Flow

```
Admin opens WordPress dashboard
    ↓
Dashboard widgets register
    ↓
Dashboard::render_sentiment_widget()
    ↓
Database::get_sentiment_stats() - Query last 30 days
    ↓
Calculate percentages
    ↓
Display sentiment breakdown
    ↓
Dashboard::render_pending_widget()
    ↓
Database::get_pending_responses() - Get pending approvals
    ↓
Display pending responses with AI suggestions
```

## Database Schema

### wp_wc_ai_review_invitations

Purpose: Track review invitations sent to customers

```sql
CREATE TABLE wp_wc_ai_review_invitations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255),
    product_id BIGINT NOT NULL,
    product_name VARCHAR(255),
    invitation_sent_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    invitation_opened BOOL DEFAULT 0,
    opened_date DATETIME,
    
    KEY order_id (order_id),
    KEY product_id (product_id),
    KEY invitation_sent_date (invitation_sent_date)
);
```

### wp_wc_ai_review_responses

Purpose: Store review sentiment analysis and AI responses

```sql
CREATE TABLE wp_wc_ai_review_responses (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    review_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    review_content LONGTEXT,
    sentiment VARCHAR(20),
    sentiment_score DECIMAL(4,2),
    ai_response_generated LONGTEXT,
    ai_response_used BOOL DEFAULT 0,
    custom_response LONGTEXT,
    response_status VARCHAR(20) DEFAULT 'pending',
    created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY review_id (review_id),
    KEY product_id (product_id),
    KEY sentiment (sentiment),
    KEY response_status (response_status),
    KEY created_date (created_date)
);
```

## Security Implementation

### Input Validation & Sanitization

- All user inputs sanitized using WordPress functions:
  - `sanitize_text_field()` - Text inputs
  - `sanitize_email()` - Email addresses
  - `absint()` - Numeric IDs
  - `floatval()` - Decimal numbers
  - `wp_kses_post()` - HTML content

### Output Escaping

- All outputs escaped for context:
  - `esc_html()` - HTML context
  - `esc_attr()` - HTML attributes
  - `esc_url()` - URLs
  - `wp_kses_post()` - Post content

### Access Control

- Capability checks: `manage_woocommerce`
- Nonce verification on settings forms
- Admin-only settings page
- Proper WordPress capability system

### API Security

- HTTPS-only communication with Gemini API
- API key stored in options table (not exposed in frontend)
- API key field type: password (hidden input)
- No API key logging or exposure in error messages

## Performance Optimizations

### Database

- Indexed queries on frequently searched columns (order_id, product_id, sentiment, dates)
- Proper primary keys and relationships
- BIGINT for IDs to handle large stores
- DECIMAL(4,2) for sentiment scores (fixed precision)

### Scheduled Events

- Uses WordPress cron for background tasks
- Single events for per-order scheduling
- Batch processing for pending invitations
- Graceful error handling if cron fails

### API Rate Limiting

- No built-in rate limiting (relies on Google's limits)
- Error handling for rate limit responses
- Continues processing even if single analysis fails

## Extension Points

### Hooks & Filters

Developers can extend functionality:

```php
// Sentiment analysis
do_action( 'wc_ai_review_manager_sentiment_analyzed', $sentiment_data, $review_id );
apply_filters( 'wc_ai_review_manager_sentiment_data', $sentiment_data, $review_id );

// Response generation
apply_filters( 'wc_ai_review_manager_generated_response', $response_text, $review_id );

// Invitation
do_action( 'wc_ai_review_manager_invitation_sent', $order_id, $product_id );
apply_filters( 'wc_ai_review_manager_invitation_subject', $subject );
apply_filters( 'wc_ai_review_manager_invitation_message', $message, $order, $product );
```

## Configuration & Constants

All configuration done via Settings page (wp_options):
- No hardcoded constants needed
- All settings user-configurable
- Defaults provided for first-time setup
- Settings validated and sanitized on save

## Third-Party Dependencies

- **WordPress**: Core framework
- **WooCommerce**: Order and product integration
- **Google Gemini API**: Sentiment analysis and response generation
- No additional plugins required
- Only uses WordPress built-in functions and WooCommerce APIs

## Known Limitations & Future Improvements

### Current Limitations
1. Single language only (responses in English)
2. No email template customization UI
3. Manual response posting (not automatic)
4. No integration with third-party email platforms
5. Limited to WordPress/WooCommerce ecosystems

### Recommended Follow-Up Tasks

**Phase 2: Enhanced UX**
- Email template editor in admin
- Bulk review import tool
- Response templates for shop owners
- Notification on negative review

**Phase 3: Analytics**
- Advanced sentiment trends and forecasting
- Product-level sentiment insights
- Competitor review monitoring
- CSV/PDF export functionality

**Phase 4: Integration**
- Mailchimp integration for email sequences
- Zapier/Make integration for external workflows
- SMS invitation option
- Social proof widget for website

## Testing Strategy

### Recommended Test Cases

**Unit Tests**
- Sentiment classification logic
- Settings sanitization
- Email template rendering

**Integration Tests**
- Order completion → invitation flow
- Review posting → sentiment analysis
- API error handling

**E2E Tests**
- Full invitation → review → response flow
- Dashboard functionality
- Settings page validation

**Security Tests**
- SQL injection attempts
- XSS vulnerability checks
- CSRF attack prevention
- Capability bypass attempts

## Deployment Checklist

Before production deployment:

- [ ] API key configured and tested
- [ ] WP-Cron enabled
- [ ] Database tables created successfully
- [ ] Admin settings page accessible
- [ ] Email sending tested
- [ ] API rate limits understood
- [ ] Security audit passed
- [ ] Documentation reviewed
- [ ] Backup procedure established
- [ ] Rollback plan defined

## Monitoring & Maintenance

### Health Checks

```bash
# Check if tables exist
SELECT COUNT(*) FROM information_schema.tables 
WHERE table_schema = 'wordpress' 
AND table_name IN ('wp_wc_ai_review_invitations', 'wp_wc_ai_review_responses');

# Check pending responses
SELECT COUNT(*) FROM wp_wc_ai_review_responses 
WHERE response_status = 'pending';

# Check API connectivity
wp eval 'echo WC_AI_Review_Manager\Settings::get_setting("gemini_api_key") ? "API Key Configured" : "Missing API Key";'
```

### Regular Maintenance

- Archive old records monthly (90+ days)
- Monitor API usage and costs
- Review error logs weekly
- Update documentation as needed
- Test API key periodically
