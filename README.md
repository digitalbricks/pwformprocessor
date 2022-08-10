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
        'fallback' => 0         // a fallback value if field is empty (only usefull for non-mandatory fields)
    ),
    'ob234xsd_nam' => array(    // Note: some fieldnames are obfuscated with a random prefix in this example
        'label'=> 'Name',
        'sanitizer' => 'text',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_mail' => array(
        'label'=> 'E-Mail-Adresse',
        'sanitizer' => 'email',
        'required' => true,
        'fallback' => false
    ),
    'ob234xsd_notes' => array(
        'label'=> 'Bemerkungen',
        'sanitizer' => 'textarea',
        'required' => false,
        'fallback' => false,
        'htmloptions' => 'nl2br' // option for HTML value processing (optional)
    ),
    'privacy' => array(          // the "I read the privacy police" checkbox, mandatory but does not show in email because of missing label
        'required' => true
    )
));

// OPTIONAL: set the replyTo mail header (available since v0.3)
$user_mail = $wire->input->post('ob234xsd_mail', 'email');
if($user_mail AND $user_mail!=""){
    $fp->setMailReplyTo($user_mail);
}
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

The method `processFormAndSend()` returns an array with the keys `success`(`true` if mail was sent, `false` if there was an error) and `reason` (holds the reason for failure – such as `mailnotsent`, `honeypot`, `required`, `timestamp`). So we may check for this return values and generate corresponding error- or success-messages for the user.

The second method `processForm()` does **not** send the mail directly – it just validates / sanitizes and returns an array of sanitized fields. Using this method we are able to modify the content of the mail to be sent – e.g. merging the input for _Name_ and _First name_ into before handing the data over to the mail sending method `sendEmail()`.

In the following example I didn't merge any fields – I just take the sanitized return value (array of sanitized fields & values, with human readable labels) and hand it over to the `sendEmail()` method. In this example you can also see how we handle validation errors:

```php
$processResult = $fp->processForm();
if(array_key_exists('success',$processResult) AND $processResult['success'] == true){
    // at this point we could merge some fields (stored in $processResult['sanitizedfields']) for the html generation
    // but for now we just pass the method the sanitized fields (wich contains labels and values)
    if($fp->sendEmail($processResult['sanitizedfields'])){
        echo "Your message was successfully submitted";
        exit;
    } else {
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
```


