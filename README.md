# pwformprocessor
A helper class for processing ajax submitted email forms with ProcessWire. **Work in progress.**

## What it does
This class is just for automating some recurring (server side) tasks I had to perform on ProcessWire websites when handling  asynchronous contact form submissions (Ajax) – such as **input sanitation**, **checking mandatory fields**, **checking honeypot fields** and **validating the form timestamp** for spam reduction.

As the great ProcessWire CMS has build-in methods for input sanitation and mail sending, this helper class depends on ProcessWire API and is just a kind of wrapper for iterating over given fields. 

## Basic usage
### Setup
In my projects I often have a contact form wich is submitted via Javascript to an form processor script – let's say under `[ROOT]/specials/form-processor.php`. In this script, we first had to load the ProcessWire API by including its `index.php`. Once this is done, we instanciate the `PwFormprocessor` class and handing over the `$wire` object:

```php
// load ProcessWire API
require_once '../../index.php';

// load & instanciate PwFormprocessor class, giving the $wire object
require_once 'class.pwformprocessor.php';
$fp = new PwFormprocessor($wire);
```

After that, we can set some basic option for the email, such as sender, receiver, subject – wich are later passed to WireMail:

```php
// define mail sender and receiver
$fp->setMailReceiver('info@example.com');
$fp->setMailSender('info@example.com');
$fp->setMailSenderName('John Doe');
$fp->setMailSubject('My Mail Subject');

// set the field to be used as replyTo mail header (available since v0.5)
$fp->setMailReplyToField('email');
```

Now we can define the our fields:

```php
// define honeypot fields (form won't send if populated)
$fp->setHoneypotFields(array('name','email','subject', 'message'));

// set fields
$fp->setFields(array(
    'amount' => array(
        'label'=> 'Amount',     // human readable label, will be used for email output (no laben = no output in email)
        'sanitizer' => 'int',   // name of the sanitizer method, see https://processwire.com/api/ref/sanitizer/
        'required' => true,     // true if this is a mandatory field
        'fallback' => 0,        // a fallback value if field is empty (only usefull for 
        'errortext' => 'Please specify amount.'
    ),
    'name' => array
        'label'=> 'Name',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false,
        'errortext' => 'Please enter a name.'
    ),
    'email' => array(
        'label'=> 'E-Mail-Adresse',
        'sanitizer' => 'email',
        'required' => true,
        'fallback' => false
    ),
    'message' => array(
        'label'=> 'Bemerkungen',
        'sanitizer' => 'textarea',
        'required' => false,
        'fallback' => false,
        'htmloptions' => 'nl2br fullwidth' // option for HTML value processing (optional)
    ),
    'customfield--demo' => array( // custom field (since v0.5), NO sanitation applied, be careful!
        'label'=> 'Custom field Demo',
        'value' => 'You may use array keys with "customfield--" prefix to manually add information to the email that will be sent. ',
        'htmloptions' => 'fullwidth nohtmlentities'
    ),
    'privacy' => array(          // the "I read the privacy police" checkbox, mandatory but does not show in email because of missing label
        'required' => true
    )
));



// OPTIONAL: change seperator string for multi value fields (default is " | " – available since v0.4)
$fp->setMulivalueseperator(" ," );
```
#### Available `htmloptions` for field configuration
You may define addition options for the **HTML part** of the email. These options has to be separated with a space and are processed in the order they are define – ecxept `nohtmlentities`.

| Setting          | Notes                                                                  |
|------------------|------------------------------------------------------------------------|
| `nl2br`          | Applies PHP `nl2br()`and is always helpfull when processing textareas  |
| `fullwidth`      | If set, the field label and value are **not** splitted into two table rows – usefull e.g. for contact form message  |
| `nohtmlentities` | Does **not** apply `htmlentities()` to the field **value**. Please note, that ProcessWire sanitizer removes HTML in the most cases, so this setting makes only sense if no `sanitizer` defined |


### Form Processing
Currently there are two methods for processing the form: The first one checks the fields, sanitize them and subsequently send them in the given oder by mail:

```php
$processResult = $fp->processFormAndSend();
```

The method `processFormAndSend()` returns an array with the keys `success` (`true` if mail was sent, `false` if there was an error) and `reason` (holds the reason for failure – such as `mailnotsent`, `honeypot`, `required`, `timestamp` and `notspecified`). So we may check for this return values and generate corresponding error- or success-messages for the user.

Here is an example, including a check for possible problems:

```php
// OPTIONAL: If the $wire object is not available in your file, include PWs index
//require_once '../../index.php';

// load class and instanciate
require_once 'classes/class.pwformprocessor.php';
$fp = new PwFormprocessor($wire);

// define mail sender and receiver
$fp->setMailReceiver('info@example.com'); // single email address or array of multiple addresses
$fp->setMailSender('info@example.com');
$fp->setMailSenderName('John Doe');
$fp->setMailSubject('My Mail Subject');

// set the field to be used as replyTo mail header (available since v0.5)
$fp->setMailReplyToField('email');

// define honeypot fields (form won't send if populated)
$fp->setHoneypotFields(array('confirm_email'));

// set timestamp field (optional for spam protection, a hidden field wich contains a unix timestamp)
$fp->setTimestampfield('stamp');
$fp->setTimestampduration(172800) // =2 days, default is 86400 =  1 day (set it with PW Caching duration in mind)

$fp->setFields(array(

    'name' => array(
        'label'=> 'Name',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false,
        'errortext' => 'Please enter a name',
    ),
    'customfield--demo' => array(
        'label'=> 'Customfield Demo',
        'value' => 'This ia custom field which may contain <strong>HTML</strong>',
        'htmloptions' => 'fullwidth nohtmlentities'
    ),
    'firstname' => array(
        'label'=> 'First Name',
        'sanitizer' => 'text',
        'required' => false,
        'fallback' => 'not provided'
    ),
    'email' => array(
        'label'=> 'E-Mail',
        'sanitizer' => 'email',
        'required' => true,
        'fallback' => false
        'errortext' => 'Please provide a email adress',
    ),
    'message' => array(
        'label'=> 'Message',
        'sanitizer' => 'textarea',
        'required' => false,
        'fallback' => false,
        'htmloptions' => 'nl2br fullwidth'
    ),
));

$processResult = $fp->processFormAndSend();
if(array_key_exists('success',$processResult) AND $processResult['success'] == true){
    echo "Your message was successfully submitted.";
    exit;
} elseif(array_key_exists('reason',$processResult)){
    http_response_code(400);
    switch ($processResult['reason']) {
        case "honeypot":
            echo "Submission failed due to suspected spam (ERRO S1)";
            break;

        case "timestamp":
            echo "Submission failed due to suspected spam (ERROR S2)";
            break;

        case "required":
            echo "Please fill out all required fields:";
            if(array_key_exists('missingfields',$processResult)){
                foreach ($processResult['missingfields'] as $errortext){
                    echo "<br>".$errortext;
                }
            }
            break;

        case "mailnotsent":
            echo "Your mail could not be delivered because of technical reasons. Please call us. (ERROR T1)";
            break;

        case "notspecfied":
            echo "Your mail could not be delivered because of technical reasons. Please call us. (ERROR T2)";
            break;
    }
}
```


