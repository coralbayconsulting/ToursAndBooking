# BST Plugin Productization Roadmap

**Document Version:** 1.0  
**Last Updated:** February 12, 2026  
**Status:** ACTIVE DEVELOPMENT GUIDE

---

## 🎯 CRITICAL CONTEXT FOR AI ASSISTANTS

**READ THIS FIRST BEFORE ANY CODE CHANGES:**

This document is the **MASTER ROADMAP** for converting the Blue Strada Tours (BST) custom plugin into a commercial WordPress product. Every enhancement, feature addition, or bug fix should be evaluated against this roadmap.

### Before Making ANY Changes, Ask:

1. **Does this change move us toward commercial viability?**
   - Will it work for customers beyond Blue Strada Tours?
   - Does it require hard-coded Blue Strada references?
   - Can it be configured via settings instead of code?

2. **Does this change increase technical debt?**
   - Does it add more Gravity Forms dependencies? (We're migrating away)
   - Does it add more hard-coded values?
   - Does it make the code less portable?

3. **Where does this fit in the roadmap phases?**
   - MVP (Phase 1) - Core productization
   - Launch-Ready (Phase 2) - Polish and distribution
   - Growth (Phase 3) - Advanced features

### Decision Framework

```
ALWAYS DO:
✅ Add settings/configuration options instead of hard-coding
✅ Use feature toggles for optional functionality
✅ Write code that works for any tour operator
✅ Document new features in help docs
✅ Consider white-label/multi-tenant implications

NEVER DO:
❌ Hard-code company names, addresses, or branding
❌ Add features that only work for motorcycle tours
❌ Increase Gravity Forms dependency (migrate away instead)
❌ Skip input validation or security checks
❌ Ignore this roadmap without documented reason
```

---

## Current State Assessment

### What We Have (Blue Strada Tours Production System)

**Core Booking System (90% complete):**
- ✅ Custom post types for tours and bookings
- ✅ Real-time capacity tracking with overbooking alerts
- ✅ Multi-currency bank accounts (Airwallex USD, CAD, GBP, EUR, AUD - manual wire transfers)
- ✅ Stripe payment integration (CC, ApplePay, GooglePay, AmazonPay, SEPA)
- ✅ Manual bank wire workflow
- ✅ EU VAT automatic calculation
- ✅ Commission tracking system
- ✅ Extension pricing matrix
- ✅ Motor club integration
- ✅ Passenger details collection
- ✅ Dashboard with booking tiles

**Email System (95% complete):**
- ✅ Custom email template system with merge fields
- ✅ Batch sending with tracking (bst_email_batch table)
- ✅ Email log with batch_id, error tracking
- ✅ Gravity Forms email capture (type: 'Gravity')
- ✅ Bulk finalization emails

**Technical Foundation (80% complete):**
- ✅ WordPress mu-plugin architecture
- ✅ MySQL database with custom tables
- ✅ Azure App Service hosting
- ✅ Stripe webhook integration
- ✅ REST API endpoints
- ✅ AJAX handlers for admin UI

### Critical Dependencies & Technical Debt

**MAJOR BLOCKER: Gravity Forms Dependency**
- ❌ GF9 (booking form) - 50+ custom fields, conditional logic
- ❌ GF10 (finalization form) - passenger details, payment info
- ❌ Gravity Forms notifications captured via wp_mail filter
- ❌ Entry meta data stored in Gravity Forms tables
- ⚠️ **Impact:** Cannot distribute product without requiring Gravity Forms license ($259/year per customer)
- ⚠️ **Solution:** Migrate to custom form system (Phase 1 Priority)

**Child Theme Dependencies:**
- tour-bookings.php (main dashboard page template)
- booking-display.php (booking confirmation + payment instructions)
- Custom CSS overrides for admin UI
- Template hooks and filters

**Hard-Coded Blue Strada References:**
- Company name: "Blue Strada Tours" in 30+ locations
- Email addresses: info@bluestradatours.com, claudio@bluestradatours.com
- Bank account details (Airwallex accounts)
- Tour-specific terminology ("motor club", "extensions")
- Italian company VAT logic

---

## Phase 1: MVP - Core Productization
**Goal:** Functional product installable by any tour operator  
**Timeline:** 190 hours (5 weeks full-time / 12 weeks part-time)  
**Target:** Beta release with 2-3 pilot agencies

### 1.0 Vendor / Code Prefix Migration: BST → CBC (Ongoing)
**Purpose:** Position the plugin as a Coral Bay Consulting (CBC) product, not custom code for a single client. Blue Strada Tours remains the customer; CBC is the vendor/product owner.

**Naming:**
- **BST** = Blue Strada Tours (customer, deployment-specific branding)
- **CBC** = Coral Bay Consulting (company/product prefix for code, assets, and product identity)

**Tasks:**
- [ ] **New code and refactors:** Use `cbc_` prefix for new PHP functions, hooks, options, and CSS/JS handles where practical (e.g. `cbc_tools_section`, `cbc_get_tools_error_log_path()`).
- [ ] **Existing code:** Gradually replace `bst_` with `cbc_` when touching files (avoid one-shot rename of entire codebase to reduce risk).
- [ ] **Database/options:** Plan migration path for options and table names (e.g. `bst_*` → `cbc_*`) with backward compatibility or a one-time migration script.
- [ ] **Plugin slug/folder:** Eventually rename plugin slug/folder (e.g. `bst_plugin` → `cbc_tours` or similar) with upgrade path for existing installs.
- [ ] **User-facing strings:** Keep "Blue Strada" only where it is customer-specific content (e.g. sample data, one site); use "CBC Tours" or product name in admin UI and docs.

**Reference:** This roadmap and codebase; align with "Remove all BST branding" (Phase 3.7) for full white-label later.

### 1.1 Settings & Configuration System (30 hours)
**Priority:** CRITICAL - Unblocks all other work

**Tasks:**
- [ ] Create admin settings page (wp-admin → BST Settings)
- [ ] Company Information tab:
  - [ ] Company name, address, phone, email
  - [ ] Logo upload (use in emails, invoices)
  - [ ] Timezone setting
  - [ ] Default language
- [ ] Currency & Payments tab:
  - [ ] Base currency selection
  - [ ] Multi-currency enable/disable
  - [ ] Stripe API keys (test/live)
  - [ ] Bank account details for manual transfers (multi-currency support)
    - [ ] USD account (bank name, SWIFT, account number, routing)
    - [ ] EUR account (IBAN, SWIFT, bank details)
    - [ ] GBP account
    - [ ] CAD account (with Interac e-Transfer email)
    - [ ] AUD account
  - [ ] Airwallex API keys (optional - for future payment link automation)
  - [ ] Payment gateway enable/disable (Stripe, manual bank)
  - [ ] Bank account details (for manual transfers)
- [ ] Email Settings tab:
  - [ ] From name, from email
  - [ ] Reply-to email
  - [ ] BCC address (optional)
  - [ ] Email footer text
- [ ] Tour Settings tab:
  - [ ] Booking confirmation message
  - [ ] Terms & conditions (link or text)
  - [ ] Cancellation policy text
  - [ ] Default capacity per tour
- [ ] VAT/Tax Settings tab:
  - [ ] Enable/disable VAT
  - [ ] VAT rate and calculation rules
  - [ ] Tax ID display in invoices
- [ ] Advanced tab:
  - [ ] Debug mode enable/disable
  - [ ] Database table prefix (for multi-tenant)
  - [ ] Webhook secret keys

**Migration Work:**
- [ ] Replace all hard-coded "Blue Strada Tours" with get_option('bst_company_name')
- [ ] Replace all hard-coded emails with get_option('bst_company_email')
- [ ] Replace all bank details with settings retrieval
- [ ] Update database to store setting values
- [ ] Create settings API for programmatic access

**Acceptance Criteria:**
- ✅ Fresh install has setup wizard prompting for all settings
- ✅ No hard-coded company information remains in codebase
- ✅ Settings validate input and sanitize data
- ✅ Settings export/import for backup

---

### 1.2 Feature Toggles (20 hours)
**Priority:** HIGH - Enables flexible product tiers

**Tasks:**
- [ ] Create feature flags system (wp-admin → BST Settings → Features)
- [ ] Motor Club toggle (enable/disable motorcycle tour features)
  - [ ] Hides motor club field if disabled
  - [ ] Updates UI labels (generic "Add-ons" vs "Motor Club")
- [ ] Extensions toggle (enable/disable extension pricing matrix)
  - [ ] Hides extension fields if disabled
  - [ ] Removes extension calculations
- [ ] Commission tracking toggle
  - [ ] Hides commission fields if disabled
  - [ ] Removes commission calculations from reports
- [ ] VAT/Tax toggle (already partially exists, formalize it)
- [ ] Offline bookings toggle
  - [ ] Enables/disables manual payment entry
- [ ] Multi-currency toggle
  - [ ] Enables/disables multiple bank account display
  - [ ] Forces single currency if disabled
  - [ ] Shows/hides currency selector in booking forms
- [ ] Email batch system toggle
  - [ ] Enables/disables bulk email features
- [ ] Overbooking alerts toggle

**Acceptance Criteria:**
- ✅ Toggling feature immediately affects UI and functionality
- ✅ Disabled features don't process data or show in reports
- ✅ Feature states saved per installation
- ✅ License tier can control available features (Pro vs Free)

---

### 1.3 Migrate Away from Gravity Forms (50 hours)
**Priority:** CRITICAL - Blocks distribution without expensive GF licenses

**Tasks:**
- [ ] **Custom Booking Form Builder (25 hours)**
  - [ ] Create custom form renderer (replace GF9)
  - [ ] Field types: text, email, tel, select, radio, checkbox, textarea
  - [ ] Conditional logic system (show/hide fields)
  - [ ] Field validation (required, email format, phone format)
  - [ ] Multi-step form support (for long booking forms)
  - [ ] AJAX form submission
  - [ ] File upload support (for waivers, documents)
  - [ ] Form preview in admin
  
- [ ] **Booking Form Configuration (10 hours)**
  - [ ] Admin UI to build booking forms (drag-and-drop)
  - [ ] Default form templates (motorcycle tour, general tour, retreat, etc.)
  - [ ] Field mapping to booking database
  - [ ] Passenger info repeater fields
  - [ ] Terms & conditions checkbox
  - [ ] CAPTCHA/spam protection

- [ ] **Finalization Form System (10 hours)**
  - [ ] Replace GF10 with custom form
  - [ ] Payment information collection
  - [ ] Passenger detail updates
  - [ ] Balance confirmation
  - [ ] Link to booking record

- [ ] **Data Migration (5 hours)**
  - [ ] Script to migrate existing GF9/GF10 entries to custom tables
  - [ ] Map Gravity Forms meta to booking fields
  - [ ] Preserve historical data
  - [ ] Rollback plan if migration fails

**Acceptance Criteria:**
- ✅ Custom forms render identically to Gravity Forms
- ✅ All conditional logic works correctly
- ✅ Form submissions save to booking database
- ✅ No Gravity Forms dependency in codebase
- ✅ Existing bookings preserved after migration

**Impact:**
- 💰 Saves customers $259/year per site (no GF license needed)
- 🚀 Removes major distribution blocker
- 🎨 More control over form styling and UX

---

### 1.4 Setup Wizard (35 hours)
**Priority:** HIGH - Critical for good first impression

**Tasks:**
- [ ] **Welcome Screen (5 hours)**
  - [ ] Welcome message and product overview
  - [ ] What to expect (5-step guided setup)
  - [ ] Skip wizard option (for advanced users)

- [ ] **Step 1: Company Information (5 hours)**
  - [ ] Company name, address, phone, email
  - [ ] Logo upload
  - [ ] Timezone selection

- [ ] **Step 2: Currency & Payments (8 hours)**
  - [ ] Base currency selection
  - [ ] Multi-currency setup (optional)
    - [ ] Bank account details entry for each currency
    - [ ] SWIFT/IBAN/routing number fields
  - [ ] Stripe API key setup + connection test
  - [ ] Manual bank transfer details
  - [ ] Payment gateway enable/disable

- [ ] **Step 3: Sample Tour Creation (10 hours)**
  - [ ] Guided tour creation form
  - [ ] Pre-filled example tour (editable)
  - [ ] Tour dates setup
  - [ ] Pricing configuration
  - [ ] Capacity setting
  - [ ] Option to import sample tours

- [ ] **Step 4: Email Configuration (5 hours)**
  - [ ] From name/email
  - [ ] Send test email
  - [ ] Email template preview
  - [ ] Optional: WP Mail SMTP setup guide

- [ ] **Step 5: Complete & Next Steps (2 hours)**
  - [ ] Setup summary
  - [ ] Links to documentation
  - [ ] First steps checklist
  - [ ] Launch tour operator dashboard

**Acceptance Criteria:**
- ✅ Wizard auto-launches on first activation
- ✅ Can skip wizard and configure manually
- ✅ Each step validates input before proceeding
- ✅ Can go back to previous steps
- ✅ Progress saved if wizard abandoned
- ✅ Setup completion tracked (don't re-show wizard)

---

### 1.5 Basic Documentation (40 hours)
**Priority:** HIGH - Reduces support burden

**Tasks:**
- [ ] **Installation Guide (5 hours)**
  - [ ] WordPress requirements (PHP 7.4+, MySQL 5.7+)
  - [ ] Plugin installation steps
  - [ ] Database table creation
  - [ ] Stripe account setup
  - [ ] Common installation issues

- [ ] **Quick Start Guide (10 hours)**
  - [ ] Setup wizard walkthrough
  - [ ] Create your first tour
  - [ ] Accept your first booking
  - [ ] Process payments
  - [ ] Send confirmation emails
  - [ ] Generate reports

- [ ] **User Manual (15 hours)**
  - [ ] Dashboard overview
  - [ ] Tour management
  - [ ] Booking management
  - [ ] Payment processing
  - [ ] Email system
  - [ ] Reports and exports
  - [ ] Settings configuration

- [ ] **FAQ (5 hours)**
  - [ ] Common questions
  - [ ] Troubleshooting
  - [ ] Feature requests
  - [ ] Support contact

- [ ] **Developer Documentation (5 hours)**
  - [ ] Hooks and filters reference
  - [ ] Database schema
  - [ ] REST API endpoints
  - [ ] Extending the plugin

**Deliverables:**
- [ ] PDF user manual
- [ ] Online help center (WordPress documentation site or readme.txt)
- [ ] In-app help tooltips (? icons next to complex features)

**Acceptance Criteria:**
- ✅ Non-technical user can install and configure without support
- ✅ All major features documented with screenshots
- ✅ Troubleshooting covers 80% of common issues

---

### 1.6 Security Audit (25 hours + $2,000 external audit)
**Priority:** CRITICAL - Cannot release without this

**Tasks:**
- [ ] **Input Validation (8 hours)**
  - [ ] Audit all $_POST, $_GET, $_REQUEST usage
  - [ ] Add sanitization (sanitize_text_field, wp_kses, etc.)
  - [ ] Validate data types and ranges
  - [ ] Test with malicious input

- [ ] **Nonce Verification (5 hours)**
  - [ ] Audit all AJAX handlers for nonce checks
  - [ ] Add wp_verify_nonce() to all form submissions
  - [ ] Test CSRF protection

- [ ] **Capability Checks (5 hours)**
  - [ ] Audit all admin pages for current_user_can()
  - [ ] Define custom capabilities (manage_tours, manage_bookings)
  - [ ] Test with different user roles

- [ ] **SQL Injection Prevention (5 hours)**
  - [ ] Audit all database queries
  - [ ] Use $wpdb->prepare() everywhere
  - [ ] Test with SQL injection payloads

- [ ] **XSS Protection (2 hours)**
  - [ ] Audit all output for esc_html(), esc_attr(), esc_url()
  - [ ] Test with XSS payloads

- [ ] **External Security Audit ($2,000)**
  - [ ] Hire WordPress security firm (WordFence, Sucuri, Patchstack)
  - [ ] Penetration testing
  - [ ] Code review
  - [ ] Remediation of findings

**Acceptance Criteria:**
- ✅ Passes WordPress Plugin Review Team standards
- ✅ External audit finds no critical vulnerabilities
- ✅ All high/medium severity issues resolved
- ✅ Security documentation created

---

### 1.7 Licensing System (10 hours)
**Priority:** HIGH - Required for commercial distribution

**Tasks:**
- [ ] **Integrate Freemius SDK (5 hours)**
  - [ ] Add Freemius WordPress SDK
  - [ ] Configure license tiers (Free, Pro, Agency)
  - [ ] Set pricing ($0, $400/year, $1200/year)
  - [ ] License activation flow

- [ ] **Feature Gating (3 hours)**
  - [ ] Free tier: Basic features only
  - [ ] Pro tier: All features unlocked
  - [ ] Agency tier: 5-site license + white-label
  - [ ] Check license status before enabling pro features

- [ ] **Update System (2 hours)**
  - [ ] Automatic update checks via Freemius
  - [ ] Changelog display
  - [ ] One-click updates

**Acceptance Criteria:**
- ✅ License key validates on activation
- ✅ Pro features disabled without valid license
- ✅ Automatic updates work correctly
- ✅ Freemius dashboard shows license analytics

---

### 1.8 Sample Data & Demos (15 hours)
**Priority:** MEDIUM - Helps users understand features

**Tasks:**
- [ ] **Sample Tours (5 hours)**
  - [ ] 3 pre-built tour templates
  - [ ] Motorcycle tour example
  - [ ] General group tour example
  - [ ] Retreat/workshop example
  - [ ] With photos and descriptions

- [ ] **Sample Bookings (3 hours)**
  - [ ] 10-15 fake bookings
  - [ ] Various statuses (confirmed, pending, cancelled)
  - [ ] Different payment states

- [ ] **Import/Export System (5 hours)**
  - [ ] One-click sample data install
  - [ ] One-click sample data removal
  - [ ] Export real tours for backup

- [ ] **Demo Site (2 hours)**
  - [ ] Public demo site with sample data
  - [ ] Reset daily
  - [ ] Link from product website

**Acceptance Criteria:**
- ✅ Sample data installs in <30 seconds
- ✅ Demonstrates all major features
- ✅ Easy to delete sample data
- ✅ Demo site available for prospects

---

## Phase 2: Launch-Ready Product
**Goal:** Professional polish, ready for public distribution  
**Timeline:** 235 hours (6 weeks full-time / 15 weeks part-time)  
**Target:** Public launch on WordPress.org and CodeCanyon

### 2.1 Admin UI Polish (40 hours)

**Tasks:**
- [ ] Consistent styling across all admin pages
- [ ] Loading states and spinners
- [ ] Error message design
- [ ] Success notifications (toast messages)
- [ ] Mobile-responsive admin
- [ ] Accessibility (WCAG 2.1 AA)
- [ ] Dark mode support (optional)

---

### 2.2 Comprehensive Error Handling (30 hours)

**Tasks:**
- [ ] Form validation with inline errors
- [ ] Database transaction rollbacks
- [ ] API error handling and retries
- [ ] User-friendly error messages
- [ ] Error logging system
- [ ] Admin error notification dashboard

---

### 2.3 Bank Transfer Automation (40 hours)

**Priority:** HIGH - Closes competitive gap with WeTravel

**Tasks:**
- [ ] **GoCardless Integration (25 hours)**
  - [ ] GoCardless API integration (1% fee)
  - [ ] SEPA direct debit setup
  - [ ] ACH setup (if supported for Italy)
  - [ ] Payment status webhooks
  - [ ] Automatic booking confirmation on payment

- [ ] **Airwallex Payment Links API (15 hours)**
  - [ ] Airwallex API authentication
  - [ ] Generate unique payment links per booking
  - [ ] Support for all currencies (USD, CAD, GBP, EUR, AUD)
  - [ ] Webhook listener for payment confirmation
  - [ ] Automatic email with payment link
  - [ ] Payment reconciliation dashboard
  - [ ] NOTE: This is currently manual - customers receive static bank account details

- [ ] **Manual Bank Wire Improvements (5 hours)**
  - [ ] Better bank instruction templates
  - [ ] Payment reference generation
  - [ ] Manual payment marking UI
  - [ ] Payment confirmation emails

**Acceptance Criteria:**
- ✅ Customer receives payment link automatically
- ✅ Payment status updates booking in real-time
- ✅ Works with multi-currency bookings

---

### 2.4 Update System (25 hours)

**Tasks:**
- [ ] Version checking
- [ ] One-click updates via WordPress admin
- [ ] Changelog display
- [ ] Backup automation before update
- [ ] Rollback capability
- [ ] Update notifications

---

### 2.5 Support Tools (20 hours)

**Tasks:**
- [ ] System requirements checker
- [ ] Diagnostic report generator
- [ ] Debug logging system
- [ ] Database repair tool
- [ ] Export data for support requests
- [ ] Support ticket system integration

---

### 2.6 Video Tutorials (40 hours)

**Tasks:**
- [ ] Installation & setup (10 min)
- [ ] Creating your first tour (8 min)
- [ ] Managing bookings (12 min)
- [ ] Payment processing (10 min)
- [ ] Email system (10 min)
- [ ] Reports & analytics (8 min)
- [ ] Advanced features (15 min)
- [ ] Troubleshooting common issues (10 min)

**Deliverables:**
- [ ] 8 professional videos
- [ ] Hosted on YouTube
- [ ] Embedded in documentation
- [ ] Transcripts for accessibility

---

### 2.7 Product Website (60 hours)

**Tasks:**
- [ ] **Landing Page (20 hours)**
  - [ ] Hero section with value proposition
  - [ ] Feature comparison table (vs WeTravel)
  - [ ] Pricing table
  - [ ] Testimonials
  - [ ] Call-to-action buttons

- [ ] **Feature Pages (15 hours)**
  - [ ] Booking management
  - [ ] Payment processing
  - [ ] Email automation
  - [ ] Reports
  - [ ] Integrations

- [ ] **Documentation Portal (15 hours)**
  - [ ] Searchable help articles
  - [ ] Category organization
  - [ ] Video embeds
  - [ ] PDF downloads

- [ ] **Demo Site (5 hours)**
  - [ ] Live demo environment
  - [ ] Sample data pre-loaded
  - [ ] Auto-reset daily

- [ ] **Blog (5 hours)**
  - [ ] Launch announcement post
  - [ ] 3-5 feature highlight posts
  - [ ] SEO optimization

**Acceptance Criteria:**
- ✅ Professional design matching plugin quality
- ✅ Fast page load (<2 seconds)
- ✅ Mobile responsive
- ✅ SEO optimized (score >90)

---

### 2.8 Distribution Setup (20 hours)

**Tasks:**
- [ ] **WordPress.org Listing (10 hours)**
  - [ ] Plugin submission
  - [ ] SVN repository setup
  - [ ] Plugin assets (banner, icon, screenshots)
  - [ ] Readme.txt file
  - [ ] Plugin review process

- [ ] **CodeCanyon Listing (5 hours)**
  - [ ] Product submission
  - [ ] Item description
  - [ ] Preview images
  - [ ] Documentation link
  - [ ] Review process

- [ ] **AppSumo Launch Deal (5 hours)**
  - [ ] AppSumo partner application
  - [ ] Lifetime deal structure
  - [ ] Launch campaign setup
  - [ ] Customer onboarding flow

**Acceptance Criteria:**
- ✅ Approved on WordPress.org
- ✅ Approved on CodeCanyon
- ✅ AppSumo launch scheduled

---

## Phase 3: Growth & Advanced Features
**Goal:** Competitive differentiation and premium features  
**Timeline:** Ongoing (post-launch)

### 3.1 Advanced Itinerary Builder (60 hours)

**Tasks:**
- [ ] Drag-and-drop day-by-day itinerary
- [ ] Activity templates library
- [ ] Accommodation booking integration
- [ ] Photo galleries per day
- [ ] PDF export (professional layout)
- [ ] Printable itinerary
- [ ] Share link for customers

---

### 3.2 Mobile App (200+ hours or outsource)

**Tasks:**
- [ ] React Native app (iOS + Android)
- [ ] Customer booking view
- [ ] Itinerary display
- [ ] Payment status
- [ ] Push notifications
- [ ] Offline mode

**Alternative:** Progressive Web App (50 hours)

---

### 3.3 Abandoned Cart Recovery (15 hours)

**Tasks:**
- [ ] Track incomplete bookings
- [ ] Automated email sequence (1 hour, 24 hours, 7 days)
- [ ] Unique recovery links
- [ ] Discount code automation
- [ ] Conversion tracking

---

### 3.4 Advanced Reporting (30 hours)

**Tasks:**
- [ ] Revenue forecasting
- [ ] Occupancy analytics
- [ ] Customer lifetime value
- [ ] Booking source attribution
- [ ] Custom report builder
- [ ] Export to Excel/CSV
- [ ] Scheduled email reports

---

### 3.5 CRM Integration (40 hours)

**Tasks:**
- [ ] HubSpot integration
- [ ] Mailchimp sync
- [ ] ActiveCampaign integration
- [ ] Customer segmentation
- [ ] Marketing automation triggers

---

### 3.6 Multi-Language Support (30 hours)

**Tasks:**
- [ ] Full i18n/l10n implementation
- [ ] .pot file generation
- [ ] Spanish translation
- [ ] French translation
- [ ] German translation
- [ ] Italian translation
- [ ] WPML compatibility

---

### 3.7 White-Label Agency Solution (50 hours)

**Tasks:**
- [ ] Remove all BST branding
- [ ] Agency license tier
- [ ] Multi-tenant architecture
- [ ] Per-client settings
- [ ] Agency dashboard
- [ ] Revenue sharing model

---

### 3.8 Marketplace & Add-ons (100+ hours)

**Tasks:**
- [ ] Add-on API architecture
- [ ] Payment gateway add-ons (PayPal, Square, Authorize.net)
- [ ] Integration add-ons (Zapier, Make)
- [ ] Industry-specific add-ons (motorcycle tours, retreats, cruises)
- [ ] Template marketplace

---

## Timeline & Resource Planning

### MVP Timeline (Phase 1)
| Task | Hours | Dependencies |
|------|-------|--------------|
| Settings System | 30 | None |
| Feature Toggles | 20 | Settings System |
| Gravity Forms Migration | 50 | None (parallel) |
| Setup Wizard | 35 | Settings System |
| Documentation | 40 | All features |
| Security Audit | 25 | All features |
| Licensing | 10 | None (parallel) |
| Sample Data | 15 | Gravity Forms Migration |
| **TOTAL** | **190 hours** | **~5 weeks full-time** |

### Launch-Ready Timeline (Phase 2)
| Task | Hours | Dependencies |
|------|-------|--------------|
| Admin UI Polish | 40 | MVP complete |
| Error Handling | 30 | MVP complete |
| Bank Transfer Automation | 40 | None (parallel) |
| Update System | 25 | Licensing |
| Support Tools | 20 | None (parallel) |
| Video Tutorials | 40 | Documentation |
| Product Website | 60 | None (parallel) |
| Distribution Setup | 20 | All features |
| **TOTAL** | **235 hours** | **~6 weeks full-time** |

### Combined MVP + Launch
**Total: 425 hours (11 weeks full-time / 27 weeks part-time)**

---

## Success Metrics

### Phase 1 (MVP) Success Criteria
- [ ] Plugin installs successfully on fresh WordPress site
- [ ] Setup wizard completes without errors
- [ ] First tour created within 10 minutes of setup
- [ ] First booking processed successfully
- [ ] No hard-coded Blue Strada references in codebase
- [ ] Passes WordPress Plugin Review Team guidelines
- [ ] External security audit shows no critical vulnerabilities
- [ ] 2-3 pilot agencies successfully using the plugin

### Phase 2 (Launch) Success Criteria
- [ ] Listed on WordPress.org with 4+ star rating
- [ ] Listed on CodeCanyon with 4+ star rating
- [ ] 50+ active installations within 30 days
- [ ] <5% churn rate
- [ ] <10 support tickets per week
- [ ] 80% of users complete setup wizard
- [ ] Product website ranks in top 10 for "WordPress booking plugin for tours"

### Phase 3 (Growth) Success Criteria
- [ ] 500+ active installations
- [ ] $5,000+ MRR (monthly recurring revenue)
- [ ] 3+ add-ons published
- [ ] Partner program launched (agencies, developers)
- [ ] Featured in WordPress news/blogs

---

## Risk Mitigation

### Technical Risks
| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Gravity Forms migration breaks bookings | HIGH | MEDIUM | Thorough testing, gradual rollout, rollback plan |
| Security vulnerabilities | HIGH | MEDIUM | External audit, bug bounty program |
| WordPress core updates break plugin | MEDIUM | HIGH | Regular testing on beta versions |
| Database performance issues at scale | MEDIUM | LOW | Query optimization, caching, indexing |

### Business Risks
| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Low market demand | HIGH | LOW | Pre-validate with pilot agencies |
| WeTravel adds free tier | MEDIUM | MEDIUM | Focus on self-hosted value prop |
| Support overwhelms capacity | HIGH | MEDIUM | Comprehensive docs, community forum |
| Pricing too high/low | MEDIUM | MEDIUM | A/B test pricing, adjust based on data |

---

## Pricing Strategy

### Free Tier (WordPress.org)
- Basic booking management
- Single currency
- Manual payments only
- Email templates (basic)
- Community support

**Goal:** Lead generation, build userbase, gather feedback

### Pro License ($400/year per site)
- All features unlocked
- Multi-currency support
- Stripe + bank transfer automation
- Advanced email automation
- Priority email support
- Automatic updates

**Target:** Individual tour operators (10-100 tours/year)

### Agency License ($1,200/year for 5 sites)
- All Pro features
- Install on up to 5 client sites
- White-label branding option
- Agency dashboard
- Priority phone support
- Training webinars

**Target:** Travel agencies, web developers

### Enterprise (Custom Pricing)
- Unlimited sites
- Full white-label
- Custom development
- Dedicated account manager
- SLA guarantee

**Target:** Large tour operators, franchises

---

## Marketing & Distribution

### Launch Strategy

**Month 1-2 (Pre-Launch):**
- [ ] Beta program with 5-10 agencies
- [ ] Collect testimonials
- [ ] Build email list via lead magnet
- [ ] Create launch content

**Month 3 (Launch):**
- [ ] WordPress.org submission
- [ ] CodeCanyon submission
- [ ] AppSumo lifetime deal ($99 for lifetime Pro)
- [ ] Product Hunt launch
- [ ] Press release (WP Tavern, TorqueMag)
- [ ] Social media campaign

**Month 4-6 (Growth):**
- [ ] Content marketing (blog posts, case studies)
- [ ] SEO optimization
- [ ] Motorcycle tour association partnerships
- [ ] Travel industry conference sponsorships
- [ ] Affiliate program launch

### Target Audiences

**Primary:**
1. Motorcycle tour operators (niche, underserved)
2. Small adventure tour companies (10-50 tours/year)
3. Retreat organizers (yoga, wellness, spiritual)

**Secondary:**
4. Travel agencies building white-label solutions
5. Web developers serving tour operator clients
6. Cost-conscious operators leaving WeTravel

**Geographic Focus:**
- Europe (EU VAT, SEPA, multi-currency focus)
- North America (secondary)
- Australia/New Zealand (motorcycle tour culture)

---

## Competitive Positioning

### Key Messages

**vs WeTravel:**
- "Self-hosted alternative - pay once, use forever"
- "No monthly fees, no booking fees, no hidden costs"
- "Full control of your data and customer experience"
- "WordPress native - works with your existing site"

**vs TourCMS, Rezdy:**
- "Built specifically for WordPress"
- "No complex integrations or iframe embeds"
- "Faster page loads, better SEO"

**vs Gravity Forms + WooCommerce:**
- "Purpose-built for tour operators"
- "No plugin frankensteining required"
- "Tour-specific features out of the box"

---

## Support & Maintenance Plan

### Support Channels
1. **Documentation** (self-service, available 24/7)
2. **Community Forum** (free tier, peer support)
3. **Email Support** (Pro tier, 24-48 hour response)
4. **Priority Support** (Agency tier, 12-hour response)
5. **Phone Support** (Enterprise only)

### Maintenance Schedule
- **Security Updates:** Within 24 hours of vulnerability disclosure
- **Bug Fixes:** Weekly releases for critical bugs
- **Feature Updates:** Monthly releases
- **Major Versions:** Quarterly (with beta testing period)

### Support Metrics
- First response time: <24 hours (Pro), <12 hours (Agency)
- Resolution time: <7 days for non-critical issues
- Customer satisfaction: >4.5/5 star rating
- Documentation coverage: 90% of common questions

---

## Technical Debt & Future Improvements

### Known Technical Debt
1. **Child theme dependency** - Migrate templates to plugin
2. **Gravity Forms dependency** - PRIORITY, Phase 1
3. **Hard-coded values** - Addressed in Phase 1
4. **Inline JavaScript** - Move to separate files
5. **Limited test coverage** - Add unit tests in Phase 2
6. **No caching** - Add transient caching for reports

### Future Architectural Improvements
- [ ] REST API expansion (for mobile app, integrations)
- [ ] GraphQL endpoint (for modern frontend frameworks)
- [ ] Webhook system (for third-party integrations)
- [ ] Queue system for background jobs (email sending, report generation)
- [ ] Redis caching for high-traffic sites
- [ ] Multi-tenant architecture for SaaS version

---

## Appendix: Decision Log

### Major Decisions Made

**2026-02-12: Migrate away from Gravity Forms**
- **Decision:** Build custom form system
- **Rationale:** Cannot distribute product requiring $259/year Gravity Forms license per customer
- **Impact:** 50 hours additional dev time, but removes major blocker
- **Status:** Approved

**2026-02-12: Use Freemius for licensing**
- **Decision:** Freemius SDK vs custom license system
- **Rationale:** Proven solution, handles payments/updates/analytics, costs 10% of revenue but saves 100+ dev hours
- **Impact:** 10-hour integration vs 100+ hour custom build
- **Status:** Approved

**2026-02-12: Target motorcycle tour niche first**
- **Decision:** Focus on motorcycle tours vs generic tours
- **Rationale:** Less competition, Blue Strada domain expertise, motor club features differentiation
- **Impact:** Narrower market but clearer positioning
- **Status:** Approved

---

## Changelog

**v1.0 - 2026-02-12**
- Initial roadmap created
- Phases 1-3 defined with hour estimates
- Gravity Forms migration prioritized
- Marketing strategy outlined
- Decision framework established for AI assistants

---

**Document End** 

*This roadmap is a living document. Update it as priorities shift, features are completed, or market conditions change. Always refer to this before making architectural decisions or adding new features.*
