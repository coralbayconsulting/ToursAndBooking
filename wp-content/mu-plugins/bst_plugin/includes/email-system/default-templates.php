<?php

/**
 * Create default email templates
 */
function bst_create_default_email_templates() {
    
    // Check if templates already exist
    if (get_option('bst_default_templates_created')) {
        return;
    }
    
    // Reservation Email Template
    $reservation_template = array(
        'post_title' => 'Default Reservation Email',
        'post_content' => '
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2c5282;">Your Booking Confirmation - {booking_reference}</h2>
    
    <p>Dear {guest1_first_name},</p>
    
    <p>Thank you for booking with us! Your reservation has been confirmed.</p>
    
    <div style="background: #f7fafc; padding: 20px; border-left: 4px solid #2c5282; margin: 20px 0;">
        <h3 style="margin-top: 0;">Booking Details:</h3>
        <p><strong>Booking ID:</strong> {booking_id}</p>
        <p><strong>Tour:</strong> {tour_name}</p>
        <p><strong>Date:</strong> {tour_date}</p>
        <p><strong>Time:</strong> {tour_time}</p>
        <p><strong>Total Price:</strong> {total_price}</p>
        <p><strong>Balance Due:</strong> {balance_due}</p>
    </div>
    
    <p>To complete your reservation, please use the link below:</p>
    <p><a href="{reservation_link}" style="background: #2c5282; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Complete Reservation</a></p>
    
    <p>If you have any questions, please don\'t hesitate to contact us.</p>
    
    <p>Best regards,<br>The Tour Team</p>
</body>
</html>',
        'post_status' => 'publish',
        'post_type' => 'email-template',
        'meta_input' => array(
            '_bst_email_type' => 'reservation',
            '_bst_email_subject' => 'Booking Confirmation - {booking_id}'
        )
    );
    
    wp_insert_post($reservation_template);
    
    // Finalization Email Template
    $finalization_template = array(
        'post_title' => 'Default Finalization Email',
        'post_content' => '
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2c5282;">Final Details for Your Tour - {booking_reference}</h2>
    
    <p>Dear {guest1_first_name},</p>
    
    <p>Your tour is approaching! Here are the final details for your booking.</p>
    
    <div style="background: #f7fafc; padding: 20px; border-left: 4px solid #2c5282; margin: 20px 0;">
        <h3 style="margin-top: 0;">Tour Information:</h3>
        <p><strong>Tour:</strong> {tour_name}</p>
        <p><strong>Date:</strong> {tour_date}</p>
        <p><strong>Time:</strong> {tour_time}</p>
        <p><strong>Duration:</strong> {tour_duration}</p>
        <p><strong>Meeting Point:</strong> [To be filled in template]</p>
    </div>
    
    <div style="background: #edf2f7; padding: 20px; border-left: 4px solid #4a5568; margin: 20px 0;">
        <h3 style="margin-top: 0;">What to Bring:</h3>
        <ul>
            <li>Comfortable walking shoes</li>
            <li>Weather appropriate clothing</li>
            <li>Camera</li>
            <li>Water bottle</li>
        </ul>
    </div>
    
    <p>For any final arrangements or questions, please use the link below:</p>
    <p><a href="{finalization_link}" style="background: #2c5282; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Final Details</a></p>
    
    <p>We look forward to seeing you!</p>
    
    <p>Best regards,<br>The Tour Team</p>
</body>
</html>',
        'post_status' => 'publish',
        'post_type' => 'email-template',
        'meta_input' => array(
            '_bst_email_type' => 'finalization',
            '_bst_email_subject' => 'Final Tour Details - {booking_id}'
        )
    );
    
    wp_insert_post($finalization_template);
    
    // Invoice Email Template
    $invoice_template = array(
        'post_title' => 'Default Invoice Email',
        'post_content' => '
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2c5282;">Invoice for Booking {booking_reference}</h2>
    
    <p>Dear {guest1_first_name},</p>
    
    <p>Please find your invoice details below:</p>
    
    <div style="background: #f7fafc; padding: 20px; border: 1px solid #e2e8f0; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #2d3748;">Invoice Details</h3>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px; font-weight: bold;">Booking ID:</td>
                <td style="padding: 10px;">{booking_id}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px; font-weight: bold;">Tour:</td>
                <td style="padding: 10px;">{tour_name}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px; font-weight: bold;">Date:</td>
                <td style="padding: 10px;">{tour_date}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px; font-weight: bold;">Total Price:</td>
                <td style="padding: 10px; font-weight: bold; color: #2c5282;">{total_price}</td>
            </tr>
            <tr>
                <td style="padding: 10px; font-weight: bold;">Balance Due:</td>
                <td style="padding: 10px; font-weight: bold; color: #e53e3e;">{balance_due}</td>
            </tr>
        </table>
    </div>
    
    <p>If you have any questions about this invoice, please contact us.</p>
    
    <p>Thank you for your business!</p>
    
    <p>Best regards,<br>The Tour Team</p>
</body>
</html>',
        'post_status' => 'publish',
        'post_type' => 'email-template',
        'meta_input' => array(
            '_bst_email_type' => 'invoice',
            '_bst_email_subject' => 'Invoice - {booking_id}'
        )
    );
    
    wp_insert_post($invoice_template);
    
    // Booking Completed Notification Template
    $booking_completed_template = array(
        'post_title' => 'Booking Completed Notification',
        'post_content' => '
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2c5282;">Booking Confirmed - {booking_id}</h2>
    
    <p>Dear {guest1_first_name},</p>
    
    <p>Great news! Your booking has been confirmed and is now secured.</p>
    
    <div style="background: #f7fafc; padding: 20px; border-left: 4px solid #38a169; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #38a169;">Confirmed Booking Details:</h3>
        <p><strong>Booking ID:</strong> {booking_id}</p>
        <p><strong>Tour:</strong> {tour_name}</p>
        <p><strong>Date:</strong> {tour_date}</p>
        <p><strong>Status:</strong> Confirmed</p>
    </div>
    
    <p>We\'ll send you final details closer to your tour date.</p>
    
    <p>Best regards,<br>The Tour Team</p>
</body>
</html>',
        'post_status' => 'publish',
        'post_type' => 'email-template',
        'meta_input' => array(
            '_bst_email_type' => 'notification',
            '_bst_email_subject' => 'Booking Confirmed - {booking_id}',
            '_bst_email_trigger' => 'booking_completed'
        )
    );
    
    wp_insert_post($booking_completed_template);
    
    // Waiting List Confirmation Template
    $waiting_list_template = array(
        'post_title' => 'Waiting List Confirmation',
        'post_content' => '
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2c5282;">Added to Waiting List - {booking_id}</h2>
    
    <p>Dear {guest1_first_name},</p>
    
    <p>Thank you for your interest in our tour! You have been added to the waiting list.</p>
    
    <div style="background: #fff5f5; padding: 20px; border-left: 4px solid #f56565; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #f56565;">Waiting List Details:</h3>
        <p><strong>Booking ID:</strong> {booking_id}</p>
        <p><strong>Tour:</strong> {tour_name}</p>
        <p><strong>Date:</strong> {tour_date}</p>
        <p><strong>Status:</strong> Waiting List</p>
    </div>
    
    <p>We\'ll contact you immediately if a space becomes available.</p>
    
    <p>Best regards,<br>The Tour Team</p>
</body>
</html>',
        'post_status' => 'publish',
        'post_type' => 'email-template',
        'meta_input' => array(
            '_bst_email_type' => 'notification',
            '_bst_email_subject' => 'Added to Waiting List - {booking_id}',
            '_bst_email_trigger' => 'waiting_list_created'
        )
    );
    
    wp_insert_post($waiting_list_template);
    
    // Finalization Complete Notification Template
    $finalization_complete_template = array(
        'post_title' => 'Finalization Complete Notification',
        'post_content' => '
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2c5282;">Finalization Confirmed - {booking_id}</h2>
    
    <p>Dear {guest1_first_name},</p>
    
    <p>Thank you for completing your booking finalization! All your details have been received and confirmed.</p>
    
    <div style="background: #f7fafc; padding: 20px; border-left: 4px solid #38a169; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #38a169;">Finalized Booking Details:</h3>
        <p><strong>Booking ID:</strong> {booking_id}</p>
        <p><strong>Tour:</strong> {tour_name}</p>
        <p><strong>Date:</strong> {tour_date}</p>
        <p><strong>Status:</strong> Finalized</p>
    </div>
    
    <p>We look forward to seeing you on your tour!</p>
    
    <p>Best regards,<br>The Tour Team</p>
</body>
</html>',
        'post_status' => 'publish',
        'post_type' => 'email-template',
        'meta_input' => array(
            '_bst_email_type' => 'notification',
            '_bst_email_subject' => 'Finalization Confirmed - {booking_id}',
            '_bst_email_trigger' => 'finalization_completed'
        )
    );
    
    wp_insert_post($finalization_complete_template);
    
    // Finalization Reminder Template
    $finalization_reminder_template = array(
        'post_title' => 'Finalization Reminder Email',
        'post_content' => '
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #e53e3e; margin-bottom: 20px;">⏰ Friendly Reminder: Finalize Your Tour Soon!</h2>
    
    <p>Hi {guest1_first_name},</p>
    
    <p>Just a quick reminder that <strong>your tour is approaching</strong> and we haven\'t received your finalization yet. We want to make sure you don\'t miss out!</p>
    
    <div style="background: #fff5f5; padding: 20px; border-left: 4px solid #e53e3e; margin: 20px 0;">
        <h3 style="margin-top: 0; margin-bottom: 15px;"><strong>⚠️ Your Tour Details:</strong></h3>
        <p style="margin: 0 0 10px 0;"><strong>Tour:</strong> {tour_text}, {tour_date_text}, {tour_package_text}</p>
        <p style="margin: 0 0 10px 0;"><strong>Remaining Balance:</strong> {balance_due}</p>
        <p style="margin: 0 0 10px 0;"><strong>Due Date:</strong> {finalization_due_date}</p>
        <p style="margin: 10px 0 0 0;">{CustBookingDetailsLink}</p>
    </div>
    
    <h3 style="margin-bottom: 10px;"><strong>⚡ Quick Action Needed</strong></h3>
    <p>To secure your spot and ensure everything is ready for your arrival, please click the link below to finalize your booking. <strong>It only takes 5 minutes!</strong></p>
    
    <p style="text-align: left; margin: 30px 0; font-size: 18px;">👉 {finalization_link}</p>
    
    <div style="background: #f7fafc; padding: 15px; border-left: 4px solid #2c5282; margin: 20px 0;">
        <h3 style="margin-top: 0; margin-bottom: 10px;"><strong>📝 Quick Reminders:</strong></h3>
        <ul style="margin: 0; padding-left: 20px;">
            <li><strong>Notify your bank</strong> about the foreign transaction (Blue Strada is an Italian company) to prevent declined charges</li>
            <li>Use a card without foreign transaction fees if possible</li>
            <li><strong>Get your International Driver\'s Permit (IDP)</strong> if you\'ll be driving ($20 at AAA/CAA)</li>
        </ul>
    </div>
    
    <h3 style="margin-bottom: 10px;"><strong>👋 We\'re Here to Help</strong></h3>
    <p>Having trouble with the link or have questions about payment? Just reply to this email – we respond quickly and we\'re here to help make this easy for you!</p>
    
    <p style="margin-bottom: 5px;">Looking forward to seeing you on the road,</p>
    <p style="margin-top: 5px;">{BstEmailSignature}</p>
    
    <p style="margin-top: 20px; font-size: 12px; color: #666;"><em><strong>P.S.</strong> Don\'t wait until the last minute! Finalizing today means one less thing to worry about before your adventure begins. 🏍️</em></p>
</body>
</html>',
        'post_status' => 'publish',
        'post_type' => 'email-template',
        'meta_input' => array(
            '_bst_email_type' => 'finalization_reminder',
            '_bst_email_subject' => '⏰ Reminder: Finalize Your {tour_text} Tour - Due {finalization_due_date}'
        )
    );
    
    wp_insert_post($finalization_reminder_template);
    
    // Mark templates as created
    update_option('bst_default_templates_created', true);
}

// Hook to create templates after plugin activation
add_action('init', 'bst_create_default_email_templates');