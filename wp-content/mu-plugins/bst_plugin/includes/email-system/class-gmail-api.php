<?php

/**
 * BST Gmail API Integration Class
 * 
 * Handles Gmail API integration for sending emails through Google Workspace
 */
class BST_Gmail_API {
    
    private $client;
    private $service;
    
    public function __construct() {
        $this->init_gmail_client();
    }
    
    /**
     * Initialize Gmail API client
     */
    private function init_gmail_client() {
        // Only initialize if Google API client library is available
        if (!class_exists('Google_Client')) {
            // Only log if Gmail is actually enabled
            if (get_option('bst_email_method') === 'gmail') {
                error_log('BST Gmail: Google API Client library not found. Install via Composer: composer require google/apiclient');
            }
            return false;
        }
        
        try {
            $this->client = new Google_Client();
            $this->client->setApplicationName('BST Tour Booking Email System');
            
            // Set up OAuth 2.0 credentials
            $credentials_path = $this->get_credentials_path();
            if (file_exists($credentials_path)) {
                $this->client->setAuthConfig($credentials_path);
            } else {
                // Only log if Gmail is actually enabled
                if (get_option('bst_email_method') === 'gmail') {
                    error_log('BST Gmail: Gmail API credentials file not found at: ' . $credentials_path);
                }
                return false;
            }
            
            // Set required scopes for sending emails and inbox checking
            $this->client->addScope(Google_Service_Gmail::GMAIL_SEND);
            $this->client->addScope(Google_Service_Gmail::GMAIL_COMPOSE);
            $this->client->addScope(Google_Service_Gmail::GMAIL_MODIFY);
            $this->client->addScope(Google_Service_Gmail::GMAIL_READONLY); // For reading inbox
            $this->client->addScope(Google_Service_Gmail::GMAIL_LABELS);   // For managing labels
            
            // Set access type for offline access
            $this->client->setAccessType('offline');
            $this->client->setPrompt('select_account consent');
            
            // Initialize Gmail service
            $this->service = new Google_Service_Gmail($this->client);
            
            return true;
            
        } catch (Exception $e) {
            // Only log if Gmail is actually enabled
            if (get_option('bst_email_method') === 'gmail') {
                error_log('BST Gmail: Failed to initialize Gmail client: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get Gmail API credentials file path
     */
    private function get_credentials_path() {
        // Store credentials in wp-content/uploads/bst-gmail/
        $upload_dir = wp_upload_dir();
        $gmail_dir = $upload_dir['basedir'] . '/bst-gmail/';
        
        // Create directory if it doesn't exist
        if (!file_exists($gmail_dir)) {
            wp_mkdir_p($gmail_dir);
            // Add .htaccess to protect credentials
            file_put_contents($gmail_dir . '.htaccess', "deny from all\n");
        }
        
        return $gmail_dir . 'credentials.json';
    }
    
    /**
     * Get stored access token path
     */
    private function get_token_path() {
        $upload_dir = wp_upload_dir();
        $gmail_dir = $upload_dir['basedir'] . '/bst-gmail/';
        return $gmail_dir . 'token.json';
    }
    
    /**
     * Check if Gmail API is properly configured
     */
    public function is_configured() {
        $has_client = $this->client !== null;
        $has_service = $this->service !== null;
        $has_credentials = file_exists($this->get_credentials_path());
        
        error_log('BST Gmail: Configuration check - Client: ' . ($has_client ? 'YES' : 'NO') . 
                 ', Service: ' . ($has_service ? 'YES' : 'NO') . 
                 ', Credentials: ' . ($has_credentials ? 'YES' : 'NO'));
        
        return $has_client && $has_service && $has_credentials;
    }
    
    /**
     * Get authorization URL for initial setup
     */
    public function get_auth_url() {
        if (!$this->client) {
            return false;
        }
        
        return $this->client->createAuthUrl();
    }
    
    /**
     * Handle OAuth callback and store token
     */
    public function handle_oauth_callback($auth_code) {
        if (!$this->client) {
            return false;
        }
        
        try {
            // Exchange authorization code for access token
            $access_token = $this->client->fetchAccessTokenWithAuthCode($auth_code);
            
            if (array_key_exists('error', $access_token)) {
                error_log('BST Gmail: OAuth error: ' . $access_token['error']);
                return false;
            }
            
            // Save token to file
            $token_path = $this->get_token_path();
            file_put_contents($token_path, json_encode($access_token));
            
            return true;
            
        } catch (Exception $e) {
            error_log('BST Gmail: OAuth callback error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Load and set access token
     */
    private function load_access_token() {
        $token_path = $this->get_token_path();
        
        if (!file_exists($token_path)) {
            error_log('BST Gmail: Access token file not found at: ' . $token_path);
            return false;
        }
        
        $access_token = json_decode(file_get_contents($token_path), true);
        if (!$access_token) {
            error_log('BST Gmail: Failed to parse access token file');
            return false;
        }
        
        $this->client->setAccessToken($access_token);
        error_log('BST Gmail: Access token loaded successfully');
        
        // Refresh token if expired
        if ($this->client->isAccessTokenExpired()) {
            error_log('BST Gmail: Access token is expired, attempting refresh');
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                // Save refreshed token
                file_put_contents($token_path, json_encode($this->client->getAccessToken()));
                error_log('BST Gmail: Access token refreshed successfully');
            } else {
                error_log('BST Gmail: Access token expired and no refresh token available');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Send email using Gmail API
     */
    public function send_email($to, $subject, $body, $from_name = '', $booking_id = null, $email_log_id = null, $attachments = array()) {
        if (!$this->is_configured()) {
            error_log('BST Gmail: Gmail API not configured, falling back to wp_mail');
            return $this->fallback_to_wp_mail($to, $subject, $body, $from_name);
        }
        
        if (!$this->load_access_token()) {
            error_log('BST Gmail: Failed to load access token, falling back to wp_mail');
            return $this->fallback_to_wp_mail($to, $subject, $body, $from_name);
        }
        
        try {
            // Create Gmail message
            $message = $this->create_gmail_message($to, $subject, $body, $from_name, $booking_id, $email_log_id, $attachments);
            
            // Send the email
            $sent_message = $this->service->users_messages->send('me', $message);
            
            // Return success with Gmail message details
            return array(
                'success' => true,
                'gmail_message_id' => $sent_message->getId(),
                'gmail_thread_id' => $sent_message->getThreadId(),
                'message_id' => $message->custom_message_id, // Our generated Message-ID
                'message' => 'Email sent successfully via Gmail API'
            );
            
        } catch (Exception $e) {
            error_log('BST Gmail: Send error: ' . $e->getMessage());
            // Fallback to wp_mail on error
            return $this->fallback_send_email($to, $subject, $body, $from_name, $booking_id);
        }
    }
    
    /**
     * Create Gmail message object
     */
    private function create_gmail_message($to, $subject, $body, $from_name = '', $booking_id = null, $email_log_id = null, $attachments = array()) {
        // Use configured from email settings
        $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
        $default_from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
        
        // Use provided from_name or configured name as fallback
        $from_display = $from_name ? $from_name : $default_from_name;
        
        // Generate a unique Message-ID for email tracking
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $message_id = '<bst-' . uniqid() . '-' . time() . '@' . $domain . '>';
        
        // Create headers
        $headers = array();
        $headers[] = "From: {$from_display} <{$from_email}>";
        $headers[] = "To: {$to}";
        $headers[] = "Subject: {$subject}";
        $headers[] = "Message-ID: {$message_id}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        
        // Add booking reference to headers if provided
        if ($booking_id) {
            $headers[] = "X-BST-Booking-ID: {$booking_id}";
        }
        
        // Add email log reference to headers if provided
        if ($email_log_id) {
            $headers[] = "X-BST-Email-Log-ID: {$email_log_id}";
        }
        
        // Create the message with or without attachments
        if (!empty($attachments)) {
            $raw_message = $this->create_multipart_message($headers, $body, $attachments);
        } else {
            // Simple message without attachments
            $raw_message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        }
        
        // Create Gmail message object
        $message = new Google_Service_Gmail_Message();
        $message->setRaw(base64url_encode($raw_message));
        
        // Store the Message-ID as a custom property for later reference
        $message->custom_message_id = $message_id;
        
        return $message;
    }
    
    /**
     * Create multipart MIME message with attachments
     */
    private function create_multipart_message($headers, $body, $attachments) {
        $boundary = uniqid('bst_');
        
        // Update Content-Type header to multipart/mixed
        $headers_filtered = array();
        foreach ($headers as $header) {
            if (strpos($header, 'Content-Type:') === false && strpos($header, 'MIME-Version:') === false) {
                $headers_filtered[] = $header;
            }
        }
        $headers_filtered[] = "MIME-Version: 1.0";
        $headers_filtered[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        
        // Start message with headers
        $message_parts = array();
        $message_parts[] = implode("\\r\\n", $headers_filtered);
        $message_parts[] = "";
        
        // HTML body part
        $message_parts[] = "--{$boundary}";
        $message_parts[] = "Content-Type: text/html; charset=UTF-8";
        $message_parts[] = "Content-Transfer-Encoding: base64";
        $message_parts[] = "";
        $message_parts[] = chunk_split(base64_encode($body));
        
        // Add each attachment
        foreach ($attachments as $attachment_path) {
            if (!file_exists($attachment_path)) {
                continue;
            }
            
            $filename = basename($attachment_path);
            $file_content = file_get_contents($attachment_path);
            $mime_type = mime_content_type($attachment_path);
            
            $message_parts[] = "--{$boundary}";
            $message_parts[] = "Content-Type: {$mime_type}; name=\"{$filename}\"";
            $message_parts[] = "Content-Transfer-Encoding: base64";
            $message_parts[] = "Content-Disposition: attachment; filename=\"{$filename}\"";
            $message_parts[] = "";
            $message_parts[] = chunk_split(base64_encode($file_content));
        }
        
        // End boundary
        $message_parts[] = "--{$boundary}--";
        
        return implode("\\r\\n", $message_parts);
    }
    
    /**
     * Fallback to WordPress wp_mail function
     */
    private function fallback_send_email($to, $subject, $body, $from_name = '', $booking_id = null, $email_log_id = null) {
        // Generate a unique Message-ID for email tracking
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $message_id = '<bst-' . uniqid() . '-' . time() . '@' . $domain . '>';
        
        // Set headers for wp_mail
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $headers[] = "Message-ID: {$message_id}";
        
        // Add booking reference to headers if provided
        if ($booking_id) {
            $headers[] = "X-BST-Booking-ID: {$booking_id}";
        }
        
        // Add email log reference to headers if provided
        if ($email_log_id) {
            $headers[] = "X-BST-Email-Log-ID: {$email_log_id}";
        }
        
        // Use configured from email settings
        $from_email = get_option('bst_from_email_address', 'info@bluestradatours.com');
        $default_from_name = get_option('bst_from_email_name', 'Blue Strada Tours');
        $display_name = $from_name ? $from_name : $default_from_name;
        $headers[] = "From: {$display_name} <{$from_email}>";
        
        // Send via wp_mail
        $success = wp_mail($to, $subject, $body, $headers);
        
        return array(
            'success' => $success,
            'gmail_message_id' => null,
            'gmail_thread_id' => null,
            'message_id' => $message_id, // Our generated Message-ID
            'message' => $success ? 'Email sent successfully via wp_mail' : 'Email send failed'
        );
    }
    
    /**
     * List messages from Gmail inbox
     */
    public function list_messages($query = '', $maxResults = 10) {
        if (!$this->service || !$this->is_authenticated()) {
            throw new Exception('Gmail API not authenticated');
        }
        
        try {
            $optParams = array();
            if ($query) {
                $optParams['q'] = $query;
            }
            if ($maxResults) {
                $optParams['maxResults'] = $maxResults;
            }
            
            $messagesResponse = $this->service->users_messages->listUsersMessages('me', $optParams);
            return $messagesResponse->getMessages() ?: array();
            
        } catch (Exception $e) {
            error_log('BST Gmail: List messages error - ' . $e->getMessage());
            throw new Exception('Failed to list Gmail messages: ' . $e->getMessage());
        }
    }
    
    /**
     * Get a specific message by ID
     */
    public function get_message($messageId) {
        if (!$this->service || !$this->is_authenticated()) {
            throw new Exception('Gmail API not authenticated');
        }
        
        try {
            $message = $this->service->users_messages->get('me', $messageId);
            return $message->toArray();
            
        } catch (Exception $e) {
            error_log('BST Gmail: Get message error - ' . $e->getMessage());
            throw new Exception('Failed to get Gmail message: ' . $e->getMessage());
        }
    }
    
    /**
     * Mark a message as read
     */
    public function mark_message_as_read($messageId) {
        if (!$this->service || !$this->is_authenticated()) {
            throw new Exception('Gmail API not authenticated');
        }
        
        try {
            $mods = new Google_Service_Gmail_ModifyMessageRequest();
            $mods->setRemoveLabelIds(array('UNREAD'));
            
            $this->service->users_messages->modify('me', $messageId, $mods);
            return true;
            
        } catch (Exception $e) {
            error_log('BST Gmail: Mark as read error - ' . $e->getMessage());
            throw new Exception('Failed to mark Gmail message as read: ' . $e->getMessage());
        }
    }
    
    /**
     * Add label to a message (creates label if it doesn't exist)
     */
    public function add_label_to_message($messageId, $labelName) {
        if (!$this->service || !$this->is_authenticated()) {
            throw new Exception('Gmail API not authenticated');
        }
        
        try {
            // Get or create the label
            $labelId = $this->get_or_create_label($labelName);
            
            $mods = new Google_Service_Gmail_ModifyMessageRequest();
            $mods->setAddLabelIds(array($labelId));
            
            $this->service->users_messages->modify('me', $messageId, $mods);
            return true;
            
        } catch (Exception $e) {
            error_log('BST Gmail: Add label error - ' . $e->getMessage());
            throw new Exception('Failed to add label to Gmail message: ' . $e->getMessage());
        }
    }
    
    /**
     * Get label ID by name, create if it doesn't exist
     */
    private function get_or_create_label($labelName) {
        if (!$this->service || !$this->is_authenticated()) {
            throw new Exception('Gmail API not authenticated');
        }
        
        try {
            // First, try to find existing label
            $labels = $this->service->users_labels->listUsersLabels('me');
            
            foreach ($labels->getLabels() as $label) {
                if ($label->getName() === $labelName) {
                    return $label->getId();
                }
            }
            
            // Label doesn't exist, create it
            $newLabel = new Google_Service_Gmail_Label();
            $newLabel->setName($labelName);
            $newLabel->setLabelListVisibility('labelShow');
            $newLabel->setMessageListVisibility('show');
            
            $createdLabel = $this->service->users_labels->create('me', $newLabel);
            
            error_log("BST Gmail: Created new label '$labelName' with ID: " . $createdLabel->getId());
            return $createdLabel->getId();
            
        } catch (Exception $e) {
            error_log('BST Gmail: Get/create label error - ' . $e->getMessage());
            throw new Exception('Failed to get or create Gmail label: ' . $e->getMessage());
        }
    }
}

/**
 * Base64url encode function for Gmail API
 */
if (!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}