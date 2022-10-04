<?php
require_once '../../index.php';
require_once 'class.pwformprocessor.php';

$fp = new PwFormprocessor($wire);

// define mail sender and receiver
$fp->setMailReceiver('info@example.com');
$fp->setMailSender('info@example.com');
$fp->setMailSenderName('John Doe');
$fp->setMailSubject('My Mail Subject');

// define honeypot fields (form won't send if populated)
$fp->setHoneypotFields(array('name','email','subject', 'message'));

// set timestamp field (optional for spam protection, a hidden field wich contains a unix timestamp)
$fp->setTimestampfield('stamp');

// set fields
$fp->setFields(array(
    'amount' => array(
        'label'=> 'Amount',     // human readable label, will be used for email output (no laben = no output in email)
        'sanitizer' => 'int',   // name of the sanitizer method, see https://processwire.com/api/ref/sanitizer/
        'required' => true,     // true if this is a mandatory field
        'fallback' => 0         // a fallback value if field is empty (only usefull for non-mandatory fields)
    ),
    'ob234xsd_nam' => array(   // Note: some fieldnames are obfuscated with a random prefix in this example
        'label'=> 'Name',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_first' => array(
        'label'=> 'First name',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_stree' => array(
        'label'=> 'Street',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_num' => array(
        'label'=> 'Number',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_zip' => array(
        'label'=> 'ZIP',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_city' => array(
        'label'=> 'City',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_mail' => array(
        'label'=> 'Email adress',
        'sanitizer' => 'email',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_phon' => array(
        'label'=> 'Phone number',
        'sanitizer' => 'text',
        'required' => false,
        'fallback' => false
    ),
    'ob234xsd_notes' => array(
        'label'=> 'Notes',
        'sanitizer' => 'textarea',
        'required' => false,
        'fallback' => false
    ),
    'privacy' => array(             // the "I read the privacy police" checkbox, mandatory but does not show in email because of missing label
        'required' => true
    )
));


/**
 * OPTIONAL: 
 * Set ReplyTo mail header so that the receiver of the mail
 * can easily answer by using the reply function of the mail client
 */
$user_mail = $wire->input->post('ob234xsd_mail', 'email');
if($user_mail AND $user_mail!=""){
    $fp->setMailReplyTo($user_mail);
}

/**
 * OPTIONAL:
 * Set seperator for multi value fields, such as checkboxes
 * or multi selects. Default is " | " (pipe symbol).
 */
$fp->setMulivalueseperator(" ," );

/**
 *  The simple method (commented out in this example).
 *  It just take all specified fields and throw them in an email
 *  if all validation checks where passed.
 */ 
//$processResult = $fp->processFormAndSend();






/**
 * The more complex multistep method wich gives you the change to merge some
 * (already sanitized) fields into a new one – such as salutation first name and name –
 * before calling the sendMail() method.
 */
// more advanced method, where we are able to hop in before the mail body generation
$processResult = $fp->processForm();
if(array_key_exists('success',$processResult) AND $processResult['success'] == true){
    // at this point we could merge some fields (stored in $processResult['sanitizedfields']) for the html generation
    // but for now we just pass the method the sanitized fields (wich contains labels and values)
    if($fp->sendEmail($processResult['sanitizedfields'])){
        echo "Your message was successfully submitted";
        exit;
    } else {
        http_response_code(400);
        echo "Your message could not be delivered. Please contact us by phone.";
        exit;
    }
} elseif(array_key_exists('reason',$processResult)){
    switch ($processResult['reason']) {
        case "honeypot":
            http_response_code(400);
            echo "Suspected spam A";
            break;
        case "timestamp":
            http_response_code(400);
            echo "Suspected spam B";
            break;

        case "required":
            http_response_code(400);
            echo "Please fill out all mandatory fields!";
            break;
    }
}
