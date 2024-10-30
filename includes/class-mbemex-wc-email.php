<?php
/**
 *
 * @package   MailBoxesMex
 * @category Integration
 * @author   MBEMX.
 */
// Define a constant to use with html emails
define("MBEMEX_HTML_EMAIL_HEADERS", array('Content-Type: text/html; charset=UTF-8'));
// @email - Email address of the reciever
// @subject - Subject of the email
// @heading - Heading to place inside of the woocommerce template
// @message - Body content (can be HTML)
function mbemex_email_sender_woocommerce_style($email, $subject, $heading, $message, $attachments) {
    // Get woocommerce mailer from instance
    $mailer = WC()->mailer();
    // Wrap message using woocommerce html email template
    $wrapped_message = $mailer->wrap_message($heading, $message);
    // Create new WC_Email instance
    $wc_email = new WC_Email;
    // Style the wrapped message with woocommerce inline styles
    $html_message = $wc_email->style_inline($wrapped_message);
    // Send the email using wordpress mail function
    wp_mail( $email, $subject, $html_message, MBEMEX_HTML_EMAIL_HEADERS, $attachments );

}